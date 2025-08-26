-- Political Memes XYZ Database Schema
-- MySQL/MariaDB compatible

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    credits INT DEFAULT 0,
    zip_code VARCHAR(10),
    city VARCHAR(100),
    state VARCHAR(50),
    country VARCHAR(50) DEFAULT 'US',
    oauth_provider VARCHAR(50),
    oauth_id VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'suspended', 'deleted') DEFAULT 'active'
);

-- Politicians table
CREATE TABLE politicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    position VARCHAR(255) NOT NULL,
    party VARCHAR(100),
    city VARCHAR(100),
    state VARCHAR(50),
    county VARCHAR(100),
    zip_code VARCHAR(10),
    district VARCHAR(50),
    office_level ENUM('federal', 'state', 'county', 'city', 'local') NOT NULL,
    photo_url VARCHAR(500),
    bio TEXT,
    website_url VARCHAR(500),
    social_media JSON,
    verification_status ENUM('verified', 'pending', 'unverified') DEFAULT 'unverified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_location (state, city, county, zip_code),
    INDEX idx_office (office_level, district),
    INDEX idx_status (status, verification_status)
);

-- Politician votes table
CREATE TABLE politician_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    politician_id INT NOT NULL,
    vote_type ENUM('upvote', 'downvote') NOT NULL,
    credits_spent INT DEFAULT 2,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_politician_vote (user_id, politician_id)
);

-- Policies table
CREATE TABLE policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    policy_type VARCHAR(100),
    status ENUM('proposed', 'active', 'passed', 'failed', 'withdrawn') DEFAULT 'proposed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    INDEX idx_politician_policy (politician_id, status)
);

-- Policy votes table
CREATE TABLE policy_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    policy_id INT NOT NULL,
    vote_type ENUM('support', 'oppose') NOT NULL,
    credits_spent INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_policy_vote (user_id, policy_id)
);

-- Comments table
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    politician_id INT NOT NULL,
    parent_comment_id INT NULL,
    comment_text TEXT NOT NULL,
    is_priority BOOLEAN DEFAULT FALSE,
    credits_spent INT DEFAULT 0,
    upvotes INT DEFAULT 0,
    downvotes INT DEFAULT 0,
    status ENUM('active', 'flagged', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_politician_comments (politician_id, status, created_at),
    INDEX idx_priority_comments (politician_id, is_priority, created_at)
);

-- Comment votes table
CREATE TABLE comment_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    vote_type ENUM('upvote', 'downvote') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_comment_vote (user_id, comment_id)
);

-- Messages table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_politician_id INT NOT NULL,
    subject VARCHAR(500) NOT NULL,
    message_text TEXT NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    status ENUM('sent', 'read', 'replied', 'archived') DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    INDEX idx_politician_messages (to_politician_id, status, created_at)
);

-- Elections table
CREATE TABLE elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(500) NOT NULL,
    election_type ENUM('federal', 'state', 'county', 'city', 'local') NOT NULL,
    jurisdiction VARCHAR(255) NOT NULL,
    election_date DATE NOT NULL,
    registration_deadline DATE,
    description TEXT,
    status ENUM('upcoming', 'active', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_election_date (election_date, status),
    INDEX idx_jurisdiction (jurisdiction, election_type)
);

-- Election candidates table
CREATE TABLE election_candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    politician_id INT NOT NULL,
    position VARCHAR(255) NOT NULL,
    party VARCHAR(100),
    ballot_order INT,
    status ENUM('active', 'withdrawn', 'disqualified') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    UNIQUE KEY unique_election_politician (election_id, politician_id)
);

-- Credit transactions table
CREATE TABLE credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('purchase', 'spend', 'refund', 'bonus') NOT NULL,
    credits_amount INT NOT NULL,
    cost_usd DECIMAL(10,2) NULL,
    description VARCHAR(500),
    reference_type ENUM('vote', 'comment', 'message', 'purchase') NULL,
    reference_id INT NULL,
    payment_method VARCHAR(50) NULL,
    payment_reference VARCHAR(255) NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_transactions (user_id, created_at),
    INDEX idx_transaction_type (transaction_type, status)
);

