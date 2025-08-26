-- Legal Network Database Schema
-- AI-powered attorney matching and legal services for cannabis businesses

-- Legal firms directory
CREATE TABLE IF NOT EXISTS legal_firms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    website_url VARCHAR(300),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(2),
    zip_code VARCHAR(10),
    size ENUM('solo', 'small', 'medium', 'large') DEFAULT 'small',
    established_year INT,
    cannabis_focus BOOLEAN DEFAULT FALSE,
    rating DECIMAL(2,1) DEFAULT 0.0,
    total_attorneys INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Legal attorneys directory with AI scoring capabilities
CREATE TABLE IF NOT EXISTS legal_attorneys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firm_id INT NULL,
    name VARCHAR(150) NOT NULL,
    title VARCHAR(100),
    bio TEXT,
    photo_url VARCHAR(500),
    email VARCHAR(100),
    phone VARCHAR(20),
    states_licensed JSON NOT NULL, -- Array of state codes where licensed
    bar_numbers JSON, -- Bar admission numbers by state
    specialties JSON NOT NULL, -- Legal specialties (cannabis, licensing, compliance, etc.)
    cannabis_experience_years INT DEFAULT 0,
    total_experience_years INT DEFAULT 0,
    education JSON, -- Law school, degrees, certifications
    hourly_rate DECIMAL(8,2) DEFAULT 0.00,
    consultation_fee DECIMAL(8,2) DEFAULT 0.00,
    success_rate DECIMAL(5,2) DEFAULT 0.00, -- Percentage of successful cases
    client_rating DECIMAL(2,1) DEFAULT 0.0, -- Average client rating 1-5
    response_time_hours INT DEFAULT 48, -- Average response time
    availability_status ENUM('available', 'busy', 'unavailable') DEFAULT 'available',
    languages_spoken JSON, -- Languages attorney speaks
    case_types_handled JSON, -- Types of cases handled
    notable_cases TEXT, -- Description of notable cases
    awards_recognition TEXT, -- Awards and recognition
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (firm_id) REFERENCES legal_firms(id) ON DELETE SET NULL
);

-- AI case analysis results
CREATE TABLE IF NOT EXISTS ai_case_analyses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    case_description TEXT NOT NULL,
    case_type VARCHAR(100),
    state VARCHAR(2),
    business_type VARCHAR(100),
    analysis_results JSON NOT NULL, -- AI analysis output
    confidence_score DECIMAL(3,2) DEFAULT 0.00,
    estimated_cost_min DECIMAL(10,2),
    estimated_cost_max DECIMAL(10,2),
    complexity_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    success_probability INT DEFAULT 50, -- Percentage
    recommended_attorney_ids JSON, -- AI recommended attorneys
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Legal consultations and appointments
CREATE TABLE IF NOT EXISTS legal_consultations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    attorney_id INT NOT NULL,
    consultation_type ENUM('initial', 'follow_up', 'document_review', 'case_strategy', 'compliance_check') DEFAULT 'initial',
    case_description TEXT,
    preferred_date DATE,
    preferred_time TIME,
    scheduled_datetime TIMESTAMP NULL,
    duration_minutes INT DEFAULT 60,
    consultation_fee DECIMAL(8,2) DEFAULT 0.00,
    contact_method ENUM('phone', 'video', 'in_person', 'email') DEFAULT 'phone',
    status ENUM('requested', 'scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'requested',
    attorney_notes TEXT,
    client_notes TEXT,
    follow_up_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (attorney_id) REFERENCES legal_attorneys(id) ON DELETE CASCADE
);

