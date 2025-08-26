-- Multi-User Interface Database Schema
-- Enables personalized experiences for different user types

-- User profile types and preferences
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    profile_type ENUM('casual_gamer', 'business_owner', 'investor', 'family_member', 'collector', 'educator', 'social_user') NOT NULL,
    secondary_interests JSON, -- Additional interests beyond primary profile
    onboarding_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User interface configurations
CREATE TABLE IF NOT EXISTS user_interface_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    layout_type VARCHAR(50) DEFAULT 'default',
    widgets JSON, -- Array of enabled widgets
    theme VARCHAR(20) DEFAULT 'green',
    settings JSON, -- UI settings like sidebar collapsed, notifications, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Family connections for family-oriented users
CREATE TABLE IF NOT EXISTS family_connections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    connected_user_id INT NOT NULL,
    relationship VARCHAR(50) NOT NULL, -- spouse, parent, child, sibling, etc.
    permissions JSON, -- What family member can see/do
    status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (connected_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_family_connection (user_id, connected_user_id)
);

-- User groups for social features
CREATE TABLE IF NOT EXISTS user_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    group_type ENUM('family', 'business', 'investment', 'social', 'educational', 'general') DEFAULT 'general',
    privacy ENUM('public', 'private', 'invite_only') DEFAULT 'public',
    creator_id INT NOT NULL,
    member_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User group memberships
CREATE TABLE IF NOT EXISTS user_group_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'moderator', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_membership (group_id, user_id)
);

-- Social feed for community features
CREATE TABLE IF NOT EXISTS social_feed (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_type ENUM('achievement', 'harvest', 'trade', 'tip', 'question', 'general') DEFAULT 'general',
    content TEXT NOT NULL,
    media_urls JSON, -- Images, videos, etc.
    visibility ENUM('public', 'friends', 'family', 'group') DEFAULT 'public',
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User friendships
CREATE TABLE IF NOT EXISTS user_friends (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    friend_user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (user_id, friend_user_id)
);

-- User investments tracking (for investor profile type)
CREATE TABLE IF NOT EXISTS user_investments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    investment_type ENUM('stock', 'business', 'real_estate', 'crypto', 'other') NOT NULL,
    investment_name VARCHAR(100) NOT NULL,
    initial_investment DECIMAL(12,2) NOT NULL,
    current_value DECIMAL(12,2) NOT NULL,
    status ENUM('active', 'sold', 'closed') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Dashboard widget configurations
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    widget_name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    widget_type ENUM('chart', 'list', 'card', 'table', 'custom') DEFAULT 'card',
    default_settings JSON,
    required_permissions JSON, -- What permissions needed to use this widget
    profile_types JSON, -- Which profile types can use this widget
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User widget preferences
CREATE TABLE IF NOT EXISTS user_widget_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    widget_name VARCHAR(50) NOT NULL,
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    width INT DEFAULT 1,
    height INT DEFAULT 1,
    custom_settings JSON,
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_widget (user_id, widget_name)
);

-- Insert default dashboard widgets
INSERT INTO dashboard_widgets (widget_name, display_name, description, widget_type, profile_types) VALUES
('plant_status', 'Plant Status', 'Overview of your growing plants', 'card', '["casual_gamer", "business_owner", "family_member"]'),
('achievements', 'Achievements', 'Recent achievements and badges', 'list', '["casual_gamer", "social_user"]'),
('market_analytics', 'Market Analytics', 'Cannabis market data and trends', 'chart', '["business_owner", "investor"]'),
('portfolio_performance', 'Portfolio Performance', 'Investment portfolio tracking', 'chart', '["investor"]'),
('family_dashboard', 'Family Network', 'Family connections and shared insights', 'card', '["family_member"]'),
('nft_collection', 'NFT Collection', 'Your genetics NFT collection', 'card', '["collector"]'),
('social_feed', 'Social Feed', 'Community posts and updates', 'list', '["social_user"]'),
('educational_content', 'Educational Resources', 'Learning materials and guides', 'list', '["educator", "business_owner"]'),
('compliance_alerts', 'Compliance Alerts', 'Regulatory updates and alerts', 'list', '["business_owner"]'),
('financial_summary', 'Financial Summary', 'Business financial overview', 'card', '["business_owner"]'),
('leaderboard', 'Leaderboard', 'Top players and rankings', 'table', '["casual_gamer", "social_user"]'),
('daily_challenges', 'Daily Challenges', 'Today\'s challenges and tasks', 'list', '["casual_gamer"]'),
('market_trends', 'Market Trends', 'Industry trends and forecasts', 'chart', '["investor", "business_owner"]'),
('investment_opportunities', 'Investment Opportunities', 'New investment options', 'list', '["investor"]'),
('risk_analysis', 'Risk Analysis', 'Investment and business risk metrics', 'chart', '["investor", "business_owner"]'),
('genetics_library', 'Genetics Library', 'Available strain genetics', 'list', '["collector"]'),
('marketplace_activity', 'Marketplace Activity', 'Recent marketplace transactions', 'list', '["collector", "business_owner"]'),
('breeding_lab', 'Breeding Lab', 'Genetics breeding interface', 'custom', '["collector"]'),
('friend_activity', 'Friend Activity', 'What your friends are up to', 'list', '["social_user"]'),
('group_challenges', 'Group Challenges', 'Community group activities', 'list', '["social_user"]'),
('community_forums', 'Community Forums', 'Discussion forums access', 'list', '["social_user", "educator"]');

