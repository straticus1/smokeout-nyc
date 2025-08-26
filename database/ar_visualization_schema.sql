-- AR Visualization Database Schema
-- Enables phone camera AR plant visualization and room environments

-- AR plant models for 3D visualization
CREATE TABLE IF NOT EXISTS ar_plant_models (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plant_id INT NOT NULL,
    model_url VARCHAR(500) NOT NULL, -- URL to 3D model file (.usdz, .glb)
    texture_url VARCHAR(500), -- Texture/material file URL
    animation_data JSON, -- Animation configurations
    scale_factor DECIMAL(4,2) DEFAULT 1.00,
    growth_stage_models JSON, -- Different models for each growth stage
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_plant_model (plant_id)
);

-- AR room environments for VIP rooms
CREATE TABLE IF NOT EXISTS ar_room_environments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vip_room_id INT NULL, -- NULL for free environments
    environment_name VARCHAR(100) NOT NULL,
    description TEXT,
    environment_model_url VARCHAR(500) NOT NULL,
    lighting_config JSON, -- Lighting setup for AR
    interactive_elements JSON, -- Clickable/interactive objects
    complexity_level INT DEFAULT 1, -- 1-5 complexity rating
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vip_room_id) REFERENCES vip_rooms(id) ON DELETE SET NULL
);

-- User AR room customizations
CREATE TABLE IF NOT EXISTS ar_room_customizations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    customization_data JSON, -- User's custom layout and decorations
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES ar_room_environments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_room (user_id, room_id)
);

-- AR session tracking
CREATE TABLE IF NOT EXISTS ar_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plant_id INT NULL,
    room_id INT NULL,
    session_type ENUM('plant_visualization', 'room_tour', 'tutorial', 'social_share') NOT NULL,
    session_data JSON, -- Camera positions, interactions, screenshots
    duration_seconds INT DEFAULT 0,
    status ENUM('active', 'completed', 'abandoned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES ar_room_environments(id) ON DELETE SET NULL
);