-- Attorney reviews and ratings
CREATE TABLE IF NOT EXISTS attorney_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    attorney_id INT NOT NULL,
    consultation_id INT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    case_type VARCHAR(100),
    case_outcome ENUM('successful', 'partially_successful', 'unsuccessful', 'ongoing') NULL,
    would_recommend BOOLEAN DEFAULT TRUE,
    communication_rating INT CHECK (communication_rating >= 1 AND communication_rating <= 5),
    expertise_rating INT CHECK (expertise_rating >= 1 AND expertise_rating <= 5),
    value_rating INT CHECK (value_rating >= 1 AND value_rating <= 5),
    is_verified BOOLEAN DEFAULT FALSE, -- Verified by actual consultation
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (attorney_id) REFERENCES legal_attorneys(id) ON DELETE CASCADE,
    FOREIGN KEY (consultation_id) REFERENCES legal_consultations(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_attorney_review (user_id, attorney_id, consultation_id)
);

-- Legal case outcomes for AI learning
CREATE TABLE IF NOT EXISTS legal_case_outcomes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attorney_id INT NOT NULL,
    case_type VARCHAR(100) NOT NULL,
    case_description TEXT,
    client_business_type VARCHAR(100),
    state VARCHAR(2),
    case_start_date DATE,
    resolution_date DATE,
    outcome_rating INT CHECK (outcome_rating >= 1 AND outcome_rating <= 5), -- 1=poor, 5=excellent
    outcome_description TEXT,
    settlement_amount DECIMAL(12,2) NULL,
    legal_fees DECIMAL(10,2),
    case_duration_days INT,
    complexity_level ENUM('low', 'medium', 'high'),
    challenges_faced TEXT,
    lessons_learned TEXT,
    is_public BOOLEAN DEFAULT FALSE, -- Can be used for marketing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attorney_id) REFERENCES legal_attorneys(id) ON DELETE CASCADE
);

-- AI usage tracking for billing and analytics
CREATE TABLE IF NOT EXISTS ai_usage_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    feature_type ENUM('attorney_matching', 'case_analysis', 'outcome_prediction', 'cost_estimation') NOT NULL,
    metadata JSON, -- Request parameters and results
    processing_time_ms INT DEFAULT 0,
    confidence_score DECIMAL(3,2),
    tokens_used INT DEFAULT 0, -- For AI model usage tracking
    cost_cents INT DEFAULT 0, -- Cost in cents for this AI operation
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Legal document templates and resources
CREATE TABLE IF NOT EXISTS legal_document_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    document_type ENUM('contract', 'license_application', 'compliance_form', 'operating_agreement', 'employment', 'other') NOT NULL,
    state_specific VARCHAR(2) NULL, -- NULL means applicable to all states
    business_types JSON, -- Which business types this applies to
    template_content TEXT, -- Template with placeholders
    required_fields JSON, -- Fields that must be filled
    attorney_id INT NULL, -- Attorney who created/verified this template
    is_premium BOOLEAN DEFAULT FALSE, -- Premium members only
    download_count INT DEFAULT 0,
    rating DECIMAL(2,1) DEFAULT 0.0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (attorney_id) REFERENCES legal_attorneys(id) ON DELETE SET NULL
);

-- Legal compliance alerts and updates
CREATE TABLE IF NOT EXISTS legal_compliance_updates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(300) NOT NULL,
    description TEXT NOT NULL,
    update_type ENUM('new_law', 'regulation_change', 'court_decision', 'enforcement_action', 'deadline_reminder') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    states_affected JSON, -- Array of state codes
    business_types_affected JSON, -- Which business types this affects
    effective_date DATE,
    deadline_date DATE NULL,
    source_url VARCHAR(500),
    attorney_analysis TEXT, -- Expert analysis of the update
    action_required TEXT, -- What businesses should do
    is_published BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User subscriptions to legal updates
CREATE TABLE IF NOT EXISTS legal_update_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    states JSON, -- States user wants updates for
    business_types JSON, -- Business types user is interested in
    update_types JSON, -- Types of updates to receive
    notification_method ENUM('email', 'sms', 'in_app', 'all') DEFAULT 'email',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample legal firms
