<?php
require_once 'config/database.php';
require_once 'vendor/autoload.php'; // For OAuth libraries

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

array_shift($segments); // remove 'api'

$endpoint = $segments[0] ?? '';
$action = $segments[1] ?? '';

try {
    switch ($endpoint) {
        case 'auth':
            handleAuthEndpoints($method, $action);
            break;
        case 'oauth':
            handleOAuthEndpoints($method, $action);
            break;
        case 'verify':
            handleVerificationEndpoints($method, $action);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleAuthEndpoints($method, $action) {
    switch ($action) {
        case 'register':
            if ($method === 'POST') {
                handleRegistration();
            }
            break;
        case 'login':
            if ($method === 'POST') {
                handleLogin();
            }
            break;
        case 'refresh':
            if ($method === 'POST') {
                handleTokenRefresh();
            }
            break;
        case 'logout':
            if ($method === 'POST') {
                handleLogout();
            }
            break;
        case 'forgot-password':
            if ($method === 'POST') {
                handleForgotPassword();
            }
            break;
        case 'reset-password':
            if ($method === 'POST') {
                handleResetPassword();
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Auth endpoint not found']);
    }
}

function handleRegistration() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['username', 'email', 'password', 'phone_number'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '{$field}' is required"]);
            return;
        }
    }
    
    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = $data['password'];
    $phone_number = trim($data['phone_number']);
    $first_name = trim($data['first_name'] ?? '');
    $last_name = trim($data['last_name'] ?? '');
    $interests = $data['interests'] ?? [];
    
    // Validate input
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }
    
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters']);
        return;
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username must be 3-30 characters, letters, numbers, and underscores only']);
        return;
    }
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? OR phone_number = ?");
    $stmt->execute([$email, $username, $phone_number]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'User already exists with this email, username, or phone number']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, phone_number, first_name, last_name, interests, account_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification')
        ");
        $stmt->execute([
            $username, $email, $password_hash, $phone_number, 
            $first_name, $last_name, json_encode($interests)
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Add selected interests
        if (!empty($interests)) {
            $interest_stmt = $pdo->prepare("
                INSERT INTO user_interest_selections (user_id, interest_id)
                SELECT ?, id FROM user_interests WHERE name IN (" . 
                str_repeat('?,', count($interests) - 1) . "?)
            ");
            $interest_stmt->execute(array_merge([$user_id], $interests));
        }
        
        // Create game player profile
        $game_stmt = $pdo->prepare("
            INSERT INTO game_players (user_id, tokens, experience_points, level)
            VALUES (?, 100.00, 0, 1)
        ");
        $game_stmt->execute([$user_id]);
        
        // Send verification codes
        $email_code = sendEmailVerification($user_id, $email);
        $sms_code = sendSMSVerification($user_id, $phone_number);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful. Please verify your email and phone number.',
            'user_id' => $user_id,
            'verification_required' => true
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function handleLogin() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $identifier = trim($data['identifier'] ?? ''); // username, email, or phone
    $password = $data['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Identifier and password are required']);
        return;
    }
    
    // Check rate limiting
    $stmt = $pdo->prepare("CALL CheckRateLimit(?, ?, @is_blocked, @attempts_count)");
    $stmt->execute([$identifier, $ip_address]);
    
    $result = $pdo->query("SELECT @is_blocked as is_blocked, @attempts_count as attempts_count")->fetch();
    
    if ($result['is_blocked']) {
        // Log failed attempt
        $pdo->prepare("CALL RecordLoginAttempt(?, ?, FALSE, 'rate_limited', ?)")
            ->execute([$identifier, $ip_address, $user_agent]);
        
        http_response_code(429);
        echo json_encode([
            'error' => 'Too many failed attempts. Please try again later.',
            'retry_after' => 900 // 15 minutes
        ]);
        return;
    }
    
    // Find user
    $stmt = $pdo->prepare("
        SELECT id, username, email, password_hash, account_status, email_verified, phone_verified, preferred_2fa
        FROM users 
        WHERE username = ? OR email = ? OR phone_number = ?
    ");
    $stmt->execute([$identifier, $identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Log failed attempt
        $pdo->prepare("CALL RecordLoginAttempt(?, ?, FALSE, 'invalid_credentials', ?)")
            ->execute([$identifier, $ip_address, $user_agent]);
        
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }
    
    // Check account status
    if ($user['account_status'] === 'suspended') {
        http_response_code(403);
        echo json_encode(['error' => 'Account suspended']);
        return;
    }
    
    if ($user['account_status'] === 'pending_verification') {
        echo json_encode([
            'error' => 'Account verification required',
            'verification_required' => true,
            'user_id' => $user['id']
        ]);
        return;
    }
    
    // Check if 2FA is required
    if ($user['preferred_2fa'] !== 'none' && ($user['email_verified'] || $user['phone_verified'])) {
        // Send 2FA code
        $code = send2FACode($user['id'], $user['preferred_2fa']);
        
        echo json_encode([
            'success' => true,
            'requires_2fa' => true,
            'user_id' => $user['id'],
            'method' => $user['preferred_2fa']
        ]);
        return;
    }
    
    // Create session
    $session_data = createUserSession($user['id'], 'password', $ip_address, $user_agent);
    
    // Log successful attempt
    $pdo->prepare("CALL RecordLoginAttempt(?, ?, TRUE, NULL, ?)")
        ->execute([$identifier, $ip_address, $user_agent]);
    
    // Update login stats
    $pdo->prepare("
        UPDATE users 
        SET last_login = NOW(), login_count = login_count + 1 
        WHERE id = ?
    ")->execute([$user['id']]);
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ],
        'session' => $session_data
    ]);
}

function handleOAuthEndpoints($method, $action) {
    switch ($action) {
        case 'google':
            if ($method === 'GET') {
                initiateGoogleOAuth();
            } elseif ($method === 'POST') {
                handleGoogleCallback();
            }
            break;
        case 'facebook':
            if ($method === 'GET') {
                initiateFacebookOAuth();
            } elseif ($method === 'POST') {
                handleFacebookCallback();
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'OAuth provider not found']);
    }
}

function initiateGoogleOAuth() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT client_id, authorization_url, scope FROM oauth_providers WHERE name = 'google' AND is_active = TRUE");
    $stmt->execute();
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider) {
        http_response_code(500);
        echo json_encode(['error' => 'Google OAuth not configured']);
        return;
    }
    
    $state = bin2hex(random_bytes(16));
    $redirect_uri = getenv('SITE_URL') . '/api/oauth/google/callback';
    
    // Store state in session for security
    session_start();
    $_SESSION['oauth_state'] = $state;
    
    $auth_url = $provider['authorization_url'] . '?' . http_build_query([
        'client_id' => $provider['client_id'],
        'redirect_uri' => $redirect_uri,
        'scope' => $provider['scope'],
        'response_type' => 'code',
        'state' => $state,
        'access_type' => 'offline',
        'prompt' => 'consent'
    ]);
    
    echo json_encode(['auth_url' => $auth_url]);
}

