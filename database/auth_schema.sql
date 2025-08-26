-- Enhanced Authentication System Schema

-- Update users table with comprehensive fields
ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(50) UNIQUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20);
ALTER TABLE users ADD COLUMN IF NOT EXISTS interests JSON;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image_url VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS login_count INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS account_status ENUM('active', 'suspended', 'pending_verification') DEFAULT 'pending_verification';
ALTER TABLE users ADD COLUMN IF NOT EXISTS preferred_2fa ENUM('none', 'sms', 'email', 'app') DEFAULT 'none';

-- OAuth Providers Table
CREATE TABLE oauth_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    authorization_url VARCHAR(500) NOT NULL,
    token_url VARCHAR(500) NOT NULL,
    user_info_url VARCHAR(500) NOT NULL,
    scope VARCHAR(255) DEFAULT '',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User OAuth Connections
CREATE TABLE user_oauth_connections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    provider_id INT NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP NULL,
    provider_email VARCHAR(255),
    provider_name VARCHAR(255),
    provider_avatar_url VARCHAR(500),
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES oauth_providers(id),
    UNIQUE KEY unique_user_provider (user_id, provider_id),
    UNIQUE KEY unique_provider_user (provider_id, provider_user_id),
    INDEX idx_provider_user_id (provider_id, provider_user_id)
);

-- Verification Codes Table (for email/SMS verification)
CREATE TABLE verification_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    type ENUM('email', 'sms', 'password_reset', '2fa') NOT NULL,
    contact_info VARCHAR(255) NOT NULL, -- email or phone number
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, type),
    INDEX idx_code_expiry (code, expires_at),
    INDEX idx_contact_type (contact_info, type)
);

-- Login Sessions Table
CREATE TABLE login_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    refresh_token VARCHAR(255) NOT NULL UNIQUE,
    device_info JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_method ENUM('password', 'oauth_google', 'oauth_facebook', 'oauth2') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_session_token (session_token),
    INDEX idx_expiry (expires_at)
);

-- Login Attempts Table (for security monitoring)
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL, -- username, email, or phone
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    failure_reason VARCHAR(100),
    user_agent TEXT,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_ip (identifier, ip_address),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_success (success, attempted_at)
);

-- Password Reset Tokens
CREATE TABLE password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_expiry (user_id, expires_at)
);

-- User Interests/Preferences
CREATE TABLE user_interests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Interest Selections
CREATE TABLE user_interest_selections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    interest_id INT NOT NULL,
    selected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (interest_id) REFERENCES user_interests(id),
    UNIQUE KEY unique_user_interest (user_id, interest_id)
);

-- Insert default OAuth providers
INSERT INTO oauth_providers (name, client_id, client_secret, authorization_url, token_url, user_info_url, scope) VALUES
('google', 
 COALESCE(ENV('GOOGLE_OAUTH_CLIENT_ID'), 'your-google-client-id'),
 COALESCE(ENV('GOOGLE_OAUTH_CLIENT_SECRET'), 'your-google-client-secret'),
 'https://accounts.google.com/o/oauth2/auth',
 'https://oauth2.googleapis.com/token',
 'https://www.googleapis.com/oauth2/v2/userinfo',
 'openid email profile'),

('facebook',
 COALESCE(ENV('FACEBOOK_APP_ID'), 'your-facebook-app-id'),
 COALESCE(ENV('FACEBOOK_APP_SECRET'), 'your-facebook-app-secret'),
 'https://www.facebook.com/v18.0/dialog/oauth',
 'https://graph.facebook.com/v18.0/oauth/access_token',
 'https://graph.facebook.com/v18.0/me',
 'email,public_profile');