-- User sessions table (for OAuth2 and session management)
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    refresh_token VARCHAR(255) UNIQUE NULL,
    expires_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_user_sessions (user_id, expires_at)
);

-- Audit log table
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id, created_at),
    INDEX idx_audit_action (action, table_name, created_at)
);

-- Featured content table (for meme of the day, etc.)
CREATE TABLE featured_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('politician', 'policy', 'comment', 'meme') NOT NULL,
    content_id INT NOT NULL,
    feature_type ENUM('meme_of_day', 'trending', 'featured') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_featured_active (feature_type, start_date, end_date),
    INDEX idx_content_featured (content_type, content_id)
);

-- User follows table
CREATE TABLE user_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    politician_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_follow (user_id, politician_id)
);

-- Create indexes for performance
CREATE INDEX idx_politicians_name ON politicians(name);
CREATE INDEX idx_politicians_slug ON politicians(slug);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_votes_politician ON politician_votes(politician_id);
CREATE INDEX idx_votes_user ON politician_votes(user_id);
CREATE INDEX idx_comments_politician ON comments(politician_id);
CREATE INDEX idx_comments_user ON comments(user_id);

-- Create triggers for updating vote counts
DELIMITER //

CREATE TRIGGER update_politician_vote_counts
AFTER INSERT ON politician_votes
FOR EACH ROW
BEGIN
    -- This would be handled by the application layer for better control
    -- But keeping here for reference
END //

CREATE TRIGGER update_comment_vote_counts
AFTER INSERT ON comment_votes
FOR EACH ROW
BEGIN
    IF NEW.vote_type = 'upvote' THEN
        UPDATE comments SET upvotes = upvotes + 1 WHERE id = NEW.comment_id;
    ELSE
        UPDATE comments SET downvotes = downvotes + 1 WHERE id = NEW.comment_id;
    END IF;
END //

CREATE TRIGGER update_user_credits_on_transaction
AFTER UPDATE ON credit_transactions
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        IF NEW.transaction_type = 'purchase' THEN
            UPDATE users SET credits = credits + NEW.credits_amount WHERE id = NEW.user_id;
        ELSEIF NEW.transaction_type = 'spend' THEN
            UPDATE users SET credits = credits - NEW.credits_amount WHERE id = NEW.user_id;
        ELSEIF NEW.transaction_type = 'refund' THEN
            UPDATE users SET credits = credits + NEW.credits_amount WHERE id = NEW.user_id;
        END IF;
    END IF;
END //

DELIMITER ;

-- Insert some sample data
INSERT INTO politicians (name, slug, position, party, city, state, office_level, verification_status) VALUES
('Alexandria Rodriguez', 'alexandria-rodriguez', 'State Senator, District 15', 'Democratic', 'New York', 'NY', 'state', 'verified'),
('Michael Thompson', 'michael-thompson', 'Mayor', 'Independent', 'Austin', 'TX', 'city', 'verified'),
('Sarah Chen', 'sarah-chen', 'House Representative, District 8', 'Republican', 'Phoenix', 'AZ', 'federal', 'verified'),
('David Martinez', 'david-martinez', 'County Commissioner', 'Democratic', 'Miami', 'FL', 'county', 'verified');

-- Insert sample policies
INSERT INTO policies (politician_id, title, description, policy_type, status) VALUES
(1, 'Green Energy Initiative', 'Proposal to increase renewable energy funding by 40% over the next 5 years', 'Environmental', 'proposed'),
(1, 'Education Reform Act', 'Comprehensive reform of public education funding and curriculum standards', 'Education', 'active'),
(2, 'Downtown Revitalization Project', 'Multi-million dollar project to revitalize downtown Austin area', 'Economic Development', 'active'),
(3, 'Healthcare Access Expansion', 'Expanding healthcare access to underserved communities', 'Healthcare', 'proposed');

