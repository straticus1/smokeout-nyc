<?php
/**
 * Health Check Endpoint
 * SmokeoutNYC v2.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config/database.php';

function checkDatabaseConnection() {
    try {
        $db = DB::getInstance();
        $pdo = $db->getConnection();
        
        // Simple query to test connection
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        return $result && $result['test'] == 1;
    } catch (Exception $e) {
        return false;
    }
}

function checkDiskSpace() {
    $freeBytes = disk_free_space(".");
    $totalBytes = disk_total_space(".");
    
    if ($freeBytes && $totalBytes) {
        $percentage = ($freeBytes / $totalBytes) * 100;
        return [
            'free_space_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
            'total_space_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'free_percentage' => round($percentage, 2),
            'status' => $percentage > 10 ? 'ok' : 'warning'
        ];
    }
    
    return ['status' => 'unknown'];
}

function getSystemInfo() {
    return [
        'php_version' => phpversion(),
        'memory_limit' => ini_get('memory_limit'),
        'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        'server_time' => date('Y-m-d H:i:s T'),
        'timezone' => date_default_timezone_get()
    ];
}

function checkRequiredExtensions() {
    $required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl'];
    $status = [];
    
    foreach ($required as $ext) {
        $status[$ext] = extension_loaded($ext) ? 'loaded' : 'missing';
    }
    
    return $status;
}

try {
    $health = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => '2.0.0',
        'app_name' => 'SmokeoutNYC',
        'checks' => [
            'database' => checkDatabaseConnection() ? 'connected' : 'failed',
            'disk_space' => checkDiskSpace(),
            'php_extensions' => checkRequiredExtensions(),
            'system_info' => getSystemInfo()
        ]
    ];
    
    // Overall health status
    $hasErrors = false;
    if ($health['checks']['database'] !== 'connected') {
        $hasErrors = true;
    }
    
    if ($health['checks']['disk_space']['status'] === 'warning') {
        $health['status'] = 'warning';
    }
    
    foreach ($health['checks']['php_extensions'] as $ext => $status) {
        if ($status === 'missing') {
            $hasErrors = true;
            break;
        }
    }
    
    if ($hasErrors) {
        $health['status'] = 'error';
        http_response_code(503); // Service Unavailable
    }
    
    echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Health check failed',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>
