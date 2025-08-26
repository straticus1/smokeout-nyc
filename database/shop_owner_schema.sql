-- Shop Owner & Advanced Business Features Schema

-- Shop Owner Profiles
CREATE TABLE shop_owners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    business_name VARCHAR(200) NOT NULL,
    business_type ENUM('dispensary', 'delivery', 'cultivation', 'manufacturing', 'testing_lab') NOT NULL,
    license_number VARCHAR(100),
    license_status ENUM('active', 'pending', 'expired', 'suspended') DEFAULT 'pending',
    business_address TEXT,
    phone_number VARCHAR(20),
    email VARCHAR(255),
    website_url VARCHAR(500),
    established_date DATE,
    employee_count INT DEFAULT 1,
    monthly_revenue DECIMAL(12,2) DEFAULT 0.00,
    compliance_score DECIMAL(3,2) DEFAULT 0.0, -- 0.0 to 1.0
    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    membership_tier ENUM('basic', 'professional', 'enterprise') DEFAULT 'basic',
    membership_expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_business_type (business_type),
    INDEX idx_license_status (license_status),
    INDEX idx_membership (membership_tier, membership_expires_at)
);

-- Physical Locations for Purchase/Rent
CREATE TABLE property_listings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'NY',
    zip_code VARCHAR(10),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    property_type ENUM('retail', 'warehouse', 'cultivation', 'manufacturing', 'office') NOT NULL,
    square_footage INT,
    zoning_compliant BOOLEAN DEFAULT FALSE,
    cannabis_license_eligible BOOLEAN DEFAULT FALSE,
    purchase_price DECIMAL(12,2),
    monthly_rent DECIMAL(10,2),
    listing_type ENUM('sale', 'rent', 'both') NOT NULL,
    owner_contact JSON, -- Contact information
    amenities JSON, -- Parking, security, etc.
    restrictions JSON, -- Zoning restrictions, etc.
    risk_factors JSON, -- Enforcement history, neighborhood issues
    market_score DECIMAL(3,2) DEFAULT 0.0, -- Market attractiveness 0-1
    competition_density INT DEFAULT 0, -- Number of nearby dispensaries
    foot_traffic_score DECIMAL(3,2) DEFAULT 0.0,
    is_available BOOLEAN DEFAULT TRUE,
    listed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_city_type (city, property_type),
    INDEX idx_price_range (purchase_price, monthly_rent),
    INDEX idx_compliance (cannabis_license_eligible, zoning_compliant)
);

-- Shop Ownership & Locations
CREATE TABLE shop_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_owner_id INT NOT NULL,
    property_listing_id INT NULL, -- NULL for street selling
    location_name VARCHAR(200),
    location_type ENUM('physical_store', 'street_corner', 'delivery_hub', 'cultivation_site') NOT NULL,
    address TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    purchase_price DECIMAL(12,2) DEFAULT 0.00,
    monthly_operating_cost DECIMAL(10,2) DEFAULT 0.00,
    daily_foot_traffic INT DEFAULT 0,
    security_level ENUM('none', 'basic', 'advanced', 'premium') DEFAULT 'none',
    enforcement_risk DECIMAL(3,2) DEFAULT 0.5, -- 0.0 to 1.0
    market_demand DECIMAL(3,2) DEFAULT 0.5,
    competition_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('active', 'inactive', 'under_construction', 'closed') DEFAULT 'active',
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_owner_id) REFERENCES shop_owners(id) ON DELETE CASCADE,
    FOREIGN KEY (property_listing_id) REFERENCES property_listings(id),
    INDEX idx_owner_status (shop_owner_id, status),
    INDEX idx_location_type (location_type),
    INDEX idx_risk_demand (enforcement_risk, market_demand)
);

-- Revenue Sharing & Transactions
CREATE TABLE revenue_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_owner_id INT NOT NULL,
    transaction_type ENUM('sale', 'purchase', 'rent', 'fee', 'commission') NOT NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    platform_fee_rate DECIMAL(5,4) DEFAULT 0.1000, -- 10%
    platform_fee_amount DECIMAL(12,2) NOT NULL,
    net_amount DECIMAL(12,2) NOT NULL,
    customer_id INT NULL,
    location_id INT NULL,
    product_details JSON,
    payment_method ENUM('tokens', 'real_money', 'crypto') DEFAULT 'tokens',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_owner_id) REFERENCES shop_owners(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (location_id) REFERENCES shop_locations(id),
    INDEX idx_owner_date (shop_owner_id, processed_at),
    INDEX idx_transaction_type (transaction_type, status),
    INDEX idx_revenue_tracking (processed_at, platform_fee_amount)
);

