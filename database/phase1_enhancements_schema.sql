-- Phase 1 Enhancement Database Schema
-- AI Risk Assistant, Enhanced Multiplayer Gaming, Advanced Notifications

-- ====================================
-- AI Risk Assistant Tables
-- ====================================

-- AI Conversations for chat-based risk consultation
CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'Risk Consultation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- AI Messages for storing conversation history
CREATE TABLE IF NOT EXISTS ai_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    role ENUM('user', 'assistant') NOT NULL,
    content TEXT NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_role (role),
    INDEX idx_created_at (created_at)
);

-- Risk Assessment Cache for improved performance
CREATE TABLE IF NOT EXISTS risk_assessment_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_hash VARCHAR(64) NOT NULL UNIQUE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    city VARCHAR(100),
    state VARCHAR(50),
    risk_assessment JSON NOT NULL,
    natural_language_explanation JSON,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location_hash (location_hash),
    INDEX idx_coordinates (latitude, longitude),
    INDEX idx_expires_at (expires_at),
    SPATIAL INDEX idx_spatial (POINT(longitude, latitude))
);

-- ====================================
-- Enhanced Multiplayer Gaming Tables
-- ====================================

-- Gaming Guilds/Teams
CREATE TABLE IF NOT EXISTS game_guilds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    guild_type ENUM('casual', 'competitive', 'professional') DEFAULT 'casual',
    max_members INT DEFAULT 50,
    current_members INT DEFAULT 0,
    is_public BOOLEAN DEFAULT true,
    join_requirements JSON, -- level, reputation, etc.
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_name (name),
    INDEX idx_guild_type (guild_type),
    INDEX idx_is_public (is_public),
    INDEX idx_created_by (created_by)
);

-- Guild Memberships
CREATE TABLE IF NOT EXISTS game_guild_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guild_id INT NOT NULL,
    player_id INT NOT NULL,
    role ENUM('member', 'officer', 'leader') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    contribution_points INT DEFAULT 0,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guild_id) REFERENCES game_guilds(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_guild_player (guild_id, player_id),
    INDEX idx_guild_id (guild_id),
    INDEX idx_player_id (player_id),
    INDEX idx_role (role)
);

-- Cooperative Growing Operations
CREATE TABLE IF NOT EXISTS game_coop_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    guild_id INT,
    operation_type ENUM('small_grow', 'medium_grow', 'large_grow', 'processing_facility') DEFAULT 'small_grow',
    location_id INT NOT NULL,
    status ENUM('planning', 'active', 'harvesting', 'completed', 'abandoned') DEFAULT 'planning',
    total_investment DECIMAL(10, 2) DEFAULT 0.00,
    expected_yield DECIMAL(8, 2) DEFAULT 0.00,
    actual_yield DECIMAL(8, 2) DEFAULT 0.00,
    profit_sharing JSON, -- How profits are distributed among participants
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guild_id) REFERENCES game_guilds(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES growing_locations(id) ON DELETE RESTRICT,
    INDEX idx_guild_id (guild_id),
    INDEX idx_status (status),
    INDEX idx_operation_type (operation_type),
    INDEX idx_location_id (location_id)
);

-- Participants in cooperative operations
CREATE TABLE IF NOT EXISTS game_coop_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation_id INT NOT NULL,
    player_id INT NOT NULL,
    role ENUM('investor', 'grower', 'manager', 'specialist') DEFAULT 'investor',
    investment_amount DECIMAL(10, 2) DEFAULT 0.00,
    time_investment_hours DECIMAL(6, 2) DEFAULT 0.00,
    profit_share_percentage DECIMAL(5, 2) DEFAULT 0.00,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operation_id) REFERENCES game_coop_operations(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_operation_player (operation_id, player_id),
    INDEX idx_operation_id (operation_id),
    INDEX idx_player_id (player_id),
    INDEX idx_role (role)
);

-- Player-to-Player Trading System
CREATE TABLE IF NOT EXISTS game_player_trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    buyer_id INT,
    trade_type ENUM('direct', 'marketplace', 'auction') DEFAULT 'marketplace',
    item_type ENUM('plant', 'product', 'strain_seeds', 'equipment', 'tokens') NOT NULL,
    item_id INT, -- References plants, products, etc.
    quantity DECIMAL(8, 2) DEFAULT 1.00,
    asking_price DECIMAL(10, 2) NOT NULL,
    final_price DECIMAL(10, 2),
    status ENUM('listed', 'pending', 'completed', 'cancelled', 'expired') DEFAULT 'listed',
    expires_at TIMESTAMP NOT NULL,
    trade_notes TEXT,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES game_players(id) ON DELETE SET NULL,
    INDEX idx_seller_id (seller_id),
    INDEX idx_buyer_id (buyer_id),
    INDEX idx_trade_type (trade_type),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- Guild Competitions and Tournaments
