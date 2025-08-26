-- Insurance Marketplace Database Schema
-- Enables cannabis businesses to access specialized insurance products

-- Insurance providers directory
CREATE TABLE IF NOT EXISTS insurance_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    logo_url VARCHAR(500),
    website_url VARCHAR(300),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    states_covered JSON, -- Array of state codes
    insurance_types JSON, -- Types of insurance offered
    rating DECIMAL(2,1) DEFAULT 0.0, -- Provider rating out of 5.0
    customer_service_rating DECIMAL(2,1) DEFAULT 0.0,
    claims_processing_time INT DEFAULT 30, -- Average days to process claims
    years_in_business INT DEFAULT 0,
    cannabis_experience_years INT DEFAULT 0,
    license_numbers JSON, -- Insurance license numbers by state
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insurance products offered by providers
CREATE TABLE IF NOT EXISTS insurance_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    coverage_type ENUM('general_liability', 'product_liability', 'property', 'crop', 'cyber', 
                      'workers_comp', 'employment_practices', 'directors_officers', 'umbrella', 
                      'business_interruption', 'equipment', 'key_person') NOT NULL,
    base_premium DECIMAL(10,2) NOT NULL,
    min_coverage_amount DECIMAL(12,2) DEFAULT 100000,
    max_coverage_amount DECIMAL(12,2) DEFAULT 10000000,
    deductible_options JSON, -- Available deductible amounts
    features JSON, -- Product features and benefits
    eligible_business_types JSON, -- Which business types can purchase
    exclusions TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES insurance_providers(id) ON DELETE CASCADE
);

-- User insurance quote requests
CREATE TABLE IF NOT EXISTS insurance_quotes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    business_info JSON NOT NULL, -- Business details for underwriting
    coverage_details JSON NOT NULL, -- Requested coverage amounts and options
    estimated_premium DECIMAL(10,2),
    final_premium DECIMAL(10,2) NULL,
    quote_expires_at TIMESTAMP NULL,
    additional_info TEXT,
    status ENUM('pending', 'under_review', 'approved', 'declined', 'expired', 'purchased') DEFAULT 'pending',
    provider_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES insurance_products(id) ON DELETE CASCADE
);

-- Active insurance policies
CREATE TABLE IF NOT EXISTS insurance_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    quote_id INT NOT NULL,
    policy_number VARCHAR(50) UNIQUE NOT NULL,
    provider_id INT NOT NULL,
    product_id INT NOT NULL,
    premium_amount DECIMAL(10,2) NOT NULL,
    billing_frequency ENUM('monthly', 'quarterly', 'semi_annual', 'annual') DEFAULT 'monthly',
    effective_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    coverage_details JSON NOT NULL,
    policy_documents JSON, -- URLs to policy documents
    status ENUM('active', 'cancelled', 'expired', 'suspended') DEFAULT 'active',
    cancellation_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quote_id) REFERENCES insurance_quotes(id),
    FOREIGN KEY (provider_id) REFERENCES insurance_providers(id),
    FOREIGN KEY (product_id) REFERENCES insurance_products(id)
);

-- Insurance claims
CREATE TABLE IF NOT EXISTS insurance_claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    policy_id INT NOT NULL,
    claim_number VARCHAR(50) UNIQUE NOT NULL,
    claim_type ENUM('property_damage', 'product_liability', 'general_liability', 'theft', 
                   'crop_loss', 'equipment_breakdown', 'cyber_incident', 'employment', 'other') NOT NULL,
    incident_date DATE NOT NULL,
    reported_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT NOT NULL,
    estimated_loss DECIMAL(12,2) DEFAULT 0,
    actual_loss DECIMAL(12,2) NULL,
    settlement_amount DECIMAL(12,2) NULL,
    supporting_documents JSON, -- URLs to uploaded documents
    adjuster_assigned VARCHAR(100) NULL,
    adjuster_contact JSON, -- Phone, email of adjuster
    status ENUM('submitted', 'under_investigation', 'approved', 'denied', 'settled', 'closed') DEFAULT 'submitted',
    status_notes TEXT,
    settlement_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (policy_id) REFERENCES insurance_policies(id) ON DELETE CASCADE
);

