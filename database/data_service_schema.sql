-- Data-as-a-Service Database Schema
-- Enables selling anonymized market intelligence to cannabis businesses

-- Data packages available for subscription
CREATE TABLE IF NOT EXISTS data_packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    package_name VARCHAR(100) NOT NULL,
    description TEXT,
    monthly_price DECIMAL(10,2) NOT NULL,
    annual_price DECIMAL(10,2) NOT NULL,
    api_calls_per_month INT NOT NULL DEFAULT 10000,
    data_types JSON, -- ["market_intelligence", "pricing_data", "demand_analytics", "regulatory_updates"]
    access_levels JSON, -- ["basic", "advanced", "premium", "enterprise"]
    features JSON, -- {"real_time_data": true, "historical_data": true, "export_capability": true}
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Data subscriptions for businesses
CREATE TABLE IF NOT EXISTS data_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_user_id INT NOT NULL,
    package_id INT NOT NULL,
    api_key VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('active', 'suspended', 'expired', 'cancelled') DEFAULT 'active',
    expires_at DATETIME NOT NULL,
    api_calls_used INT DEFAULT 0,
    api_calls_limit INT NOT NULL,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES data_packages(id)
);

-- API usage logging for billing and analytics
CREATE TABLE IF NOT EXISTS data_usage_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    api_key VARCHAR(100) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    data_type VARCHAR(50),
    request_size_kb INT DEFAULT 0,
    response_size_kb INT DEFAULT 0,
    processing_time_ms INT DEFAULT 0,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key_date (api_key, used_at),
    INDEX idx_endpoint_date (endpoint, used_at)
);

