-- Advanced Cannabis Game Features Schema

-- Smoke Shops Table (High-level selling locations)
CREATE TABLE smoke_shops (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    location_id INT NOT NULL,
    owner_type ENUM('npc', 'player') DEFAULT 'npc',
    owner_id INT NULL,
    reputation_required INT DEFAULT 500,
    level_required INT DEFAULT 15,
    bulk_discount DECIMAL(3,2) DEFAULT 0.95, -- 5% discount for bulk
    preferred_strains JSON DEFAULT '[]',
    max_weekly_purchase DECIMAL(10,2) DEFAULT 1000.00,
    current_week_purchased DECIMAL(10,2) DEFAULT 0.00,
    week_reset_date DATE,
    status ENUM('open', 'closed', 'raided') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (owner_id) REFERENCES game_players(id),
    INDEX idx_reputation_level (reputation_required, level_required)
);

-- Dealers Table (NPC and Player dealers)
CREATE TABLE dealers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('street', 'premium', 'bulk', 'specialty') DEFAULT 'street',
    location_id INT NOT NULL,
    reputation_required INT DEFAULT 100,
    level_required INT DEFAULT 8,
    markup_percentage DECIMAL(5,2) DEFAULT 20.00, -- 20% markup
    reliability_rating DECIMAL(3,2) DEFAULT 0.8, -- 80% reliable
    preferred_quantities JSON DEFAULT '[]',
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    bust_probability DECIMAL(4,3) DEFAULT 0.05, -- 5% chance
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    INDEX idx_type_risk (type, risk_level)
);

-- Player Consumption Table
CREATE TABLE player_consumption (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    product_type ENUM('flower', 'edible', 'concentrate') NOT NULL,
    strain_id INT NOT NULL,
    quantity DECIMAL(5,2) NOT NULL,
    potency DECIMAL(5,2) NOT NULL, -- THC percentage
    consumption_method ENUM('smoke', 'vape', 'eat', 'dab') NOT NULL,
    consumed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duration_minutes INT DEFAULT 120, -- How long effects last
    impairment_level DECIMAL(3,2) DEFAULT 0.0, -- 0.0 to 1.0
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (strain_id) REFERENCES strains(id),
    INDEX idx_player_active (player_id, expires_at),
    INDEX idx_expiration (expires_at)
);

-- Player Impairment Effects
CREATE TABLE impairment_effects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    effect_type ENUM('mistake_chance', 'reaction_time', 'decision_quality', 'luck_modifier') NOT NULL,
    severity DECIMAL(3,2) NOT NULL, -- 0.0 to 1.0
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    source_consumption_id INT,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (source_consumption_id) REFERENCES player_consumption(id),
    INDEX idx_player_active (player_id, expires_at)
);

-- Mistakes/Accidents Table
CREATE TABLE game_mistakes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    mistake_type ENUM('plant_death', 'bad_sale', 'police_attention', 'dealer_scam', 'quality_loss', 'timing_error') NOT NULL,
    description TEXT,
    loss_amount DECIMAL(10,2) DEFAULT 0.00,
    loss_type ENUM('tokens', 'plants', 'reputation', 'experience') DEFAULT 'tokens',
    caused_by_impairment BOOLEAN DEFAULT FALSE,
    impairment_level DECIMAL(3,2) DEFAULT 0.0,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    INDEX idx_player_mistakes (player_id, occurred_at),
    INDEX idx_impairment_caused (caused_by_impairment, impairment_level)
);

-- Products Table (Processed goods)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    source_plant_id INT NOT NULL,
    product_type ENUM('flower', 'edible', 'concentrate', 'pre_roll') NOT NULL,
    quantity DECIMAL(5,2) NOT NULL,
    potency DECIMAL(5,2) NOT NULL,
    quality_rating DECIMAL(3,2) NOT NULL,
    production_cost DECIMAL(8,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL, -- Some products expire
    status ENUM('available', 'consumed', 'sold', 'expired') DEFAULT 'available',
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (source_plant_id) REFERENCES plants(id),
    INDEX idx_player_available (player_id, status),
    INDEX idx_expiration (expires_at, status)
);