-- Insert default user interests
INSERT INTO user_interests (name, category, description) VALUES
('Cannabis Cultivation', 'growing', 'Growing and cultivating cannabis plants'),
('Strain Research', 'education', 'Learning about different cannabis strains'),
('Medical Cannabis', 'health', 'Using cannabis for medical purposes'),
('Cannabis Cooking', 'lifestyle', 'Making edibles and cannabis-infused foods'),
('Cannabis News', 'information', 'Staying updated on cannabis industry news'),
('Legalization Advocacy', 'activism', 'Supporting cannabis legalization efforts'),
('Cannabis Business', 'business', 'Cannabis industry and entrepreneurship'),
('Vaping Technology', 'technology', 'Vaporizers and consumption technology'),
('Cannabis Events', 'social', 'Attending cannabis-related events and meetups'),
('Hydroponics', 'growing', 'Hydroponic growing systems and techniques'),
('Organic Growing', 'growing', 'Organic and sustainable growing methods'),
('Cannabis Testing', 'science', 'Lab testing and quality assurance'),
('Cannabis Tourism', 'travel', 'Cannabis-friendly travel and destinations'),
('Cannabis Art', 'culture', 'Cannabis-inspired art and creativity'),
('Cannabis Community', 'social', 'Connecting with other cannabis enthusiasts');

-- Create indexes for performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email_verified ON users(email, email_verified);
CREATE INDEX idx_users_phone_verified ON users(phone_number, phone_verified);
CREATE INDEX idx_users_account_status ON users(account_status);
CREATE INDEX idx_verification_codes_active ON verification_codes(expires_at, used_at);
CREATE INDEX idx_sessions_cleanup ON login_sessions(expires_at, is_active);

-- Create stored procedures for authentication

DELIMITER //

-- Procedure to clean up expired sessions and codes
CREATE PROCEDURE CleanupExpiredAuth()
BEGIN
    -- Clean up expired verification codes
    DELETE FROM verification_codes WHERE expires_at < NOW();
    
    -- Clean up expired sessions
    UPDATE login_sessions SET is_active = FALSE WHERE expires_at < NOW();
    
    -- Clean up old login attempts (keep last 30 days)
    DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Clean up used password reset tokens
    DELETE FROM password_reset_tokens WHERE used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
END //

-- Procedure to record login attempt
CREATE PROCEDURE RecordLoginAttempt(
    IN p_identifier VARCHAR(255),
    IN p_ip_address VARCHAR(45),
    IN p_success BOOLEAN,
    IN p_failure_reason VARCHAR(100),
    IN p_user_agent TEXT
)
BEGIN
    INSERT INTO login_attempts (identifier, ip_address, success, failure_reason, user_agent)
    VALUES (p_identifier, p_ip_address, p_success, p_failure_reason, p_user_agent);
END //

-- Procedure to check rate limiting
CREATE PROCEDURE CheckRateLimit(
    IN p_identifier VARCHAR(255),
    IN p_ip_address VARCHAR(45),
    OUT p_is_blocked BOOLEAN,
    OUT p_attempts_count INT
)
BEGIN
    DECLARE attempts_in_window INT DEFAULT 0;
    
    -- Count failed attempts in last 15 minutes
    SELECT COUNT(*) INTO attempts_in_window
    FROM login_attempts
    WHERE (identifier = p_identifier OR ip_address = p_ip_address)
    AND success = FALSE
    AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE);
    
    SET p_attempts_count = attempts_in_window;
    SET p_is_blocked = attempts_in_window >= 5; -- Block after 5 failed attempts
END //

-- Procedure to create verification code
CREATE PROCEDURE CreateVerificationCode(
    IN p_user_id INT,
    IN p_type VARCHAR(20),
    IN p_contact_info VARCHAR(255),
    OUT p_code VARCHAR(10)
)
BEGIN
    DECLARE code_length INT DEFAULT 6;
    
    -- Generate random 6-digit code
    SET p_code = LPAD(FLOOR(RAND() * 1000000), code_length, '0');
    
    -- Invalidate existing codes of same type
    UPDATE verification_codes 
    SET used_at = NOW() 
    WHERE user_id = p_user_id AND type = p_type AND used_at IS NULL;
    
    -- Insert new code
    INSERT INTO verification_codes (user_id, code, type, contact_info, expires_at)
    VALUES (p_user_id, p_code, p_type, p_contact_info, DATE_ADD(NOW(), INTERVAL 15 MINUTE));
END //

DELIMITER ;

-- Create event scheduler to clean up expired data
CREATE EVENT IF NOT EXISTS cleanup_expired_auth
ON SCHEDULE EVERY 1 HOUR
DO CALL CleanupExpiredAuth();
