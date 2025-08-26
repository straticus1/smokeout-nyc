-- NFT Integration Database Schema
-- Enables rare strain genetics as collectible NFTs

-- Genetics NFTs representing rare strain genetics
CREATE TABLE IF NOT EXISTS genetics_nfts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    strain_id INT NOT NULL,
    genetics_name VARCHAR(150) NOT NULL,
    description TEXT,
    rarity_level ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
    generation_number INT DEFAULT 1, -- G1, G2, G3, etc.
    genetic_traits JSON, -- {"yield": 85, "potency": 92, "growth_speed": 78, "disease_resistance": 88}
    breeding_potential INT DEFAULT 100, -- Decreases with each breeding
    max_supply INT DEFAULT 1000,
    current_supply INT DEFAULT 0,
    mint_cost INT NOT NULL DEFAULT 500, -- Tokens required to mint
    image_url VARCHAR(500),
    metadata JSON, -- Additional NFT metadata
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (strain_id) REFERENCES strains(id) ON DELETE CASCADE
);

-- User-owned genetics NFTs
CREATE TABLE IF NOT EXISTS user_genetics_nfts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    genetics_nft_id INT NOT NULL,
    token_id VARCHAR(100) UNIQUE NOT NULL, -- Unique NFT identifier
    breeding_count INT DEFAULT 0, -- How many times used for breeding
    parent_plant_ids JSON, -- Plant IDs used to create this NFT
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (genetics_nft_id) REFERENCES genetics_nfts(id) ON DELETE CASCADE
);

-- NFT marketplace for trading
CREATE TABLE IF NOT EXISTS nft_marketplace (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_user_id INT NOT NULL,
    buyer_user_id INT NULL,
    user_nft_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'sold', 'cancelled', 'expired') DEFAULT 'active',
    listed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sold_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (seller_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_nft_id) REFERENCES user_genetics_nfts(id) ON DELETE CASCADE
);

-- NFT breeding records
CREATE TABLE IF NOT EXISTS nft_breeding_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_nft_id INT NULL, -- Resulting NFT (if successful)
    parent_nft_ids JSON NOT NULL, -- Array of parent NFT IDs
    breeding_success BOOLEAN DEFAULT FALSE,
    traits_inherited JSON, -- Traits passed to offspring
    breeding_cost DECIMAL(8,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_nft_id) REFERENCES user_genetics_nfts(id) ON DELETE SET NULL
);

-- Insert sample genetics NFTs
INSERT INTO genetics_nfts (strain_id, genetics_name, description, rarity_level, generation_number, genetic_traits, mint_cost, max_supply) VALUES
(1, 'OG Kush Genesis', 'Original OG Kush genetics with perfect lineage', 'legendary', 1, 
 '{"yield": 95, "potency": 98, "growth_speed": 85, "disease_resistance": 92, "flavor_profile": 96}', 2000, 100),

(2, 'Blue Dream Prime', 'Premium Blue Dream genetics with enhanced traits', 'epic', 1,
 '{"yield": 88, "potency": 90, "growth_speed": 92, "disease_resistance": 85, "flavor_profile": 89}', 1000, 250),

(3, 'White Widow Classic', 'Classic White Widow genetics from original Dutch stock', 'rare', 1,
 '{"yield": 82, "potency": 85, "growth_speed": 78, "disease_resistance": 88, "flavor_profile": 83}', 500, 500),

(4, 'Green Crack Energy', 'High-energy Green Crack genetics with speed boost', 'rare', 1,
 '{"yield": 75, "potency": 82, "growth_speed": 95, "disease_resistance": 80, "flavor_profile": 85}', 400, 400),

(5, 'Purple Haze Mystic', 'Mystical Purple Haze genetics with unique coloration', 'epic', 1,
 '{"yield": 80, "potency": 88, "growth_speed": 75, "disease_resistance": 90, "flavor_profile": 94}', 800, 200),

(6, 'Sour Diesel Turbo', 'Turbocharged Sour Diesel with enhanced yield', 'uncommon', 1,
 '{"yield": 90, "potency": 78, "growth_speed": 88, "disease_resistance": 82, "flavor_profile": 80}', 250, 750),

(7, 'Northern Lights Aurora', 'Aurora-enhanced Northern Lights with stability', 'rare', 1,
 '{"yield": 85, "potency": 87, "growth_speed": 80, "disease_resistance": 95, "flavor_profile": 88}', 600, 300),

(8, 'AK-47 Legendary', 'Legendary AK-47 genetics with maximum potency', 'legendary', 1,
 '{"yield": 88, "potency": 99, "growth_speed": 82, "disease_resistance": 89, "flavor_profile": 91}', 2500, 50);

