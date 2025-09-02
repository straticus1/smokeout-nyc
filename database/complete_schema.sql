-- SmokeoutNYC v2.0 Complete Database Schema
-- MySQL/MariaDB compatible
-- This schema includes all tables needed for the application

-- Set SQL mode for proper handling
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================
-- CORE USER SYSTEM
-- =============================================

-- Users table (enhanced)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'store_owner', 'admin', 'super_admin') DEFAULT 'user',
    credits INT DEFAULT 0,
    zip_code VARCHAR(10),
    city VARCHAR(100),
    state VARCHAR(50),
    country VARCHAR(50) DEFAULT 'US',
    oauth_provider VARCHAR(50),
    oauth_id VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_token_expires TIMESTAMP NULL,
    avatar_url VARCHAR(500),
    bio TEXT,
    preferences JSON,
    last_login TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('active', 'suspended', 'deleted', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_oauth (oauth_provider, oauth_id),
    INDEX idx_verification (verification_token),
    INDEX idx_reset (reset_token)
);

-- User sessions for JWT and session management
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    refresh_token VARCHAR(255),
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_token (session_token),
    INDEX idx_expires (expires_at)
);

-- Rate limiting table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    identifier VARCHAR(100) NOT NULL, -- IP address or user ID
    endpoint VARCHAR(100) NOT NULL,
    request_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_endpoint (identifier, endpoint),
    INDEX idx_window (window_start),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- API access logs
CREATE TABLE IF NOT EXISTS api_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    endpoint VARCHAR(200) NOT NULL,
    method ENUM('GET', 'POST', 'PUT', 'DELETE', 'PATCH') NOT NULL,
    params JSON,
    response_code INT DEFAULT 200,
    response_time_ms INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_endpoint (user_id, endpoint),
    INDEX idx_created (created_at),
    INDEX idx_response_code (response_code),
    INDEX idx_endpoint (endpoint)
);

-- =============================================
-- MEMBERSHIP SYSTEM
-- =============================================

-- Membership tiers
CREATE TABLE IF NOT EXISTS membership_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    price_monthly DECIMAL(10,2) DEFAULT 0.00,
    price_annually DECIMAL(10,2) DEFAULT 0.00,
    features JSON, -- Array of feature names
    limits JSON,   -- Feature limits (e.g., {"ai_assessments_per_month": 100})
    priority INT DEFAULT 0, -- Higher number = higher priority
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_priority (priority)
);

-- User memberships
CREATE TABLE IF NOT EXISTS user_memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tier_id INT NOT NULL,
    status ENUM('active', 'cancelled', 'expired', 'suspended') DEFAULT 'active',
    payment_method ENUM('stripe', 'paypal', 'crypto', 'manual') NOT NULL,
    subscription_id VARCHAR(255), -- External subscription ID
    starts_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    auto_renew BOOLEAN DEFAULT TRUE,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    cancelled_at TIMESTAMP NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES membership_tiers(id) ON DELETE RESTRICT,
    INDEX idx_user_active (user_id, status),
    INDEX idx_expires (expires_at),
    INDEX idx_subscription (subscription_id)
);

-- Membership usage tracking
CREATE TABLE IF NOT EXISTS membership_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feature VARCHAR(100) NOT NULL,
    usage_count INT DEFAULT 1,
    metadata JSON,
    reset_at TIMESTAMP NULL, -- When this usage period resets
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_feature (user_id, feature),
    INDEX idx_created (created_at),
    INDEX idx_reset (reset_at)
);

-- =============================================
-- SMOKE SHOP SYSTEM
-- =============================================

-- Smoke shops/stores
CREATE TABLE IF NOT EXISTS smoke_shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    address VARCHAR(500),
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(10),
    country VARCHAR(50) DEFAULT 'US',
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(500),
    hours JSON, -- Store hours
    status ENUM('open', 'closed_smokeout', 'closed_other', 'reopened', 'permanently_closed') DEFAULT 'open',
    closure_date TIMESTAMP NULL,
    closure_reason TEXT,
    owner_id INT, -- Link to users table for claimed stores
    verified BOOLEAN DEFAULT FALSE,
    rating DECIMAL(3,2) DEFAULT 0.00,
    review_count INT DEFAULT 0,
    images JSON, -- Array of image URLs
    social_media JSON, -- Social media links
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_location (latitude, longitude),
    INDEX idx_status (status),
    INDEX idx_city_state (city, state),
    INDEX idx_owner (owner_id),
    INDEX idx_verified (verified),
    FULLTEXT KEY ft_search (name, description, address)
);

-- Store ownership claims
CREATE TABLE IF NOT EXISTS store_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    store_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verification_method ENUM('business_license', 'utility_bill', 'other') NOT NULL,
    verification_documents JSON,
    admin_notes TEXT,
    processed_by INT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES smoke_shops(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_store (store_id),
    INDEX idx_status (status)
);

