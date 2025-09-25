-- Cannabis Policy Tracking Enhancement for SmokeoutNYC Political System
-- Adds constraints and tracking for cannabis-friendly vs non-friendly candidates

-- Add cannabis policy fields to politicians table
ALTER TABLE politicians 
ADD COLUMN cannabis_stance ENUM('pro_cannabis', 'anti_cannabis', 'neutral', 'unknown') DEFAULT 'unknown' AFTER party,
ADD COLUMN cannabis_score INT DEFAULT NULL COMMENT 'Score from 0-100, 100 being most cannabis-friendly' AFTER cannabis_stance,
ADD COLUMN last_policy_update TIMESTAMP NULL AFTER cannabis_score,
ADD COLUMN policy_updated_by INT NULL AFTER last_policy_update,
ADD INDEX idx_cannabis_stance (cannabis_stance),
ADD INDEX idx_cannabis_score (cannabis_score),
ADD FOREIGN KEY fk_policy_updater (policy_updated_by) REFERENCES users(id);

-- Cannabis policy positions tracking
CREATE TABLE IF NOT EXISTS cannabis_policy_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    position_type ENUM('legalization', 'decriminalization', 'medical_only', 'expungement', 'taxation', 'licensing', 'social_equity', 'home_cultivation', 'public_consumption') NOT NULL,
    stance ENUM('strongly_support', 'support', 'neutral', 'oppose', 'strongly_oppose', 'unknown') NOT NULL DEFAULT 'unknown',
    confidence_level ENUM('confirmed', 'likely', 'rumored', 'assumed') DEFAULT 'assumed',
    source_type ENUM('voting_record', 'public_statement', 'campaign_platform', 'endorsement', 'news_report', 'interview', 'survey_response') NOT NULL,
    source_url VARCHAR(500),
    source_date DATE,
    notes TEXT,
    verified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_politician_position (politician_id, position_type),
    INDEX idx_stance (stance),
    INDEX idx_confidence (confidence_level),
    INDEX idx_source (source_type),
    UNIQUE KEY uk_politician_position (politician_id, position_type)
);

-- Cannabis legislation votes tracking
CREATE TABLE IF NOT EXISTS cannabis_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    legislation_name VARCHAR(255) NOT NULL,
    bill_number VARCHAR(100),
    vote ENUM('yes', 'no', 'abstain', 'absent', 'present') NOT NULL,
    vote_date DATE NOT NULL,
    office_level ENUM('federal', 'state', 'county', 'city', 'local') NOT NULL,
    chamber ENUM('senate', 'house', 'assembly', 'council', 'commission') DEFAULT NULL,
    legislation_type ENUM('legalization', 'decriminalization', 'medical', 'taxation', 'licensing', 'expungement', 'social_equity', 'hemp', 'research') NOT NULL,
    cannabis_impact ENUM('very_positive', 'positive', 'neutral', 'negative', 'very_negative') NOT NULL,
    source_url VARCHAR(500),
    notes TEXT,
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_politician_vote (politician_id, vote_date),
    INDEX idx_legislation (legislation_type, cannabis_impact),
    INDEX idx_vote_office (office_level, chamber),
    INDEX idx_verified (verified)
);

-- Cannabis endorsements and ratings from organizations
CREATE TABLE IF NOT EXISTS cannabis_endorsements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    organization_name VARCHAR(255) NOT NULL,
    organization_type ENUM('advocacy_group', 'industry_association', 'pac', 'union', 'publication', 'rating_agency') NOT NULL,
    endorsement_type ENUM('endorsement', 'rating', 'scorecard', 'recommendation') NOT NULL,
    score VARCHAR(50), -- Could be letter grade, percentage, or numeric score
    max_score VARCHAR(50),
    endorsement_date DATE,
    election_cycle VARCHAR(10), -- e.g., "2024", "2022"
    source_url VARCHAR(500),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    INDEX idx_politician_org (politician_id, organization_name),
    INDEX idx_organization (organization_name, endorsement_type),
    INDEX idx_election_cycle (election_cycle)
);

