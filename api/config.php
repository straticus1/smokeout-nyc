<?php
/**
 * Street Game API Configuration
 */

require_once __DIR__ . '/../config/database.php';

// Initialize database connection
try {
    $db = DB::getInstance();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Set timezone
date_default_timezone_set('America/New_York');

// Error reporting for development
if ($_ENV['NODE_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>