-- Insurance consultation requests
CREATE TABLE IF NOT EXISTS insurance_consultations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    provider_id INT NOT NULL,
    consultation_type ENUM('general', 'quote_review', 'claim_assistance', 'policy_review', 'risk_assessment') DEFAULT 'general',
    preferred_date DATE,
    preferred_time TIME,
    contact_method ENUM('phone', 'video', 'in_person', 'email') DEFAULT 'phone',
    notes TEXT,
    scheduled_date TIMESTAMP NULL,
    consultant_assigned VARCHAR(100) NULL,
    status ENUM('requested', 'scheduled', 'completed', 'cancelled') DEFAULT 'requested',
    consultation_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES insurance_providers(id) ON DELETE CASCADE
);

-- Insurance notifications and communications
CREATE TABLE IF NOT EXISTS insurance_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    provider_id INT NULL,
    quote_id INT NULL,
    policy_id INT NULL,
    claim_id INT NULL,
    consultation_id INT NULL,
    notification_type ENUM('quote_request', 'quote_approved', 'quote_declined', 'policy_confirmation',
                          'policy_renewal', 'claim_filed', 'claim_update', 'payment_due', 'consultation_scheduled') NOT NULL,
    message TEXT,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (provider_id) REFERENCES insurance_providers(id) ON DELETE SET NULL,
    FOREIGN KEY (quote_id) REFERENCES insurance_quotes(id) ON DELETE SET NULL,
    FOREIGN KEY (policy_id) REFERENCES insurance_policies(id) ON DELETE SET NULL,
    FOREIGN KEY (claim_id) REFERENCES insurance_claims(id) ON DELETE SET NULL,
    FOREIGN KEY (consultation_id) REFERENCES insurance_consultations(id) ON DELETE SET NULL
);

-- Insert sample insurance providers
INSERT INTO insurance_providers (name, description, contact_phone, contact_email, states_covered, insurance_types, rating, cannabis_experience_years) VALUES
('Cannabis Guard Insurance', 'Specialized cannabis industry insurance with comprehensive coverage options', '1-800-CANNABIS', 'info@cannabisguard.com', 
 '["CA", "CO", "WA", "OR", "NV", "MA", "MI", "IL", "NY", "NJ"]', 
 '["general_liability", "product_liability", "property", "crop", "cyber"]', 4.8, 8),

('Green Shield Insurance', 'Full-service insurance for cannabis businesses nationwide', '1-855-GREEN-SHIELD', 'quotes@greenshield.com',
 '["CA", "CO", "WA", "OR", "AZ", "NV", "MA", "CT", "RI", "VT", "ME", "NH", "MI", "IL", "OH", "PA", "NY", "NJ", "MD", "DE", "DC", "VA", "WV", "NC", "SC", "GA", "FL", "AL", "MS", "TN", "KY", "IN", "WI", "MN", "IA", "MO", "AR", "LA", "TX", "OK", "KS", "NE", "SD", "ND", "MT", "WY", "UT", "ID", "AK", "HI"]',
 '["general_liability", "product_liability", "property", "workers_comp", "employment_practices", "directors_officers"]', 4.6, 6),

('Harvest Protection Insurance', 'Crop and cultivation focused insurance solutions', '1-888-HARVEST-PRO', 'cultivators@harvestpro.com',
 '["CA", "CO", "WA", "OR", "NV", "AZ", "NM", "MT", "ND", "SD", "MN", "WI", "MI", "IL", "OH", "PA", "NY", "VT", "ME", "MA", "RI", "CT", "NJ", "MD", "DE", "DC", "VA", "WV"]',
 '["crop", "property", "equipment", "business_interruption"]', 4.7, 10),

('MedCann Insurance Solutions', 'Medical cannabis focused insurance and risk management', '1-877-MEDCANN', 'medical@medcann.com',
 '["CA", "CO", "WA", "OR", "NV", "AZ", "NM", "UT", "MT", "ND", "MN", "WI", "MI", "IL", "MO", "AR", "LA", "TX", "OK", "OH", "PA", "WV", "VA", "MD", "DE", "NJ", "NY", "CT", "RI", "MA", "VT", "ME", "NH", "FL", "AL", "MS", "GA", "SC", "NC", "TN", "KY"]',
 '["general_liability", "product_liability", "cyber", "key_person"]', 4.5, 7),

