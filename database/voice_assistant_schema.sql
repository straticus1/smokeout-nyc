-- Voice Assistant Database Schema
-- Enables voice commands and speech interaction for the game

-- Voice command history tracking
CREATE TABLE IF NOT EXISTS voice_command_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    command_text TEXT NOT NULL,
    parsed_action VARCHAR(100),
    success BOOLEAN DEFAULT FALSE,
    confidence_score DECIMAL(3,2) DEFAULT 0.00,
    response_text TEXT,
    execution_time_ms INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User voice profiles for personalized recognition
CREATE TABLE IF NOT EXISTS voice_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    training_data JSON, -- Voice samples and training phrases
    accuracy_score DECIMAL(3,2) DEFAULT 0.00,
    language_preference VARCHAR(10) DEFAULT 'en-US',
    voice_characteristics JSON, -- Pitch, tone, accent markers
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Voice command templates and patterns
CREATE TABLE IF NOT EXISTS voice_command_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    command_name VARCHAR(100) NOT NULL,
    command_pattern VARCHAR(500) NOT NULL, -- Regex pattern for matching
    action_type VARCHAR(50) NOT NULL,
    parameters JSON, -- Expected parameters and types
    response_template TEXT,
    usage_count INT DEFAULT 0,
    success_rate DECIMAL(3,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Voice interaction sessions
CREATE TABLE IF NOT EXISTS voice_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(100) UNIQUE NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    total_commands INT DEFAULT 0,
    successful_commands INT DEFAULT 0,
    session_data JSON, -- Context and state information
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add voice_settings column to users table if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS voice_settings JSON DEFAULT '{}';

-- Insert default voice command templates
INSERT INTO voice_command_templates (command_name, command_pattern, action_type, parameters, response_template) VALUES
('water_plants', 'water\\s+(my\\s+)?plants?', 'game_action', '{}', 'Watering your plants now!'),
('harvest_plants', 'harvest\\s+(my\\s+)?plants?', 'game_action', '{}', 'Harvesting ready plants!'),
('plant_seeds', 'plant\\s+(\\w+)\\s*seeds?', 'game_action', '{"strain": "string"}', 'Planting {strain} seeds!'),
('check_plants', 'check\\s+(my\\s+)?(plants?|garden)', 'status_check', '{}', 'Checking your plant status...'),
('check_stats', 'check\\s+(my\\s+)?(stats?|status|profile)', 'status_check', '{}', 'Here are your current stats...'),
('check_balance', 'how\\s+much\\s+(money|cash|tokens?)', 'status_check', '{}', 'Checking your balance...'),
('sell_product', 'sell\\s+(all\\s+)?(\\w+)', 'market_action', '{"product": "string", "sell_all": "boolean"}', 'Selling {product}...'),
('buy_item', 'buy\\s+(\\d+)?\\s*(\\w+)', 'market_action', '{"item": "string", "quantity": "number"}', 'Purchasing {item}...'),
('navigate', 'go\\s+to\\s+(\\w+)', 'navigation', '{"location": "string"}', 'Going to {location}...'),
('help', 'help|what\\s+can\\s+i\\s+(do|say)', 'help', '{}', 'Here are the available commands...'),
('list_commands', 'list\\s+commands', 'help', '{}', 'Here are all available voice commands...');

-- Create indexes for performance
CREATE INDEX idx_voice_command_history_user ON voice_command_history(user_id);
CREATE INDEX idx_voice_command_history_created ON voice_command_history(created_at);
CREATE INDEX idx_voice_command_history_success ON voice_command_history(success);
CREATE INDEX idx_voice_profiles_user ON voice_profiles(user_id);
CREATE INDEX idx_voice_command_templates_active ON voice_command_templates(is_active);
CREATE INDEX idx_voice_sessions_user ON voice_sessions(user_id);
CREATE INDEX idx_voice_sessions_token ON voice_sessions(session_token);

-- Create stored procedure for command analytics
DELIMITER //
CREATE PROCEDURE GetVoiceCommandAnalytics(
    IN p_user_id INT,
    IN p_days_back INT DEFAULT 30
)
BEGIN
    SELECT 
        parsed_action,
        COUNT(*) as total_uses,
        SUM(CASE WHEN success = TRUE THEN 1 ELSE 0 END) as successful_uses,
        ROUND(AVG(confidence_score), 2) as avg_confidence,
        ROUND(AVG(execution_time_ms), 2) as avg_execution_time,
        DATE(created_at) as usage_date
    FROM voice_command_history 
    WHERE user_id = p_user_id 
    AND created_at >= DATE_SUB(NOW(), INTERVAL p_days_back DAY)
    GROUP BY parsed_action, DATE(created_at)
    ORDER BY usage_date DESC, total_uses DESC;
END //
DELIMITER ;

-- Create stored procedure for voice training progress
DELIMITER //
CREATE PROCEDURE UpdateVoiceTrainingProgress(
    IN p_user_id INT,
    IN p_command_success BOOLEAN,
    IN p_confidence_score DECIMAL(3,2)
)
BEGIN
    DECLARE v_current_accuracy DECIMAL(3,2);
    DECLARE v_total_commands INT;
    DECLARE v_successful_commands INT;
    
    -- Get current stats
    SELECT 
        COALESCE(accuracy_score, 0.00),
        (SELECT COUNT(*) FROM voice_command_history WHERE user_id = p_user_id),
        (SELECT COUNT(*) FROM voice_command_history WHERE user_id = p_user_id AND success = TRUE)
    INTO v_current_accuracy, v_total_commands, v_successful_commands;
    
    -- Calculate new accuracy
    IF p_command_success THEN
        SET v_successful_commands = v_successful_commands + 1;
    END IF;
    SET v_total_commands = v_total_commands + 1;
    
    -- Update voice profile accuracy
    INSERT INTO voice_profiles (user_id, accuracy_score)
    VALUES (p_user_id, v_successful_commands / v_total_commands)
    ON DUPLICATE KEY UPDATE 
    accuracy_score = v_successful_commands / v_total_commands,
    updated_at = NOW();
    
    -- Update command template success rates
    UPDATE voice_command_templates vct
    SET usage_count = usage_count + 1,
        success_rate = (
            SELECT AVG(CASE WHEN success = TRUE THEN 1.0 ELSE 0.0 END)
            FROM voice_command_history 
            WHERE parsed_action = vct.action_type
        )
    WHERE EXISTS (
        SELECT 1 FROM voice_command_history 
        WHERE user_id = p_user_id 
        AND parsed_action = vct.action_type
        ORDER BY created_at DESC LIMIT 1
    );
END //
DELIMITER ;

-- Create view for voice usage statistics
CREATE VIEW voice_usage_stats AS
SELECT 
    u.id as user_id,
    u.username,
    COUNT(vch.id) as total_commands,
    SUM(CASE WHEN vch.success = TRUE THEN 1 ELSE 0 END) as successful_commands,
    ROUND(AVG(vch.confidence_score), 2) as avg_confidence,
    COALESCE(vp.accuracy_score, 0.00) as voice_accuracy,
    vp.language_preference,
    DATE(MAX(vch.created_at)) as last_voice_command
FROM users u
LEFT JOIN voice_command_history vch ON u.id = vch.user_id
LEFT JOIN voice_profiles vp ON u.id = vp.user_id
WHERE vch.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY u.id, u.username, vp.accuracy_score, vp.language_preference
HAVING total_commands > 0
ORDER BY total_commands DESC;

-- Create view for popular voice commands
CREATE VIEW popular_voice_commands AS
SELECT 
    vct.command_name,
    vct.action_type,
    vct.usage_count,
    vct.success_rate,
    COUNT(vch.id) as recent_uses,
    ROUND(AVG(vch.confidence_score), 2) as avg_recent_confidence
FROM voice_command_templates vct
LEFT JOIN voice_command_history vch ON vct.action_type = vch.parsed_action
    AND vch.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
WHERE vct.is_active = TRUE
GROUP BY vct.id, vct.command_name, vct.action_type, vct.usage_count, vct.success_rate
ORDER BY recent_uses DESC, vct.usage_count DESC;
