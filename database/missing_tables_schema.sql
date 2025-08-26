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
    INDEX idx_endpoint_created (endpoint, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
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
    INDEX idx_user_expires (user_id, expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Missing users table columns (if not exists)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS role ENUM('user', 'store_owner', 'admin', 'super_admin') DEFAULT 'user',
ADD COLUMN IF NOT EXISTS credits DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS phone_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS verification_token VARCHAR(128) NULL,
ADD COLUMN IF NOT EXISTS oauth_provider VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS oauth_id VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;

-- Store closure risk assessments
CREATE TABLE IF NOT EXISTS risk_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_type VARCHAR(50) NOT NULL, -- 'dispensary_risk', 'closure_risk', 'enforcement_risk'
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(50) NULL,
    risk_score DECIMAL(5,3) NOT NULL, -- 0.000 to 1.000
    risk_factors JSON NULL,
    ai_model_version VARCHAR(20) DEFAULT 'v1.0',
    confidence_level DECIMAL(5,3) DEFAULT 0.80,
    expires_at TIMESTAMP NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_city_state (city, state),
    INDEX idx_assessment_type (assessment_type),
    INDEX idx_expires (expires_at)
);

-- User membership tiers and tracking
CREATE TABLE IF NOT EXISTS membership_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price_monthly DECIMAL(8,2) NOT NULL,
    price_yearly DECIMAL(8,2) NULL,
    limits_config JSON NULL, -- Store usage limits like ai_assessments_per_month
    features JSON NULL, -- List of enabled features
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User membership subscriptions
CREATE TABLE IF NOT EXISTS user_memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tier_id INT NOT NULL,
    status ENUM('active', 'cancelled', 'expired', 'suspended') DEFAULT 'active',
    starts_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES membership_tiers(id)
);

-- Revenue transactions for shop owners and platform
CREATE TABLE IF NOT EXISTS revenue_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_owner_id INT NULL,
    user_id INT NULL,
    transaction_type VARCHAR(50) NOT NULL, -- 'api_usage', 'membership_fee', 'premium_feature'
    gross_amount DECIMAL(10,2) NOT NULL,
    platform_fee_amount DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50) NULL,
    payment_reference VARCHAR(255) NULL,
    description TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shop_owner (shop_owner_id),
    INDEX idx_user (user_id),
    INDEX idx_type_status (transaction_type, status),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Politicians table for donation system
CREATE TABLE IF NOT EXISTS politicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    position VARCHAR(255) NOT NULL, -- 'Mayor', 'Governor', 'Senator', etc.
    slug VARCHAR(255) UNIQUE NOT NULL,
    party VARCHAR(100) NULL,
    bio TEXT NULL,
    photo_url VARCHAR(500) NULL,
    website_url VARCHAR(500) NULL,
    donations_enabled BOOLEAN DEFAULT FALSE,
    min_donation_amount DECIMAL(8,2) DEFAULT 5.00,
    max_donation_amount DECIMAL(8,2) DEFAULT 2800.00,
    processing_fee_percent DECIMAL(5,2) DEFAULT 3.00,
    campaign_contact_email VARCHAR(255) NULL,
    donation_instructions TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
);

-- Insert default membership tiers
INSERT IGNORE INTO membership_tiers (tier_name, display_name, description, price_monthly, price_yearly, limits_config, features) VALUES
('free', 'Free Tier', 'Basic access with limited features', 0.00, 0.00, 
 '{"ai_assessments_per_month": 3, "api_calls_per_day": 100}', 
 '["basic_search", "store_listings"]'),
 
('pro', 'Pro Membership', 'Professional features for business users', 29.99, 299.99,
 '{"ai_assessments_per_month": 50, "api_calls_per_day": 1000, "advanced_analytics": true}',
 '["basic_search", "store_listings", "ai_risk_assessment", "advanced_analytics", "priority_support"]'),
 
('enterprise', 'Enterprise', 'Full access for large organizations', 99.99, 999.99,
 '{"ai_assessments_per_month": -1, "api_calls_per_day": -1, "white_label": true}',
 '["*"]');

-- Insert sample politician data
INSERT IGNORE INTO politicians (name, position, slug, party, donations_enabled, bio) VALUES
('Eric Adams', 'Mayor of New York City', 'eric-adams', 'Democratic', TRUE, 'Current Mayor of New York City, focused on public safety and economic development.'),
('Kathy Hochul', 'Governor of New York', 'kathy-hochul', 'Democratic', TRUE, 'Governor of New York State, advocate for cannabis reform and business development.'),
('Chuck Schumer', 'U.S. Senator', 'chuck-schumer', 'Democratic', TRUE, 'Senior U.S. Senator from New York, Senate Majority Leader.');

-- Property listings for market analysis
CREATE TABLE IF NOT EXISTS property_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address VARCHAR(500) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(50) NOT NULL,
    zip_code VARCHAR(20) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    property_type ENUM('retail', 'warehouse', 'office', 'mixed_use') DEFAULT 'retail',
    square_footage INT NULL,
    rent_price DECIMAL(10,2) NULL,
    sale_price DECIMAL(12,2) NULL,
    market_score DECIMAL(3,2) DEFAULT 0.50, -- Market attractiveness 0.00 to 1.00
    zoning_type VARCHAR(100) NULL,
    is_available BOOLEAN DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_city_state (city, state),
    INDEX idx_available (is_available),
    INDEX idx_price_range (rent_price, sale_price)
);

-- Shop locations for competition analysis  
CREATE TABLE IF NOT EXISTS shop_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(500) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(50) NOT NULL,
    zip_code VARCHAR(20) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    location_type ENUM('physical_store', 'delivery_only', 'manufacturing') DEFAULT 'physical_store',
    business_type VARCHAR(100) NULL,
    status ENUM('active', 'closed', 'temporary_closed', 'pending') DEFAULT 'active',
    last_verified TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_city_state (city, state),
    INDEX idx_status (status),
    INDEX idx_business_type (business_type)
);

-- Market data for economic analysis
CREATE TABLE IF NOT EXISTS market_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(100) NOT NULL, -- 'NYC', 'Brooklyn', 'Manhattan', etc.
    metric_name VARCHAR(100) NOT NULL, -- 'average_rent', 'foot_traffic', 'competition_density'
    metric_value DECIMAL(15,4) NOT NULL,
    metric_unit VARCHAR(50) NULL, -- 'dollars', 'count', 'percentage'
    data_source VARCHAR(100) NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_region_metric (region, metric_name),
    INDEX idx_recorded (recorded_at)
);