-- Monetization: Premium Features
CREATE TABLE premium_features (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    feature_type ENUM('growing_slot', 'storage_expansion', 'time_boost', 'quality_boost', 'mistake_insurance', 'vip_access') NOT NULL,
    cost_tokens DECIMAL(8,2) DEFAULT 0.00,
    cost_real_money DECIMAL(6,2) DEFAULT 0.00,
    duration_days INT DEFAULT 30,
    is_permanent BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Player Premium Features
CREATE TABLE player_premium_features (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    feature_id INT NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES premium_features(id),
    INDEX idx_player_active (player_id, is_active, expires_at)
);

-- Reward System: Daily/Weekly Challenges
CREATE TABLE challenges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    challenge_type ENUM('daily', 'weekly', 'monthly', 'special') NOT NULL,
    objective_type ENUM('grow_plants', 'make_sales', 'earn_tokens', 'reach_level', 'avoid_mistakes') NOT NULL,
    target_value INT NOT NULL,
    reward_tokens DECIMAL(8,2) DEFAULT 0.00,
    reward_experience INT DEFAULT 0,
    reward_item_id INT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_dates (is_active, start_date, end_date)
);

-- Player Challenge Progress
CREATE TABLE player_challenges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    challenge_id INT NOT NULL,
    current_progress INT DEFAULT 0,
    completed_at TIMESTAMP NULL,
    reward_claimed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id),
    UNIQUE KEY unique_player_challenge (player_id, challenge_id),
    INDEX idx_player_active (player_id, completed_at)
);

-- Loyalty/Reward Points System
CREATE TABLE loyalty_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    points_earned INT DEFAULT 0,
    points_spent INT DEFAULT 0,
    points_balance INT DEFAULT 0,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tier_level ENUM('bronze', 'silver', 'gold', 'platinum', 'diamond') DEFAULT 'bronze',
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    INDEX idx_tier_points (tier_level, points_balance)
);

-- Loyalty Rewards Catalog
CREATE TABLE loyalty_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    cost_points INT NOT NULL,
    reward_type ENUM('tokens', 'premium_feature', 'exclusive_strain', 'boost_item') NOT NULL,
    reward_value JSON, -- Flexible reward data
    tier_required ENUM('bronze', 'silver', 'gold', 'platinum', 'diamond') DEFAULT 'bronze',
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Update existing tables with new columns
ALTER TABLE game_players ADD COLUMN impairment_level DECIMAL(3,2) DEFAULT 0.0;
ALTER TABLE game_players ADD COLUMN total_consumed DECIMAL(8,2) DEFAULT 0.0;
ALTER TABLE game_players ADD COLUMN mistakes_count INT DEFAULT 0;
ALTER TABLE game_players ADD COLUMN premium_member BOOLEAN DEFAULT FALSE;
ALTER TABLE game_players ADD COLUMN loyalty_points INT DEFAULT 0;

ALTER TABLE strains ADD COLUMN thc_percentage DECIMAL(5,2) DEFAULT 15.0;
ALTER TABLE strains ADD COLUMN cbd_percentage DECIMAL(5,2) DEFAULT 1.0;
ALTER TABLE strains ADD COLUMN can_make_edibles BOOLEAN DEFAULT TRUE;
ALTER TABLE strains ADD COLUMN edible_potency_multiplier DECIMAL(3,2) DEFAULT 1.5;

-- Insert Advanced Game Data

-- Smoke Shops
INSERT INTO smoke_shops (name, description, location_id, reputation_required, level_required, bulk_discount, max_weekly_purchase) VALUES
('Green Dreams Dispensary', 'High-end Manhattan dispensary', 2, 500, 15, 0.90, 2000.00),
('Brooklyn Botanicals', 'Trendy Brooklyn smoke shop', 1, 300, 12, 0.93, 1500.00),
('Queens Cannabis Co.', 'Community-focused Queens shop', 3, 400, 14, 0.92, 1800.00),
('Upstate Premium', 'Exclusive upstate distributor', 6, 800, 18, 0.85, 5000.00);

-- Dealers
INSERT INTO dealers (name, type, location_id, reputation_required, level_required, markup_percentage, reliability_rating, risk_level, bust_probability) VALUES
('Street Mike', 'street', 1, 50, 5, 25.00, 0.7, 'high', 0.15),
('Premium Pete', 'premium', 2, 200, 10, 15.00, 0.9, 'low', 0.02),
('Bulk Bobby', 'bulk', 4, 300, 12, 10.00, 0.85, 'medium', 0.08),
('Specialty Sam', 'specialty', 5, 400, 15, 30.00, 0.95, 'low', 0.01);