-- Cannabis policy statements and quotes
CREATE TABLE IF NOT EXISTS cannabis_statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    statement_text TEXT NOT NULL,
    statement_date DATE,
    context VARCHAR(255), -- e.g., "Campaign Rally", "Committee Hearing", "Press Release"
    stance_extracted ENUM('pro_cannabis', 'anti_cannabis', 'neutral') DEFAULT 'neutral',
    statement_type ENUM('policy_position', 'campaign_promise', 'public_comment', 'interview_response', 'social_media') NOT NULL,
    source_url VARCHAR(500),
    source_publication VARCHAR(255),
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    flagged_for_review BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_politician_date (politician_id, statement_date),
    INDEX idx_stance (stance_extracted),
    INDEX idx_type (statement_type),
    INDEX idx_verification (verified, flagged_for_review),
    FULLTEXT KEY ft_statement (statement_text)
);

-- Cannabis policy change history
CREATE TABLE IF NOT EXISTS cannabis_policy_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    politician_id INT NOT NULL,
    field_changed VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    change_reason TEXT,
    changed_by INT NOT NULL,
    change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (politician_id) REFERENCES politicians(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX idx_politician_date (politician_id, change_date),
    INDEX idx_changed_by (changed_by)
);

-- Donation constraints based on cannabis stance
CREATE TABLE IF NOT EXISTS donation_constraints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    constraint_type ENUM('cannabis_stance_filter', 'minimum_score', 'blacklist', 'whitelist', 'auto_approval') NOT NULL,
    constraint_value JSON NOT NULL, -- Flexible constraint parameters
    constraint_description TEXT,
    applies_to ENUM('all_users', 'specific_users', 'user_groups') DEFAULT 'all_users',
    target_users JSON, -- User IDs if applies_to is not 'all_users'
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_constraint_type (constraint_type),
    INDEX idx_active (is_active)
);

-- User preferences for cannabis-friendly donations
CREATE TABLE IF NOT EXISTS user_cannabis_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    only_cannabis_friendly BOOLEAN DEFAULT FALSE,
    minimum_cannabis_score INT DEFAULT NULL,
    blocked_politicians JSON, -- Array of politician IDs to block
    preferred_policy_positions JSON, -- Preferred stances on specific issues
    auto_donate_to_cannabis_friendly BOOLEAN DEFAULT FALSE,
    auto_donate_amount DECIMAL(10,2) DEFAULT NULL,
    notification_preferences JSON, -- When to notify about candidate policy changes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_cannabis_friendly (only_cannabis_friendly),
    INDEX idx_minimum_score (minimum_cannabis_score)
);

-- Views for easy querying
CREATE OR REPLACE VIEW cannabis_friendly_politicians AS
SELECT 
    p.*,
    COALESCE(p.cannabis_score, 0) as effective_score,
    COUNT(DISTINCT cp.id) as policy_positions_count,
    COUNT(DISTINCT cv.id) as votes_count,
    COUNT(DISTINCT ce.id) as endorsements_count,
    AVG(CASE cv.cannabis_impact 
        WHEN 'very_positive' THEN 5
        WHEN 'positive' THEN 4
        WHEN 'neutral' THEN 3
        WHEN 'negative' THEN 2
        WHEN 'very_negative' THEN 1
    END) as avg_vote_impact
FROM politicians p
LEFT JOIN cannabis_policy_positions cp ON p.id = cp.politician_id
LEFT JOIN cannabis_votes cv ON p.id = cv.politician_id
LEFT JOIN cannabis_endorsements ce ON p.id = ce.politician_id
WHERE p.status = 'active'
GROUP BY p.id;

CREATE OR REPLACE VIEW donation_eligibility AS
SELECT 
    p.id,
    p.name,
    p.cannabis_stance,
    p.cannabis_score,
    CASE 
        WHEN p.cannabis_stance = 'pro_cannabis' THEN 'eligible'
        WHEN p.cannabis_stance = 'anti_cannabis' THEN 'restricted'
        WHEN p.cannabis_score >= 70 THEN 'eligible'
        WHEN p.cannabis_score <= 30 THEN 'restricted'
        ELSE 'review_required'
    END as donation_eligibility,
    p.status
FROM politicians p
WHERE p.status = 'active';

-- Insert some example constraint records
INSERT INTO donation_constraints (constraint_type, constraint_value, constraint_description, created_by) VALUES
('cannabis_stance_filter', '{"blocked_stances": ["anti_cannabis"], "preferred_stances": ["pro_cannabis"]}', 'Block donations to anti-cannabis politicians, prefer pro-cannabis', 1),
('minimum_score', '{"minimum_score": 60, "applies_to_unknown": false}', 'Require minimum cannabis-friendliness score of 60', 1),
('auto_approval', '{"cannabis_score_threshold": 80, "auto_approve_amount_limit": 500}', 'Auto-approve donations under $500 to politicians with 80+ cannabis score', 1);