-- Create indexes for performance
CREATE INDEX idx_genetics_nfts_rarity ON genetics_nfts(rarity_level);
CREATE INDEX idx_genetics_nfts_strain ON genetics_nfts(strain_id);
CREATE INDEX idx_user_genetics_nfts_user ON user_genetics_nfts(user_id);
CREATE INDEX idx_user_genetics_nfts_genetics ON user_genetics_nfts(genetics_nft_id);
CREATE INDEX idx_nft_marketplace_status ON nft_marketplace(status);
CREATE INDEX idx_nft_marketplace_expires ON nft_marketplace(expires_at);
CREATE INDEX idx_nft_breeding_records_success ON nft_breeding_records(breeding_success);

-- Create stored procedure for NFT breeding
DELIMITER //
CREATE PROCEDURE BreedGeneticsNfts(
    IN p_user_id INT,
    IN p_parent_nft_id1 INT,
    IN p_parent_nft_id2 INT,
    OUT p_success BOOLEAN,
    OUT p_offspring_nft_id INT
)
BEGIN
    DECLARE v_breeding_cost DECIMAL(8,2) DEFAULT 200.00;
    DECLARE v_player_tokens DECIMAL(10,2);
    DECLARE v_player_id INT;
    DECLARE v_success_rate DECIMAL(3,2) DEFAULT 0.70;
    DECLARE v_random_roll DECIMAL(3,2);
    DECLARE v_parent1_traits JSON;
    DECLARE v_parent2_traits JSON;
    DECLARE v_offspring_traits JSON;
    DECLARE v_offspring_rarity VARCHAR(20);
    
    -- Get player details
    SELECT id, tokens INTO v_player_id, v_player_tokens
    FROM game_players WHERE user_id = p_user_id;
    
    -- Check if user has enough tokens
    IF v_player_tokens < v_breeding_cost THEN
        SET p_success = FALSE;
        SET p_offspring_nft_id = NULL;
        SELECT 'Insufficient tokens' as error_message;
    ELSE
        -- Get parent traits
        SELECT gn.genetic_traits INTO v_parent1_traits
        FROM user_genetics_nfts ugn
        JOIN genetics_nfts gn ON ugn.genetics_nft_id = gn.id
        WHERE ugn.id = p_parent_nft_id1 AND ugn.user_id = p_user_id;
        
        SELECT gn.genetic_traits INTO v_parent2_traits
        FROM user_genetics_nfts ugn
        JOIN genetics_nfts gn ON ugn.genetics_nft_id = gn.id
        WHERE ugn.id = p_parent_nft_id2 AND ugn.user_id = p_user_id;
        
        -- Calculate breeding success
        SET v_random_roll = RAND();
        SET p_success = v_random_roll <= v_success_rate;
        
        -- Deduct breeding cost
        UPDATE game_players 
        SET tokens = tokens - v_breeding_cost 
        WHERE id = v_player_id;
        
        -- Create breeding record
        INSERT INTO nft_breeding_records 
        (parent_nft_ids, breeding_success, breeding_cost)
        VALUES (JSON_ARRAY(p_parent_nft_id1, p_parent_nft_id2), p_success, v_breeding_cost);
        
        -- If successful, create offspring NFT
        IF p_success THEN
            -- Combine traits (simplified logic)
            SET v_offspring_traits = JSON_OBJECT(
                'yield', (JSON_EXTRACT(v_parent1_traits, '$.yield') + JSON_EXTRACT(v_parent2_traits, '$.yield')) / 2,
                'potency', (JSON_EXTRACT(v_parent1_traits, '$.potency') + JSON_EXTRACT(v_parent2_traits, '$.potency')) / 2,
                'growth_speed', (JSON_EXTRACT(v_parent1_traits, '$.growth_speed') + JSON_EXTRACT(v_parent2_traits, '$.growth_speed')) / 2,
                'disease_resistance', (JSON_EXTRACT(v_parent1_traits, '$.disease_resistance') + JSON_EXTRACT(v_parent2_traits, '$.disease_resistance')) / 2
            );
            
            -- Determine rarity based on trait average
            SET v_offspring_rarity = CASE 
                WHEN (JSON_EXTRACT(v_offspring_traits, '$.yield') + JSON_EXTRACT(v_offspring_traits, '$.potency') + 
                      JSON_EXTRACT(v_offspring_traits, '$.growth_speed') + JSON_EXTRACT(v_offspring_traits, '$.disease_resistance')) / 4 >= 90 
                THEN 'legendary'
                WHEN (JSON_EXTRACT(v_offspring_traits, '$.yield') + JSON_EXTRACT(v_offspring_traits, '$.potency') + 
                      JSON_EXTRACT(v_offspring_traits, '$.growth_speed') + JSON_EXTRACT(v_offspring_traits, '$.disease_resistance')) / 4 >= 80 
                THEN 'epic'
                WHEN (JSON_EXTRACT(v_offspring_traits, '$.yield') + JSON_EXTRACT(v_offspring_traits, '$.potency') + 
                      JSON_EXTRACT(v_offspring_traits, '$.growth_speed') + JSON_EXTRACT(v_offspring_traits, '$.disease_resistance')) / 4 >= 70 
                THEN 'rare'
                ELSE 'uncommon'
            END;
            
            -- Create new genetics NFT
            INSERT INTO genetics_nfts 
            (strain_id, genetics_name, description, rarity_level, generation_number, genetic_traits, mint_cost, max_supply)
            SELECT 1, CONCAT('Hybrid Gen-', UNIX_TIMESTAMP()), 'Player-bred hybrid genetics', 
                   v_offspring_rarity, 2, v_offspring_traits, 100, 1;
            
            SET @new_genetics_id = LAST_INSERT_ID();
            
            -- Mint NFT to user
            INSERT INTO user_genetics_nfts 
            (user_id, genetics_nft_id, token_id)
            VALUES (p_user_id, @new_genetics_id, CONCAT('BRED-', UPPER(HEX(RANDOM_BYTES(8)))));
            
            SET p_offspring_nft_id = LAST_INSERT_ID();
            
            -- Update breeding record with offspring
            UPDATE nft_breeding_records 
            SET user_nft_id = p_offspring_nft_id,
                traits_inherited = v_offspring_traits
            WHERE id = LAST_INSERT_ID();
            
        ELSE
            SET p_offspring_nft_id = NULL;
        END IF;
        
        -- Increment breeding count for parents
        UPDATE user_genetics_nfts 
        SET breeding_count = breeding_count + 1 
        WHERE id IN (p_parent_nft_id1, p_parent_nft_id2);
    END IF;
