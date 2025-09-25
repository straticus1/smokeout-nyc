-- Street Level Gaming System Schema
-- Adds player progression, street dealers, corrupt cops, and territory mechanics
-- SmokeoutNYC v2.4 - Street Level Expansion

-- Player Level and Progression System
CREATE TABLE IF NOT EXISTS player_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    current_level INT DEFAULT 1,
    experience_points INT DEFAULT 0,
    total_experience INT DEFAULT 0,
    reputation_score INT DEFAULT 0,
    street_cred INT DEFAULT 0,
    respect_level ENUM('nobody', 'small_timer', 'player', 'heavyweight', 'kingpin') DEFAULT 'nobody',
    unlock_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_level_up TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_level (user_id, current_level),
    INDEX idx_reputation (reputation_score),
    INDEX idx_street_cred (street_cred)
);

-- Level Requirements and Rewards
CREATE TABLE IF NOT EXISTS level_requirements (
    level INT PRIMARY KEY,
    experience_needed INT NOT NULL,
    title VARCHAR(100),
    description TEXT,
    unlocks JSON, -- Features unlocked at this level
    rewards JSON, -- Rewards given (money, items, abilities)
    street_dealer_spawn_chance DECIMAL(5,2) DEFAULT 0.00, -- Chance dealers appear
    max_dealers_per_territory INT DEFAULT 0,
    cop_corruption_available BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Street Dealers System
CREATE TABLE IF NOT EXISTS street_dealers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    nickname VARCHAR(50),
    territory_id INT NOT NULL,
    aggression_level ENUM('passive', 'moderate', 'aggressive', 'violent') DEFAULT 'moderate',
    street_smarts INT DEFAULT 50, -- Intelligence/cunning
    violence_tendency INT DEFAULT 30, -- Likelihood to use violence
    customer_base INT DEFAULT 10, -- Number of regular customers
    product_quality ENUM('garbage', 'low', 'mid', 'high', 'premium') DEFAULT 'low',
    cash_on_hand DECIMAL(10,2) DEFAULT 500.00,
    inventory_size INT DEFAULT 20,
    respect_level INT DEFAULT 0,
    heat_level INT DEFAULT 0, -- Police attention
    is_active BOOLEAN DEFAULT TRUE,
    spawn_level INT DEFAULT 10, -- Player level when they can spawn
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_territory (territory_id),
    INDEX idx_active_dealers (is_active, territory_id),
    INDEX idx_spawn_level (spawn_level)
);

-- Dealer Actions and Events
CREATE TABLE IF NOT EXISTS dealer_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dealer_id INT NOT NULL,
    target_type ENUM('player', 'customer', 'territory', 'police', 'rival_dealer') NOT NULL,
    target_id INT,
    action_type ENUM('robbery', 'intimidation', 'customer_theft', 'territory_encroachment', 'violence', 'bribery', 'cooperation') NOT NULL,
    severity ENUM('minor', 'moderate', 'serious', 'severe') DEFAULT 'minor',
    success BOOLEAN DEFAULT FALSE,
    consequences JSON, -- What happened as a result
    player_response ENUM('ignore', 'negotiate', 'retaliate', 'call_police', 'bribe_cops', 'flee') DEFAULT 'ignore',
    outcome_description TEXT,
    money_involved DECIMAL(10,2) DEFAULT 0.00,
    reputation_change INT DEFAULT 0,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dealer_id) REFERENCES street_dealers(id) ON DELETE CASCADE,
    INDEX idx_dealer_actions (dealer_id, occurred_at),
    INDEX idx_target (target_type, target_id)
);

