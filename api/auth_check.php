<?php
/**
 * Authentication Check for Street Game API
 */

function get_authenticated_user_id() {
    // Simple JWT token verification
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    
    if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
        return null;
    }
    
    $token = substr($auth_header, 7);
    
    // For development purposes, accept a simple user ID token
    // In production, this would be proper JWT verification
    if (preg_match('/^user_(\d+)$/', $token, $matches)) {
        return (int)$matches[1];
    }
    
    // Try to decode as actual JWT (simplified)
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    
    try {
        $payload = json_decode(base64_decode($parts[1]), true);
        return $payload['user_id'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}

// For testing purposes, allow override via session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function set_test_user($user_id) {
    $_SESSION['test_user_id'] = $user_id;
}

function get_test_user() {
    return $_SESSION['test_user_id'] ?? null;
}
?>