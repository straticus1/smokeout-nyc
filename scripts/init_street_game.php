<?php
/**
 * Initialize Street Game Database Tables and Data
 * Run this script to set up the player level system and street dealers
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = DB::getInstance();
    $pdo = $db->getConnection();
    
    echo "Initializing Street Game Database...\n";
    
    // Detect database type and use appropriate schema
    $db_info = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($db_info === 'sqlite') {
        echo "Using SQLite schema...\n";
        $schema_sql = file_get_contents(__DIR__ . '/../database/street_level_gaming_sqlite.sql');
    } else {
        echo "Using MySQL schema...\n";
        $schema_sql = file_get_contents(__DIR__ . '/../database/street_level_gaming_schema.sql');
    }
    
    // Split statements and execute them
    $statements = array_filter(array_map('trim', explode(';', $schema_sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !str_starts_with($statement, '--')) {
            try {
                $pdo->exec($statement);
                echo "✓ Executed statement\n";
            } catch (PDOException $e) {
                // Ignore table already exists errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠ Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    // Check if users table exists (SQLite compatible)
    if ($db_info === 'sqlite') {
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    } else {
        $result = $pdo->query("SHOW TABLES LIKE 'users'");
    }
    
    if ($result->rowCount() === 0) {
        echo "Creating basic users table...\n";
        if ($db_info === 'sqlite') {
            $pdo->exec("
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    email TEXT UNIQUE NOT NULL,
                    password_hash TEXT NOT NULL,
                    balance REAL DEFAULT 1000.00,
                    status TEXT CHECK(status IN ('active', 'inactive', 'banned')) DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            $pdo->exec("
                CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) UNIQUE NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    balance DECIMAL(10,2) DEFAULT 1000.00,
                    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Insert test user
        $pdo->prepare("
            INSERT INTO users (username, email, password_hash, balance) 
            VALUES ('testplayer', 'test@smokeout.nyc', ?, 5000.00)
        ")->execute([password_hash('testpass', PASSWORD_DEFAULT)]);
        
        echo "✓ Created users table with test user\n";
    }
    
    echo "\n🎮 Street Game Database initialized successfully!\n";
    echo "Test user created: username='testplayer', password='testpass'\n";
    echo "Use token 'user_1' for API testing\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>