-- Market Data & Analytics
CREATE TABLE market_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    region VARCHAR(100) NOT NULL, -- City, State, or ZIP
    data_type ENUM('price', 'demand', 'supply', 'competition', 'enforcement') NOT NULL,
    product_category VARCHAR(100), -- flower, edibles, concentrates, etc.
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(12,4) NOT NULL,
    data_source VARCHAR(100), -- API, manual, calculated
    confidence_score DECIMAL(3,2) DEFAULT 1.0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_region_type (region, data_type),
    INDEX idx_metric_time (metric_name, recorded_at),
    INDEX idx_expiration (expires_at)
);

-- AI Risk Assessment
CREATE TABLE risk_assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NULL, -- NULL for general area assessment
    assessment_type ENUM('dispensary_risk', 'enforcement_risk', 'market_risk', 'compliance_risk') NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    city VARCHAR(100),
    state VARCHAR(20),
    risk_score DECIMAL(3,2) NOT NULL, -- 0.0 to 1.0
    risk_factors JSON, -- Detailed breakdown of risk components
    enforcement_history JSON, -- Past enforcement actions in area
    market_conditions JSON, -- Current market state
    regulatory_environment JSON, -- Local laws and regulations
    ai_model_version VARCHAR(50),
    confidence_level DECIMAL(3,2) DEFAULT 0.8,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES shop_locations(id),
    INDEX idx_location_risk (latitude, longitude, assessment_type),
    INDEX idx_city_state (city, state, assessment_type),
    INDEX idx_risk_score (risk_score, assessment_type),
    INDEX idx_expiration (expires_at)
);

-- Membership Programs
CREATE TABLE membership_tiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tier_name VARCHAR(50) NOT NULL UNIQUE,
    monthly_price DECIMAL(8,2) NOT NULL,
    annual_price DECIMAL(10,2),
    features JSON NOT NULL, -- List of included features
    limits_config JSON, -- Usage limits and restrictions
    priority_level INT DEFAULT 1, -- Higher number = higher priority
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Memberships
CREATE TABLE user_memberships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tier_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    auto_renew BOOLEAN DEFAULT TRUE,
    payment_method ENUM('credit_card', 'paypal', 'crypto', 'tokens') DEFAULT 'credit_card',
    status ENUM('active', 'expired', 'cancelled', 'suspended') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES membership_tiers(id),
    INDEX idx_user_status (user_id, status),
    INDEX idx_expiration (expires_at, status)
);

-- Compliance Tracking
CREATE TABLE compliance_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_owner_id INT NOT NULL,
    compliance_type ENUM('license_renewal', 'tax_filing', 'inventory_report', 'security_audit', 'lab_testing') NOT NULL,
    due_date DATE NOT NULL,
    completed_date DATE NULL,
    status ENUM('pending', 'completed', 'overdue', 'failed') DEFAULT 'pending',
    details JSON,
    reminder_sent BOOLEAN DEFAULT FALSE,
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_owner_id) REFERENCES shop_owners(id) ON DELETE CASCADE,
    INDEX idx_owner_status (shop_owner_id, status),
    INDEX idx_due_date (due_date, status)
);

-- Street Selling Mechanics
CREATE TABLE street_selling_spots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    city VARCHAR(100) NOT NULL,
    neighborhood VARCHAR(100),
    foot_traffic_level ENUM('low', 'medium', 'high', 'very_high') DEFAULT 'medium',
    police_patrol_frequency ENUM('rare', 'occasional', 'regular', 'frequent') DEFAULT 'regular',
    bust_risk DECIMAL(3,2) DEFAULT 0.3, -- 0.0 to 1.0
    profit_potential DECIMAL(3,2) DEFAULT 0.5,
    competition_level INT DEFAULT 0, -- Number of other sellers
    time_of_day_modifiers JSON, -- Risk/profit changes by hour
    weather_impact DECIMAL(3,2) DEFAULT 0.1,
    is_active BOOLEAN DEFAULT TRUE,
    discovered_by_user_id INT NULL, -- User who found this spot
    discovery_bonus DECIMAL(8,2) DEFAULT 0.00,
    FOREIGN KEY (discovered_by_user_id) REFERENCES users(id),
    INDEX idx_location (latitude, longitude),
    INDEX idx_risk_profit (bust_risk, profit_potential),
    INDEX idx_city_neighborhood (city, neighborhood)
);

-- Street Selling Sessions
CREATE TABLE street_selling_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    spot_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    products_sold JSON, -- What was sold
    gross_revenue DECIMAL(10,2) DEFAULT 0.00,
    expenses DECIMAL(8,2) DEFAULT 0.00, -- Bribes, protection, etc.
    net_profit DECIMAL(10,2) DEFAULT 0.00,
    platform_fee DECIMAL(8,2) DEFAULT 0.00,
    encounters JSON, -- Police, customers, competitors
    risk_events JSON, -- Busts, robberies, etc.
    experience_gained INT DEFAULT 0,
    reputation_change INT DEFAULT 0,
    status ENUM('active', 'completed', 'busted', 'robbed') DEFAULT 'active',
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (spot_id) REFERENCES street_selling_spots(id),
    INDEX idx_player_sessions (player_id, started_at),
    INDEX idx_spot_activity (spot_id, started_at),
    INDEX idx_revenue_tracking (started_at, net_profit)
);

