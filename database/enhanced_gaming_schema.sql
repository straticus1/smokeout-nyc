-- Enhanced Gaming System Database Schema
-- Complete multiplayer, genetics, weather, and advanced gaming features

-- Enhanced gaming sessions with real-time support
CREATE TABLE IF NOT EXISTS gaming_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_type ENUM('single_player', 'multiplayer', 'cooperative', 'competitive') DEFAULT 'single_player',
    multiplayer_room_id VARCHAR(128) NULL,
    session_data JSON NULL,
    genetics_data JSON NULL,
    weather_effects JSON NULL,
    market_conditions JSON NULL,
    social_interactions JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_room_active (multiplayer_room_id, is_active),
    INDEX idx_session_type (session_type),
    INDEX idx_last_activity (last_activity)
);

-- Advanced strain genetics with inheritance system
CREATE TABLE IF NOT EXISTS strain_genetics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    strain_id INT NOT NULL,
    parent1_strain_id INT NULL,
    parent2_strain_id INT NULL,
    generation INT DEFAULT 1,
    genetic_profile JSON NOT NULL,
    phenotype_expressions JSON NULL,
    breeding_history JSON NULL,
    stability_rating DECIMAL(3,2) DEFAULT 0.50,
    vigor_rating DECIMAL(3,2) DEFAULT 0.50,
    rarity_score INT DEFAULT 1,
    created_by_user_id INT NOT NULL,
    is_stable BOOLEAN DEFAULT FALSE,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_strain_genetics (strain_id),
    INDEX idx_parents (parent1_strain_id, parent2_strain_id),
    INDEX idx_creator (created_by_user_id),
    INDEX idx_rarity_public (rarity_score, is_public),
    FOREIGN KEY (strain_id) REFERENCES strains(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Weather effects system with real-time data
CREATE TABLE IF NOT EXISTS weather_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    weather_type ENUM('temperature', 'humidity', 'barometric_pressure', 'wind', 'precipitation', 'uv_index') NOT NULL,
    current_value DECIMAL(8,3) NOT NULL,
    optimal_min DECIMAL(8,3) NOT NULL,
    optimal_max DECIMAL(8,3) NOT NULL,
    stress_factor DECIMAL(4,3) DEFAULT 0.000,
    affects_growth_rate BOOLEAN DEFAULT TRUE,
    affects_yield BOOLEAN DEFAULT TRUE,
    affects_potency BOOLEAN DEFAULT FALSE,
    forecast_data JSON NULL,
    event_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    event_end TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location_active (location_id, event_end),
    INDEX idx_weather_type (weather_type),
    INDEX idx_event_timerange (event_start, event_end),
    FOREIGN KEY (location_id) REFERENCES growing_locations(id) ON DELETE CASCADE
);

-- Advanced plant genetics with individual variations
CREATE TABLE IF NOT EXISTS plant_genetics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plant_id INT NOT NULL,
    strain_genetics_id INT NOT NULL,
    phenotype_expression JSON NOT NULL,
    genetic_variation DECIMAL(4,3) DEFAULT 0.100,
    mutation_factors JSON NULL,
    environmental_adaptations JSON NULL,
    growth_modifiers JSON NOT NULL,
    potency_genetics JSON NOT NULL,
    disease_resistances JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_plant_genetics (plant_id),
    INDEX idx_strain_genetics (strain_genetics_id),
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE,
    FOREIGN KEY (strain_genetics_id) REFERENCES strain_genetics(id) ON DELETE CASCADE
);

