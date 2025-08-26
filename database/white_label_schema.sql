-- White Label Licensing System Database Schema
-- Enables licensing the platform to other markets and states

-- White label license tiers
CREATE TABLE IF NOT EXISTS white_label_tiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tier_name VARCHAR(100) NOT NULL,
    description TEXT,
    monthly_fee DECIMAL(10,2) NOT NULL,
    setup_fee DECIMAL(10,2) DEFAULT 0.00,
    revenue_share_percentage DECIMAL(5,2) NOT NULL, -- Platform's cut (e.g., 15.00 for 15%)
    max_users INT DEFAULT -1, -- -1 for unlimited
    max_transactions_per_month INT DEFAULT -1,
    features JSON, -- {"custom_branding": true, "api_access": true, "analytics": true}
    limitations JSON, -- {"custom_domains": 1, "admin_users": 3}
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- White label licenses issued to businesses
CREATE TABLE IF NOT EXISTS white_label_licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    licensee_user_id INT NOT NULL,
    tier_id INT NOT NULL,
    business_name VARCHAR(200) NOT NULL,
    target_market VARCHAR(100) NOT NULL, -- "California", "New York", "Canada", etc.
    domain_name VARCHAR(255) UNIQUE,
    license_key VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('pending', 'active', 'suspended', 'expired', 'rejected') DEFAULT 'pending',
    custom_config JSON, -- Custom configuration for this license
    branding_config JSON, -- Logo, colors, company name, etc.
    business_details JSON, -- Business registration, contact info, etc.
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (licensee_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES white_label_tiers(id)
);

-- White label deployments tracking
CREATE TABLE IF NOT EXISTS white_label_deployments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT NOT NULL,
    deployment_url VARCHAR(255) NOT NULL,
    deployment_status ENUM('pending', 'deploying', 'deployed', 'failed', 'maintenance') DEFAULT 'pending',
    server_config JSON, -- Server specifications, region, etc.
    last_deployed TIMESTAMP NULL,
    deployed_at TIMESTAMP NULL,
    health_check_url VARCHAR(255),
    last_health_check TIMESTAMP NULL,
    health_status ENUM('healthy', 'warning', 'critical', 'unknown') DEFAULT 'unknown',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES white_label_licenses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_license_deployment (license_id)
);

-- Revenue tracking for white label instances
CREATE TABLE IF NOT EXISTS white_label_revenue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    gross_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    platform_share DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    licensee_share DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    transaction_count INT DEFAULT 0,
    active_users INT DEFAULT 0,
    new_users INT DEFAULT 0,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES white_label_licenses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_license_period (license_id, period_start, period_end)
);

-- Add white label reference to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS white_label_license_id INT NULL;
ALTER TABLE users ADD FOREIGN KEY (white_label_license_id) REFERENCES white_label_licenses(id);

-- Insert white label tiers
INSERT INTO white_label_tiers (tier_name, description, monthly_fee, setup_fee, revenue_share_percentage, max_users, features, limitations) VALUES
('Starter', 'Perfect for small markets and testing', 299.00, 500.00, 25.00, 1000, 
 '{"custom_branding": true, "basic_analytics": true, "email_support": true, "game_features": "basic"}', 
 '{"custom_domains": 1, "admin_users": 2, "api_calls_per_day": 10000}'),

('Professional', 'Ideal for established businesses and medium markets', 799.00, 1000.00, 20.00, 5000,
 '{"custom_branding": true, "advanced_analytics": true, "priority_support": true, "game_features": "standard", "white_label_api": true, "custom_integrations": true}',
 '{"custom_domains": 3, "admin_users": 5, "api_calls_per_day": 50000}'),

('Enterprise', 'Full-featured solution for large markets and corporations', 1999.00, 2500.00, 15.00, -1,
 '{"custom_branding": true, "enterprise_analytics": true, "dedicated_support": true, "game_features": "premium", "white_label_api": true, "custom_integrations": true, "multi_region": true, "sso_integration": true}',
 '{"custom_domains": -1, "admin_users": -1, "api_calls_per_day": -1}'),

('Regional Network', 'Multi-state or country-wide licensing', 4999.00, 10000.00, 12.00, -1,
 '{"custom_branding": true, "enterprise_analytics": true, "dedicated_account_manager": true, "game_features": "premium", "white_label_api": true, "custom_integrations": true, "multi_region": true, "sso_integration": true, "franchise_management": true}',
 '{"custom_domains": -1, "admin_users": -1, "api_calls_per_day": -1, "sub_licenses": 10}');

-- Create indexes for performance
CREATE INDEX idx_white_label_licenses_status ON white_label_licenses(status);
CREATE INDEX idx_white_label_licenses_market ON white_label_licenses(target_market);
CREATE INDEX idx_white_label_deployments_status ON white_label_deployments(deployment_status);
CREATE INDEX idx_white_label_revenue_period ON white_label_revenue(period_start, period_end);
CREATE INDEX idx_users_white_label ON users(white_label_license_id);