-- Premium Features
INSERT INTO premium_features (name, description, feature_type, cost_tokens, cost_real_money, duration_days, is_permanent) VALUES
('Extra Growing Slot', 'Add one more plant growing slot', 'growing_slot', 500.00, 4.99, 30, FALSE),
('Storage Expansion', 'Double your product storage capacity', 'storage_expansion', 300.00, 2.99, 30, FALSE),
('Time Boost', '25% faster growing times', 'time_boost', 200.00, 1.99, 7, FALSE),
('Quality Boost', '+10% quality on all harvests', 'quality_boost', 400.00, 3.99, 14, FALSE),
('Mistake Insurance', 'Protect against impairment mistakes', 'mistake_insurance', 600.00, 5.99, 30, FALSE),
('VIP Access', 'Access to exclusive strains and locations', 'vip_access', 1000.00, 9.99, 30, FALSE),
('Permanent Growing Slot', 'Permanent extra growing slot', 'growing_slot', 2000.00, 19.99, 0, TRUE);

-- Daily Challenges
INSERT INTO challenges (name, description, challenge_type, objective_type, target_value, reward_tokens, reward_experience, start_date, end_date) VALUES
('Daily Grower', 'Plant 3 seeds today', 'daily', 'grow_plants', 3, 50.00, 25, CURDATE(), CURDATE()),
('Sales Target', 'Make 5 sales today', 'daily', 'make_sales', 5, 75.00, 35, CURDATE(), CURDATE()),
('Token Collector', 'Earn 200 tokens today', 'daily', 'earn_tokens', 200, 100.00, 50, CURDATE(), CURDATE()),
('Mistake-Free Day', 'Complete day without mistakes', 'daily', 'avoid_mistakes', 0, 150.00, 75, CURDATE(), CURDATE());

-- Loyalty Rewards
INSERT INTO loyalty_rewards (name, description, cost_points, reward_type, reward_value, tier_required) VALUES
('Token Bonus', '100 free tokens', 500, 'tokens', '{"amount": 100}', 'bronze'),
('Quality Seeds', 'Premium strain seed pack', 1000, 'exclusive_strain', '{"strain_ids": [4,5]}', 'silver'),
('Growth Accelerator', '50% faster growth for 24h', 1500, 'boost_item', '{"duration": 24, "boost": 0.5}', 'gold'),
('VIP Week Pass', '7 days of VIP access', 2000, 'premium_feature', '{"feature_id": 6, "duration": 7}', 'platinum'),
('Legendary Seed', 'Exclusive legendary strain', 5000, 'exclusive_strain', '{"strain_id": 6}', 'diamond');

-- Create stored procedures for game mechanics
DELIMITER //

CREATE PROCEDURE CalculateImpairment(IN player_id INT)
BEGIN
    DECLARE total_impairment DECIMAL(3,2) DEFAULT 0.0;
    
    SELECT COALESCE(SUM(impairment_level), 0.0) INTO total_impairment
    FROM player_consumption 
    WHERE player_id = player_id AND expires_at > NOW();
    
    UPDATE game_players 
    SET impairment_level = LEAST(total_impairment, 1.0)
    WHERE id = player_id;
END //

CREATE PROCEDURE CheckForMistakes(IN player_id INT, IN action_type VARCHAR(50))
BEGIN
    DECLARE current_impairment DECIMAL(3,2) DEFAULT 0.0;
    DECLARE mistake_chance DECIMAL(3,2) DEFAULT 0.0;
    DECLARE random_roll DECIMAL(3,2);
    
    SELECT impairment_level INTO current_impairment
    FROM game_players WHERE id = player_id;
    
    SET mistake_chance = current_impairment * 0.3; -- Max 30% mistake chance
    SET random_roll = RAND();
    
    IF random_roll < mistake_chance THEN
        CALL TriggerMistake(player_id, action_type, current_impairment);
    END IF;
END //

CREATE PROCEDURE TriggerMistake(IN player_id INT, IN action_type VARCHAR(50), IN impairment DECIMAL(3,2))
BEGIN
    DECLARE mistake_type VARCHAR(50);
    DECLARE loss_amount DECIMAL(10,2) DEFAULT 0.0;
    
    -- Determine mistake type based on action and impairment level
    CASE action_type
        WHEN 'plant_seed' THEN SET mistake_type = 'plant_death';
        WHEN 'harvest' THEN SET mistake_type = 'quality_loss';
        WHEN 'sell' THEN SET mistake_type = 'bad_sale';
        ELSE SET mistake_type = 'timing_error';
    END CASE;
    
    -- Calculate loss based on impairment level
    SET loss_amount = impairment * 100; -- Scale loss with impairment
    
    INSERT INTO game_mistakes (player_id, mistake_type, loss_amount, loss_type, caused_by_impairment, impairment_level)
    VALUES (player_id, mistake_type, loss_amount, 'tokens', TRUE, impairment);
    
    -- Apply the loss
    UPDATE game_players 
    SET tokens = GREATEST(tokens - loss_amount, 0),
        mistakes_count = mistakes_count + 1
    WHERE id = player_id;
END //

DELIMITER ;