END //
DELIMITER ;

-- Create view for NFT collection value
CREATE VIEW nft_collection_value AS
SELECT 
    u.id as user_id,
    u.username,
    COUNT(ugn.id) as total_nfts,
    SUM(CASE WHEN gn.rarity_level = 'legendary' THEN 1 ELSE 0 END) as legendary_count,
    SUM(CASE WHEN gn.rarity_level = 'epic' THEN 1 ELSE 0 END) as epic_count,
    SUM(CASE WHEN gn.rarity_level = 'rare' THEN 1 ELSE 0 END) as rare_count,
    SUM(CASE WHEN gn.rarity_level = 'uncommon' THEN 1 ELSE 0 END) as uncommon_count,
    SUM(CASE WHEN gn.rarity_level = 'common' THEN 1 ELSE 0 END) as common_count,
    SUM(ugn.breeding_count) as total_breeding_uses,
    -- Estimated collection value
    SUM(CASE 
        WHEN gn.rarity_level = 'legendary' THEN 2000
        WHEN gn.rarity_level = 'epic' THEN 800
        WHEN gn.rarity_level = 'rare' THEN 400
        WHEN gn.rarity_level = 'uncommon' THEN 150
        ELSE 50
    END) as estimated_value
FROM users u
LEFT JOIN user_genetics_nfts ugn ON u.id = ugn.user_id
LEFT JOIN genetics_nfts gn ON ugn.genetics_nft_id = gn.id
GROUP BY u.id, u.username
HAVING total_nfts > 0
ORDER BY estimated_value DESC;

-- Create view for marketplace analytics
CREATE VIEW nft_marketplace_analytics AS
SELECT 
    gn.rarity_level,
    COUNT(nm.id) as listings_count,
    AVG(nm.price) as avg_price,
    MIN(nm.price) as min_price,
    MAX(nm.price) as max_price,
    COUNT(CASE WHEN nm.status = 'sold' THEN 1 END) as sold_count,
    COUNT(CASE WHEN nm.status = 'active' THEN 1 END) as active_listings
FROM nft_marketplace nm
JOIN user_genetics_nfts ugn ON nm.user_nft_id = ugn.id
JOIN genetics_nfts gn ON ugn.genetics_nft_id = gn.id
WHERE nm.listed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY gn.rarity_level
ORDER BY avg_price DESC;
