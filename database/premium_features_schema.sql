-- Premium Features Database Schema
-- VIP Rooms, Exclusive Strains, Boosters, Cosmetics, and Premium Achievements

-- VIP Rooms for premium players
CREATE TABLE IF NOT EXISTS vip_rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_name VARCHAR(100) NOT NULL,
    description TEXT,
    tier_level INT NOT NULL DEFAULT 1,
    token_cost_per_day INT NOT NULL,
    max_plants INT DEFAULT 20,
    growth_speed_multiplier DECIMAL(3,2) DEFAULT 1.50,
    yield_bonus_percentage INT DEFAULT 25,
    benefits JSON, -- {"faster_harvest": true, "bonus_yield": 25, "exclusive_strains": true}
    requirements JSON, -- {"min_level": 10, "achievements": ["master_grower"]}
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Player VIP Room ownership
CREATE TABLE IF NOT EXISTS player_vip_rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    vip_room_id INT NOT NULL,
    expires_at DATETIME,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (vip_room_id) REFERENCES vip_rooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_room (player_id, vip_room_id)
);

-- Boosters for temporary advantages
CREATE TABLE IF NOT EXISTS boosters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booster_name VARCHAR(100) NOT NULL,
    booster_type ENUM('growth', 'yield', 'quality', 'protection', 'experience') NOT NULL,
    description TEXT,
    token_cost INT NOT NULL,
    duration_hours INT NOT NULL DEFAULT 24,
    effects JSON, -- {"growth_speed": 2.0, "yield_multiplier": 1.5, "quality_bonus": 10}
    rarity_level ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Player booster inventory
CREATE TABLE IF NOT EXISTS player_boosters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    booster_id INT NOT NULL,
    quantity INT DEFAULT 1,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (booster_id) REFERENCES boosters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_booster (player_id, booster_id)
);

-- Active booster effects
CREATE TABLE IF NOT EXISTS active_boosters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    booster_id INT NOT NULL,
    target_type ENUM('player', 'plant', 'location') NOT NULL,
    target_id INT NULL, -- plant_id or location_id if applicable
    effects JSON,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (booster_id) REFERENCES boosters(id) ON DELETE CASCADE
);