INSERT INTO legal_firms (name, description, phone, email, city, state, size, cannabis_focus, rating) VALUES
('Cannabis Legal Partners', 'Full-service cannabis law firm specializing in licensing, compliance, and business formation', '1-800-CANNABIS', 'info@cannabislegal.com', 'Los Angeles', 'CA', 'medium', TRUE, 4.8),
('Green Law Group', 'Boutique firm focused on cannabis regulatory compliance and litigation', '1-855-GREEN-LAW', 'contact@greenlawgroup.com', 'Denver', 'CO', 'small', TRUE, 4.6),
('Hemp & Associates', 'Comprehensive legal services for hemp and cannabis businesses nationwide', '1-888-HEMP-LAW', 'lawyers@hempassociates.com', 'Seattle', 'WA', 'large', TRUE, 4.7),
('Budlaw Professional Corporation', 'Cannabis business law, real estate, and intellectual property', '1-877-BUD-LAW', 'info@budlaw.com', 'Portland', 'OR', 'medium', TRUE, 4.5),
('Marijuana Legal Defense', 'Criminal defense and regulatory compliance for cannabis industry', '1-800-MJ-DEFENSE', 'defense@mjlegal.com', 'Las Vegas', 'NV', 'small', TRUE, 4.4);

-- Insert sample attorneys
INSERT INTO legal_attorneys (firm_id, name, title, states_licensed, specialties, cannabis_experience_years, total_experience_years, hourly_rate, consultation_fee, success_rate, client_rating) VALUES
(1, 'Sarah Chen', 'Senior Partner', '["CA", "NV", "AZ"]', '["cannabis", "licensing", "compliance", "business_formation"]', 8, 15, 450.00, 200.00, 92.5, 4.9),
(1, 'Michael Rodriguez', 'Associate Attorney', '["CA", "CO"]', '["cannabis", "employment", "contracts", "litigation"]', 5, 8, 350.00, 150.00, 88.0, 4.7),
(2, 'Jennifer Walsh', 'Founding Partner', '["CO", "WY", "NM"]', '["cannabis", "regulatory", "compliance", "criminal_defense"]', 10, 18, 425.00, 175.00, 94.0, 4.8),
(2, 'David Kim', 'Cannabis Attorney', '["CO", "UT"]', '["cannabis", "licensing", "zoning", "real_estate"]', 6, 10, 375.00, 125.00, 90.5, 4.6),
(3, 'Amanda Foster', 'Partner', '["WA", "OR", "CA", "NV"]', '["cannabis", "intellectual_property", "trademarks", "business"]', 7, 12, 500.00, 250.00, 91.0, 4.8),
(3, 'Robert Thompson', 'Senior Associate', '["WA", "ID", "MT"]', '["cannabis", "compliance", "regulatory", "litigation"]', 4, 9, 400.00, 150.00, 87.5, 4.5),
(4, 'Lisa Martinez', 'Managing Partner', '["OR", "WA", "CA"]', '["cannabis", "business_formation", "contracts", "real_estate"]', 9, 16, 475.00, 200.00, 93.5, 4.9),
(4, 'James Wilson', 'Cannabis Counsel', '["OR", "NV"]', '["cannabis", "licensing", "compliance", "employment"]', 3, 7, 325.00, 100.00, 85.0, 4.4),
(5, 'Maria Gonzalez', 'Senior Attorney', '["NV", "CA", "AZ"]', '["cannabis", "criminal_defense", "regulatory", "compliance"]', 11, 20, 550.00, 300.00, 95.0, 4.9),
(5, 'Thomas Lee', 'Associate', '["NV", "UT"]', '["cannabis", "business", "contracts", "licensing"]', 2, 5, 275.00, 75.00, 82.0, 4.2);