-- Create indexes for performance
CREATE INDEX idx_user_profiles_type ON user_profiles(profile_type);
CREATE INDEX idx_user_profiles_user ON user_profiles(user_id);
CREATE INDEX idx_user_interface_configs_user ON user_interface_configs(user_id);
CREATE INDEX idx_family_connections_user ON family_connections(user_id);
CREATE INDEX idx_family_connections_status ON family_connections(status);
CREATE INDEX idx_user_groups_type ON user_groups(group_type);
CREATE INDEX idx_user_groups_privacy ON user_groups(privacy);
CREATE INDEX idx_user_group_members_group ON user_group_members(group_id);
CREATE INDEX idx_user_group_members_user ON user_group_members(user_id);
CREATE INDEX idx_social_feed_user ON social_feed(user_id);
CREATE INDEX idx_social_feed_created ON social_feed(created_at);
CREATE INDEX idx_user_friends_user ON user_friends(user_id);
CREATE INDEX idx_user_friends_status ON user_friends(status);
CREATE INDEX idx_user_investments_user ON user_investments(user_id);
CREATE INDEX idx_user_investments_status ON user_investments(status);
CREATE INDEX idx_dashboard_widgets_active ON dashboard_widgets(is_active);
CREATE INDEX idx_user_widget_preferences_user ON user_widget_preferences(user_id);

-- Add preferences column to users table if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS preferences JSON DEFAULT '{}';

-- Create stored procedure for setting up new user profile
DELIMITER //
CREATE PROCEDURE SetupUserProfile(
    IN p_user_id INT,
    IN p_profile_type VARCHAR(50),
    IN p_secondary_interests JSON
)
BEGIN
    DECLARE v_widget_list JSON;
    
    -- Insert user profile
    INSERT INTO user_profiles (user_id, profile_type, secondary_interests)
    VALUES (p_user_id, p_profile_type, p_secondary_interests)
    ON DUPLICATE KEY UPDATE 
    profile_type = VALUES(profile_type),
    secondary_interests = VALUES(secondary_interests),
    updated_at = NOW();
    
    -- Get appropriate widgets for profile type
    SELECT JSON_ARRAYAGG(widget_name) INTO v_widget_list
    FROM dashboard_widgets 
    WHERE JSON_CONTAINS(profile_types, JSON_QUOTE(p_profile_type))
    AND is_active = TRUE;
    
    -- Set up default interface configuration
    INSERT INTO user_interface_configs (user_id, layout_type, widgets, theme)
    VALUES (p_user_id, CONCAT(p_profile_type, '_layout'), v_widget_list, 
            CASE p_profile_type
                WHEN 'casual_gamer' THEN 'green'
                WHEN 'business_owner' THEN 'blue'
                WHEN 'investor' THEN 'orange'
                WHEN 'family_member' THEN 'purple'
                WHEN 'collector' THEN 'pink'
                WHEN 'educator' THEN 'gray'
                WHEN 'social_user' THEN 'red'
                ELSE 'green'
            END)
    ON DUPLICATE KEY UPDATE 
    layout_type = VALUES(layout_type),
    widgets = VALUES(widgets),
    theme = VALUES(theme),
    updated_at = NOW();
END //
DELIMITER ;

-- Create view for user dashboard summary
CREATE VIEW user_dashboard_summary AS
SELECT 
    u.id as user_id,
    u.username,
    up.profile_type,
    up.secondary_interests,
    uic.layout_type,
    uic.theme,
    JSON_LENGTH(uic.widgets) as widget_count,
    (SELECT COUNT(*) FROM family_connections WHERE user_id = u.id AND status = 'accepted') as family_connections,
    (SELECT COUNT(*) FROM user_friends WHERE user_id = u.id AND status = 'accepted') as friend_count,
    (SELECT COUNT(*) FROM user_group_members WHERE user_id = u.id) as group_memberships,
    up.onboarding_completed
FROM users u
LEFT JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN user_interface_configs uic ON u.id = uic.user_id;

-- Create view for profile type analytics
CREATE VIEW profile_type_analytics AS
SELECT 
    profile_type,
    COUNT(*) as user_count,
    AVG(CASE WHEN onboarding_completed THEN 1 ELSE 0 END) as completion_rate,
    AVG(JSON_LENGTH(secondary_interests)) as avg_secondary_interests,
    COUNT(CASE WHEN up.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d
FROM user_profiles up
GROUP BY profile_type
ORDER BY user_count DESC;