-- Real-time plant monitoring and alerts
CREATE TABLE IF NOT EXISTS plant_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plant_id INT NOT NULL,
    monitoring_type ENUM('growth_rate', 'health_status', 'nutrient_levels', 'pest_detection', 'disease_check', 'harvest_readiness') NOT NULL,
    current_status VARCHAR(50) NOT NULL,
    status_value DECIMAL(8,3) NULL,
    alert_threshold_min DECIMAL(8,3) NULL,
    alert_threshold_max DECIMAL(8,3) NULL,
    requires_attention BOOLEAN DEFAULT FALSE,
    automated_action_taken BOOLEAN DEFAULT FALSE,
    action_details JSON NULL,
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_check_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_plant_monitoring (plant_id, monitoring_type),
    INDEX idx_attention_required (requires_attention, next_check_at),
    INDEX idx_next_check (next_check_at),
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
);

-- Advanced market dynamics with micro-economics
CREATE TABLE IF NOT EXISTS market_microeconomics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    strain_id INT NOT NULL,
    current_demand DECIMAL(8,3) NOT NULL,
    current_supply DECIMAL(8,3) NOT NULL,
    price_per_gram DECIMAL(8,2) NOT NULL,
    price_volatility DECIMAL(4,3) DEFAULT 0.100,
    trend_direction ENUM('bullish', 'bearish', 'sideways') DEFAULT 'sideways',
    market_sentiment DECIMAL(4,3) DEFAULT 0.500,
    seasonal_factor DECIMAL(4,3) DEFAULT 1.000,
    competition_factor DECIMAL(4,3) DEFAULT 1.000,
    quality_premium DECIMAL(4,3) DEFAULT 1.000,
    forecast_data JSON NULL,
    last_transaction_price DECIMAL(8,2) NULL,
    last_transaction_time TIMESTAMP NULL,
    data_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location_strain (location_id, strain_id),
    INDEX idx_price_trend (price_per_gram, trend_direction),
    INDEX idx_expires (expires_at),
    INDEX idx_timestamp (data_timestamp),
    FOREIGN KEY (location_id) REFERENCES growing_locations(id) ON DELETE CASCADE,
    FOREIGN KEY (strain_id) REFERENCES strains(id) ON DELETE CASCADE
);

-- Multiplayer room management
CREATE TABLE IF NOT EXISTS multiplayer_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(32) UNIQUE NOT NULL,
    room_name VARCHAR(255) NOT NULL,
    room_type ENUM('cooperative', 'competitive', 'educational', 'social') NOT NULL,
    max_participants INT DEFAULT 8,
    current_participants INT DEFAULT 0,
    room_settings JSON NULL,
    game_rules JSON NULL,
    created_by_user_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_private BOOLEAN DEFAULT FALSE,
    password_hash VARCHAR(255) NULL,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_room_code (room_code),
    INDEX idx_active_public (is_active, is_private),
    INDEX idx_creator (created_by_user_id),
    INDEX idx_room_type (room_type),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Multiplayer room participants with roles
CREATE TABLE IF NOT EXISTS multiplayer_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    participant_role ENUM('host', 'co-host', 'participant', 'observer') DEFAULT 'participant',
    join_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    contribution_score INT DEFAULT 0,
    achievements_earned JSON NULL,
    left_at TIMESTAMP NULL,
    kick_reason VARCHAR(255) NULL,
    INDEX idx_room_active (room_id, is_active),
    INDEX idx_user_rooms (user_id, is_active),
    INDEX idx_role (participant_role),
    UNIQUE KEY unique_room_user (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES multiplayer_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Real-time multiplayer actions and events
CREATE TABLE IF NOT EXISTS multiplayer_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('plant_seed', 'water_plant', 'harvest', 'trade_offer', 'chat_message', 'resource_share', 'challenge_issued') NOT NULL,
    action_data JSON NOT NULL,
    target_user_id INT NULL,
    target_plant_id INT NULL,
    requires_confirmation BOOLEAN DEFAULT FALSE,
    confirmed_by_user_id INT NULL,
    confirmed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_actions (room_id, created_at),
    INDEX idx_user_actions (user_id, created_at),
    INDEX idx_action_type (action_type),
    INDEX idx_pending_confirmation (requires_confirmation, confirmed_at),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (room_id) REFERENCES multiplayer_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (target_plant_id) REFERENCES plants(id) ON DELETE SET NULL
);