CREATE TABLE IF NOT EXISTS game_competitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    competition_type ENUM('growing_contest', 'sales_competition', 'knowledge_quiz', 'guild_battle') NOT NULL,
    status ENUM('upcoming', 'registration_open', 'active', 'completed', 'cancelled') DEFAULT 'upcoming',
    registration_fee DECIMAL(8, 2) DEFAULT 0.00,
    prize_pool DECIMAL(10, 2) DEFAULT 0.00,
    max_participants INT DEFAULT 100,
    current_participants INT DEFAULT 0,
    rules JSON,
    registration_opens TIMESTAMP NULL,
    registration_closes TIMESTAMP NULL,
    starts_at TIMESTAMP NOT NULL,
    ends_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_competition_type (competition_type),
    INDEX idx_status (status),
    INDEX idx_starts_at (starts_at),
    INDEX idx_registration_period (registration_opens, registration_closes)
);

-- Competition Participants
CREATE TABLE IF NOT EXISTS game_competition_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    participant_id INT NOT NULL, -- Can be player_id or guild_id
    participant_type ENUM('player', 'guild') NOT NULL,
    registration_fee_paid DECIMAL(8, 2) DEFAULT 0.00,
    final_score DECIMAL(10, 2) DEFAULT 0.00,
    final_rank INT,
    prize_awarded DECIMAL(8, 2) DEFAULT 0.00,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES game_competitions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_competition_participant (competition_id, participant_id, participant_type),
    INDEX idx_competition_id (competition_id),
    INDEX idx_participant (participant_id, participant_type),
    INDEX idx_final_rank (final_rank)
);

-- Real-time Player Status for multiplayer features
CREATE TABLE IF NOT EXISTS game_player_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL UNIQUE,
    is_online BOOLEAN DEFAULT false,
    current_activity ENUM('idle', 'growing', 'trading', 'guild_activity', 'competition') DEFAULT 'idle',
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    current_location_id INT,
    status_message VARCHAR(200),
    privacy_level ENUM('public', 'friends', 'guild', 'private') DEFAULT 'public',
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (current_location_id) REFERENCES growing_locations(id) ON DELETE SET NULL,
    INDEX idx_player_id (player_id),
    INDEX idx_is_online (is_online),
    INDEX idx_last_seen (last_seen),
    INDEX idx_activity (current_activity)
);

-- ====================================
-- Advanced Notification System Tables
-- ====================================

-- Notification Templates for different types
CREATE TABLE IF NOT EXISTS notification_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    template_type ENUM('system', 'game', 'risk_alert', 'social', 'transaction', 'emergency') NOT NULL,
    title_template VARCHAR(255) NOT NULL,
    content_template TEXT NOT NULL,
    priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
    channels JSON NOT NULL, -- ['in_app', 'email', 'sms', 'push', 'discord', 'slack']
    personalization_fields JSON,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_name (template_name),
    INDEX idx_template_type (template_type),
    INDEX idx_priority (priority),
    INDEX idx_is_active (is_active)
);

-- User Notification Preferences
CREATE TABLE IF NOT EXISTS user_notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    email_notifications BOOLEAN DEFAULT true,
    sms_notifications BOOLEAN DEFAULT false,
    push_notifications BOOLEAN DEFAULT true,
    in_app_notifications BOOLEAN DEFAULT true,
    discord_notifications BOOLEAN DEFAULT false,
    slack_notifications BOOLEAN DEFAULT false,
    
    -- Granular preferences by category
    system_alerts BOOLEAN DEFAULT true,
    game_notifications BOOLEAN DEFAULT true,
    risk_alerts BOOLEAN DEFAULT true,
    social_notifications BOOLEAN DEFAULT true,
    transaction_alerts BOOLEAN DEFAULT true,
    emergency_alerts BOOLEAN DEFAULT true,
    
    -- Quiet hours
    quiet_hours_enabled BOOLEAN DEFAULT false,
    quiet_hours_start TIME DEFAULT '22:00:00',
    quiet_hours_end TIME DEFAULT '08:00:00',
    quiet_hours_timezone VARCHAR(50) DEFAULT 'America/New_York',
    
    -- Frequency limits
    max_notifications_per_hour INT DEFAULT 10,
    max_notifications_per_day INT DEFAULT 50,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Smart Notification Queue
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    template_id INT NOT NULL,
    priority ENUM('low', 'normal', 'high', 'critical') NOT NULL,
    channels JSON NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    personalization_data JSON,
    
    -- Smart delivery options
    delivery_method ENUM('immediate', 'batched', 'scheduled', 'smart_timing') DEFAULT 'immediate',
    scheduled_for TIMESTAMP NULL,
    optimal_delivery_time TIMESTAMP NULL,
    
    -- Processing status
    status ENUM('queued', 'processing', 'sent', 'failed', 'cancelled') DEFAULT 'queued',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_attempt TIMESTAMP NULL,
    error_message TEXT,
    
    -- Engagement tracking
    delivered_at TIMESTAMP NULL,
    opened_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    dismissed_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_priority (priority),
    INDEX idx_status (status),
    INDEX idx_delivery_method (delivery_method),
    INDEX idx_scheduled_for (scheduled_for),
    INDEX idx_created_at (created_at)
);