-- NYC Police System with Corruption
CREATE TABLE IF NOT EXISTS nyc_cops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    badge_number VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    rank_title ENUM('Officer', 'Detective', 'Sergeant', 'Lieutenant', 'Captain') DEFAULT 'Officer',
    precinct INT NOT NULL, -- NYC precinct number
    corruption_level ENUM('clean', 'minor', 'moderate', 'dirty', 'totally_corrupt') DEFAULT 'clean',
    bribe_threshold DECIMAL(10,2) DEFAULT NULL, -- Minimum bribe they'll accept
    loyalty_price DECIMAL(10,2) DEFAULT NULL, -- Monthly payment for protection
    specialties JSON, -- What they can help with (evidence, raids, intel)
    heat_reduction_ability INT DEFAULT 10, -- How much heat they can reduce
    territory_coverage JSON, -- Areas they patrol/control
    last_bribe TIMESTAMP NULL,
    total_bribes_taken DECIMAL(12,2) DEFAULT 0.00,
    reliability_score INT DEFAULT 50, -- How trustworthy they are
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_precinct (precinct),
    INDEX idx_corruption (corruption_level),
    INDEX idx_bribe_threshold (bribe_threshold)
);

-- Player-Cop Relationships
CREATE TABLE IF NOT EXISTS player_cop_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cop_id INT NOT NULL,
    relationship_type ENUM('unknown', 'neutral', 'suspicious', 'friendly', 'owned', 'hostile') DEFAULT 'unknown',
    trust_level INT DEFAULT 0, -- -100 to +100
    last_interaction TIMESTAMP NULL,
    total_bribes_paid DECIMAL(10,2) DEFAULT 0.00,
    services_used INT DEFAULT 0,
    times_betrayed INT DEFAULT 0,
    protection_active BOOLEAN DEFAULT FALSE,
    protection_expires TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cop_id) REFERENCES nyc_cops(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_cop (user_id, cop_id),
    INDEX idx_relationship (relationship_type, trust_level)
);

-- Territory Control System
CREATE TABLE IF NOT EXISTS territories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    borough ENUM('Manhattan', 'Brooklyn', 'Queens', 'Bronx', 'Staten Island') NOT NULL,
    neighborhood VARCHAR(100),
    coordinates JSON, -- Polygon coordinates for map display
    size_sq_blocks INT DEFAULT 4,
    population_density ENUM('low', 'medium', 'high', 'very_high') DEFAULT 'medium',
    police_presence ENUM('minimal', 'light', 'moderate', 'heavy', 'overwhelming') DEFAULT 'moderate',
    gentrification_level ENUM('none', 'early', 'moderate', 'advanced', 'complete') DEFAULT 'none',
    average_income ENUM('low', 'working_class', 'middle_class', 'upper_middle', 'wealthy') DEFAULT 'working_class',
    cannabis_tolerance ENUM('very_hostile', 'hostile', 'neutral', 'tolerant', 'very_tolerant') DEFAULT 'neutral',
    competition_level INT DEFAULT 3, -- Number of active dealers
    customer_demand INT DEFAULT 50, -- Market demand 0-100
    heat_level INT DEFAULT 10, -- Police attention 0-100
    is_contested BOOLEAN DEFAULT FALSE,
    controlled_by INT NULL, -- Player who controls this territory
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (controlled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_borough (borough),
    INDEX idx_competition (competition_level),
    INDEX idx_controller (controlled_by)
);

-- Player Territory Claims and Conflicts
CREATE TABLE IF NOT EXISTS territory_control (
    id INT AUTO_INCREMENT PRIMARY KEY,
    territory_id INT NOT NULL,
    user_id INT NOT NULL,
    control_percentage DECIMAL(5,2) DEFAULT 0.00, -- 0-100% control
    influence_points INT DEFAULT 0,
    established_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_defended TIMESTAMP NULL,
    times_challenged INT DEFAULT 0,
    revenue_per_day DECIMAL(8,2) DEFAULT 0.00,
    protection_level INT DEFAULT 0, -- Investment in security
    status ENUM('expanding', 'stable', 'contested', 'under_attack', 'lost') DEFAULT 'expanding',
    FOREIGN KEY (territory_id) REFERENCES territories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_territory_user (territory_id, user_id),
    INDEX idx_control_level (control_percentage),
    INDEX idx_status (status)
);

