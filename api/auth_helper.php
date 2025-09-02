<?php
/**
 * Global Authentication Helper Functions
 * Used across all API endpoints
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/models/User.php';

// Initialize global PDO connection
$db = DB::getInstance();
$pdo = $db->getConnection();

/**
 * Global authentication function used by all API endpoints
 * @return array|null User data if authenticated, null if not
 */
function authenticate() {
    global $pdo;
    
    $token = getBearerToken();
    if (!$token) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, s.expires_at 
            FROM users u 
            JOIN user_sessions s ON u.id = s.user_id 
            WHERE s.session_token = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Remove sensitive data
            unset($user['password_hash']);
            return $user;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return null;
    }
}

/**
 * Extract Bearer token from Authorization header
 * @return string|null
 */
function getBearerToken() {
    $headers = getallheaders();
    
    // Handle case-insensitive headers
    $headers = array_change_key_case($headers, CASE_LOWER);
    
    if (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

/**
 * Check if user has specific permission/role
 * @param array $user User data from authenticate()
 * @param string $permission Permission to check
 * @return bool
 */
function hasPermission($user, $permission) {
    if (!$user) return false;
    
    $role = $user['role'] ?? 'user';
    
    $permissions = [
        'user' => ['basic'],
        'store_owner' => ['basic', 'store_management'],
        'admin' => ['basic', 'store_management', 'user_management', 'content_management'],
        'super_admin' => ['*'] // All permissions
    ];
    
    if ($role === 'super_admin') return true;
    
    return in_array($permission, $permissions[$role] ?? []);
}

/**
 * Validate API request rate limiting
 * @param string $identifier User ID or IP address
 * @param int $maxRequests Maximum requests per time window
 * @param int $timeWindow Time window in seconds
 * @return bool True if request is allowed, false if rate limited
 */
function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
    global $pdo;
    
    try {
        // Clean old entries
        $pdo->prepare("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)")
             ->execute([$timeWindow]);
        
        // Count current requests
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$identifier, $timeWindow]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $maxRequests) {
            return false;
        }
        
        // Log this request
        $pdo->prepare("INSERT INTO rate_limits (identifier, created_at) VALUES (?, NOW())")
             ->execute([$identifier]);
        
        return true;
    } catch (Exception $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return true; // Allow request if rate limiting fails
    }
}

/**
 * Log API access for audit purposes
 * @param array|null $user User data
 * @param string $endpoint Endpoint accessed
 * @param string $method HTTP method
 * @param array $data Request data
 */
function logApiAccess($user, $endpoint, $method, $data = []) {
    global $pdo;
    
    try {
        $userId = $user['id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $pdo->prepare("
            INSERT INTO api_access_logs 
            (user_id, endpoint, method, ip_address, user_agent, request_data, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $userId, $endpoint, $method, $ipAddress, $userAgent, json_encode($data)
        ]);
    } catch (Exception $e) {
        error_log("API access logging error: " . $e->getMessage());
    }
}

/**
 * Sanitize input data for security
 * @param mixed $data Input data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    if (is_string($data)) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Validate required fields in request data
 * @param array $data Request data
 * @param array $required Required field names
 * @return array|null Null if valid, array of missing fields if invalid
 */
function validateRequiredFields($data, $required) {
    $missing = [];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    return empty($missing) ? null : $missing;
}

/**
 * Send standardized JSON response
 * @param bool $success Success status
 * @param mixed $data Response data
 * @param string|null $message Optional message
 * @param int $httpCode HTTP status code
 */
function sendJsonResponse($success, $data = null, $message = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = ['success' => $success];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($message !== null) {
        $response['message'] = $message;
    }
    
    if (!$success && $httpCode >= 400) {
        $response['error'] = $message ?? 'An error occurred';
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Handle API errors consistently
 * @param Exception $e Exception that occurred
 * @param string $context Context where error occurred
 */
function handleApiError($e, $context = 'API') {
    error_log("{$context} Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Don't expose internal error details in production
    $isDevelopment = ($_ENV['APP_ENV'] ?? 'production') === 'development';
    
    if ($isDevelopment) {
        sendJsonResponse(false, null, $e->getMessage(), 500);
    } else {
        sendJsonResponse(false, null, 'Internal server error', 500);
    }
}

/**
 * Check membership limits for specific features
 * @param int $user_id User ID
 * @param string $feature Feature to check (e.g., 'ai_assessments_per_month')
 * @return bool True if within limits
 */
function checkMembershipLimits($user_id, $feature) {
    global $pdo;
    
    try {
        // Get user's current membership
        $stmt = $pdo->prepare("
            SELECT mt.*, um.status, um.expires_at
            FROM user_memberships um
            JOIN membership_tiers mt ON um.tier_id = mt.id
            WHERE um.user_id = ? AND um.status = 'active' AND um.expires_at > NOW()
            ORDER BY mt.priority DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $membership = $stmt->fetch();
        
        if (!$membership) {
            // Free tier limits
            $limits = [
                'ai_assessments_per_month' => 5,
                'api_requests_per_day' => 100,
                'file_uploads_per_month' => 10,
                'game_actions_per_day' => 50
            ];
        } else {
            // Paid tier limits from database
            $limits = json_decode($membership['limits'], true) ?? [];
        }
        
        $limit = $limits[$feature] ?? 0;
        
        if ($limit <= 0) {
            return true; // Unlimited
        }
        
        // Check current usage
        $timeframe = strpos($feature, 'per_month') !== false ? 'MONTH' : 'DAY';
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as usage_count
            FROM membership_usage
            WHERE user_id = ? AND feature = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 $timeframe)
        ");
        $stmt->execute([$user_id, $feature]);
        $usage = $stmt->fetch();
        
        return ($usage['usage_count'] ?? 0) < $limit;
    } catch (Exception $e) {
        error_log("Membership limit check error: " . $e->getMessage());
        return true; // Allow if check fails
    }
}

/**
 * Log membership usage for tracking
 * @param int $user_id User ID
 * @param string $feature Feature used
 * @param array $metadata Additional metadata
 */
function logMembershipUsage($user_id, $feature, $metadata = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO membership_usage (user_id, feature, metadata, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $feature,
            json_encode($metadata)
        ]);
    } catch (Exception $e) {
        error_log("Membership usage logging error: " . $e->getMessage());
    }
}

/**
 * Get user's current membership tier
 * @param int $user_id User ID
 * @return array|null Membership data or null
 */
function getUserMembership($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT mt.*, um.status, um.expires_at, um.created_at as subscribed_at
            FROM user_memberships um
            JOIN membership_tiers mt ON um.tier_id = mt.id
            WHERE um.user_id = ? AND um.status = 'active' AND um.expires_at > NOW()
            ORDER BY mt.priority DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user membership error: " . $e->getMessage());
        return null;
    }
}

?>
