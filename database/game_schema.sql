-- Cannabis Growing Game Database Schema

-- Game Players Table
CREATE TABLE game_players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tokens DECIMAL(10,2) DEFAULT 100.00,
    experience_points INT DEFAULT 0,
    level INT DEFAULT 1,
    reputation INT DEFAULT 0,
    unlocked_locations JSON DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_player (user_id)
);

-- Cannabis Strains Table
CREATE TABLE strains (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    rarity ENUM('common', 'uncommon', 'rare', 'legendary') DEFAULT 'common',
    base_yield DECIMAL(5,2) DEFAULT 1.0,
    base_quality DECIMAL(5,2) DEFAULT 1.0,
    base_price DECIMAL(8,2) DEFAULT 10.00,
    growth_time INT DEFAULT 24, -- hours
    required_level INT DEFAULT 1,
    seed_cost DECIMAL(8,2) DEFAULT 5.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rarity (rarity),
    INDEX idx_required_level (required_level)
);

-- Game Locations Table
CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'NY',
    market_modifier DECIMAL(3,2) DEFAULT 1.0,
    required_level INT DEFAULT 1,
    required_reputation INT DEFAULT 0,
    max_plants INT DEFAULT 3,
    is_unlocked_by_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_required_level (required_level),
    INDEX idx_city (city)
);

-- Plants Table
CREATE TABLE plants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    strain_id INT NOT NULL,
    planted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    harvest_ready_at TIMESTAMP NOT NULL,
    status ENUM('growing', 'ready', 'harvested', 'dead') DEFAULT 'growing',
    quality_modifier DECIMAL(3,2) DEFAULT 1.0,
    yield_modifier DECIMAL(3,2) DEFAULT 1.0,
    location_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (strain_id) REFERENCES strains(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    INDEX idx_player_status (player_id, status),
    INDEX idx_harvest_ready (harvest_ready_at, status)
);

-- Sales Table
CREATE TABLE sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    plant_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity DECIMAL(5,2) NOT NULL,
    quality DECIMAL(5,2) NOT NULL,
    base_price DECIMAL(8,2) NOT NULL,
    final_price DECIMAL(8,2) NOT NULL,
    experience_gained INT DEFAULT 0,
    reputation_gained INT DEFAULT 0,
    sold_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (plant_id) REFERENCES plants(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    INDEX idx_player_sales (player_id, sold_at),
    INDEX idx_location_sales (location_id, sold_at)
);

-- Achievements Table
CREATE TABLE achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('sales_milestone', 'level_milestone', 'strain_collection', 'location_unlock', 'reputation_milestone') NOT NULL,
    requirement_value INT NOT NULL,
    reward_tokens DECIMAL(8,2) DEFAULT 0,
    reward_experience INT DEFAULT 0,
    unlock_location_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unlock_location_id) REFERENCES locations(id),
    INDEX idx_type (type)
);

-- Player Achievements Table
CREATE TABLE player_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id),
    UNIQUE KEY unique_player_achievement (player_id, achievement_id),
    INDEX idx_player_earned (player_id, earned_at)
);

-- Game Transactions Table
CREATE TABLE game_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    type ENUM('token_purchase', 'seed_purchase', 'sale', 'achievement_reward', 'level_bonus') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    reference_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    INDEX idx_player_transactions (player_id, created_at),
    INDEX idx_type_reference (type, reference_id)
);

-- Market Conditions Table
CREATE TABLE market_conditions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_id INT NOT NULL,
    strain_id INT NOT NULL,
    demand_level ENUM('very_low', 'low', 'normal', 'high', 'very_high') DEFAULT 'normal',
    supply_level ENUM('very_low', 'low', 'normal', 'high', 'very_high') DEFAULT 'normal',
    price_modifier DECIMAL(3,2) DEFAULT 1.0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (strain_id) REFERENCES strains(id),
    UNIQUE KEY unique_location_strain (location_id, strain_id),
    INDEX idx_updated (updated_at)
);

-- Insert Initial Game Data