('Budtender Business Insurance', 'Retail dispensary and delivery service insurance', '1-800-BUDTENDER', 'retail@budtender.com',
 '["CA", "CO", "WA", "OR", "NV", "AZ", "IL", "MI", "MA", "NY", "NJ", "PA", "MD", "FL"]',
 '["general_liability", "product_liability", "property", "cyber", "employment_practices"]', 4.4, 5);

-- Insert sample insurance products
INSERT INTO insurance_products (provider_id, name, description, coverage_type, base_premium, min_coverage_amount, max_coverage_amount, eligible_business_types) VALUES
(1, 'Cannabis General Liability Pro', 'Comprehensive general liability coverage for cannabis businesses', 'general_liability', 2500.00, 1000000, 5000000, '["cultivation", "manufacturing", "dispensary", "delivery", "testing_lab"]'),
(1, 'Product Liability Shield', 'Protection against product-related claims and lawsuits', 'product_liability', 3500.00, 1000000, 10000000, '["manufacturing", "dispensary", "delivery"]'),
(1, 'Crop Protection Plus', 'Comprehensive crop insurance for cultivation operations', 'crop', 4000.00, 500000, 5000000, '["cultivation"]'),

(2, 'Green Business Liability', 'General liability insurance tailored for cannabis operations', 'general_liability', 2200.00, 1000000, 3000000, '["cultivation", "manufacturing", "dispensary", "delivery", "consulting"]'),
(2, 'Cannabis Property Guard', 'Property insurance for cannabis facilities and equipment', 'property', 2800.00, 250000, 2000000, '["cultivation", "manufacturing", "dispensary", "testing_lab"]'),
(2, 'Workers Compensation Cannabis', 'Workers compensation insurance for cannabis employees', 'workers_comp', 1800.00, 100000, 1000000, '["cultivation", "manufacturing", "dispensary", "delivery", "testing_lab"]'),

(3, 'Harvest Shield Crop Insurance', 'Specialized crop insurance for cannabis cultivation', 'crop', 3800.00, 500000, 3000000, '["cultivation"]'),
(3, 'Equipment Breakdown Coverage', 'Protection for cultivation and processing equipment', 'equipment', 2200.00, 100000, 1000000, '["cultivation", "manufacturing"]'),

(4, 'MedCann Cyber Protection', 'Cybersecurity insurance for cannabis businesses', 'cyber', 1500.00, 500000, 5000000, '["dispensary", "delivery", "manufacturing", "consulting"]'),
(4, 'Key Person Life Insurance', 'Life insurance for key cannabis business personnel', 'key_person', 1200.00, 500000, 2000000, '["cultivation", "manufacturing", "dispensary", "delivery", "consulting"]'),

(5, 'Dispensary Liability Pro', 'Specialized liability coverage for retail cannabis operations', 'general_liability', 2000.00, 1000000, 2000000, '["dispensary", "delivery"]'),
(5, 'Cannabis Employment Practices', 'Employment practices liability insurance', 'employment_practices', 1400.00, 1000000, 3000000, '["cultivation", "manufacturing", "dispensary", "delivery", "testing_lab"]');

-- Create indexes for performance
CREATE INDEX idx_insurance_providers_active ON insurance_providers(is_active);
CREATE INDEX idx_insurance_providers_rating ON insurance_providers(rating DESC);
CREATE INDEX idx_insurance_products_provider ON insurance_products(provider_id);
CREATE INDEX idx_insurance_products_coverage_type ON insurance_products(coverage_type);
CREATE INDEX idx_insurance_products_active ON insurance_products(is_active);
CREATE INDEX idx_insurance_quotes_user ON insurance_quotes(user_id);
CREATE INDEX idx_insurance_quotes_status ON insurance_quotes(status);
CREATE INDEX idx_insurance_quotes_created ON insurance_quotes(created_at);
CREATE INDEX idx_insurance_policies_user ON insurance_policies(user_id);
CREATE INDEX idx_insurance_policies_status ON insurance_policies(status);
CREATE INDEX idx_insurance_policies_expiration ON insurance_policies(expiration_date);
CREATE INDEX idx_insurance_claims_user ON insurance_claims(user_id);
CREATE INDEX idx_insurance_claims_policy ON insurance_claims(policy_id);
CREATE INDEX idx_insurance_claims_status ON insurance_claims(status);
CREATE INDEX idx_insurance_consultations_user ON insurance_consultations(user_id);
CREATE INDEX idx_insurance_consultations_provider ON insurance_consultations(provider_id);
CREATE INDEX idx_insurance_consultations_status ON insurance_consultations(status);
CREATE INDEX idx_insurance_notifications_user ON insurance_notifications(user_id);
CREATE INDEX idx_insurance_notifications_type ON insurance_notifications(notification_type);
CREATE INDEX idx_insurance_notifications_status ON insurance_notifications(status);