-- Insert sample elections
INSERT INTO elections (name, election_type, jurisdiction, election_date, status) VALUES
('2024 Presidential Election', 'federal', 'United States', '2024-11-05', 'upcoming'),
('New York State Senate Elections', 'state', 'New York', '2024-11-05', 'upcoming'),
('Austin Mayoral Election', 'city', 'Austin, TX', '2024-05-15', 'upcoming');

-- Insert election candidates
INSERT INTO election_candidates (election_id, politician_id, position, party) VALUES
(2, 1, 'State Senator District 15', 'Democratic'),
(3, 2, 'Mayor', 'Independent');

-- Donations table
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    politician_id INT NOT NULL,
    amount_usd DECIMAL(10,2) NOT NULL,
    processing_fee_percent DECIMAL(5,2) DEFAULT 3.00,
    processing_fee_amount DECIMAL(10,2) NOT NULL,
    net_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_reference VARCHAR(255),
    donor_name VARCHAR(255),
    donor_email VARCHAR(255),
    donor_address TEXT,
    is_anonymous BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'completed', 'failed', 'refunded', 'forwarded') DEFAULT 'pending',
    forwarded_at TIMESTAMP NULL,
    forwarded_reference VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    INDEX idx_politician_donations (politician_id, status, created_at),
    INDEX idx_user_donations (user_id, created_at),
    INDEX idx_donation_status (status, created_at)
);

-- Donation settings table
CREATE TABLE donation_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    donations_enabled BOOLEAN DEFAULT TRUE,
    min_donation_amount DECIMAL(10,2) DEFAULT 5.00,
    max_donation_amount DECIMAL(10,2) DEFAULT 2800.00,
    processing_fee_percent DECIMAL(5,2) DEFAULT 3.00,
    campaign_finance_id VARCHAR(255),
    campaign_contact_email VARCHAR(255),
    donation_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    UNIQUE KEY unique_politician_donation_settings (politician_id)
);

-- Politician messages to followers
CREATE TABLE politician_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    message_text TEXT NOT NULL,
    message_type ENUM('announcement', 'update', 'campaign', 'policy') DEFAULT 'announcement',
    is_priority BOOLEAN DEFAULT FALSE,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    status ENUM('draft', 'scheduled', 'sent', 'cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    INDEX idx_politician_messages_status (politician_id, status, scheduled_at)
);

-- Message recipients (for tracking who received what)
CREATE TABLE message_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES politician_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_message_recipient (message_id, user_id),
    INDEX idx_user_messages (user_id, read_at)
);

-- Real-time chat system
CREATE TABLE chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    room_type ENUM('public', 'politician', 'private', 'group') DEFAULT 'public',
    politician_id INT NULL,
    description TEXT,
    max_participants INT DEFAULT 100,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_room_type (room_type, is_active)
);

CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    message_text TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'system') DEFAULT 'text',
    reply_to_message_id INT NULL,
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_message_id) REFERENCES chat_messages(id) ON DELETE SET NULL,
    INDEX idx_room_messages (room_id, created_at),
    INDEX idx_user_messages_chat (user_id, created_at)
);

CREATE TABLE chat_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'moderator', 'admin') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_online BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_participant (room_id, user_id),
    INDEX idx_online_participants (room_id, is_online, last_seen_at)
);

-- User online status
CREATE TABLE user_online_status (
    user_id INT PRIMARY KEY,
    is_online BOOLEAN DEFAULT FALSE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    current_page VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_online_users (is_online, last_activity)
);

-- News system
CREATE TABLE news_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT,
    author VARCHAR(255),
    source VARCHAR(255) NOT NULL,
    source_url VARCHAR(500),
    image_url VARCHAR(500),
    article_type ENUM('internal', 'syndicated') NOT NULL,
    category VARCHAR(100),
    tags JSON,
    published_at TIMESTAMP NOT NULL,
    syndicated_at TIMESTAMP NULL,
    is_featured BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_news_published (status, published_at),
    INDEX idx_news_source (source, published_at),
    INDEX idx_news_category (category, published_at),
    FULLTEXT KEY idx_news_search (title, content, excerpt)
);