-- =============================================
-- GAMING SYSTEM
-- =============================================

-- Game players (extends users for game-specific data)
CREATE TABLE IF NOT EXISTS game_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    level INT DEFAULT 1,
    experience INT DEFAULT 0,
    tokens INT DEFAULT 0, -- Premium game currency
    reputation INT DEFAULT 100,
    current_impairment DECIMAL(4,3) DEFAULT 0.000, -- 0.000 to 1.000
    last_consumption TIMESTAMP NULL,
    total_harvest_weight DECIMAL(10,3) DEFAULT 0.000, -- grams
    total_sales DECIMAL(12,2) DEFAULT 0.00, -- dollars
    mistakes_count INT DEFAULT 0,
    achievements JSON,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_level (level),
    INDEX idx_reputation (reputation),
    INDEX idx_tokens (tokens)
);

-- Cannabis strains
CREATE TABLE IF NOT EXISTS strains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    type ENUM('indica', 'sativa', 'hybrid') NOT NULL,
    thc_min DECIMAL(4,2) DEFAULT 0.00,
    thc_max DECIMAL(4,2) DEFAULT 0.00,
    cbd_min DECIMAL(4,2) DEFAULT 0.00,
    cbd_max DECIMAL(4,2) DEFAULT 0.00,
    flowering_time_min INT DEFAULT 56, -- days
    flowering_time_max INT DEFAULT 70, -- days
    yield_indoor_min DECIMAL(8,3) DEFAULT 0.000, -- grams per plant
    yield_indoor_max DECIMAL(8,3) DEFAULT 0.000,
    yield_outdoor_min DECIMAL(8,3) DEFAULT 0.000,
    yield_outdoor_max DECIMAL(8,3) DEFAULT 0.000,
    difficulty ENUM('beginner', 'intermediate', 'expert') DEFAULT 'beginner',
    effects JSON, -- Array of effect names
    flavors JSON, -- Array of flavor names
    description TEXT,
    genetics TEXT,
    breeder VARCHAR(100),
    unlock_level INT DEFAULT 1,
    seed_price DECIMAL(8,2) DEFAULT 10.00,
    rarity ENUM('common', 'uncommon', 'rare', 'legendary') DEFAULT 'common',
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_difficulty (difficulty),
    INDEX idx_unlock_level (unlock_level),
    INDEX idx_rarity (rarity),
    INDEX idx_active (is_active),
    FULLTEXT KEY ft_search (name, description, genetics, breeder)
);

-- Growing locations
CREATE TABLE IF NOT EXISTS growing_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('indoor_tent', 'indoor_room', 'greenhouse', 'outdoor_garden', 'outdoor_field') NOT NULL,
    capacity INT DEFAULT 1, -- Max plants
    base_yield_multiplier DECIMAL(4,3) DEFAULT 1.000,
    base_quality_multiplier DECIMAL(4,3) DEFAULT 1.000,
    maintenance_cost DECIMAL(8,2) DEFAULT 0.00, -- Per day
    unlock_level INT DEFAULT 1,
    purchase_price DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_unlock_level (unlock_level),
    INDEX idx_active (is_active)
);

-- Player plants
CREATE TABLE IF NOT EXISTS plants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    strain_id INT NOT NULL,
    location_id INT NOT NULL,
    name VARCHAR(100), -- Custom name given by player
    stage ENUM('germination', 'seedling', 'vegetative', 'flowering', 'harvest_ready', 'harvested', 'dead') DEFAULT 'germination',
    health DECIMAL(4,3) DEFAULT 1.000, -- 0.000 to 1.000
    planted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    harvest_ready_at TIMESTAMP NULL,
    harvested_at TIMESTAMP NULL,
    final_weight DECIMAL(8,3), -- grams when harvested
    final_thc DECIMAL(4,2),
    final_cbd DECIMAL(4,2),
    final_quality DECIMAL(4,3) DEFAULT 1.000, -- 0.000 to 1.000
    care_log JSON, -- Array of care actions taken
    problems JSON, -- Array of problems encountered
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (strain_id) REFERENCES strains(id) ON DELETE RESTRICT,
    FOREIGN KEY (location_id) REFERENCES growing_locations(id) ON DELETE RESTRICT,
    INDEX idx_player_stage (player_id, stage),
    INDEX idx_harvest_ready (harvest_ready_at),
    INDEX idx_stage (stage)
);

