-- Street Level Gaming System Schema - SQLite Version
-- Adds player progression, street dealers, corrupt cops, and territory mechanics
-- SmokeoutNYC v2.4 - Street Level Expansion

-- Player Level and Progression System
CREATE TABLE IF NOT EXISTS player_levels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    current_level INTEGER DEFAULT 1,
    experience_points INTEGER DEFAULT 0,
    total_experience INTEGER DEFAULT 0,
    reputation_score INTEGER DEFAULT 0,
    street_cred INTEGER DEFAULT 0,
    respect_level TEXT CHECK(respect_level IN ('nobody', 'small_timer', 'player', 'heavyweight', 'kingpin')) DEFAULT 'nobody',
    unlock_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_level_up TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_player_levels_user_level ON player_levels (user_id, current_level);
CREATE INDEX IF NOT EXISTS idx_player_levels_reputation ON player_levels (reputation_score);
CREATE INDEX IF NOT EXISTS idx_player_levels_street_cred ON player_levels (street_cred);

-- Level Requirements and Rewards
CREATE TABLE IF NOT EXISTS level_requirements (
    level INTEGER PRIMARY KEY,
    experience_needed INTEGER NOT NULL,
    title TEXT,
    description TEXT,
    unlocks TEXT, -- JSON stored as TEXT
    rewards TEXT, -- JSON stored as TEXT
    street_dealer_spawn_chance REAL DEFAULT 0.00,
    max_dealers_per_territory INTEGER DEFAULT 0,
    cop_corruption_available INTEGER DEFAULT 0, -- SQLite doesn't have boolean
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Street Dealers System
CREATE TABLE IF NOT EXISTS street_dealers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    nickname TEXT,
    territory_id INTEGER NOT NULL,
    aggression_level TEXT CHECK(aggression_level IN ('passive', 'moderate', 'aggressive', 'violent')) DEFAULT 'moderate',
    street_smarts INTEGER DEFAULT 50,
    violence_tendency INTEGER DEFAULT 30,
    customer_base INTEGER DEFAULT 10,
    product_quality TEXT CHECK(product_quality IN ('garbage', 'low', 'mid', 'high', 'premium')) DEFAULT 'low',
    cash_on_hand REAL DEFAULT 500.00,
    inventory_size INTEGER DEFAULT 20,
    respect_level INTEGER DEFAULT 0,
    heat_level INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1, -- SQLite boolean as integer
    spawn_level INTEGER DEFAULT 10,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_street_dealers_territory ON street_dealers (territory_id);
CREATE INDEX IF NOT EXISTS idx_street_dealers_active ON street_dealers (is_active, territory_id);
CREATE INDEX IF NOT EXISTS idx_street_dealers_spawn_level ON street_dealers (spawn_level);

-- Dealer Actions and Events
CREATE TABLE IF NOT EXISTS dealer_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    dealer_id INTEGER NOT NULL,
    target_type TEXT CHECK(target_type IN ('player', 'customer', 'territory', 'police', 'rival_dealer')) NOT NULL,
    target_id INTEGER,
    action_type TEXT CHECK(action_type IN ('robbery', 'intimidation', 'customer_theft', 'territory_encroachment', 'violence', 'bribery', 'cooperation')) NOT NULL,
    severity TEXT CHECK(severity IN ('minor', 'moderate', 'serious', 'severe')) DEFAULT 'minor',
    success INTEGER DEFAULT 0, -- SQLite boolean as integer
    consequences TEXT, -- JSON stored as TEXT
    player_response TEXT CHECK(player_response IN ('ignore', 'negotiate', 'retaliate', 'call_police', 'bribe_cops', 'flee')) DEFAULT 'ignore',
    outcome_description TEXT,
    money_involved REAL DEFAULT 0.00,
    reputation_change INTEGER DEFAULT 0,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dealer_id) REFERENCES street_dealers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_dealer_actions_dealer ON dealer_actions (dealer_id, occurred_at);
CREATE INDEX IF NOT EXISTS idx_dealer_actions_target ON dealer_actions (target_type, target_id);