-- Advanced trading system with escrow
CREATE TABLE IF NOT EXISTS advanced_trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trade_type ENUM('plant', 'genetics', 'resources', 'tokens', 'services') NOT NULL,
    seller_user_id INT NOT NULL,
    buyer_user_id INT NULL,
    item_type VARCHAR(50) NOT NULL,
    item_id INT NULL,
    item_data JSON NOT NULL,
    asking_price DECIMAL(10,2) NOT NULL,
    escrow_amount DECIMAL(10,2) DEFAULT 0.00,
    trade_status ENUM('listed', 'negotiating', 'escrowed', 'completed', 'cancelled', 'disputed') DEFAULT 'listed',
    negotiation_data JSON NULL,
    escrow_released_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_seller (seller_user_id, trade_status),
    INDEX idx_buyer (buyer_user_id, trade_status),
    INDEX idx_trade_type_status (trade_type, trade_status),
    INDEX idx_expires (expires_at),
    INDEX idx_item (item_type, item_id),
    FOREIGN KEY (seller_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Advanced achievement system with prerequisites
CREATE TABLE IF NOT EXISTS advanced_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('growing', 'genetics', 'trading', 'social', 'competitive', 'exploration', 'mastery') NOT NULL,
    achievement_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    difficulty ENUM('bronze', 'silver', 'gold', 'platinum', 'diamond') DEFAULT 'bronze',
    points_reward INT DEFAULT 100,
    token_reward INT DEFAULT 0,
    unlock_reward JSON NULL,
    prerequisites JSON NULL,
    completion_criteria JSON NOT NULL,
    is_hidden BOOLEAN DEFAULT FALSE,
    is_repeatable BOOLEAN DEFAULT FALSE,
    repeat_cooldown_hours INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_difficulty (category, difficulty),
    INDEX idx_hidden_repeatable (is_hidden, is_repeatable)
);

-- Player achievement progress tracking
CREATE TABLE IF NOT EXISTS player_achievement_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    current_progress JSON NOT NULL,
    progress_percentage DECIMAL(5,2) DEFAULT 0.00,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    last_progress_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user_progress (user_id, is_completed),
    INDEX idx_achievement_completion (achievement_id, is_completed),
    INDEX idx_progress_update (last_progress_update),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES advanced_achievements(id) ON DELETE CASCADE
);

-- Real-time notifications and alerts
CREATE TABLE IF NOT EXISTS game_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type ENUM('harvest_ready', 'plant_sick', 'trade_offer', 'achievement_unlocked', 'weather_alert', 'multiplayer_invite', 'market_opportunity') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    action_data JSON NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_dismissed BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    dismissed_at TIMESTAMP NULL,
    INDEX idx_user_unread (user_id, is_read, is_dismissed),
    INDEX idx_priority_created (priority, created_at),
    INDEX idx_type_expires (notification_type, expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- WebSocket connections for real-time features
CREATE TABLE IF NOT EXISTS websocket_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    connection_id VARCHAR(255) UNIQUE NOT NULL,
    room_id INT NULL,
    connection_type ENUM('gaming', 'chat', 'notifications', 'trading') DEFAULT 'gaming',
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    disconnected_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_room_connections (room_id, is_active),
    INDEX idx_connection_type (connection_type),
    INDEX idx_last_ping (last_ping),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES multiplayer_rooms(id) ON DELETE SET NULL
);