-- AR models library (plants, decorations, effects)
CREATE TABLE IF NOT EXISTS ar_models (
    id INT PRIMARY KEY AUTO_INCREMENT,
    model_name VARCHAR(100) NOT NULL,
    model_type ENUM('plant', 'decoration', 'effect', 'room_element') NOT NULL,
    model_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500),
    model_data JSON, -- Technical specifications
    animation_config JSON, -- Animation parameters
    rarity_level ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    unlock_cost INT DEFAULT 0, -- Tokens required to unlock
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User unlocked AR models
CREATE TABLE IF NOT EXISTS user_ar_models (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    model_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES ar_models(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_model (user_id, model_id)
);

-- Add AR interaction tracking to plants table
ALTER TABLE plants ADD COLUMN IF NOT EXISTS ar_interactions INT DEFAULT 0;
ALTER TABLE plants ADD COLUMN IF NOT EXISTS last_ar_session TIMESTAMP NULL;

-- Insert AR room environments
INSERT INTO ar_room_environments (vip_room_id, environment_name, description, environment_model_url, lighting_config, interactive_elements, complexity_level) VALUES
(NULL, 'Basic Grow Tent', 'Simple tent environment for beginners', '/ar/environments/basic_tent.usdz', 
 '{"ambient_light": 0.3, "directional_light": 0.7, "color_temperature": 6500}',
 '{"water_station": {"position": [1, 0, 0], "action": "water_plants"}, "nutrient_shelf": {"position": [-1, 0.5, 0], "action": "add_nutrients"}}', 1),

(1, 'Greenhouse Elite AR', 'Premium greenhouse with climate controls', '/ar/environments/greenhouse_elite.usdz',
 '{"ambient_light": 0.4, "directional_light": 0.8, "color_temperature": 5500, "dynamic_shadows": true}',
 '{"climate_control": {"position": [0, 2, -2], "action": "adjust_climate"}, "automated_watering": {"position": [2, 0, 1], "action": "toggle_irrigation"}}', 2),

(2, 'Hydroponic Paradise AR', 'Advanced hydroponic facility', '/ar/environments/hydro_paradise.usdz',
 '{"ambient_light": 0.5, "directional_light": 0.9, "color_temperature": 4000, "dynamic_shadows": true, "reflection_probes": true}',
 '{"nutrient_reservoir": {"position": [0, -0.5, 2], "action": "check_nutrients"}, "ph_monitor": {"position": [1.5, 1, 0], "action": "check_ph"}, "pump_controls": {"position": [-1.5, 0.5, 1], "action": "control_pumps"}}', 3),

(3, 'Laboratory Complex AR', 'Scientific research facility', '/ar/environments/lab_complex.usdz',
 '{"ambient_light": 0.6, "directional_light": 1.0, "color_temperature": 6000, "dynamic_shadows": true, "reflection_probes": true, "volumetric_lighting": true}',
 '{"microscope_station": {"position": [2, 1, -1], "action": "analyze_samples"}, "genetic_sequencer": {"position": [-2, 0.8, 0], "action": "sequence_dna"}, "climate_chamber": {"position": [0, 0, 3], "action": "control_environment"}}', 4),

(4, 'Penthouse Garden AR', 'Luxury rooftop facility', '/ar/environments/penthouse_garden.usdz',
 '{"ambient_light": 0.7, "directional_light": 1.0, "color_temperature": 5000, "dynamic_shadows": true, "reflection_probes": true, "volumetric_lighting": true, "skybox": "city_skyline"}',
 '{"infinity_pool": {"position": [5, 0, 0], "action": "relax"}, "champagne_bar": {"position": [-3, 1, -2], "action": "celebrate"}, "helicopter_pad": {"position": [0, 0, 8], "action": "travel"}}', 5);

-- Insert AR models
INSERT INTO ar_models (model_name, model_type, model_url, thumbnail_url, model_data, animation_config, rarity_level, unlock_cost) VALUES
('Cannabis Seedling', 'plant', '/ar/models/cannabis_seedling.usdz', '/ar/thumbnails/seedling.jpg',
 '{"vertices": 1200, "textures": 2, "file_size_mb": 1.2}',
 '{"idle": {"duration": 3, "loop": true}, "growth": {"duration": 10, "loop": false}}', 'common', 0),

('Cannabis Vegetative', 'plant', '/ar/models/cannabis_vegetative.usdz', '/ar/thumbnails/vegetative.jpg',
 '{"vertices": 3500, "textures": 4, "file_size_mb": 2.8}',
 '{"idle": {"duration": 4, "loop": true}, "sway": {"duration": 2, "loop": true}, "growth": {"duration": 15, "loop": false}}', 'common', 0),

('Cannabis Flowering', 'plant', '/ar/models/cannabis_flowering.usdz', '/ar/thumbnails/flowering.jpg',
 '{"vertices": 5200, "textures": 6, "file_size_mb": 4.1}',
 '{"idle": {"duration": 5, "loop": true}, "sway": {"duration": 3, "loop": true}, "sparkle": {"duration": 1, "loop": true}}', 'rare', 50),

('Cannabis Mature', 'plant', '/ar/models/cannabis_mature.usdz', '/ar/thumbnails/mature.jpg',
 '{"vertices": 7800, "textures": 8, "file_size_mb": 6.5}',
 '{"idle": {"duration": 6, "loop": true}, "sway": {"duration": 4, "loop": true}, "harvest_glow": {"duration": 2, "loop": true}}', 'rare', 75),

('Golden Cannabis', 'plant', '/ar/models/golden_cannabis.usdz', '/ar/thumbnails/golden.jpg',
 '{"vertices": 9500, "textures": 12, "file_size_mb": 8.9, "special_effects": ["golden_particles", "shimmer"]}',
 '{"idle": {"duration": 8, "loop": true}, "golden_sway": {"duration": 5, "loop": true}, "treasure_sparkle": {"duration": 3, "loop": true}}', 'legendary', 500),

('Holographic Plant Scanner', 'decoration', '/ar/models/holo_scanner.usdz', '/ar/thumbnails/holo_scanner.jpg',
 '{"vertices": 2100, "textures": 3, "file_size_mb": 1.8, "interactive": true}',
 '{"scan_animation": {"duration": 2, "loop": false}, "data_display": {"duration": 5, "loop": true}}', 'epic', 200),

('Floating Nutrient Dispenser', 'decoration', '/ar/models/nutrient_dispenser.usdz', '/ar/thumbnails/nutrient_dispenser.jpg',
 '{"vertices": 1800, "textures": 4, "file_size_mb": 2.1, "interactive": true}',
 '{"float": {"duration": 10, "loop": true}, "dispense": {"duration": 1, "loop": false}}', 'rare', 100),

('Rainbow Growth Aura', 'effect', '/ar/models/rainbow_aura.usdz', '/ar/thumbnails/rainbow_aura.jpg',
 '{"vertices": 800, "textures": 6, "file_size_mb": 1.5, "particle_system": true}',
 '{"color_cycle": {"duration": 8, "loop": true}, "pulse": {"duration": 2, "loop": true}}', 'epic', 300),

('Dragon Smoke Effect', 'effect', '/ar/models/dragon_smoke.usdz', '/ar/thumbnails/dragon_smoke.jpg',
 '{"vertices": 1200, "textures": 8, "file_size_mb": 3.2, "particle_system": true}',
 '{"smoke_spiral": {"duration": 12, "loop": true}, "dragon_breath": {"duration": 3, "loop": false}}', 'legendary', 750);

-- Create indexes for performance
CREATE INDEX idx_ar_plant_models_plant ON ar_plant_models(plant_id);
CREATE INDEX idx_ar_sessions_user_type ON ar_sessions(user_id, session_type);
CREATE INDEX idx_ar_sessions_created ON ar_sessions(created_at);
CREATE INDEX idx_user_ar_models_user ON user_ar_models(user_id);
CREATE INDEX idx_ar_models_type_rarity ON ar_models(model_type, rarity_level);

-- Create stored procedure for AR session analytics
DELIMITER //
CREATE PROCEDURE GetArAnalytics(IN p_user_id INT, IN p_days INT DEFAULT 30)
BEGIN
    SELECT 
        session_type,
        COUNT(*) as session_count,
        AVG(duration_seconds) as avg_duration,
        SUM(duration_seconds) as total_duration,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
        COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned_sessions
    FROM ar_sessions
    WHERE user_id = p_user_id
    AND created_at >= DATE_SUB(NOW(), INTERVAL p_days DAY)
    GROUP BY session_type
    ORDER BY session_count DESC;
END //
DELIMITER ;

-- Create stored procedure for plant AR interaction tracking
DELIMITER //
CREATE PROCEDURE TrackPlantArInteraction(IN p_plant_id INT, IN p_interaction_type VARCHAR(50))
BEGIN
    UPDATE plants 
    SET ar_interactions = ar_interactions + 1,
        last_ar_session = NOW()
    WHERE id = p_plant_id;
    
    -- Bonus XP for AR interactions
    UPDATE game_players gp
    JOIN plants p ON gp.id = p.player_id
    SET gp.experience_points = gp.experience_points + 5
    WHERE p.id = p_plant_id;
    
    SELECT 'AR interaction tracked' as message, 
           (SELECT ar_interactions FROM plants WHERE id = p_plant_id) as total_interactions;
END //
DELIMITER ;

-- Create view for AR engagement metrics
CREATE VIEW ar_engagement_metrics AS
SELECT 
    u.id as user_id,
    u.username,
    COUNT(DISTINCT ars.id) as total_ar_sessions,
    SUM(ars.duration_seconds) as total_ar_time,
    AVG(ars.duration_seconds) as avg_session_duration,
    COUNT(DISTINCT ars.plant_id) as plants_visualized,
    COUNT(DISTINCT ars.room_id) as rooms_explored,
    COUNT(DISTINCT uam.model_id) as ar_models_unlocked,
    MAX(ars.created_at) as last_ar_session
FROM users u
LEFT JOIN ar_sessions ars ON u.id = ars.user_id
LEFT JOIN user_ar_models uam ON u.id = uam.user_id
WHERE ars.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY u.id, u.username
HAVING total_ar_sessions > 0
ORDER BY total_ar_time DESC;