-- RSS feed sources
CREATE TABLE rss_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL UNIQUE,
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    last_fetched_at TIMESTAMP NULL,
    fetch_frequency_hours INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rss_active (is_active, last_fetched_at)
);

-- User avatars
CREATE TABLE user_avatars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    avatar_type ENUM('upload', 'generated', 'default') DEFAULT 'default',
    avatar_url VARCHAR(500),
    avatar_data LONGBLOB NULL,
    background_color VARCHAR(7) DEFAULT '#6366f1',
    text_color VARCHAR(7) DEFAULT '#ffffff',
    initials VARCHAR(3),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_avatar (user_id)
);

-- Politician image sources and metadata
CREATE TABLE politician_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    image_type ENUM('official', 'campaign', 'headshot', 'candid') DEFAULT 'official',
    source VARCHAR(255),
    source_url VARCHAR(500),
    copyright_info TEXT,
    is_primary BOOLEAN DEFAULT FALSE,
    image_data LONGBLOB NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    INDEX idx_politician_primary_image (politician_id, is_primary),
    INDEX idx_image_source (source, image_type)
);

-- Configuration table for system settings
CREATE TABLE system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    config_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
);

-- Insert default system configurations
INSERT INTO system_config (config_key, config_value, config_type, description) VALUES
('donation_processing_fee_percent', '3.00', 'number', 'Default processing fee percentage for donations'),
('donation_min_amount', '5.00', 'number', 'Minimum donation amount in USD'),
('donation_max_amount', '2800.00', 'number', 'Maximum donation amount in USD (FEC limit)'),
('chat_max_message_length', '500', 'number', 'Maximum characters allowed in chat messages'),
('online_timeout_minutes', '15', 'number', 'Minutes before user is considered offline'),
('rss_fetch_interval_hours', '1', 'number', 'How often to fetch RSS feeds'),
('max_avatar_size_mb', '5', 'number', 'Maximum avatar file size in MB');

-- Insert RSS sources
INSERT INTO rss_sources (name, url, category) VALUES
('Wall Street Journal - Politics', 'https://feeds.a.dj.com/rss/RSSPolitics.xml', 'politics'),
('CNBC - Politics', 'https://www.cnbc.com/id/10000113/device/rss/rss.html', 'politics'),
('New York Times - Politics', 'https://rss.nytimes.com/services/xml/rss/nyt/Politics.xml', 'politics'),
('Reuters - Politics', 'https://feeds.reuters.com/reuters/politicsNews', 'politics'),
('Associated Press - Politics', 'https://feeds.apnews.com/rss/apf-politics', 'politics'),
('Politico', 'https://www.politico.com/rss/politicopicks.xml', 'politics'),
('The Hill - News', 'https://thehill.com/news/feed/', 'politics'),
('CNN Politics', 'http://rss.cnn.com/rss/cnn_allpolitics.rss', 'politics');

-- Create default chat rooms
INSERT INTO chat_rooms (name, room_type, description, created_by) VALUES
('General Discussion', 'public', 'General political discussion for all users', NULL),
('Election 2024', 'public', 'Discussion about the upcoming 2024 elections', NULL),
('Policy Debates', 'public', 'Debate current policies and legislation', NULL),
('Local Politics', 'public', 'Discuss local political issues', NULL);

-- Add donation settings for sample politicians
INSERT INTO donation_settings (politician_id, donations_enabled, campaign_contact_email) VALUES
(1, TRUE, 'donations@alexandriarodriguez.com'),
(2, TRUE, 'finance@michaelthompson.com'),
(3, FALSE, NULL),
(4, TRUE, 'donations@davidmartinez.com');