-- Notification Analytics and AI Learning
CREATE TABLE IF NOT EXISTS notification_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_id INT NOT NULL,
    template_id INT NOT NULL,
    
    -- Engagement metrics
    was_delivered BOOLEAN DEFAULT false,
    was_opened BOOLEAN DEFAULT false,
    was_clicked BOOLEAN DEFAULT false,
    was_dismissed BOOLEAN DEFAULT false,
    engagement_score DECIMAL(3, 2) DEFAULT 0.00,
    
    -- Context when sent
    sent_at TIMESTAMP NOT NULL,
    user_timezone VARCHAR(50),
    user_activity_level ENUM('inactive', 'low', 'moderate', 'high') DEFAULT 'moderate',
    device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',
    
    -- AI learning features
    predicted_engagement DECIMAL(3, 2),
    actual_engagement DECIMAL(3, 2),
    prediction_accuracy DECIMAL(3, 2),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (notification_id) REFERENCES notification_queue(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_notification_id (notification_id),
    INDEX idx_template_id (template_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_engagement_score (engagement_score)
);

-- Real-time Risk Monitoring for Smart Alerts
CREATE TABLE IF NOT EXISTS risk_monitoring_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_type ENUM('location_specific', 'area_wide', 'industry_wide') NOT NULL,
    
    -- Location parameters
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    radius_miles DECIMAL(6, 2) DEFAULT 5.00,
    city VARCHAR(100),
    state VARCHAR(50),
    
    -- Risk thresholds
    enforcement_risk_threshold DECIMAL(3, 2) DEFAULT 0.50,
    regulatory_risk_threshold DECIMAL(3, 2) DEFAULT 0.50,
    market_risk_threshold DECIMAL(3, 2) DEFAULT 0.60,
    overall_risk_threshold DECIMAL(3, 2) DEFAULT 0.50,
    
    -- Alert frequency
    alert_frequency ENUM('immediate', 'hourly', 'daily', 'weekly') DEFAULT 'immediate',
    last_alert_sent TIMESTAMP NULL,
    
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_subscription_type (subscription_type),
    INDEX idx_coordinates (latitude, longitude),
    INDEX idx_is_active (is_active)
);

-- AI-Powered User Behavior Analysis for Smart Notifications
CREATE TABLE IF NOT EXISTS user_behavior_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    
    -- Activity patterns
    most_active_hours JSON, -- Array of hour preferences
    most_active_days JSON,  -- Array of day preferences
    avg_session_duration INT DEFAULT 0, -- minutes
    notification_engagement_rate DECIMAL(3, 2) DEFAULT 0.00,
    
    -- Preferences learned from behavior
    preferred_notification_channels JSON,
    optimal_notification_timing JSON,
    risk_tolerance_level ENUM('low', 'moderate', 'high') DEFAULT 'moderate',
    
    -- AI model predictions
    churn_risk_score DECIMAL(3, 2) DEFAULT 0.00,
    engagement_prediction DECIMAL(3, 2) DEFAULT 0.50,
    value_score DECIMAL(5, 2) DEFAULT 0.00,
    
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_calculated (last_calculated)
);

-- ====================================
-- Insert Default Data
-- ====================================