-- Player unlocked strains (for exclusive access)
CREATE TABLE IF NOT EXISTS player_strains (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    strain_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (strain_id) REFERENCES strains(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_strain (player_id, strain_id)
);

-- Cosmetic items for customization
CREATE TABLE IF NOT EXISTS cosmetics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    cosmetic_type ENUM('avatar', 'grow_room', 'plant_pot', 'decoration', 'background') NOT NULL,
    description TEXT,
    token_cost INT NOT NULL,
    rarity_level ENUM('common', 'rare', 'epic', 'legendary', 'mythic') DEFAULT 'common',
    image_url VARCHAR(255),
    animation_effects JSON, -- {"glow": true, "particles": "sparkle"}
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Player cosmetic collection
CREATE TABLE IF NOT EXISTS player_cosmetics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    cosmetic_id INT NOT NULL,
    equipped BOOLEAN DEFAULT FALSE,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE,
    FOREIGN KEY (cosmetic_id) REFERENCES cosmetics(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_cosmetic (player_id, cosmetic_id)
);

-- Update achievements table for premium achievements
ALTER TABLE achievements 
ADD COLUMN IF NOT EXISTS is_premium BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS token_reward INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS cosmetic_reward_id INT NULL,
ADD FOREIGN KEY (cosmetic_reward_id) REFERENCES cosmetics(id);

-- Insert VIP Rooms
INSERT INTO vip_rooms (room_name, description, tier_level, token_cost_per_day, max_plants, growth_speed_multiplier, yield_bonus_percentage, benefits, requirements) VALUES
('Greenhouse Elite', 'Premium greenhouse with climate control and automated systems', 1, 50, 25, 1.25, 15, '{"faster_harvest": true, "bonus_yield": 15, "climate_control": true}', '{"min_level": 5}'),
('Hydroponic Paradise', 'Advanced hydroponic setup with nutrient optimization', 2, 100, 35, 1.50, 25, '{"faster_harvest": true, "bonus_yield": 25, "hydroponic_bonus": true, "nutrient_optimization": true}', '{"min_level": 10, "achievements": ["hydro_master"]}'),
('Laboratory Complex', 'Scientific growing facility with genetic modification capabilities', 3, 200, 50, 1.75, 40, '{"faster_harvest": true, "bonus_yield": 40, "genetic_modification": true, "research_bonus": true}', '{"min_level": 20, "achievements": ["scientist", "master_grower"]}'),
('Penthouse Garden', 'Luxury rooftop facility with premium amenities', 4, 350, 75, 2.00, 60, '{"faster_harvest": true, "bonus_yield": 60, "luxury_amenities": true, "vip_access": true}', '{"min_level": 35, "achievements": ["high_roller", "master_grower", "business_mogul"]}'),
('Underground Bunker', 'Military-grade secure facility with maximum protection', 5, 500, 100, 2.25, 80, '{"faster_harvest": true, "bonus_yield": 80, "maximum_security": true, "stealth_mode": true, "disaster_proof": true}', '{"min_level": 50, "achievements": ["underground_king", "security_expert", "master_grower"]});

-- Insert Boosters
INSERT INTO boosters (booster_name, booster_type, description, token_cost, duration_hours, effects, rarity_level) VALUES
('Speed Grow Serum', 'growth', 'Accelerates plant growth by 100%', 25, 12, '{"growth_speed": 2.0}', 'common'),
('Mega Yield Formula', 'yield', 'Increases harvest yield by 50%', 40, 24, '{"yield_multiplier": 1.5}', 'rare'),
('Quality Enhancer', 'quality', 'Improves product quality and potency', 35, 18, '{"quality_bonus": 20, "potency_increase": 15}', 'rare'),
('Pest Shield', 'protection', 'Protects plants from pests and diseases', 20, 48, '{"pest_immunity": true, "disease_resistance": 90}', 'common'),
('XP Multiplier', 'experience', 'Doubles experience gained from all activities', 60, 6, '{"xp_multiplier": 2.0}', 'epic'),
('Golden Touch', 'yield', 'Legendary booster that triples yield and quality', 200, 4, '{"yield_multiplier": 3.0, "quality_bonus": 50, "golden_effect": true}', 'legendary'),
('Time Warp', 'growth', 'Instantly completes 50% of remaining growth time', 150, 1, '{"instant_growth": 0.5}', 'epic'),
('Master Cultivator', 'growth', 'Ultimate growing enhancement - all bonuses combined', 500, 12, '{"growth_speed": 2.5, "yield_multiplier": 2.0, "quality_bonus": 30, "xp_multiplier": 1.5}', 'legendary');

-- Insert Cosmetic Items
INSERT INTO cosmetics (item_name, cosmetic_type, description, token_cost, rarity_level, animation_effects) VALUES
('Neon Avatar Skin', 'avatar', 'Glowing neon outline for your character', 100, 'rare', '{"glow": true, "color": "neon"}'),
('Crystal Grow Room', 'grow_room', 'Sparkling crystal-themed growing environment', 250, 'epic', '{"sparkle": true, "crystal_reflections": true}'),
('Golden Plant Pots', 'plant_pot', 'Luxurious golden pots that shimmer', 75, 'rare', '{"shimmer": true, "golden_glow": true}'),
('Floating Gardens', 'background', 'Magical floating garden background', 300, 'epic', '{"floating_particles": true, "magic_aura": true}'),
('Rainbow Smoke Effect', 'decoration', 'Colorful smoke effects around plants', 150, 'epic', '{"rainbow_smoke": true, "color_cycle": true}'),
('Holographic Display', 'decoration', 'Futuristic holographic plant data display', 200, 'legendary', '{"hologram": true, "data_stream": true}'),
('Dragon Avatar', 'avatar', 'Mythical dragon-themed character skin', 500, 'mythic', '{"fire_breathing": true, "wing_flutter": true, "scale_shimmer": true}'),
('Cyberpunk Grow Lab', 'grow_room', 'High-tech cyberpunk laboratory theme', 400, 'legendary', '{"neon_lights": true, "digital_effects": true, "cyber_grid": true}');

-- Insert Premium Achievements
INSERT INTO achievements (achievement_name, description, requirements, token_reward, xp_reward, is_premium, cosmetic_reward_id) VALUES
('VIP Member', 'Purchase your first VIP room', '{"vip_rooms_purchased": 1}', 100, 500, TRUE, NULL),
('High Roller', 'Spend 10,000 tokens on premium features', '{"premium_spending": 10000}', 500, 2000, TRUE, 1),
('Collector Supreme', 'Own 25 different cosmetic items', '{"cosmetics_owned": 25}', 300, 1500, TRUE, 5),
('Booster Addict', 'Use 100 boosters', '{"boosters_used": 100}', 200, 1000, TRUE, NULL),
('Legendary Grower', 'Unlock all legendary strains', '{"legendary_strains": "all"}', 1000, 5000, TRUE, 7),
('Master of Time', 'Use Time Warp booster 50 times', '{"time_warp_uses": 50}', 400, 2000, TRUE, NULL),
('Golden Touch Master', 'Use Golden Touch booster 10 times', '{"golden_touch_uses": 10}', 600, 3000, TRUE, 3),
('Premium Pioneer', 'Be among the first 100 premium users', '{"early_adopter": true}', 1500, 7500, TRUE, 8);

-- Create indexes for performance
CREATE INDEX idx_player_vip_rooms_expires ON player_vip_rooms(expires_at);
CREATE INDEX idx_active_boosters_expires ON active_boosters(expires_at);
CREATE INDEX idx_player_boosters_player ON player_boosters(player_id);
CREATE INDEX idx_cosmetics_type_rarity ON cosmetics(cosmetic_type, rarity_level);
CREATE INDEX idx_player_cosmetics_equipped ON player_cosmetics(player_id, equipped);

-- Create stored procedure to clean up expired boosters
DELIMITER //
CREATE PROCEDURE CleanupExpiredBoosters()
BEGIN
    DELETE FROM active_boosters WHERE expires_at < NOW();
    DELETE FROM player_vip_rooms WHERE expires_at IS NOT NULL AND expires_at < NOW();
END //
DELIMITER ;

-- Create stored procedure to apply booster effects
DELIMITER //
CREATE PROCEDURE ApplyBoosterEffects(IN p_player_id INT, IN p_plant_id INT DEFAULT NULL)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE boost_growth DECIMAL(3,2) DEFAULT 1.0;
    DECLARE boost_yield DECIMAL(3,2) DEFAULT 1.0;
    DECLARE boost_quality INT DEFAULT 0;
    DECLARE boost_xp DECIMAL(3,2) DEFAULT 1.0;
    
    DECLARE cur CURSOR FOR 
        SELECT JSON_EXTRACT(effects, '$.growth_speed') as growth,
               JSON_EXTRACT(effects, '$.yield_multiplier') as yield_mult,
               JSON_EXTRACT(effects, '$.quality_bonus') as quality,
               JSON_EXTRACT(effects, '$.xp_multiplier') as xp_mult
        FROM active_boosters 
        WHERE player_id = p_player_id 
        AND expires_at > NOW()
        AND (target_type = 'player' OR (target_type = 'plant' AND target_id = p_plant_id));
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO boost_growth, boost_yield, boost_quality, boost_xp;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Multiply effects (they stack)
        SET boost_growth = boost_growth * COALESCE(boost_growth, 1.0);
        SET boost_yield = boost_yield * COALESCE(boost_yield, 1.0);
        SET boost_quality = boost_quality + COALESCE(boost_quality, 0);
        SET boost_xp = boost_xp * COALESCE(boost_xp, 1.0);
    END LOOP;
    
    CLOSE cur;
    
    -- Return the calculated multipliers
    SELECT boost_growth as growth_multiplier, 
           boost_yield as yield_multiplier,
           boost_quality as quality_bonus,
           boost_xp as xp_multiplier;
END //
DELIMITER ;
