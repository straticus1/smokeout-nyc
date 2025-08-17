#!/usr/bin/env php
<?php
/**
 * NYC Smoke Shop Database Importer
 * 
 * CLI script to import smoke shop data from JSON file into PostgreSQL database
 * Supports refresh mode to detect status changes and new/closed stores
 * 
 * Usage:
 *   php import_shops.php --file=nyc_smoke_shops.json [--refresh] [--dry-run] [--verbose]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

class SmokeShopImporter {
    private $pdo;
    private $verbose = false;
    private $dryRun = false;
    
    public function __construct($verbose = false, $dryRun = false) {
        $this->verbose = $verbose;
        $this->dryRun = $dryRun;
        $this->initializeDatabase();
    }
    
    private function initializeDatabase() {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
        
        $databaseUrl = $_ENV['DATABASE_URL'] ?? null;
        if (!$databaseUrl) {
            throw new Exception('DATABASE_URL environment variable not found');
        }
        
        // Parse PostgreSQL URL
        $parsed = parse_url($databaseUrl);
        $host = $parsed['host'];
        $port = $parsed['port'] ?? 5432;
        $dbname = ltrim($parsed['path'], '/');
        $user = $parsed['user'];
        $password = $parsed['pass'];
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        
        try {
            $this->pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            $this->log("Connected to database successfully");
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function importFromJson($jsonFile, $refreshMode = false) {
        if (!file_exists($jsonFile)) {
            throw new Exception("JSON file not found: $jsonFile");
        }
        
        $data = json_decode(file_get_contents($jsonFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON file: " . json_last_error_msg());
        }
        
        if (!isset($data['shops']) || !is_array($data['shops'])) {
            throw new Exception("Invalid JSON structure: 'shops' array not found");
        }
        
        $metadata = $data['metadata'] ?? [];
        $shops = $data['shops'];
        
        $this->log("Starting import of " . count($shops) . " shops");
        $this->log("Collection date: " . ($metadata['collection_date'] ?? 'Unknown'));
        $this->log("Sources: " . implode(', ', $metadata['sources'] ?? []));
        
        if ($refreshMode) {
            return $this->refreshImport($shops);
        } else {
            return $this->fullImport($shops);
        }
    }
    
    private function fullImport($shops) {
        $stats = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        
        if (!$this->dryRun) {
            $this->pdo->beginTransaction();
        }
        
        try {
            foreach ($shops as $shopData) {
                $result = $this->processShop($shopData, false);
                $stats[$result]++;
                
                if ($this->verbose) {
                    $this->log("Processed: {$shopData['name']} - $result");
                }
            }
            
            if (!$this->dryRun) {
                $this->pdo->commit();
                $this->log("Transaction committed successfully");
            }
            
        } catch (Exception $e) {
            if (!$this->dryRun) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        
        return $stats;
    }
    
    private function refreshImport($shops) {
        $stats = [
            'inserted' => 0,
            'updated' => 0,
            'status_changed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'potentially_closed' => 0
        ];
        
        // Get existing shops from database
        $existingShops = $this->getExistingShops();
        $importedShops = [];
        
        if (!$this->dryRun) {
            $this->pdo->beginTransaction();
        }
        
        try {
            // Process each shop from JSON
            foreach ($shops as $shopData) {
                $shopKey = $this->generateShopKey($shopData);
                $importedShops[$shopKey] = true;
                
                $result = $this->processShop($shopData, true);
                $stats[$result]++;
                
                if ($this->verbose) {
                    $this->log("Processed: {$shopData['name']} - $result");
                }
            }
            
            // Check for shops that might have closed (not in new data)
            foreach ($existingShops as $existingShop) {
                $shopKey = $this->generateShopKey($existingShop);
                
                if (!isset($importedShops[$shopKey]) && $existingShop['status'] === 'OPEN') {
                    // Shop not found in new data but was previously open
                    $this->log("POTENTIALLY CLOSED: {$existingShop['name']} at {$existingShop['address']}");
                    $stats['potentially_closed']++;
                    
                    // Optionally mark as potentially closed
                    if (!$this->dryRun) {
                        $this->markAsPotentiallyClosed($existingShop['id']);
                    }
                }
            }
            
            if (!$this->dryRun) {
                $this->pdo->commit();
                $this->log("Refresh transaction committed successfully");
            }
            
        } catch (Exception $e) {
            if (!$this->dryRun) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        
        return $stats;
    }
    
    private function processShop($shopData, $refreshMode) {
        try {
            // Validate required fields
            if (empty($shopData['name']) || empty($shopData['address'])) {
                return 'skipped';
            }
            
            // Check if shop already exists
            $existingShop = $this->findExistingShop($shopData);
            
            if ($existingShop) {
                // Update existing shop
                if ($this->shouldUpdateShop($existingShop, $shopData, $refreshMode)) {
                    if (!$this->dryRun) {
                        $this->updateShop($existingShop['id'], $shopData);
                    }
                    
                    // Check if status changed
                    if ($refreshMode && $existingShop['status'] !== $this->mapStatus($shopData['status'])) {
                        $this->log("STATUS CHANGE: {$shopData['name']} - {$existingShop['status']} → {$this->mapStatus($shopData['status'])}");
                        return 'status_changed';
                    }
                    
                    return 'updated';
                } else {
                    return 'skipped';
                }
            } else {
                // Insert new shop
                if (!$this->dryRun) {
                    $this->insertShop($shopData);
                }
                return 'inserted';
            }
            
        } catch (Exception $e) {
            $this->log("ERROR processing {$shopData['name']}: " . $e->getMessage());
            return 'errors';
        }
    }
    
    private function findExistingShop($shopData) {
        // Try to find by exact name and address match first
        $stmt = $this->pdo->prepare("
            SELECT * FROM stores 
            WHERE LOWER(name) = LOWER(?) 
            AND LOWER(address) = LOWER(?)
        ");
        $stmt->execute([$shopData['name'], $shopData['address']]);
        $shop = $stmt->fetch();
        
        if ($shop) {
            return $shop;
        }
        
        // Try fuzzy matching by coordinates if available
        if (!empty($shopData['latitude']) && !empty($shopData['longitude'])) {
            $stmt = $this->pdo->prepare("
                SELECT *, 
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(latitude)))) AS distance
                FROM stores 
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                HAVING distance < 0.1
                ORDER BY distance
                LIMIT 1
            ");
            $stmt->execute([
                $shopData['latitude'], 
                $shopData['longitude'], 
                $shopData['latitude']
            ]);
            $shop = $stmt->fetch();
            
            if ($shop) {
                return $shop;
            }
        }
        
        return null;
    }
    
    private function shouldUpdateShop($existingShop, $newData, $refreshMode) {
        // Always update in refresh mode if data has changed
        if ($refreshMode) {
            return true;
        }
        
        // Check if important fields have changed
        $fieldsToCheck = ['phone', 'website', 'status', 'latitude', 'longitude'];
        
        foreach ($fieldsToCheck as $field) {
            $existingValue = $existingShop[$field] ?? null;
            $newValue = $this->mapFieldValue($field, $newData[$field] ?? null);
            
            if ($existingValue !== $newValue && !empty($newValue)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function insertShop($shopData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO stores (
                name, address, latitude, longitude, phone, email, website,
                description, status, hours, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
        
        $hours = !empty($shopData['hours']) ? json_encode($shopData['hours']) : null;
        $description = $this->generateDescription($shopData);
        
        $stmt->execute([
            $shopData['name'],
            $shopData['address'],
            $shopData['latitude'] ?? null,
            $shopData['longitude'] ?? null,
            $shopData['phone'] ?? null,
            null, // email
            $shopData['website'] ?? null,
            $description,
            $this->mapStatus($shopData['status']),
            $hours
        ]);
    }
    
    private function updateShop($shopId, $shopData) {
        $stmt = $this->pdo->prepare("
            UPDATE stores SET
                name = COALESCE(?, name),
                address = COALESCE(?, address),
                latitude = COALESCE(?, latitude),
                longitude = COALESCE(?, longitude),
                phone = COALESCE(?, phone),
                website = COALESCE(?, website),
                status = ?,
                hours = COALESCE(?, hours),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $hours = !empty($shopData['hours']) ? json_encode($shopData['hours']) : null;
        
        $stmt->execute([
            $shopData['name'],
            $shopData['address'],
            $shopData['latitude'] ?? null,
            $shopData['longitude'] ?? null,
            $shopData['phone'] ?? null,
            $shopData['website'] ?? null,
            $this->mapStatus($shopData['status']),
            $hours,
            $shopId
        ]);
    }
    
    private function markAsPotentiallyClosed($shopId) {
        $stmt = $this->pdo->prepare("
            UPDATE stores SET 
                status = 'CLOSED_UNKNOWN',
                closure_reason = 'Not found in recent data collection',
                updated_at = NOW()
            WHERE id = ? AND status = 'OPEN'
        ");
        $stmt->execute([$shopId]);
    }
    
    private function getExistingShops() {
        $stmt = $this->pdo->query("SELECT id, name, address, status FROM stores");
        return $stmt->fetchAll();
    }
    
    private function generateShopKey($shopData) {
        $name = strtolower(preg_replace('/[^\w\s]/', '', $shopData['name']));
        $address = strtolower(preg_replace('/[^\w\s]/', '', $shopData['address']));
        return md5($name . '|' . $address);
    }
    
    private function mapStatus($status) {
        $statusMap = [
            'OPEN' => 'OPEN',
            'OPERATIONAL' => 'OPEN',
            'CLOSED_TEMPORARILY' => 'CLOSED_OTHER',
            'CLOSED_PERMANENTLY' => 'CLOSED_OTHER',
            'CLOSED_OTHER' => 'CLOSED_OTHER',
            'UNKNOWN' => 'OPEN' // Default to open for unknown status
        ];
        
        return $statusMap[$status] ?? 'OPEN';
    }
    
    private function mapFieldValue($field, $value) {
        switch ($field) {
            case 'status':
                return $this->mapStatus($value);
            default:
                return $value;
        }
    }
    
    private function generateDescription($shopData) {
        $parts = [];
        
        if (!empty($shopData['business_type'])) {
            $parts[] = ucfirst(str_replace('_', ' ', $shopData['business_type']));
        }
        
        if (!empty($shopData['source'])) {
            $parts[] = "Data source: {$shopData['source']}";
        }
        
        if (!empty($shopData['borough'])) {
            $parts[] = "Located in {$shopData['borough']}";
        }
        
        return implode('. ', $parts);
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] $message\n";
    }
    
    public function printStats($stats) {
        echo "\n=== IMPORT STATISTICS ===\n";
        foreach ($stats as $key => $value) {
            echo sprintf("%-20s: %d\n", ucfirst(str_replace('_', ' ', $key)), $value);
        }
        echo "========================\n\n";
    }
}

// CLI argument parsing
function parseArguments($argv) {
    $args = [
        'file' => null,
        'refresh' => false,
        'dry-run' => false,
        'verbose' => false,
        'help' => false
    ];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if (strpos($arg, '--file=') === 0) {
            $args['file'] = substr($arg, 7);
        } elseif ($arg === '--refresh') {
            $args['refresh'] = true;
        } elseif ($arg === '--dry-run') {
            $args['dry-run'] = true;
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $args['verbose'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $args['help'] = true;
        }
    }
    
    return $args;
}

function printUsage() {
    echo "NYC Smoke Shop Database Importer\n\n";
    echo "Usage: php import_shops.php --file=<json_file> [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --file=<file>     JSON file to import (required)\n";
    echo "  --refresh         Refresh mode: detect status changes and new/closed stores\n";
    echo "  --dry-run         Show what would be done without making changes\n";
    echo "  --verbose, -v     Verbose output\n";
    echo "  --help, -h        Show this help message\n\n";
    echo "Examples:\n";
    echo "  php import_shops.php --file=nyc_smoke_shops.json\n";
    echo "  php import_shops.php --file=nyc_smoke_shops.json --refresh --verbose\n";
    echo "  php import_shops.php --file=nyc_smoke_shops.json --dry-run\n\n";
}

// Main execution
try {
    $args = parseArguments($argv);
    
    if ($args['help']) {
        printUsage();
        exit(0);
    }
    
    if (!$args['file']) {
        echo "Error: --file argument is required\n\n";
        printUsage();
        exit(1);
    }
    
    $importer = new SmokeShopImporter($args['verbose'], $args['dry-run']);
    
    if ($args['dry-run']) {
        echo "DRY RUN MODE - No changes will be made to the database\n\n";
    }
    
    $stats = $importer->importFromJson($args['file'], $args['refresh']);
    $importer->printStats($stats);
    
    echo "Import completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
