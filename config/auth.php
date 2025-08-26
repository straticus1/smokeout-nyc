<?php
/**
 * Authentication Configuration and Helper Class
 * Political Memes XYZ
 */

class Auth {
    private $jwtSecret;
    
    public function __construct() {
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-in-production';
    }

    /**
     * Generate a secure session token
     */
    public function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a refresh token
     */
    public function generateRefreshToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate JWT token (if needed for API authentication)
     */
    public function generateJWT($userId, $expiresIn = 3600) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + $expiresIn
        ]);

        $headerEncoded = $this->base64UrlEncode($header);
        $payloadEncoded = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $this->jwtSecret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }

    /**
     * Verify JWT token
     */
    public function verifyJWT($jwt) {
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) {
            return false;
        }

        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];

        $signature = hash_hmac('sha256', $tokenParts[0] . "." . $tokenParts[1], $this->jwtSecret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        if ($signatureEncoded !== $signatureProvided) {
            return false;
        }

        $payloadData = json_decode($payload, true);
        
        if ($payloadData['exp'] < time()) {
            return false; // Token expired
        }

        return $payloadData;
    }

    /**
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate random verification token
     */
    public function generateVerificationToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Rate limiting check
     */
    public function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        // Simple file-based rate limiting - in production use Redis or database
        $rateLimitFile = sys_get_temp_dir() . '/rate_limit_' . md5($identifier);
        
        if (file_exists($rateLimitFile)) {
            $data = json_decode(file_get_contents($rateLimitFile), true);
            
            // Clean old attempts
            $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($timeWindow) {
                return time() - $timestamp < $timeWindow;
            });
            
            if (count($data['attempts']) >= $maxAttempts) {
                return false; // Rate limit exceeded
            }
        } else {
            $data = ['attempts' => []];
        }
        
        // Add current attempt
        $data['attempts'][] = time();
        file_put_contents($rateLimitFile, json_encode($data));
        
        return true;
    }

    /**
     * OAuth2 configuration
     */
    public function getOAuthConfig($provider) {
        $configs = [
            'google' => [
                'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
                'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
                'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? '',
                'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo'
            ],
            'facebook' => [
                'client_id' => $_ENV['FACEBOOK_CLIENT_ID'] ?? '',
                'client_secret' => $_ENV['FACEBOOK_CLIENT_SECRET'] ?? '',
                'redirect_uri' => $_ENV['FACEBOOK_REDIRECT_URI'] ?? '',
                'auth_url' => 'https://www.facebook.com/v18.0/dialog/oauth',
                'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
                'user_info_url' => 'https://graph.facebook.com/me'
            ],
            'twitter' => [
                'client_id' => $_ENV['TWITTER_CLIENT_ID'] ?? '',
                'client_secret' => $_ENV['TWITTER_CLIENT_SECRET'] ?? '',
                'redirect_uri' => $_ENV['TWITTER_REDIRECT_URI'] ?? '',
                'auth_url' => 'https://twitter.com/i/oauth2/authorize',
                'token_url' => 'https://api.twitter.com/2/oauth2/token',
                'user_info_url' => 'https://api.twitter.com/2/users/me'
            ]
        ];

        return $configs[$provider] ?? null;
    }

    /**
     * Generate OAuth2 authorization URL
     */
    public function getOAuthAuthUrl($provider, $state = null) {
        $config = $this->getOAuthConfig($provider);
        if (!$config) {
            return null;
        }

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => $this->getOAuthScope($provider),
            'state' => $state ?? bin2hex(random_bytes(16))
        ];

        return $config['auth_url'] . '?' . http_build_query($params);
    }

    /**
     * Get OAuth2 scopes for provider
     */
    private function getOAuthScope($provider) {
        $scopes = [
            'google' => 'openid email profile',
            'facebook' => 'email public_profile',
            'twitter' => 'tweet.read users.read'
        ];

        return $scopes[$provider] ?? '';
    }

    /**
     * Exchange OAuth2 code for access token
     */
    public function exchangeOAuthCode($provider, $code) {
        $config = $this->getOAuthConfig($provider);
        if (!$config) {
            return false;
        }

        $data = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $config['redirect_uri']
        ];

        $response = $this->makeHttpRequest($config['token_url'], 'POST', $data);
        return json_decode($response, true);
    }

    /**
     * Get user info from OAuth2 provider
     */
    public function getOAuthUserInfo($provider, $accessToken) {
        $config = $this->getOAuthConfig($provider);
        if (!$config) {
            return false;
        }

        $headers = ['Authorization: Bearer ' . $accessToken];
        $response = $this->makeHttpRequest($config['user_info_url'], 'GET', null, $headers);
        
        return json_decode($response, true);
    }

    /**
     * Make HTTP request
     */
    private function makeHttpRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("HTTP request failed: {$httpCode} - {$response}");
            return false;
        }

        return $response;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize input
     */
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email format
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password strength
     */
    public function validatePassword($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }
}
?>