-- Default Strains
INSERT INTO strains (name, description, rarity, base_yield, base_quality, base_price, growth_time, required_level, seed_cost) VALUES
('Bag Seed', 'Basic unknown genetics, low quality but cheap to start', 'common', 0.8, 0.6, 8.00, 48, 1, 3.00),
('Northern Lights', 'Classic indica strain, reliable and forgiving', 'common', 1.2, 1.0, 15.00, 36, 2, 8.00),
('White Widow', 'Balanced hybrid with good yields', 'uncommon', 1.5, 1.3, 25.00, 30, 5, 15.00),
('OG Kush', 'Premium strain with high market value', 'rare', 1.8, 1.8, 45.00, 28, 10, 30.00),
('Girl Scout Cookies', 'Elite genetics with exceptional quality', 'rare', 2.0, 2.2, 65.00, 32, 15, 50.00),
('Gorilla Glue #4', 'Legendary strain with massive yields', 'legendary', 2.5, 2.5, 100.00, 35, 20, 80.00);

-- Default Locations
INSERT INTO locations (name, description, city, market_modifier, required_level, required_reputation, max_plants, is_unlocked_by_default) VALUES
('Brooklyn Corner', 'Small local market in Brooklyn', 'Brooklyn', 0.9, 1, 0, 2, TRUE),
('Manhattan Dispensary', 'High-end Manhattan location', 'Manhattan', 1.3, 5, 100, 4, FALSE),
('Queens Collective', 'Community-focused Queens spot', 'Queens', 1.0, 3, 25, 3, FALSE),
('Bronx Underground', 'Street-level Bronx operation', 'Bronx', 0.8, 2, 10, 3, FALSE),
('Staten Island Supply', 'Island distribution network', 'Staten Island', 1.1, 8, 200, 5, FALSE),
('Upstate Network', 'Rural upstate New York market', 'Albany', 1.2, 12, 500, 6, FALSE),
('Jersey Connect', 'Cross-border New Jersey operation', 'Newark', 1.4, 15, 750, 8, FALSE),
('Connecticut Elite', 'Premium Connecticut clientele', 'Hartford', 1.6, 20, 1000, 10, FALSE);

-- Default Achievements
INSERT INTO achievements (name, description, type, requirement_value, reward_tokens, reward_experience, unlock_location_id) VALUES
('First Harvest', 'Harvest your first plant', 'sales_milestone', 1, 50, 25, NULL),
('Neighborhood Dealer', 'Complete 10 sales', 'sales_milestone', 10, 100, 50, 3),
('Borough Boss', 'Complete 50 sales', 'sales_milestone', 50, 250, 100, 4),
('City Kingpin', 'Complete 200 sales', 'sales_milestone', 200, 500, 200, 5),
('State Legend', 'Complete 500 sales', 'sales_milestone', 500, 1000, 500, 6),
('Novice Grower', 'Reach level 5', 'level_milestone', 5, 75, 0, 2),
('Expert Cultivator', 'Reach level 10', 'level_milestone', 10, 150, 0, NULL),
('Master Breeder', 'Reach level 15', 'level_milestone', 15, 300, 0, 7),
('Cannabis Connoisseur', 'Reach level 20', 'level_milestone', 20, 500, 0, 8),
('Strain Collector', 'Grow all common strains', 'strain_collection', 2, 200, 100, NULL),
('Rare Cultivator', 'Grow all rare strains', 'strain_collection', 4, 500, 250, NULL),
('Respected Dealer', 'Reach 100 reputation', 'reputation_milestone', 100, 100, 50, NULL),
('Trusted Supplier', 'Reach 500 reputation', 'reputation_milestone', 500, 300, 150, NULL),
('Underground Legend', 'Reach 1000 reputation', 'reputation_milestone', 1000, 750, 500, NULL);

-- Create indexes for performance
CREATE INDEX idx_plants_ready_harvest ON plants (harvest_ready_at) WHERE status = 'growing';
CREATE INDEX idx_market_conditions_location ON market_conditions (location_id, updated_at);
CREATE INDEX idx_sales_player_date ON sales (player_id, sold_at DESC);
CREATE INDEX idx_transactions_player_date ON game_transactions (player_id, created_at DESC);
