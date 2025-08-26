<?php
/**
 * Authentication API Endpoints
 * Political Memes XYZ
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/../config/auth.php';

$user = new User();
$auth = new Auth();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

try {
    switch ($method) {
        case 'POST':
            if (end($pathParts) === 'register') {
                handleRegister();
            } elseif (end($pathParts) === 'login') {
                handleLogin();
            } elseif (end($pathParts) === 'logout') {
                handleLogout();
            } elseif (end($pathParts) === 'verify-email') {
                handleEmailVerification();
            } elseif (end($pathParts) === 'oauth') {
                handleOAuthLogin();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        case 'GET':
            if (end($pathParts) === 'profile') {
                handleGetProfile();
            } elseif (end($pathParts) === 'verify-session') {
                handleVerifySession();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        case 'PUT':
            if (end($pathParts) === 'profile') {
                handleUpdateProfile();
            } elseif (end($pathParts) === 'location') {
                handleUpdateLocation();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleRegister() {
    global $user, $auth;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['username'], $data['email'], $data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    // Validate input
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }

    if (strlen($data['password']) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters']);
        return;
    }

    // Check if user exists
    if ($user->findByEmail($data['email'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        return;
    }

    if ($user->findByUsername($data['username'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already taken']);
        return;
    }

    // Generate verification token
    $verificationToken = bin2hex(random_bytes(32));
    
    // Get location from GeoIP if available
    $location = getLocationFromIP();
    
    $userData = [
        'username' => $data['username'],
        'email' => $data['email'],
        'password' => $data['password'],
        'phone' => $data['phone'] ?? null,
        'zip_code' => $location['zip_code'] ?? null,
        'city' => $location['city'] ?? null,
        'state' => $location['state'] ?? null,
        'verification_token' => $verificationToken
    ];

    $userId = $user->create($userData);
    
    // Send verification email
    sendVerificationEmail($data['email'], $verificationToken);
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful. Please check your email for verification.',
        'user_id' => $userId
    ]);
}

function handleLogin() {
    global $user, $auth;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['email'], $data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing email or password']);
        return;
    }

    $userData = $user->verifyPassword($data['email'], $data['password']);
    
    if (!$userData) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }

    // Create session
    $sessionToken = $auth->generateSessionToken();
    $refreshToken = $auth->generateRefreshToken();
    
    $user->createSession(
        $userData['id'],
        $sessionToken,
        $refreshToken,
        null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    );
    
    // Update last login
    $user->updateLastLogin($userData['id']);
    
    // Remove sensitive data
    unset($userData['password_hash']);
    unset($userData['verification_token']);
    
    echo json_encode([
        'success' => true,
        'user' => $userData,
        'session_token' => $sessionToken,
        'refresh_token' => $refreshToken
    ]);
}

function handleLogout() {
    global $user;
    
    $sessionToken = getBearerToken();
    
    if ($sessionToken) {
        $user->deleteSession($sessionToken);
    }
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function handleEmailVerification() {
    global $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['token'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing verification token']);
        return;
    }

    if ($user->verifyEmail($data['token'])) {
        echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired verification token']);
    }
}

function handleOAuthLogin() {
    global $user, $auth;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['provider'], $data['oauth_id'], $data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing OAuth data']);
        return;
    }

    // Check if user exists with this OAuth ID
    $existingUser = $user->findByEmail($data['email']);
    
    if ($existingUser) {
        // Login existing user
        $userId = $existingUser['id'];
    } else {
        // Create new user
        $location = getLocationFromIP();
        
        $userData = [
            'username' => $data['username'] ?? generateUsernameFromEmail($data['email']),
            'email' => $data['email'],
            'password' => bin2hex(random_bytes(16)), // Random password for OAuth users
            'oauth_provider' => $data['provider'],
            'oauth_id' => $data['oauth_id'],
            'zip_code' => $location['zip_code'] ?? null,
            'city' => $location['city'] ?? null,
            'state' => $location['state'] ?? null
        ];
        
        $userId = $user->create($userData);
        
        // Auto-verify email for OAuth users
        $user->verifyEmail(null); // Will be handled differently for OAuth
    }

    // Create session
    $sessionToken = $auth->generateSessionToken();
    $refreshToken = $auth->generateRefreshToken();
    
    $user->createSession(
        $userId,
        $sessionToken,
        $refreshToken,
        null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    );
    
    $userData = $user->findById($userId);
    unset($userData['password_hash']);
    
    echo json_encode([
        'success' => true,
        'user' => $userData,
        'session_token' => $sessionToken,
        'refresh_token' => $refreshToken
    ]);
}

function handleGetProfile() {
    global $user;
    
    $sessionToken = getBearerToken();
    $session = $user->validateSession($sessionToken);
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid session']);
        return;
    }
    
    $profile = $user->getProfile($session['user_id']);
    echo json_encode(['success' => true, 'profile' => $profile]);
}

function handleVerifySession() {
    global $user;
    
    $sessionToken = getBearerToken();
    
    if (!$sessionToken) {
        http_response_code(401);
        echo json_encode(['error' => 'No session token provided']);
        return;
    }
    
    $session = $user->validateSession($sessionToken);
    
    if ($session) {
        unset($session['password_hash']);
        echo json_encode(['success' => true, 'user' => $session]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid session']);
    }
}

function handleUpdateProfile() {
    global $user;
    
    $sessionToken = getBearerToken();
    $session = $user->validateSession($sessionToken);
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid session']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Update profile logic here
    echo json_encode(['success' => true, 'message' => 'Profile updated']);
}

function handleUpdateLocation() {
    global $user;
    
    $sessionToken = getBearerToken();
    $session = $user->validateSession($sessionToken);
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid session']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['zip_code'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing zip code']);
        return;
    }
    
    $user->updateLocation(
        $session['user_id'],
        $data['zip_code'],
        $data['city'] ?? null,
        $data['state'] ?? null
    );
    
    echo json_encode(['success' => true, 'message' => 'Location updated']);
}

// Helper functions
function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        return str_replace('Bearer ', '', $headers['Authorization']);
    }
    return null;
}

function getLocationFromIP() {
    // Simple GeoIP implementation - in production, use a proper service
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // For demo purposes, return sample data
    return [
        'zip_code' => '10001',
        'city' => 'New York',
        'state' => 'NY',
        'country' => 'US'
    ];
}

function sendVerificationEmail($email, $token) {
    // Email sending logic - integrate with your email service
    $verificationUrl = "https://politicalmemes.xyz/verify-email?token=" . $token;
    
    // For now, just log it
    error_log("Verification email for {$email}: {$verificationUrl}");
}

function generateUsernameFromEmail($email) {
    $username = explode('@', $email)[0];
    return preg_replace('/[^a-zA-Z0-9]/', '', $username) . rand(100, 999);
}
?>