-- Create stored procedure for revenue calculation
DELIMITER //
CREATE PROCEDURE CalculateWhiteLabelRevenue(IN p_license_id INT, IN p_start_date DATE, IN p_end_date DATE)
BEGIN
    DECLARE v_revenue_share DECIMAL(5,2);
    DECLARE v_gross_revenue DECIMAL(12,2) DEFAULT 0.00;
    DECLARE v_platform_share DECIMAL(12,2);
    DECLARE v_licensee_share DECIMAL(12,2);
    DECLARE v_transaction_count INT DEFAULT 0;
    DECLARE v_active_users INT DEFAULT 0;
    DECLARE v_new_users INT DEFAULT 0;
    
    -- Get revenue share percentage
    SELECT wlt.revenue_share_percentage INTO v_revenue_share
    FROM white_label_licenses wl
    JOIN white_label_tiers wlt ON wl.tier_id = wlt.id
    WHERE wl.id = p_license_id;
    
    -- Calculate gross revenue from game transactions
    SELECT 
        COALESCE(SUM(gt.amount), 0),
        COUNT(*)
    INTO v_gross_revenue, v_transaction_count
    FROM game_transactions gt
    JOIN game_players gp ON gt.player_id = gp.id
    JOIN users u ON gp.user_id = u.id
    WHERE u.white_label_license_id = p_license_id
    AND DATE(gt.transaction_date) BETWEEN p_start_date AND p_end_date;
    
    -- Add revenue from membership fees
    SELECT 
        COALESCE(SUM(rt.gross_amount), 0) + v_gross_revenue
    INTO v_gross_revenue
    FROM revenue_transactions rt
    JOIN shop_owners so ON rt.shop_owner_id = so.id
    JOIN users u ON so.user_id = u.id
    WHERE u.white_label_license_id = p_license_id
    AND DATE(rt.processed_at) BETWEEN p_start_date AND p_end_date;
    
    -- Calculate active and new users
    SELECT 
        COUNT(DISTINCT CASE WHEN u.last_login BETWEEN p_start_date AND p_end_date THEN u.id END),
        COUNT(DISTINCT CASE WHEN DATE(u.created_at) BETWEEN p_start_date AND p_end_date THEN u.id END)
    INTO v_active_users, v_new_users
    FROM users u
    WHERE u.white_label_license_id = p_license_id;
    
    -- Calculate revenue shares
    SET v_platform_share = v_gross_revenue * (v_revenue_share / 100);
    SET v_licensee_share = v_gross_revenue - v_platform_share;
    
    -- Insert or update revenue record
    INSERT INTO white_label_revenue 
    (license_id, period_start, period_end, gross_revenue, platform_share, 
     licensee_share, transaction_count, active_users, new_users)
    VALUES (p_license_id, p_start_date, p_end_date, v_gross_revenue, 
            v_platform_share, v_licensee_share, v_transaction_count, 
            v_active_users, v_new_users)
    ON DUPLICATE KEY UPDATE
        gross_revenue = v_gross_revenue,
        platform_share = v_platform_share,
        licensee_share = v_licensee_share,
        transaction_count = v_transaction_count,
        active_users = v_active_users,
        new_users = v_new_users,
        calculated_at = NOW();
        
    -- Return the calculated values
    SELECT v_gross_revenue as gross_revenue, 
           v_platform_share as platform_share,
           v_licensee_share as licensee_share,
           v_transaction_count as transaction_count,
           v_active_users as active_users,
           v_new_users as new_users;
END //
DELIMITER ;

-- Create stored procedure for license activation
DELIMITER //
CREATE PROCEDURE ActivateWhiteLabelLicense(IN p_license_id INT)
BEGIN
    DECLARE v_tier_id INT;
    DECLARE v_monthly_fee DECIMAL(10,2);
    
    -- Get license details
    SELECT tier_id INTO v_tier_id
    FROM white_label_licenses
    WHERE id = p_license_id;
    
    -- Get monthly fee
    SELECT monthly_fee INTO v_monthly_fee
    FROM white_label_tiers
    WHERE id = v_tier_id;
    
    -- Activate license
    UPDATE white_label_licenses
    SET status = 'active',
        activated_at = NOW(),
        expires_at = DATE_ADD(NOW(), INTERVAL 1 YEAR)
    WHERE id = p_license_id;
    
    -- Create initial revenue record
    INSERT INTO white_label_revenue
    (license_id, period_start, period_end, gross_revenue, platform_share, licensee_share)
    VALUES (p_license_id, CURDATE(), LAST_DAY(CURDATE()), 0.00, 0.00, 0.00);
    
    SELECT 'License activated successfully' as message;
END //
DELIMITER ;

-- Create view for license dashboard
CREATE VIEW white_label_dashboard AS
SELECT 
    wl.id as license_id,
    wl.business_name,
    wl.target_market,
    wl.status,
    wlt.tier_name,
    wld.deployment_url,
    wld.deployment_status,
    wld.health_status,
    wlr.gross_revenue as monthly_revenue,
    wlr.active_users,
    wlr.new_users,
    wl.activated_at,
    wl.expires_at
FROM white_label_licenses wl
JOIN white_label_tiers wlt ON wl.tier_id = wlt.id
LEFT JOIN white_label_deployments wld ON wl.id = wld.license_id
LEFT JOIN white_label_revenue wlr ON wl.id = wlr.license_id 
    AND wlr.period_start = DATE_FORMAT(NOW(), '%Y-%m-01')
    AND wlr.period_end = LAST_DAY(NOW());