-- Processed products from harvested plants
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    plant_id INT NOT NULL,
    type ENUM('flower', 'edible', 'concentrate', 'pre_roll', 'hash', 'rosin') NOT NULL,
    name VARCHAR(100),
    weight DECIMAL(8,3) NOT NULL, -- grams
    thc_content DECIMAL(4,2),
    cbd_content DECIMAL(4,2),
    quality DECIMAL(4,3) DEFAULT 1.000,
    potency_multiplier DECIMAL(4,3) DEFAULT 1.000, -- Based on processing method
    market_value DECIMAL(8,2), -- Estimated value
    status ENUM('available', 'consumed', 'sold') DEFAULT 'available',
    processing_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE,
    INDEX idx_player_status (player_id, status),
    INDEX idx_type (type),
    INDEX idx_quality (quality)
);

-- Consumption records
CREATE TABLE IF NOT EXISTS consumptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    product_id INT NOT NULL,
    method ENUM('smoke', 'vape', 'eat', 'dab') NOT NULL,
    amount DECIMAL(6,3) NOT NULL, -- grams consumed
    impairment_added DECIMAL(4,3) NOT NULL, -- Added to player's current impairment
    duration_minutes INT NOT NULL, -- How long effects last
    effects JSON, -- Specific effects experienced
    consumed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_player_consumed (player_id, consumed_at),
    INDEX idx_method (method)
);

-- Game mistakes (when impaired)
CREATE TABLE IF NOT EXISTS game_mistakes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    mistake_type ENUM('process_product', 'sell_bulk', 'plant_care', 'harvesting', 'pricing') NOT NULL,
    impairment_level DECIMAL(4,3) NOT NULL,
    loss_amount DECIMAL(10,2) DEFAULT 0.00, -- Money lost
    loss_product_id INT, -- Product lost (if applicable)
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (loss_product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_player_type (player_id, mistake_type),
    INDEX idx_created (created_at)
);

-- Game sales
CREATE TABLE IF NOT EXISTS game_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    product_id INT NOT NULL,
    buyer_type ENUM('dispensary', 'smoke_shop', 'dealer', 'customer') NOT NULL,
    buyer_name VARCHAR(100),
    quantity DECIMAL(8,3) NOT NULL,
    price_per_gram DECIMAL(8,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    reputation_change INT DEFAULT 0,
    notes TEXT,
    sold_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_player_sold (player_id, sold_at),
    INDEX idx_buyer_type (buyer_type),
    INDEX idx_risk (risk_level)
);

-- =============================================
-- AI RISK ASSESSMENT SYSTEM
-- =============================================

-- Risk assessments
CREATE TABLE IF NOT EXISTS risk_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    assessment_type ENUM('dispensary_location', 'business_closure', 'enforcement', 'market') NOT NULL,
    location_data JSON, -- Address, coordinates, etc.
    risk_factors JSON, -- Detailed risk factor analysis
    risk_score DECIMAL(5,2) NOT NULL, -- 0.00 to 100.00
    risk_level ENUM('very_low', 'low', 'medium', 'high', 'very_high') NOT NULL,
    confidence_score DECIMAL(5,2), -- How confident the AI is
    recommendations JSON, -- Array of recommendation objects
    data_sources JSON, -- Sources used for assessment
    expires_at TIMESTAMP NULL, -- When assessment expires
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, assessment_type),
    INDEX idx_risk_level (risk_level),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at)
);

-- =============================================
-- POLITICAL/DONATION SYSTEM
-- =============================================

-- Politicians
CREATE TABLE IF NOT EXISTS politicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    position VARCHAR(255) NOT NULL,
    party VARCHAR(100),
    city VARCHAR(100),
    state VARCHAR(50),
    county VARCHAR(100),
    zip_code VARCHAR(10),
    district VARCHAR(50),
    office_level ENUM('federal', 'state', 'county', 'city', 'local') NOT NULL,
    photo_url VARCHAR(500),
    bio TEXT,
    website_url VARCHAR(500),
    social_media JSON,
    donation_settings JSON, -- Per-politician donation configuration
    verification_status ENUM('verified', 'pending', 'unverified') DEFAULT 'unverified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_location (state, city, county, zip_code),
    INDEX idx_office (office_level, district),
    INDEX idx_status (status, verification_status),
    FULLTEXT KEY ft_search (name, position, bio)
);

-- Political donations
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    politician_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    processing_fee DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'paypal', 'crypto') NOT NULL,
    payment_id VARCHAR(255), -- External payment ID
    donor_name VARCHAR(255),
    donor_address JSON, -- For FEC compliance
    is_anonymous BOOLEAN DEFAULT FALSE,
    message TEXT,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    INDEX idx_user_politician (user_id, politician_id),
    INDEX idx_amount (amount),
    INDEX idx_status (status),
    INDEX idx_processed (processed_at)
);

-- =============================================
-- NEWS SYSTEM
-- =============================================