-- Create stored procedure for quote expiration cleanup
DELIMITER //
CREATE PROCEDURE ExpireOldQuotes()
BEGIN
    UPDATE insurance_quotes 
    SET status = 'expired' 
    WHERE status = 'approved' 
    AND quote_expires_at < NOW();
END //
DELIMITER ;

-- Create stored procedure for policy renewal reminders
DELIMITER //
CREATE PROCEDURE GeneratePolicyRenewalReminders()
BEGIN
    INSERT INTO insurance_notifications (user_id, policy_id, notification_type, message, status)
    SELECT 
        pol.user_id,
        pol.id,
        'policy_renewal',
        CONCAT('Your ', prod.name, ' policy expires on ', pol.expiration_date, '. Contact us to renew.'),
        'pending'
    FROM insurance_policies pol
    JOIN insurance_products prod ON pol.product_id = prod.id
    WHERE pol.status = 'active'
    AND pol.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND NOT EXISTS (
        SELECT 1 FROM insurance_notifications 
        WHERE policy_id = pol.id 
        AND notification_type = 'policy_renewal'
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    );
END //
DELIMITER ;

-- Create view for insurance dashboard summary
CREATE VIEW insurance_dashboard_summary AS
SELECT 
    u.id as user_id,
    u.username,
    COUNT(DISTINCT pol.id) as active_policies,
    COUNT(DISTINCT iq.id) as pending_quotes,
    COUNT(DISTINCT ic.id) as open_claims,
    SUM(pol.premium_amount) as total_annual_premiums,
    MIN(pol.expiration_date) as next_policy_expiration,
    COUNT(CASE WHEN ic.status IN ('submitted', 'under_investigation') THEN 1 END) as active_claims
FROM users u
LEFT JOIN insurance_policies pol ON u.id = pol.user_id AND pol.status = 'active'
LEFT JOIN insurance_quotes iq ON u.id = iq.user_id AND iq.status IN ('pending', 'under_review', 'approved')
LEFT JOIN insurance_claims ic ON u.id = ic.user_id AND ic.status NOT IN ('settled', 'closed', 'denied')
GROUP BY u.id, u.username;

-- Create view for provider performance analytics
CREATE VIEW provider_performance_analytics AS
SELECT 
    ip.id as provider_id,
    ip.name as provider_name,
    ip.rating,
    COUNT(DISTINCT iq.id) as total_quotes,
    COUNT(CASE WHEN iq.status = 'approved' THEN 1 END) as approved_quotes,
    COUNT(CASE WHEN iq.status = 'purchased' THEN 1 END) as purchased_policies,
    ROUND(AVG(iq.final_premium), 2) as avg_premium,
    COUNT(DISTINCT ic.id) as total_claims,
    COUNT(CASE WHEN ic.status = 'settled' THEN 1 END) as settled_claims,
    ROUND(AVG(DATEDIFF(ic.updated_at, ic.created_at)), 1) as avg_claim_processing_days
FROM insurance_providers ip
LEFT JOIN insurance_products prod ON ip.id = prod.provider_id
LEFT JOIN insurance_quotes iq ON prod.id = iq.product_id
LEFT JOIN insurance_policies pol ON iq.id = pol.quote_id
LEFT JOIN insurance_claims ic ON pol.id = ic.policy_id
WHERE ip.is_active = TRUE
GROUP BY ip.id, ip.name, ip.rating
ORDER BY ip.rating DESC, purchased_policies DESC;