-- Street Events and Random Encounters
CREATE TABLE IF NOT EXISTS street_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_type ENUM('dealer_robbery', 'police_shakedown', 'customer_dispute', 'rival_intimidation', 'witness_situation', 'cop_corruption_offer', 'territory_challenge', 'random_opportunity') NOT NULL,
    severity ENUM('minor', 'moderate', 'serious', 'critical') DEFAULT 'minor',
    territory_id INT,
    dealer_id INT,
    cop_id INT,
    description TEXT NOT NULL,
    choices JSON, -- Available response options
    selected_choice VARCHAR(100),
    outcome JSON, -- Results of player's choice
    money_impact DECIMAL(10,2) DEFAULT 0.00,
    reputation_impact INT DEFAULT 0,
    heat_impact INT DEFAULT 0,
    experience_gained INT DEFAULT 0,
    resolved BOOLEAN DEFAULT FALSE,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (territory_id) REFERENCES territories(id) ON DELETE SET NULL,
    FOREIGN KEY (dealer_id) REFERENCES street_dealers(id) ON DELETE SET NULL,
    FOREIGN KEY (cop_id) REFERENCES nyc_cops(id) ON DELETE SET NULL,
    INDEX idx_user_events (user_id, resolved, occurred_at),
    INDEX idx_event_type (event_type, severity)
);