-- Game analytics and performance metrics
CREATE TABLE IF NOT EXISTS game_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    metric_type ENUM('session_duration', 'plants_grown', 'trades_completed', 'achievements_earned', 'social_interactions', 'revenue_generated') NOT NULL,
    metric_value DECIMAL(15,4) NOT NULL,
    additional_data JSON NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_metrics (user_id, metric_type),
    INDEX idx_session (session_id),
    INDEX idx_recorded (recorded_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default advanced achievements
INSERT IGNORE INTO advanced_achievements 
(category, achievement_name, description, difficulty, points_reward, token_reward, completion_criteria) VALUES

-- Growing Achievements
('growing', 'Master Cultivator', 'Successfully grow 100 plants to harvest', 'gold', 500, 100, '{"plants_harvested": 100}'),
('growing', 'Green Thumb', 'Achieve 95%+ survival rate on 50+ plants', 'silver', 300, 50, '{"survival_rate": 0.95, "min_plants": 50}'),
('growing', 'Organic Expert', 'Grow 25 plants without using any synthetic nutrients', 'gold', 400, 75, '{"organic_plants": 25}'),

-- Genetics Achievements  
('genetics', 'Genetics Pioneer', 'Create your first stable hybrid strain', 'silver', 250, 50, '{"stable_hybrids_created": 1}'),
('genetics', 'Master Breeder', 'Create 10 stable hybrid strains', 'platinum', 1000, 200, '{"stable_hybrids_created": 10}'),
('genetics', 'Genetic Diversity', 'Work with genetics from 25 different base strains', 'gold', 600, 100, '{"unique_genetics_used": 25}'),

-- Trading Achievements
('trading', 'Market Maker', 'Complete 100 successful trades', 'gold', 500, 100, '{"successful_trades": 100}'),
('trading', 'High Roller', 'Complete a single trade worth over 10,000 tokens', 'platinum', 750, 150, '{"max_trade_value": 10000}'),
('trading', 'Negotiator', 'Successfully negotiate prices on 50 trades', 'silver', 300, 60, '{"negotiated_trades": 50}'),

-- Social Achievements
('social', 'Community Leader', 'Help 100 new players get started', 'platinum', 800, 150, '{"players_helped": 100}'),
('social', 'Mentor', 'Provide guidance that leads to 25 player achievements', 'gold', 600, 100, '{"mentorship_achievements": 25}'),
('social', 'Social Butterfly', 'Interact with 500 different players', 'silver', 400, 75, '{"unique_interactions": 500}'),

-- Competitive Achievements
('competitive', 'Tournament Champion', 'Win first place in a multiplayer tournament', 'diamond', 1500, 300, '{"tournament_wins": 1}'),
('competitive', 'Consistent Competitor', 'Participate in 50 competitive events', 'gold', 500, 100, '{"competitive_events": 50}'),
('competitive', 'Rivalry Master', 'Win 10 head-to-head challenges', 'silver', 350, 70, '{"head_to_head_wins": 10}');

-- Insert default weather patterns for major growing locations
INSERT IGNORE INTO weather_events (location_id, weather_type, current_value, optimal_min, optimal_max, stress_factor, event_end) VALUES
(1, 'temperature', 72.0, 68.0, 78.0, 0.000, DATE_ADD(NOW(), INTERVAL 24 HOUR)),
(1, 'humidity', 55.0, 40.0, 65.0, 0.000, DATE_ADD(NOW(), INTERVAL 24 HOUR)),
(1, 'barometric_pressure', 30.15, 29.80, 30.50, 0.000, DATE_ADD(NOW(), INTERVAL 24 HOUR)),
(2, 'temperature', 74.0, 68.0, 78.0, 0.000, DATE_ADD(NOW(), INTERVAL 24 HOUR)),
(2, 'humidity', 60.0, 40.0, 65.0, 0.000, DATE_ADD(NOW(), INTERVAL 24 HOUR)),
(3, 'temperature', 70.0, 68.0, 78.0, 0.050, DATE_ADD(NOW(), INTERVAL 24 HOUR)),
(3, 'humidity', 70.0, 40.0, 65.0, 0.100, DATE_ADD(NOW(), INTERVAL 24 HOUR));