-- News articles
CREATE TABLE IF NOT EXISTS news_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT,
    featured_image VARCHAR(500),
    author_id INT NOT NULL,
    category VARCHAR(100),
    tags JSON,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    seo_title VARCHAR(500),
    seo_description TEXT,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_status_published (status, published_at),
    INDEX idx_category (category),
    INDEX idx_author (author_id),
    FULLTEXT KEY ft_search (title, content, excerpt)
);

-- =============================================
-- MESSAGING SYSTEM
-- =============================================

-- Chat messages
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL, -- Denormalized for performance
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'system') DEFAULT 'text',
    is_system BOOLEAN DEFAULT FALSE,
    reply_to INT, -- ID of message being replied to
    reactions JSON, -- Emoji reactions
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to) REFERENCES chat_messages(id) ON DELETE SET NULL,
    INDEX idx_created (created_at),
    INDEX idx_user (user_id),
    INDEX idx_deleted (is_deleted)
);

-- =============================================
-- AUDIT AND SYSTEM TABLES
-- =============================================

-- System logs
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    file VARCHAR(255),
    line INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_level (level),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
);

-- Admin actions audit trail
CREATE TABLE IF NOT EXISTS admin_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50), -- users, stores, etc.
    target_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_action (admin_id, action),
    INDEX idx_target (target_type, target_id),
    INDEX idx_created (created_at)
);

-- =============================================
-- INSERT DEFAULT DATA
-- =============================================

-- Default membership tiers
INSERT INTO membership_tiers (name, slug, description, price_monthly, price_annually, features, limits, priority) VALUES
('Free', 'free', 'Basic access to platform features', 0.00, 0.00, '["basic_search", "limited_ai", "community_access"]', '{"ai_assessments_per_month": 5, "api_requests_per_day": 100}', 1),
('Pro', 'pro', 'Enhanced features for serious users', 9.99, 99.99, '["unlimited_search", "full_ai", "priority_support", "advanced_analytics"]', '{"ai_assessments_per_month": 100, "api_requests_per_day": 1000}', 2),
('Premium', 'premium', 'All features for power users and businesses', 29.99, 299.99, '["everything", "white_label", "custom_branding", "api_access"]', '{"ai_assessments_per_month": -1, "api_requests_per_day": -1}', 3);

-- Default growing locations
INSERT INTO growing_locations (name, type, capacity, base_yield_multiplier, unlock_level, purchase_price, description) VALUES
('Small Tent', 'indoor_tent', 2, 1.0, 1, 0.00, 'Basic 2x2 growing tent for beginners'),
('Medium Tent', 'indoor_tent', 4, 1.1, 3, 500.00, 'Larger 4x4 tent with better ventilation'),
('Grow Room', 'indoor_room', 8, 1.2, 5, 2000.00, 'Dedicated grow room with professional setup'),
('Greenhouse', 'greenhouse', 12, 1.3, 8, 5000.00, 'Climate-controlled greenhouse'),
('Outdoor Garden', 'outdoor_garden', 6, 0.9, 1, 100.00, 'Simple outdoor garden plot'),
('Outdoor Field', 'outdoor_field', 20, 1.0, 10, 10000.00, 'Large outdoor cultivation field');

-- Sample strains
INSERT INTO strains (name, slug, type, thc_min, thc_max, difficulty, unlock_level, seed_price, rarity, description) VALUES
('Northern Lights', 'northern-lights', 'indica', 16.0, 21.0, 'beginner', 1, 10.00, 'common', 'Classic indica strain perfect for beginners'),
('Sour Diesel', 'sour-diesel', 'sativa', 18.0, 25.0, 'intermediate', 3, 15.00, 'common', 'Energizing sativa with diesel aroma'),
('Girl Scout Cookies', 'girl-scout-cookies', 'hybrid', 20.0, 28.0, 'intermediate', 5, 20.00, 'uncommon', 'Popular hybrid with sweet, earthy flavors'),
('White Widow', 'white-widow', 'hybrid', 18.0, 25.0, 'beginner', 2, 12.00, 'common', 'Balanced hybrid covered in white crystal resin'),
('OG Kush', 'og-kush', 'hybrid', 19.0, 26.0, 'expert', 7, 25.00, 'rare', 'Legendary strain with complex terpene profile');

COMMIT;

-- Create indexes for better performance
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_smoke_shops_status_city ON smoke_shops(status, city);
CREATE INDEX idx_plants_player_stage ON plants(player_id, stage);
CREATE INDEX idx_products_player_status ON products(player_id, status);
CREATE INDEX idx_risk_assessments_user_type ON risk_assessments(user_id, assessment_type);

-- Update auto increment values
ALTER TABLE users AUTO_INCREMENT = 1000;
ALTER TABLE smoke_shops AUTO_INCREMENT = 2000;
ALTER TABLE strains AUTO_INCREMENT = 100;
ALTER TABLE growing_locations AUTO_INCREMENT = 10;

-- Final commit
COMMIT;