-- Insert default notification templates
INSERT IGNORE INTO notification_templates (template_name, template_type, title_template, content_template, priority, channels) VALUES
('risk_alert_high', 'risk_alert', 'High Risk Alert: {{location}}', 'Risk levels have increased to {{risk_percentage}}% in {{location}}. {{explanation}}', 'high', '["in_app", "email", "push"]'),
('game_level_up', 'game', 'Congratulations! Level {{new_level}} Achieved!', 'You\'ve reached level {{new_level}} and earned {{token_reward}} tokens!', 'normal', '["in_app", "push"]'),
('guild_invitation', 'social', 'Guild Invitation: {{guild_name}}', '{{inviter_name}} has invited you to join {{guild_name}}. {{guild_description}}', 'normal', '["in_app", "email"]'),
('coop_harvest_ready', 'game', 'Cooperative Harvest Ready!', 'Your cooperative operation "{{operation_name}}" is ready for harvest. Expected yield: {{expected_yield}} units.', 'high', '["in_app", "email", "push"]'),
('trade_completed', 'transaction', 'Trade Completed', 'Your {{item_type}} trade has been completed for {{final_price}} tokens.', 'normal', '["in_app", "push"]'),
('enforcement_activity', 'risk_alert', 'Enforcement Activity Detected', 'New enforcement activity detected within {{radius}} miles of your monitored location.', 'critical', '["in_app", "email", "sms", "push"]');

-- Insert default user behavior patterns for new users
INSERT IGNORE INTO user_behavior_patterns (user_id, most_active_hours, most_active_days, preferred_notification_channels, optimal_notification_timing)
SELECT id, '[9,12,14,18,20]', '[1,2,3,4,5]', '["in_app", "push"]', '{"morning": 9, "afternoon": 14, "evening": 18}'
FROM users 
WHERE id NOT IN (SELECT user_id FROM user_behavior_patterns WHERE user_id IS NOT NULL);

-- Create triggers for maintaining data consistency
DELIMITER //

-- Update guild member count when members join/leave
CREATE TRIGGER IF NOT EXISTS update_guild_member_count_insert
    AFTER INSERT ON game_guild_members
    FOR EACH ROW
BEGIN
    UPDATE game_guilds 
    SET current_members = (
        SELECT COUNT(*) 
        FROM game_guild_members 
        WHERE guild_id = NEW.guild_id
    )
    WHERE id = NEW.guild_id;
END//

CREATE TRIGGER IF NOT EXISTS update_guild_member_count_delete
    AFTER DELETE ON game_guild_members
    FOR EACH ROW
BEGIN
    UPDATE game_guilds 
    SET current_members = (
        SELECT COUNT(*) 
        FROM game_guild_members 
        WHERE guild_id = OLD.guild_id
    )
    WHERE id = OLD.guild_id;
END//

-- Update competition participant count
CREATE TRIGGER IF NOT EXISTS update_competition_participant_count_insert
    AFTER INSERT ON game_competition_participants
    FOR EACH ROW
BEGIN
    UPDATE game_competitions 
    SET current_participants = (
        SELECT COUNT(*) 
        FROM game_competition_participants 
        WHERE competition_id = NEW.competition_id
    )
    WHERE id = NEW.competition_id;
END//

CREATE TRIGGER IF NOT EXISTS update_competition_participant_count_delete
    AFTER DELETE ON game_competition_participants
    FOR EACH ROW
BEGIN
    UPDATE game_competitions 
    SET current_participants = (
        SELECT COUNT(*) 
        FROM game_competition_participants 
        WHERE competition_id = OLD.competition_id
    )
    WHERE id = OLD.competition_id;
END//

DELIMITER ;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_risk_cache_expires ON risk_assessment_cache(expires_at);
CREATE INDEX IF NOT EXISTS idx_notifications_user_priority ON notification_queue(user_id, priority);
CREATE INDEX IF NOT EXISTS idx_guild_members_active ON game_guild_members(guild_id, last_active);
CREATE INDEX IF NOT EXISTS idx_player_trades_marketplace ON game_player_trades(trade_type, status, expires_at);

-- Add some stored procedures for common operations
DELIMITER //

-- Procedure to get personalized risk insights
CREATE PROCEDURE IF NOT EXISTS GetPersonalizedRiskInsights(IN p_user_id INT, IN p_days INT)
BEGIN
    SELECT 
        ra.city,
        ra.state,
        AVG(ra.risk_score) as avg_risk_score,
        COUNT(*) as assessment_count,
        MAX(ra.last_updated) as latest_assessment
    FROM risk_assessments ra 
    WHERE ra.user_id = p_user_id
    AND ra.last_updated >= DATE_SUB(NOW(), INTERVAL p_days DAY)
    GROUP BY ra.city, ra.state
    ORDER BY avg_risk_score DESC;
END//

-- Procedure to find optimal notification timing for user
CREATE PROCEDURE IF NOT EXISTS GetOptimalNotificationTiming(IN p_user_id INT)
BEGIN
    SELECT 
        HOUR(na.sent_at) as send_hour,
        AVG(na.engagement_score) as avg_engagement,
        COUNT(*) as notification_count
    FROM notification_analytics na
    WHERE na.user_id = p_user_id
    AND na.sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(na.sent_at)
    ORDER BY avg_engagement DESC
    LIMIT 5;
END//

DELIMITER ;