-- Insert sample legal document templates
INSERT INTO legal_document_templates (name, description, document_type, state_specific, business_types, is_premium) VALUES
('Cannabis Business Operating Agreement', 'Comprehensive operating agreement template for cannabis LLCs', 'operating_agreement', NULL, '["cultivation", "manufacturing", "dispensary"]', TRUE),
('Dispensary License Application Checklist', 'Complete checklist and forms for dispensary license applications', 'license_application', 'CA', '["dispensary"]', FALSE),
('Cannabis Employment Agreement', 'Employment contract template with cannabis-specific clauses', 'employment', NULL, '["cultivation", "manufacturing", "dispensary", "delivery"]', TRUE),
('Compliance Audit Checklist', 'Monthly compliance audit checklist for cannabis businesses', 'compliance_form', NULL, '["cultivation", "manufacturing", "dispensary"]', FALSE),
('Cannabis Supply Agreement', 'Template for wholesale cannabis supply contracts', 'contract', NULL, '["cultivation", "manufacturing", "dispensary"]', TRUE);

-- Create indexes for performance
CREATE INDEX idx_legal_attorneys_states ON legal_attorneys((CAST(states_licensed AS CHAR(1000))));
CREATE INDEX idx_legal_attorneys_specialties ON legal_attorneys((CAST(specialties AS CHAR(1000))));
CREATE INDEX idx_legal_attorneys_rating ON legal_attorneys(client_rating DESC);
CREATE INDEX idx_legal_attorneys_cannabis_exp ON legal_attorneys(cannabis_experience_years DESC);
CREATE INDEX idx_legal_attorneys_active ON legal_attorneys(is_active);
CREATE INDEX idx_legal_firms_state ON legal_firms(state);
CREATE INDEX idx_legal_firms_cannabis ON legal_firms(cannabis_focus);
CREATE INDEX idx_ai_case_analyses_user ON ai_case_analyses(user_id);
CREATE INDEX idx_ai_case_analyses_created ON ai_case_analyses(created_at);
CREATE INDEX idx_legal_consultations_user ON legal_consultations(user_id);
CREATE INDEX idx_legal_consultations_attorney ON legal_consultations(attorney_id);
CREATE INDEX idx_legal_consultations_status ON legal_consultations(status);
CREATE INDEX idx_attorney_reviews_attorney ON attorney_reviews(attorney_id);
CREATE INDEX idx_attorney_reviews_rating ON attorney_reviews(rating);
CREATE INDEX idx_legal_case_outcomes_attorney ON legal_case_outcomes(attorney_id);
CREATE INDEX idx_legal_case_outcomes_type ON legal_case_outcomes(case_type);
CREATE INDEX idx_ai_usage_logs_user ON ai_usage_logs(user_id);
CREATE INDEX idx_ai_usage_logs_feature ON ai_usage_logs(feature_type);
CREATE INDEX idx_legal_compliance_updates_states ON legal_compliance_updates((CAST(states_affected AS CHAR(1000))));
CREATE INDEX idx_legal_compliance_updates_severity ON legal_compliance_updates(severity);

-- Create stored procedure for AI attorney matching score calculation
DELIMITER //
CREATE PROCEDURE CalculateAttorneyAIScore(
    IN p_attorney_id INT,
    IN p_case_type VARCHAR(100),
    IN p_state VARCHAR(2),
    IN p_urgency VARCHAR(20),
    OUT p_ai_score DECIMAL(5,2)
)
BEGIN
    DECLARE v_specialty_match INT DEFAULT 0;
    DECLARE v_success_rate DECIMAL(5,2) DEFAULT 0;
    DECLARE v_client_rating DECIMAL(2,1) DEFAULT 0;
    DECLARE v_cannabis_exp INT DEFAULT 0;
    DECLARE v_availability_bonus INT DEFAULT 0;
    DECLARE v_state_licensed INT DEFAULT 0;
    
    -- Get attorney details
    SELECT 
        success_rate,
        client_rating,
        cannabis_experience_years,
        CASE WHEN availability_status = 'available' THEN 1 ELSE 0 END,
        CASE WHEN JSON_CONTAINS(states_licensed, JSON_QUOTE(p_state)) THEN 1 ELSE 0 END,
        CASE WHEN JSON_CONTAINS(specialties, JSON_QUOTE(p_case_type)) THEN 1 ELSE 0 END
    INTO v_success_rate, v_client_rating, v_cannabis_exp, v_availability_bonus, v_state_licensed, v_specialty_match
    FROM legal_attorneys 
    WHERE id = p_attorney_id;
    
    -- Calculate AI score (out of 100)
    SET p_ai_score = (
        (v_specialty_match * 40) +  -- 40% for case type match
        (v_success_rate * 0.25) +   -- 25% for success rate
        (v_client_rating * 4) +     -- 20% for client rating (4 * 5 = 20)
        (LEAST(v_cannabis_exp, 10) * 1) + -- 10% for cannabis experience (max 10 years)
        (v_availability_bonus * 5)  -- 5% for availability
    ) * v_state_licensed; -- Zero out if not licensed in state
    
    -- Urgency bonus
    IF p_urgency = 'urgent' AND v_availability_bonus = 1 THEN
        SET p_ai_score = p_ai_score + 5;
    END IF;