-- NYC Police System with Corruption
CREATE TABLE IF NOT EXISTS nyc_cops (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    badge_number TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    rank_title TEXT CHECK(rank_title IN ('Officer', 'Detective', 'Sergeant', 'Lieutenant', 'Captain')) DEFAULT 'Officer',
    precinct INTEGER NOT NULL,
    corruption_level TEXT CHECK(corruption_level IN ('clean', 'minor', 'moderate', 'dirty', 'totally_corrupt')) DEFAULT 'clean',
    bribe_threshold REAL DEFAULT NULL,
    loyalty_price REAL DEFAULT NULL,
    specialties TEXT, -- JSON stored as TEXT
    heat_reduction_ability INTEGER DEFAULT 10,
    territory_coverage TEXT, -- JSON stored as TEXT
    last_bribe TIMESTAMP NULL,
    total_bribes_taken REAL DEFAULT 0.00,
    reliability_score INTEGER DEFAULT 50,
    is_active INTEGER DEFAULT 1, -- SQLite boolean as integer
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_nyc_cops_precinct ON nyc_cops (precinct);
CREATE INDEX IF NOT EXISTS idx_nyc_cops_corruption ON nyc_cops (corruption_level);
CREATE INDEX IF NOT EXISTS idx_nyc_cops_bribe_threshold ON nyc_cops (bribe_threshold);

-- Player-Cop Relationships
CREATE TABLE IF NOT EXISTS player_cop_relations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    cop_id INTEGER NOT NULL,
    relationship_type TEXT CHECK(relationship_type IN ('unknown', 'neutral', 'suspicious', 'friendly', 'owned', 'hostile')) DEFAULT 'unknown',
    trust_level INTEGER DEFAULT 0, -- -100 to +100
    last_interaction TIMESTAMP NULL,
    total_bribes_paid REAL DEFAULT 0.00,
    services_used INTEGER DEFAULT 0,
    times_betrayed INTEGER DEFAULT 0,
    protection_active INTEGER DEFAULT 0, -- SQLite boolean as integer
    protection_expires TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cop_id) REFERENCES nyc_cops(id) ON DELETE CASCADE,
    UNIQUE(user_id, cop_id)
);

CREATE INDEX IF NOT EXISTS idx_player_cop_relations_relationship ON player_cop_relations (relationship_type, trust_level);