-- Data export jobs
CREATE TABLE IF NOT EXISTS data_exports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    requester_user_id INT NOT NULL,
    export_type ENUM('market_data', 'user_analytics', 'game_metrics', 'regulatory_data') NOT NULL,
    format ENUM('json', 'csv', 'xlsx') DEFAULT 'json',
    filters JSON, -- Export parameters and filters
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    file_path VARCHAR(500),
    file_size_mb DECIMAL(8,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL, -- When export file expires
    FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Aggregated market data for faster API responses
CREATE TABLE IF NOT EXISTS market_data_aggregated (
    id INT PRIMARY KEY AUTO_INCREMENT,
    data_type ENUM('pricing', 'demand', 'competition', 'trends') NOT NULL,
    region VARCHAR(100) NOT NULL,
    time_period ENUM('daily', 'weekly', 'monthly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    metrics JSON, -- Aggregated metrics data
    raw_data_count INT DEFAULT 0,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_aggregation (data_type, region, time_period, period_start)
);

-- Insert data packages
INSERT INTO data_packages (package_name, description, monthly_price, annual_price, api_calls_per_month, data_types, access_levels, features) VALUES
('Market Insights Basic', 'Essential market data for small cannabis businesses', 99.00, 999.00, 5000, 
 '["market_intelligence", "pricing_data"]', 
 '["basic"]',
 '{"real_time_data": false, "historical_data": "30_days", "export_capability": false, "update_frequency": "daily"}'),

('Market Insights Pro', 'Advanced analytics for growing cannabis businesses', 299.00, 2999.00, 15000,
 '["market_intelligence", "pricing_data", "demand_analytics", "competition_analysis"]',
 '["basic", "advanced"]',
 '{"real_time_data": true, "historical_data": "1_year", "export_capability": true, "update_frequency": "hourly", "custom_reports": true}'),

('Enterprise Intelligence', 'Comprehensive data suite for large cannabis operations', 799.00, 7999.00, 50000,
 '["market_intelligence", "pricing_data", "demand_analytics", "competition_analysis", "regulatory_updates", "geographic_analysis"]',
 '["basic", "advanced", "premium"]',
 '{"real_time_data": true, "historical_data": "unlimited", "export_capability": true, "update_frequency": "real_time", "custom_reports": true, "api_webhooks": true, "dedicated_support": true}'),

('Regulatory Compliance', 'Specialized regulatory and compliance data', 199.00, 1999.00, 8000,
 '["regulatory_updates", "compliance_tracking", "risk_assessments"]',
 '["basic", "advanced"]',
 '{"real_time_data": true, "historical_data": "2_years", "export_capability": true, "update_frequency": "real_time", "alert_system": true}'),

('White Label Data', 'Data service for white label licensees', 499.00, 4999.00, 25000,
 '["market_intelligence", "pricing_data", "demand_analytics", "user_analytics", "revenue_analytics"]',
 '["basic", "advanced", "premium", "enterprise"]',
 '{"real_time_data": true, "historical_data": "unlimited", "export_capability": true, "update_frequency": "real_time", "white_label_branding": true, "custom_apis": true}');

-- Create indexes for performance
CREATE INDEX idx_data_subscriptions_api_key ON data_subscriptions(api_key);
CREATE INDEX idx_data_subscriptions_expires ON data_subscriptions(expires_at);
CREATE INDEX idx_data_usage_logs_api_key ON data_usage_logs(api_key);
CREATE INDEX idx_data_exports_status ON data_exports(status);
CREATE INDEX idx_market_data_region_period ON market_data_aggregated(region, time_period, period_start);

-- Create stored procedure for API usage tracking
DELIMITER //
CREATE PROCEDURE TrackApiUsage(
    IN p_api_key VARCHAR(100),
    IN p_endpoint VARCHAR(100),
    IN p_data_type VARCHAR(50),
    IN p_request_size_kb INT,
    IN p_response_size_kb INT,
    IN p_processing_time_ms INT
)
BEGIN
    DECLARE v_subscription_id INT;
    DECLARE v_calls_limit INT;
    DECLARE v_calls_used INT;
    
    -- Get subscription details
    SELECT id, api_calls_limit, api_calls_used 
    INTO v_subscription_id, v_calls_limit, v_calls_used
    FROM data_subscriptions 
    WHERE api_key = p_api_key AND status = 'active' AND expires_at > NOW();
    
    -- Check if subscription exists and has available calls
    IF v_subscription_id IS NOT NULL AND v_calls_used < v_calls_limit THEN
        -- Update usage count
        UPDATE data_subscriptions 
        SET api_calls_used = api_calls_used + 1,
            last_used = NOW()
        WHERE id = v_subscription_id;
        
        -- Log detailed usage
        INSERT INTO data_usage_logs 
        (api_key, endpoint, data_type, request_size_kb, response_size_kb, processing_time_ms)
        VALUES (p_api_key, p_endpoint, p_data_type, p_request_size_kb, p_response_size_kb, p_processing_time_ms);
        
        SELECT 'success' as status, (v_calls_limit - v_calls_used - 1) as remaining_calls;
    ELSE
        SELECT 'limit_exceeded' as status, 0 as remaining_calls;
    END IF;
END //
DELIMITER ;

-- Create stored procedure for market data aggregation
DELIMITER //
CREATE PROCEDURE AggregateMarketData(IN p_data_type VARCHAR(50), IN p_region VARCHAR(100), IN p_period ENUM('daily', 'weekly', 'monthly'))
BEGIN
    DECLARE v_start_date DATE;
    DECLARE v_end_date DATE;
    DECLARE v_metrics JSON;
    DECLARE v_count INT;
    
    -- Calculate date range based on period
    CASE p_period
        WHEN 'daily' THEN 
            SET v_start_date = CURDATE();
            SET v_end_date = CURDATE();
        WHEN 'weekly' THEN
            SET v_start_date = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
            SET v_end_date = DATE_ADD(v_start_date, INTERVAL 6 DAY);
        WHEN 'monthly' THEN
            SET v_start_date = DATE_FORMAT(CURDATE(), '%Y-%m-01');
            SET v_end_date = LAST_DAY(CURDATE());
    END CASE;
    
    -- Aggregate data based on type
    CASE p_data_type
        WHEN 'pricing' THEN
            SELECT JSON_OBJECT(
                'avg_price', AVG(gs.sale_price),
                'min_price', MIN(gs.sale_price),
                'max_price', MAX(gs.sale_price),
                'transaction_count', COUNT(*),
                'total_volume', SUM(gs.quantity)
            ), COUNT(*)
            INTO v_metrics, v_count
            FROM game_sales gs
            JOIN locations l ON gs.location_id = l.id
            WHERE DATE(gs.sale_date) BETWEEN v_start_date AND v_end_date
            AND (p_region = 'all' OR l.city = p_region);
            
        WHEN 'demand' THEN
            SELECT JSON_OBJECT(
                'daily_avg_transactions', AVG(daily_count),
                'daily_avg_volume', AVG(daily_volume),
                'peak_demand_day', MAX(daily_count),
                'trend_direction', CASE 
                    WHEN AVG(daily_count) > LAG(AVG(daily_count)) OVER (ORDER BY DATE(gs.sale_date)) THEN 'up'
                    ELSE 'down'
                END
            ), COUNT(*)
            INTO v_metrics, v_count
            FROM (
                SELECT DATE(gs.sale_date) as sale_date, COUNT(*) as daily_count, SUM(gs.quantity) as daily_volume
                FROM game_sales gs
                JOIN locations l ON gs.location_id = l.id
                WHERE DATE(gs.sale_date) BETWEEN v_start_date AND v_end_date
                AND (p_region = 'all' OR l.city = p_region)
                GROUP BY DATE(gs.sale_date)
            ) daily_stats;
            
        ELSE
            SET v_metrics = JSON_OBJECT('error', 'Unknown data type');
            SET v_count = 0;
    END CASE;
    
    -- Insert or update aggregated data
    INSERT INTO market_data_aggregated 
    (data_type, region, time_period, period_start, period_end, metrics, raw_data_count)
    VALUES (p_data_type, p_region, p_period, v_start_date, v_end_date, v_metrics, v_count)
    ON DUPLICATE KEY UPDATE
        metrics = v_metrics,
        raw_data_count = v_count,
        calculated_at = NOW();
        
    SELECT 'success' as status, v_metrics as aggregated_data;
END //
DELIMITER ;

-- Create stored procedure for subscription management
DELIMITER //
CREATE PROCEDURE ManageDataSubscription(
    IN p_action ENUM('create', 'renew', 'suspend', 'cancel'),
    IN p_subscription_id INT,
    IN p_user_id INT DEFAULT NULL,
    IN p_package_id INT DEFAULT NULL
)
BEGIN
    DECLARE v_api_key VARCHAR(100);
    DECLARE v_expires_at DATETIME;
    DECLARE v_api_calls_limit INT;
    
    CASE p_action
        WHEN 'create' THEN
            -- Generate new API key
            SET v_api_key = CONCAT('ds_', UPPER(HEX(RANDOM_BYTES(16))));
            
            -- Get package details
            SELECT api_calls_per_month INTO v_api_calls_limit
            FROM data_packages WHERE id = p_package_id;
            
            SET v_expires_at = DATE_ADD(NOW(), INTERVAL 1 MONTH);
            
            INSERT INTO data_subscriptions 
            (subscriber_user_id, package_id, api_key, expires_at, api_calls_limit, status)
            VALUES (p_user_id, p_package_id, v_api_key, v_expires_at, v_api_calls_limit, 'active');
            
            SELECT 'created' as status, v_api_key as api_key, v_expires_at as expires_at;
            
        WHEN 'renew' THEN
            UPDATE data_subscriptions 
            SET expires_at = DATE_ADD(expires_at, INTERVAL 1 MONTH),
                api_calls_used = 0,
                status = 'active'
            WHERE id = p_subscription_id;
            
            SELECT 'renewed' as status;
            
        WHEN 'suspend' THEN
            UPDATE data_subscriptions 
            SET status = 'suspended'
            WHERE id = p_subscription_id;
            
            SELECT 'suspended' as status;
            
        WHEN 'cancel' THEN
            UPDATE data_subscriptions 
            SET status = 'cancelled'
            WHERE id = p_subscription_id;
            
            SELECT 'cancelled' as status;
    END CASE;
END //
DELIMITER ;

-- Create view for subscription analytics
CREATE VIEW data_subscription_analytics AS
SELECT 
    dp.package_name,
    COUNT(ds.id) as active_subscriptions,
    SUM(dp.monthly_price) as monthly_revenue,
    AVG(ds.api_calls_used) as avg_api_usage,
    AVG(ds.api_calls_used / ds.api_calls_limit * 100) as avg_usage_percentage,
    COUNT(CASE WHEN ds.api_calls_used >= ds.api_calls_limit * 0.8 THEN 1 END) as high_usage_subscriptions
FROM data_packages dp
LEFT JOIN data_subscriptions ds ON dp.id = ds.package_id AND ds.status = 'active'
WHERE dp.is_active = TRUE
GROUP BY dp.id, dp.package_name;

-- Create view for API usage analytics
CREATE VIEW api_usage_analytics AS
SELECT 
    DATE(dul.used_at) as usage_date,
    dul.endpoint,
    dul.data_type,
    COUNT(*) as request_count,
    AVG(dul.processing_time_ms) as avg_processing_time,
    SUM(dul.request_size_kb) as total_request_size_kb,
    SUM(dul.response_size_kb) as total_response_size_kb,
    COUNT(DISTINCT dul.api_key) as unique_api_keys
FROM data_usage_logs dul
WHERE dul.used_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(dul.used_at), dul.endpoint, dul.data_type
ORDER BY usage_date DESC, request_count DESC;