END //
DELIMITER ;

-- Create stored procedure for updating attorney ratings
DELIMITER //
CREATE PROCEDURE UpdateAttorneyRatings(IN p_attorney_id INT)
BEGIN
    DECLARE v_avg_rating DECIMAL(2,1);
    DECLARE v_success_count INT;
    DECLARE v_total_cases INT;
    DECLARE v_success_rate DECIMAL(5,2);
    
    -- Update client rating from reviews
    SELECT AVG(rating) INTO v_avg_rating
    FROM attorney_reviews 
    WHERE attorney_id = p_attorney_id AND is_public = TRUE;
    
    -- Update success rate from case outcomes
    SELECT 
        COUNT(CASE WHEN outcome_rating >= 4 THEN 1 END),
        COUNT(*),
        CASE WHEN COUNT(*) > 0 THEN 
            (COUNT(CASE WHEN outcome_rating >= 4 THEN 1 END) / COUNT(*)) * 100 
        ELSE 0 END
    INTO v_success_count, v_total_cases, v_success_rate
    FROM legal_case_outcomes 
    WHERE attorney_id = p_attorney_id;
    
    -- Update attorney record
    UPDATE legal_attorneys 
    SET 
        client_rating = COALESCE(v_avg_rating, client_rating),
        success_rate = CASE WHEN v_total_cases >= 5 THEN v_success_rate ELSE success_rate END,
        updated_at = NOW()
    WHERE id = p_attorney_id;
END //
DELIMITER ;

-- Create view for attorney search with AI scoring
CREATE VIEW attorney_search_view AS
SELECT 
    a.id,
    a.name,
    a.title,
    a.states_licensed,
    a.specialties,
    a.cannabis_experience_years,
    a.hourly_rate,
    a.consultation_fee,
    a.success_rate,
    a.client_rating,
    a.availability_status,
    f.name as firm_name,
    f.size as firm_size,
    f.city,
    f.state,
    (SELECT COUNT(*) FROM attorney_reviews WHERE attorney_id = a.id) as review_count,
    (SELECT AVG(rating) FROM attorney_reviews WHERE attorney_id = a.id) as avg_review_rating
FROM legal_attorneys a
LEFT JOIN legal_firms f ON a.firm_id = f.id
WHERE a.is_active = TRUE;

-- Create view for legal compliance dashboard
CREATE VIEW legal_compliance_dashboard AS
SELECT 
    lcu.id,
    lcu.title,
    lcu.update_type,
    lcu.severity,
    lcu.states_affected,
    lcu.business_types_affected,
    lcu.effective_date,
    lcu.deadline_date,
    lcu.created_at,
    CASE 
        WHEN lcu.deadline_date IS NOT NULL AND lcu.deadline_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
        THEN TRUE ELSE FALSE 
    END as urgent_deadline
FROM legal_compliance_updates lcu
WHERE lcu.is_published = TRUE
ORDER BY lcu.severity DESC, lcu.effective_date DESC;