function handleGoogleCallback() {
    $data = json_decode(file_get_contents('php://input'), true);
    $code = $data['code'] ?? '';
    $state = $data['state'] ?? '';
    
    session_start();
    if ($state !== $_SESSION['oauth_state']) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid state parameter']);
        return;
    }
    
    // Exchange code for token
    $token_data = exchangeGoogleCode($code);
    if (!$token_data) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to exchange code for token']);
        return;
    }
    
    // Get user info
    $user_info = getGoogleUserInfo($token_data['access_token']);
    if (!$user_info) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to get user information']);
        return;
    }
    
    // Create or login user
    $user = createOrLoginOAuthUser('google', $user_info, $token_data);
    
    echo json_encode([
        'success' => true,
        'user' => $user['user_data'],
        'session' => $user['session_data']
    ]);
}

function handleVerificationEndpoints($method, $action) {
    switch ($action) {
        case 'email':
            if ($method === 'POST') {
                verifyEmailCode();
            }
            break;
        case 'sms':
            if ($method === 'POST') {
                verifySMSCode();
            }
            break;
        case 'resend':
            if ($method === 'POST') {
                resendVerificationCode();
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Verification endpoint not found']);
    }
}

function verifyEmailCode() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? 0;
    $code = $data['code'] ?? '';
    
    if (!$user_id || !$code) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID and code are required']);
        return;
    }
    
    // Verify code
    $stmt = $pdo->prepare("
        SELECT id FROM verification_codes 
        WHERE user_id = ? AND code = ? AND type = 'email' 
        AND expires_at > NOW() AND used_at IS NULL AND attempts < max_attempts
    ");
    $stmt->execute([$user_id, $code]);
    $verification = $stmt->fetch();
    
    if (!$verification) {
        // Increment attempts
        $pdo->prepare("
            UPDATE verification_codes 
            SET attempts = attempts + 1 
            WHERE user_id = ? AND type = 'email' AND used_at IS NULL
        ")->execute([$user_id]);
        
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired verification code']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mark code as used
        $pdo->prepare("UPDATE verification_codes SET used_at = NOW() WHERE id = ?")
            ->execute([$verification['id']]);
        
        // Mark email as verified
        $pdo->prepare("UPDATE users SET email_verified = TRUE WHERE id = ?")
            ->execute([$user_id]);
        
        // Check if both email and phone are verified, then activate account
        $stmt = $pdo->prepare("SELECT email_verified, phone_verified FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user['email_verified'] && $user['phone_verified']) {
            $pdo->prepare("UPDATE users SET account_status = 'active' WHERE id = ?")
                ->execute([$user_id]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Email verified successfully',
            'account_active' => $user['email_verified'] && $user['phone_verified']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

// Helper functions

function createUserSession($user_id, $login_method, $ip_address, $user_agent) {
    global $pdo;
    
    $session_token = bin2hex(random_bytes(32));
    $refresh_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $device_info = [
        'user_agent' => $user_agent,
        'ip_address' => $ip_address,
        'timestamp' => time()
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO login_sessions 
        (user_id, session_token, refresh_token, device_info, ip_address, user_agent, login_method, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id, $session_token, $refresh_token, json_encode($device_info),
        $ip_address, $user_agent, $login_method, $expires_at
    ]);
    
    return [
        'session_token' => $session_token,
        'refresh_token' => $refresh_token,
        'expires_at' => $expires_at
    ];
}

function sendEmailVerification($user_id, $email) {
    global $pdo;
    
    $stmt = $pdo->prepare("CALL CreateVerificationCode(?, 'email', ?, @code)");
    $stmt->execute([$user_id, $email]);
    
    $result = $pdo->query("SELECT @code as code")->fetch();
    $code = $result['code'];
    
    // Send email (implement with your email service)
    sendVerificationEmail($email, $code);
    
    return $code;
}

function sendSMSVerification($user_id, $phone) {
    global $pdo;
    
    $stmt = $pdo->prepare("CALL CreateVerificationCode(?, 'sms', ?, @code)");
    $stmt->execute([$user_id, $phone]);
    
    $result = $pdo->query("SELECT @code as code")->fetch();
    $code = $result['code'];
    
    // Send SMS (implement with your SMS service)
    sendVerificationSMS($phone, $code);
    
    return $code;
}

function sendVerificationEmail($email, $code) {
    // Implement email sending logic
    // Example: use PHPMailer, SendGrid, etc.
    error_log("Email verification code for {$email}: {$code}");
}

function sendVerificationSMS($phone, $code) {
    // Implement SMS sending logic
    // Example: use Twilio, AWS SNS, etc.
    error_log("SMS verification code for {$phone}: {$code}");
}

// Additional OAuth helper functions would continue here...
?>