-- Insert default membership tiers
INSERT INTO membership_tiers (tier_name, monthly_price, annual_price, features, limits_config) VALUES
('Basic', 25.00, 250.00, 
 '["market_data_access", "basic_analytics", "compliance_reminders", "community_access"]',
 '{"api_calls_per_day": 100, "locations_tracked": 3, "reports_per_month": 5}'),
 
('Professional', 50.00, 500.00,
 '["advanced_analytics", "risk_assessments", "priority_support", "custom_reports", "ai_insights"]',
 '{"api_calls_per_day": 500, "locations_tracked": 10, "reports_per_month": 25, "ai_assessments_per_month": 50}'),
 
('Enterprise', 100.00, 1000.00,
 '["unlimited_analytics", "white_label_options", "dedicated_support", "custom_integrations", "advanced_ai"]',
 '{"api_calls_per_day": -1, "locations_tracked": -1, "reports_per_month": -1, "ai_assessments_per_month": -1}');

-- Insert sample street selling spots
INSERT INTO street_selling_spots (name, latitude, longitude, city, neighborhood, foot_traffic_level, police_patrol_frequency, bust_risk, profit_potential) VALUES
('Washington Square Park Corner', 40.7308, -73.9973, 'New York', 'Greenwich Village', 'very_high', 'frequent', 0.7, 0.8),
('Times Square Side Street', 40.7580, -73.9855, 'New York', 'Midtown', 'very_high', 'frequent', 0.9, 0.9),
('Brooklyn Bridge Approach', 40.7061, -73.9969, 'New York', 'DUMBO', 'high', 'regular', 0.4, 0.6),
('Central Park South', 40.7677, -73.9807, 'New York', 'Upper East Side', 'high', 'regular', 0.6, 0.7),
('Coney Island Boardwalk', 40.5755, -73.9707, 'New York', 'Coney Island', 'medium', 'occasional', 0.2, 0.4);

-- Create stored procedures for advanced features

DELIMITER //

-- Calculate AI Risk Score
CREATE PROCEDURE CalculateAIRiskScore(
    IN p_latitude DECIMAL(10,8),
    IN p_longitude DECIMAL(11,8),
    IN p_city VARCHAR(100),
    IN p_state VARCHAR(20),
    OUT p_risk_score DECIMAL(3,2)
)
BEGIN
    DECLARE enforcement_factor DECIMAL(3,2) DEFAULT 0.3;
    DECLARE market_factor DECIMAL(3,2) DEFAULT 0.2;
    DECLARE competition_factor DECIMAL(3,2) DEFAULT 0.2;
    DECLARE regulatory_factor DECIMAL(3,2) DEFAULT 0.3;
    
    -- This is a simplified calculation - in reality would use ML models
    -- Factor in enforcement history, market conditions, competition, regulations
    
    SET p_risk_score = LEAST(
        (enforcement_factor + market_factor + competition_factor + regulatory_factor),
        1.0
    );
    
    -- Insert the assessment
    INSERT INTO risk_assessments 
    (assessment_type, latitude, longitude, city, state, risk_score, ai_model_version, expires_at)
    VALUES 
    ('dispensary_risk', p_latitude, p_longitude, p_city, p_state, p_risk_score, 'v1.0', DATE_ADD(NOW(), INTERVAL 24 HOUR));
END //

-- Process Revenue Transaction with Platform Fee
CREATE PROCEDURE ProcessRevenueTransaction(
    IN p_shop_owner_id INT,
    IN p_transaction_type VARCHAR(20),
    IN p_gross_amount DECIMAL(12,2),
    IN p_customer_id INT,
    IN p_location_id INT,
    OUT p_transaction_id INT
)
BEGIN
    DECLARE platform_fee_rate DECIMAL(5,4) DEFAULT 0.1000; -- 10%
    DECLARE platform_fee_amount DECIMAL(12,2);
    DECLARE net_amount DECIMAL(12,2);
    
    SET platform_fee_amount = p_gross_amount * platform_fee_rate;
    SET net_amount = p_gross_amount - platform_fee_amount;
    
    INSERT INTO revenue_transactions 
    (shop_owner_id, transaction_type, gross_amount, platform_fee_rate, 
     platform_fee_amount, net_amount, customer_id, location_id, status)
    VALUES 
    (p_shop_owner_id, p_transaction_type, p_gross_amount, platform_fee_rate,
     platform_fee_amount, net_amount, p_customer_id, p_location_id, 'completed');
    
    SET p_transaction_id = LAST_INSERT_ID();
    
    -- Update shop owner revenue
    UPDATE shop_owners 
    SET monthly_revenue = monthly_revenue + net_amount
    WHERE id = p_shop_owner_id;
END //

DELIMITER ;
