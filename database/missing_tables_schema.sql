-- Missing Essential Database Tables for SmokeoutNYC
-- Tables required by the API endpoints but not yet created

-- Rate limiting table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_created (identifier, created_at)
);

-- API access logs
CREATE TABLE IF NOT EXISTS api_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_endpoint_created (endpoint, created_at)
);

-- User sessions table (referenced by authenticate function)
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(128) UNIQUE NOT NULL,
    refresh_token VARCHAR(128) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_token (session_token),
    INDEX idx_user_expires (user_id, expires_at)
);

-- Risk assessments table
CREATE TABLE IF NOT EXISTS risk_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_lat DECIMAL(10, 7) NOT NULL,
    location_lng DECIMAL(10, 7) NOT NULL,
    risk_type ENUM('dispensary', 'closure', 'enforcement') NOT NULL,
    risk_score DECIMAL(5, 2) NOT NULL,
    risk_factors JSON NULL,
    assessment_data JSON NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (location_lat, location_lng),
    INDEX idx_risk_type_created (risk_type, created_at)
);

-- Membership tiers
CREATE TABLE IF NOT EXISTS membership_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT NULL,
    price_monthly DECIMAL(10, 2) NOT NULL,
    price_yearly DECIMAL(10, 2) NULL,
    features JSON NULL,
    max_searches INT NULL,
    max_alerts INT NULL,
    priority_support BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User memberships
CREATE TABLE IF NOT EXISTS user_memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tier_id INT NOT NULL,
    subscription_id VARCHAR(128) NULL,
    payment_method ENUM('paypal', 'stripe', 'bitcoin') NOT NULL,
    status ENUM('active', 'cancelled', 'expired', 'pending') DEFAULT 'pending',
    starts_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    auto_renew BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    INDEX idx_expires (expires_at)
);

-- Politicians table for donation system
CREATE TABLE IF NOT EXISTS politicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    office VARCHAR(255) NOT NULL,
    party ENUM('Democratic', 'Republican', 'Independent', 'Other') NULL,
    bio TEXT NULL,
    photo_url VARCHAR(512) NULL,
    donation_url VARCHAR(512) NULL,
    stance_on_cannabis ENUM('supportive', 'neutral', 'opposed', 'unknown') DEFAULT 'unknown',
    cannabis_voting_record JSON NULL,
    min_donation DECIMAL(10, 2) DEFAULT 1.00,
    max_donation DECIMAL(10, 2) DEFAULT 2900.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_office_active (office, is_active)
);

-- Revenue transactions table
CREATE TABLE IF NOT EXISTS revenue_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    type ENUM('donation', 'membership', 'tokens', 'premium_feature', 'advertisement') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    fee_amount DECIMAL(10, 2) DEFAULT 0.00,
    net_amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(50) NULL,
    payment_id VARCHAR(128) NULL,
    reference_id INT NULL,
    description TEXT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_type_status (type, status),
    INDEX idx_payment_id (payment_id)
);

-- Property listings table (for market data)
CREATE TABLE IF NOT EXISTS property_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address VARCHAR(512) NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    property_type ENUM('retail', 'commercial', 'warehouse', 'office') NOT NULL,
    size_sqft INT NULL,
    rent_monthly DECIMAL(10, 2) NULL,
    sale_price DECIMAL(12, 2) NULL,
    zoning VARCHAR(50) NULL,
    cannabis_friendly BOOLEAN NULL,
    available_date DATE NULL,
    listing_url VARCHAR(512) NULL,
    contact_info JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_type_active (property_type, is_active),
    INDEX idx_price_range (rent_monthly, sale_price)
);

-- Shop locations table for competitive analysis
CREATE TABLE IF NOT EXISTS shop_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(512) NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    shop_type ENUM('smoke_shop', 'dispensary', 'cbd_store', 'head_shop') NOT NULL,
    license_status ENUM('licensed', 'unlicensed', 'pending', 'revoked', 'unknown') DEFAULT 'unknown',
    license_number VARCHAR(128) NULL,
    phone VARCHAR(20) NULL,
    website VARCHAR(512) NULL,
    hours JSON NULL,
    products JSON NULL,
    average_rating DECIMAL(3, 2) NULL,
    review_count INT DEFAULT 0,
    last_inspection_date DATE NULL,
    violations_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_type_status (shop_type, license_status),
    INDEX idx_active (is_active)
);

-- Market data table for economic analysis
CREATE TABLE IF NOT EXISTS market_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_type ENUM('pricing', 'demand', 'supply', 'competition', 'regulation') NOT NULL,
    geographic_scope ENUM('city', 'borough', 'neighborhood', 'zipcode') NOT NULL,
    scope_identifier VARCHAR(50) NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15, 4) NOT NULL,
    unit VARCHAR(50) NULL,
    data_source VARCHAR(255) NULL,
    confidence_score DECIMAL(3, 2) NULL,
    valid_from TIMESTAMP NOT NULL,
    valid_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_scope (data_type, geographic_scope, scope_identifier),
    INDEX idx_metric_date (metric_name, valid_from),
    INDEX idx_validity (valid_from, valid_until)
);

-- Insert default membership tiers
INSERT IGNORE INTO membership_tiers (id, name, description, price_monthly, price_yearly, features, max_searches, max_alerts) VALUES
(1, 'Free', 'Basic access to smoke shop data', 0.00, 0.00, '["Basic search", "Limited alerts"]', 10, 3),
(2, 'Pro', 'Enhanced features for enthusiasts', 9.99, 99.99, '["Unlimited search", "Real-time alerts", "Risk assessment", "Police proximity"]', NULL, NULL),
(3, 'Premium', 'Full access with advanced analytics', 19.99, 199.99, '["All Pro features", "Market analytics", "Predictive insights", "API access", "Priority support"]', NULL, NULL);

-- Insert sample politicians (NYC focus)
INSERT IGNORE INTO politicians (name, office, party, stance_on_cannabis, min_donation, max_donation) VALUES
('Kathy Hochul', 'Governor of New York', 'Democratic', 'supportive', 5.00, 2900.00),
('Eric Adams', 'Mayor of New York City', 'Democratic', 'supportive', 5.00, 2900.00),
('Chuck Schumer', 'U.S. Senate', 'Democratic', 'supportive', 5.00, 2900.00),
('Kirsten Gillibrand', 'U.S. Senate', 'Democratic', 'supportive', 5.00, 2900.00),
('Alexandria Ocasio-Cortez', 'U.S. House - NY-14', 'Democratic', 'supportive', 5.00, 2900.00),
('Jerrold Nadler', 'U.S. House - NY-12', 'Democratic', 'supportive', 5.00, 2900.00);