-- Territory Control System
CREATE TABLE IF NOT EXISTS territories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    borough TEXT CHECK(borough IN ('Manhattan', 'Brooklyn', 'Queens', 'Bronx', 'Staten Island')) NOT NULL,
    neighborhood TEXT,
    coordinates TEXT, -- JSON stored as TEXT
    size_sq_blocks INTEGER DEFAULT 4,
    population_density TEXT CHECK(population_density IN ('low', 'medium', 'high', 'very_high')) DEFAULT 'medium',
    police_presence TEXT CHECK(police_presence IN ('minimal', 'light', 'moderate', 'heavy', 'overwhelming')) DEFAULT 'moderate',
    gentrification_level TEXT CHECK(gentrification_level IN ('none', 'early', 'moderate', 'advanced', 'complete')) DEFAULT 'none',
    average_income TEXT CHECK(average_income IN ('low', 'working_class', 'middle_class', 'upper_middle', 'wealthy')) DEFAULT 'working_class',
    cannabis_tolerance TEXT CHECK(cannabis_tolerance IN ('very_hostile', 'hostile', 'neutral', 'tolerant', 'very_tolerant')) DEFAULT 'neutral',
    competition_level INTEGER DEFAULT 3,
    customer_demand INTEGER DEFAULT 50,
    heat_level INTEGER DEFAULT 10,
    is_contested INTEGER DEFAULT 0, -- SQLite boolean as integer
    controlled_by INTEGER NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (controlled_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_territories_borough ON territories (borough);
CREATE INDEX IF NOT EXISTS idx_territories_competition ON territories (competition_level);
CREATE INDEX IF NOT EXISTS idx_territories_controller ON territories (controlled_by);

-- Player Territory Claims and Conflicts
CREATE TABLE IF NOT EXISTS territory_control (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    territory_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    control_percentage REAL DEFAULT 0.00,
    influence_points INTEGER DEFAULT 0,
    established_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_defended TIMESTAMP NULL,
    times_challenged INTEGER DEFAULT 0,
    revenue_per_day REAL DEFAULT 0.00,
    protection_level INTEGER DEFAULT 0,
    status TEXT CHECK(status IN ('expanding', 'stable', 'contested', 'under_attack', 'lost')) DEFAULT 'expanding',
    FOREIGN KEY (territory_id) REFERENCES territories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(territory_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_territory_control_control_level ON territory_control (control_percentage);
CREATE INDEX IF NOT EXISTS idx_territory_control_status ON territory_control (status);

-- Street Events and Random Encounters
CREATE TABLE IF NOT EXISTS street_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    event_type TEXT CHECK(event_type IN ('dealer_robbery', 'police_shakedown', 'customer_dispute', 'rival_intimidation', 'witness_situation', 'cop_corruption_offer', 'territory_challenge', 'random_opportunity')) NOT NULL,
    severity TEXT CHECK(severity IN ('minor', 'moderate', 'serious', 'critical')) DEFAULT 'minor',
    territory_id INTEGER,
    dealer_id INTEGER,
    cop_id INTEGER,
    description TEXT NOT NULL,
    choices TEXT, -- JSON stored as TEXT
    selected_choice TEXT,
    outcome TEXT, -- JSON stored as TEXT
    money_impact REAL DEFAULT 0.00,
    reputation_impact INTEGER DEFAULT 0,
    heat_impact INTEGER DEFAULT 0,
    experience_gained INTEGER DEFAULT 0,
    resolved INTEGER DEFAULT 0, -- SQLite boolean as integer
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (territory_id) REFERENCES territories(id) ON DELETE SET NULL,
    FOREIGN KEY (dealer_id) REFERENCES street_dealers(id) ON DELETE SET NULL,
    FOREIGN KEY (cop_id) REFERENCES nyc_cops(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_street_events_user ON street_events (user_id, resolved, occurred_at);
CREATE INDEX IF NOT EXISTS idx_street_events_type ON street_events (event_type, severity);

-- Player Security and Protection
CREATE TABLE IF NOT EXISTS player_security (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    bodyguard_level INTEGER DEFAULT 0,
    security_budget REAL DEFAULT 0.00,
    safe_house_level INTEGER DEFAULT 0,
    early_warning_system INTEGER DEFAULT 0, -- SQLite boolean as integer
    police_scanner INTEGER DEFAULT 0, -- SQLite boolean as integer
    corrupt_cop_network INTEGER DEFAULT 0,
    street_informants INTEGER DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create basic users table if it doesn't exist
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    balance REAL DEFAULT 1000.00,
    status TEXT CHECK(status IN ('active', 'inactive', 'banned')) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Level Requirements
INSERT OR REPLACE INTO level_requirements (level, experience_needed, title, description, unlocks, rewards, street_dealer_spawn_chance, max_dealers_per_territory, cop_corruption_available) VALUES
(1, 0, 'Green Rookie', 'Just starting out in the cannabis game', '{"features": ["basic_growing", "simple_sales"]}', '{"cash": 100, "seeds": 3}', 0.00, 0, 0),
(5, 2500, 'Small Timer', 'Getting noticed in the neighborhood', '{"features": ["customer_base", "quality_control"]}', '{"cash": 500, "equipment": "basic_lights"}', 0.00, 0, 0),
(10, 7500, 'Street Player', 'Making moves on the block', '{"features": ["territory_awareness", "dealer_radar"]}', '{"cash": 1000}', 0.05, 1, 0),
(12, 10000, 'Corner Hustler', 'First dealer encounters possible', '{"features": ["basic_security", "street_intel"]}', '{"cash": 1500}', 0.10, 1, 0),
(15, 15000, 'Block Captain', 'Dealers actively compete with you', '{"features": ["reputation_system", "intimidation"]}', '{"cash": 2500}', 0.20, 2, 1),
(20, 25000, 'Neighborhood Boss', 'Territory control becomes important', '{"features": ["territory_control", "crew_management"]}', '{"cash": 5000}', 0.35, 2, 1),
(25, 40000, 'District Player', 'Multiple territories, serious competition', '{"features": ["multi_territory", "advanced_security"]}', '{"cash": 10000}', 0.50, 3, 1),
(30, 60000, 'Borough Heavyweight', 'City-wide recognition and threats', '{"features": ["police_network", "major_operations"]}', '{"cash": 20000}', 0.65, 4, 1),
(35, 90000, 'City Kingpin', 'Top of the food chain', '{"features": ["empire_management", "political_influence"]}', '{"cash": 50000}', 0.80, 5, 1);

-- Insert Sample Territories (NYC Neighborhoods)
INSERT OR REPLACE INTO territories (id, name, borough, neighborhood, size_sq_blocks, population_density, police_presence, gentrification_level, average_income, cannabis_tolerance, competition_level, customer_demand, heat_level) VALUES
(1, 'Washington Heights', 'Manhattan', 'Washington Heights', 6, 'high', 'moderate', 'early', 'working_class', 'tolerant', 4, 70, 25),
(2, 'East New York', 'Brooklyn', 'East New York', 8, 'high', 'heavy', 'early', 'low', 'neutral', 5, 80, 40),
(3, 'Jamaica', 'Queens', 'Jamaica', 10, 'very_high', 'moderate', 'moderate', 'working_class', 'neutral', 3, 65, 30),
(4, 'Mott Haven', 'Bronx', 'Mott Haven', 5, 'high', 'heavy', 'moderate', 'low', 'tolerant', 4, 75, 45),
(5, 'St. George', 'Staten Island', 'St. George', 4, 'medium', 'light', 'advanced', 'middle_class', 'hostile', 2, 40, 15),
(6, 'Harlem', 'Manhattan', 'Central Harlem', 7, 'very_high', 'moderate', 'advanced', 'middle_class', 'tolerant', 3, 60, 20),
(7, 'Bed-Stuy', 'Brooklyn', 'Bedford-Stuyvesant', 9, 'high', 'moderate', 'advanced', 'middle_class', 'very_tolerant', 2, 55, 18),
(8, 'Astoria', 'Queens', 'Astoria', 6, 'high', 'light', 'complete', 'upper_middle', 'neutral', 1, 35, 10),
(9, 'Soundview', 'Bronx', 'Soundview', 7, 'high', 'heavy', 'none', 'low', 'tolerant', 5, 85, 50);

-- Insert Sample Corrupt Cops
INSERT OR REPLACE INTO nyc_cops (id, badge_number, name, rank_title, precinct, corruption_level, bribe_threshold, loyalty_price, specialties, heat_reduction_ability, territory_coverage) VALUES
(1, '12345', 'Officer Mike Romano', 'Officer', 34, 'minor', 500.00, 2000.00, '["patrol_routes", "minor_violations"]', 15, '["Washington Heights"]'),
(2, '23456', 'Detective Sarah Chen', 'Detective', 75, 'moderate', 1500.00, 5000.00, '["evidence_handling", "case_delays"]', 30, '["East New York", "Bed-Stuy"]'),
(3, '34567', 'Sergeant Tony Martinez', 'Sergeant', 103, 'dirty', 1000.00, 3500.00, '["raid_warnings", "witness_intimidation"]', 25, '["Jamaica", "Astoria"]'),
(4, '45678', 'Lieutenant Frank O''Brien', 'Lieutenant', 40, 'totally_corrupt', 3000.00, 10000.00, '["case_dismissal", "evidence_tampering", "protection"]', 50, '["Mott Haven", "Soundview"]'),
(5, '56789', 'Captain Maria Rodriguez', 'Captain', 26, 'clean', NULL, NULL, '[]', 0, '["Harlem"]');

-- Insert test user if not exists
INSERT OR IGNORE INTO users (id, username, email, password_hash, balance) 
VALUES (1, 'testplayer', 'test@smokeout.nyc', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5000.00);