-- Player Security and Protection
CREATE TABLE IF NOT EXISTS player_security (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    bodyguard_level INT DEFAULT 0, -- 0-5 levels of protection
    security_budget DECIMAL(10,2) DEFAULT 0.00, -- Monthly security costs
    safe_house_level INT DEFAULT 0, -- Hideout security
    early_warning_system BOOLEAN DEFAULT FALSE,
    police_scanner BOOLEAN DEFAULT FALSE,
    corrupt_cop_network INT DEFAULT 0, -- Number of cops on payroll
    street_informants INT DEFAULT 0, -- Number of street informants
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert Level Requirements
INSERT INTO level_requirements (level, experience_needed, title, description, unlocks, rewards, street_dealer_spawn_chance, max_dealers_per_territory, cop_corruption_available) VALUES
(1, 0, 'Green Rookie', 'Just starting out in the cannabis game', '{"features": ["basic_growing", "simple_sales"]}', '{"cash": 100, "seeds": 3}', 0.00, 0, FALSE),
(5, 2500, 'Small Timer', 'Getting noticed in the neighborhood', '{"features": ["customer_base", "quality_control"]}', '{"cash": 500, "equipment": "basic_lights"}', 0.00, 0, FALSE),
(10, 7500, 'Street Player', 'Making moves on the block', '{"features": ["territory_awareness", "dealer_radar"]}', '{"cash": 1000}', 0.05, 1, FALSE),
(12, 10000, 'Corner Hustler', 'First dealer encounters possible', '{"features": ["basic_security", "street_intel"]}', '{"cash": 1500}', 0.10, 1, FALSE),
(15, 15000, 'Block Captain', 'Dealers actively compete with you', '{"features": ["reputation_system", "intimidation"]}', '{"cash": 2500}', 0.20, 2, TRUE),
(20, 25000, 'Neighborhood Boss', 'Territory control becomes important', '{"features": ["territory_control", "crew_management"]}', '{"cash": 5000}', 0.35, 2, TRUE),
(25, 40000, 'District Player', 'Multiple territories, serious competition', '{"features": ["multi_territory", "advanced_security"]}', '{"cash": 10000}', 0.50, 3, TRUE),
(30, 60000, 'Borough Heavyweight', 'City-wide recognition and threats', '{"features": ["police_network", "major_operations"]}', '{"cash": 20000}', 0.65, 4, TRUE),
(35, 90000, 'City Kingpin', 'Top of the food chain', '{"features": ["empire_management", "political_influence"]}', '{"cash": 50000}', 0.80, 5, TRUE);

-- Insert Sample Territories (NYC Neighborhoods)
INSERT INTO territories (name, borough, neighborhood, size_sq_blocks, population_density, police_presence, gentrification_level, average_income, cannabis_tolerance, competition_level, customer_demand, heat_level) VALUES
('Washington Heights', 'Manhattan', 'Washington Heights', 6, 'high', 'moderate', 'early', 'working_class', 'tolerant', 4, 70, 25),
('East New York', 'Brooklyn', 'East New York', 8, 'high', 'heavy', 'early', 'low', 'neutral', 5, 80, 40),
('Jamaica', 'Queens', 'Jamaica', 10, 'very_high', 'moderate', 'moderate', 'working_class', 'neutral', 3, 65, 30),
('Mott Haven', 'Bronx', 'Mott Haven', 5, 'high', 'heavy', 'moderate', 'low', 'tolerant', 4, 75, 45),
('St. George', 'Staten Island', 'St. George', 4, 'medium', 'light', 'advanced', 'middle_class', 'hostile', 2, 40, 15),
('Harlem', 'Manhattan', 'Central Harlem', 7, 'very_high', 'moderate', 'advanced', 'middle_class', 'tolerant', 3, 60, 20),
('Bed-Stuy', 'Brooklyn', 'Bedford-Stuyvesant', 9, 'high', 'moderate', 'advanced', 'middle_class', 'very_tolerant', 2, 55, 18),
('Astoria', 'Queens', 'Astoria', 6, 'high', 'light', 'complete', 'upper_middle', 'neutral', 1, 35, 10),
('Soundview', 'Bronx', 'Soundview', 7, 'high', 'heavy', 'none', 'low', 'tolerant', 5, 85, 50);

-- Insert Sample Corrupt Cops
INSERT INTO nyc_cops (badge_number, name, rank_title, precinct, corruption_level, bribe_threshold, loyalty_price, specialties, heat_reduction_ability, territory_coverage) VALUES
('12345', 'Officer Mike Romano', 'Officer', 34, 'minor', 500.00, 2000.00, '["patrol_routes", "minor_violations"]', 15, '["Washington Heights"]'),
('23456', 'Detective Sarah Chen', 'Detective', 75, 'moderate', 1500.00, 5000.00, '["evidence_handling", "case_delays"]', 30, '["East New York", "Bed-Stuy"]'),
('34567', 'Sergeant Tony Martinez', 'Sergeant', 103, 'dirty', 1000.00, 3500.00, '["raid_warnings", "witness_intimidation"]', 25, '["Jamaica", "Astoria"]'),
('45678', 'Lieutenant Frank O\'Brien', 'Lieutenant', 40, 'totally_corrupt', 3000.00, 10000.00, '["case_dismissal", "evidence_tampering", "protection"]', 50, '["Mott Haven", "Soundview"]'),
('56789', 'Captain Maria Rodriguez', 'Captain', 26, 'clean', NULL, NULL, '[]', 0, '["Harlem"]');

-- Create views for game mechanics
CREATE OR REPLACE VIEW player_game_state AS
SELECT 
    u.id as user_id,
    u.username,
    pl.current_level,
    pl.experience_points,
    pl.reputation_score,
    pl.street_cred,
    pl.respect_level,
    ps.bodyguard_level,
    ps.security_budget,
    ps.corrupt_cop_network,
    COUNT(DISTINCT tc.territory_id) as territories_controlled,
    COUNT(DISTINCT sd.id) as active_dealers_in_territories,
    AVG(t.heat_level) as avg_territory_heat,
    SUM(tc.revenue_per_day) as daily_territory_revenue
FROM users u
LEFT JOIN player_levels pl ON u.id = pl.user_id
LEFT JOIN player_security ps ON u.id = ps.user_id
LEFT JOIN territory_control tc ON u.id = tc.user_id AND tc.control_percentage > 50
LEFT JOIN territories t ON tc.territory_id = t.id
LEFT JOIN street_dealers sd ON t.id = sd.territory_id AND sd.is_active = TRUE
WHERE u.status = 'active'
GROUP BY u.id;

CREATE OR REPLACE VIEW active_street_threats AS
SELECT 
    sd.id,
    sd.name,
    sd.nickname,
    sd.territory_id,
    t.name as territory_name,
    t.borough,
    sd.aggression_level,
    sd.violence_tendency,
    sd.customer_base,
    sd.respect_level,
    sd.heat_level,
    COUNT(da.id) as recent_actions
FROM street_dealers sd
JOIN territories t ON sd.territory_id = t.id
LEFT JOIN dealer_actions da ON sd.id = da.dealer_id 
    AND da.occurred_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
WHERE sd.is_active = TRUE
GROUP BY sd.id
ORDER BY sd.aggression_level DESC, sd.violence_tendency DESC;