-- Cannabis-Specific Accounting Tools Database Schema
-- Designed for compliance with IRS Section 280E and cannabis industry regulations

-- Main accounting transactions table
CREATE TABLE IF NOT EXISTS accounting_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    transaction_type ENUM('revenue', 'expense', 'transfer') NOT NULL,
    category VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    tax_category ENUM('280e_deductible', '280e_nondeductible', 'other') DEFAULT 'other',
    is_280e_deductible BOOLEAN DEFAULT FALSE,
    reference_number VARCHAR(100),
    payment_method VARCHAR(50),
    vendor_customer VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, transaction_date),
    INDEX idx_category (category),
    INDEX idx_tax_category (tax_category),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tax calculations and 280E analysis
CREATE TABLE IF NOT EXISTS tax_calculations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    calculation_type ENUM('280e', 'quarterly', 'annual', 'estimated') NOT NULL,
    tax_year YEAR NOT NULL,
    calculation_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_calc_year (user_id, calculation_type, tax_year),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Inventory transactions for COGS tracking
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    strain_id INT NOT NULL,
    transaction_type ENUM('purchase', 'sale', 'adjustment', 'waste', 'transfer') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL, -- in grams
    unit_cost DECIMAL(8,2) NOT NULL,
    total_value DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
    batch_number VARCHAR(100),
    transaction_date DATE NOT NULL DEFAULT (CURRENT_DATE),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_strain (user_id, strain_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_date (transaction_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Inventory adjustments for shrinkage, waste, theft tracking
CREATE TABLE IF NOT EXISTS inventory_adjustments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    strain_id INT NOT NULL,
    adjustment_type ENUM('shrinkage', 'waste', 'theft', 'recount', 'damage') NOT NULL,
    quantity_change DECIMAL(10,3) NOT NULL, -- positive or negative
    reason TEXT,
    adjustment_date DATE NOT NULL DEFAULT (CURRENT_DATE),
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_strain (user_id, strain_id),
    INDEX idx_adjustment_type (adjustment_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Compliance tracking for seed-to-sale reporting
CREATE TABLE IF NOT EXISTS compliance_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    transaction_type ENUM('plant', 'harvest', 'sale', 'transfer', 'waste', 'test') NOT NULL,
    strain_id INT,
    quantity DECIMAL(10,3),
    compliance_id VARCHAR(100), -- State tracking ID
    batch_number VARCHAR(100),
    test_results JSON,
    transaction_date DATE NOT NULL,
    reported_to_state BOOLEAN DEFAULT FALSE,
    state_response JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, transaction_date),
    INDEX idx_compliance_id (compliance_id),
    INDEX idx_batch (batch_number),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chart of accounts for cannabis businesses
CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    account_code VARCHAR(20) NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    parent_account_id INT,
    is_280e_deductible BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_code (user_id, account_code),
    INDEX idx_account_type (account_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts(id)
);

-- Financial reports cache
CREATE TABLE IF NOT EXISTS financial_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    report_type ENUM('profit_loss', 'balance_sheet', 'cash_flow', '280e_analysis') NOT NULL,
    report_period VARCHAR(50) NOT NULL, -- e.g., '2024-Q1', '2024-03'
    report_data JSON NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    INDEX idx_user_type_period (user_id, report_type, report_period),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payroll tracking for cannabis businesses
CREATE TABLE IF NOT EXISTS payroll_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    employee_id VARCHAR(50),
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    gross_pay DECIMAL(10,2) NOT NULL,
    federal_tax DECIMAL(10,2) DEFAULT 0,
    state_tax DECIMAL(10,2) DEFAULT 0,
    social_security DECIMAL(10,2) DEFAULT 0,
    medicare DECIMAL(10,2) DEFAULT 0,
    other_deductions DECIMAL(10,2) DEFAULT 0,
    net_pay DECIMAL(10,2) GENERATED ALWAYS AS (gross_pay - federal_tax - state_tax - social_security - medicare - other_deductions) STORED,
    is_280e_deductible BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_period (user_id, pay_period_start, pay_period_end),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default chart of accounts for cannabis businesses
INSERT IGNORE INTO chart_of_accounts (user_id, account_code, account_name, account_type, is_280e_deductible) VALUES
(0, '1000', 'Cash', 'asset', FALSE),
(0, '1100', 'Accounts Receivable', 'asset', FALSE),
(0, '1200', 'Inventory - Raw Materials', 'asset', TRUE),
(0, '1210', 'Inventory - Work in Process', 'asset', TRUE),
(0, '1220', 'Inventory - Finished Goods', 'asset', TRUE),
(0, '1300', 'Equipment', 'asset', FALSE),
(0, '1400', 'Accumulated Depreciation', 'asset', FALSE),
(0, '2000', 'Accounts Payable', 'liability', FALSE),
(0, '2100', 'Accrued Expenses', 'liability', FALSE),
(0, '2200', 'Notes Payable', 'liability', FALSE),
(0, '3000', 'Owner Equity', 'equity', FALSE),
(0, '3100', 'Retained Earnings', 'equity', FALSE),
(0, '4000', 'Cannabis Sales Revenue', 'revenue', FALSE),
(0, '4100', 'Ancillary Revenue', 'revenue', FALSE),
(0, '5000', 'Cost of Goods Sold', 'expense', TRUE),
(0, '5100', 'Direct Labor - Cultivation', 'expense', TRUE),
(0, '5200', 'Direct Materials - Seeds/Clones', 'expense', TRUE),
(0, '5300', 'Manufacturing Overhead', 'expense', TRUE),
(0, '6000', 'Rent Expense', 'expense', FALSE),
(0, '6100', 'Utilities', 'expense', FALSE),
(0, '6200', 'Insurance', 'expense', FALSE),
(0, '6300', 'Legal & Professional', 'expense', FALSE),
(0, '6400', 'Marketing & Advertising', 'expense', FALSE),
(0, '6500', 'Office Expenses', 'expense', FALSE),
(0, '6600', 'Compliance & Licensing', 'expense', FALSE);

-- Views for common financial reports
CREATE OR REPLACE VIEW v_profit_loss AS
SELECT 
    user_id,
    YEAR(transaction_date) as year,
    MONTH(transaction_date) as month,
    SUM(CASE WHEN transaction_type = 'revenue' THEN amount ELSE 0 END) as total_revenue,
    SUM(CASE WHEN transaction_type = 'expense' AND tax_category = '280e_deductible' THEN amount ELSE 0 END) as deductible_expenses,
    SUM(CASE WHEN transaction_type = 'expense' AND tax_category = '280e_nondeductible' THEN amount ELSE 0 END) as nondeductible_expenses,
    SUM(CASE WHEN transaction_type = 'revenue' THEN amount ELSE 0 END) - 
    SUM(CASE WHEN transaction_type = 'expense' AND tax_category = '280e_deductible' THEN amount ELSE 0 END) as net_income_280e
FROM accounting_transactions
GROUP BY user_id, YEAR(transaction_date), MONTH(transaction_date);

CREATE OR REPLACE VIEW v_inventory_summary AS
SELECT 
    user_id,
    strain_id,
    SUM(CASE WHEN transaction_type IN ('purchase', 'adjustment') THEN quantity ELSE 0 END) -
    SUM(CASE WHEN transaction_type IN ('sale', 'waste') THEN quantity ELSE 0 END) as current_quantity,
    AVG(unit_cost) as avg_unit_cost,
    (SUM(CASE WHEN transaction_type IN ('purchase', 'adjustment') THEN quantity ELSE 0 END) -
     SUM(CASE WHEN transaction_type IN ('sale', 'waste') THEN quantity ELSE 0 END)) * AVG(unit_cost) as inventory_value
FROM inventory_transactions
GROUP BY user_id, strain_id
HAVING current_quantity > 0;

-- Stored procedures for common calculations
DELIMITER //

CREATE PROCEDURE CalculateMonthly280E(IN p_user_id INT, IN p_year INT, IN p_month INT)
BEGIN
    SELECT 
        SUM(CASE WHEN category = 'cannabis_sales' THEN amount ELSE 0 END) as gross_receipts,
        SUM(CASE WHEN tax_category = '280e_deductible' THEN amount ELSE 0 END) as deductible_expenses,
        SUM(CASE WHEN tax_category = '280e_nondeductible' THEN amount ELSE 0 END) as nondeductible_expenses,
        (SUM(CASE WHEN category = 'cannabis_sales' THEN amount ELSE 0 END) - 
         SUM(CASE WHEN tax_category = '280e_deductible' THEN amount ELSE 0 END)) as taxable_income_280e,
        SUM(CASE WHEN tax_category = '280e_nondeductible' THEN amount ELSE 0 END) * 0.21 as additional_tax_280e
    FROM accounting_transactions
    WHERE user_id = p_user_id 
    AND YEAR(transaction_date) = p_year 
    AND MONTH(transaction_date) = p_month;
END //

CREATE PROCEDURE GenerateInventoryReport(IN p_user_id INT, IN p_date DATE)
BEGIN
    SELECT 
        s.name as strain_name,
        vs.current_quantity,
        vs.avg_unit_cost,
        vs.inventory_value,
        (vs.current_quantity * 3.5 / 453.592) as estimated_market_value -- Convert to pounds and apply market rate
    FROM v_inventory_summary vs
    JOIN strains s ON vs.strain_id = s.id
    WHERE vs.user_id = p_user_id
    ORDER BY vs.inventory_value DESC;
END //

CREATE PROCEDURE UpdateCOGSFromSale(IN p_user_id INT, IN p_strain_id INT, IN p_quantity DECIMAL(10,3), IN p_sale_amount DECIMAL(12,2))
BEGIN
    DECLARE v_unit_cost DECIMAL(8,2);
    
    -- Get average unit cost for FIFO calculation
    SELECT AVG(unit_cost) INTO v_unit_cost
    FROM inventory_transactions
    WHERE user_id = p_user_id AND strain_id = p_strain_id AND transaction_type = 'purchase';
    
    -- Record COGS transaction
    INSERT INTO accounting_transactions (user_id, transaction_type, category, amount, description, transaction_date, tax_category, is_280e_deductible)
    VALUES (p_user_id, 'expense', 'cogs', p_quantity * v_unit_cost, CONCAT('COGS for sale of ', p_quantity, 'g'), CURDATE(), '280e_deductible', TRUE);
    
    -- Record inventory reduction
    INSERT INTO inventory_transactions (user_id, strain_id, transaction_type, quantity, unit_cost, transaction_date)
    VALUES (p_user_id, p_strain_id, 'sale', -p_quantity, v_unit_cost, CURDATE());
END //

DELIMITER ;

-- Sample data for testing
INSERT IGNORE INTO accounting_transactions (user_id, transaction_type, category, amount, description, transaction_date, tax_category, is_280e_deductible) VALUES
(1, 'revenue', 'cannabis_sales', 15000.00, 'Retail cannabis sales - Week 1', '2024-01-15', 'other', FALSE),
(1, 'expense', 'cogs', 4500.00, 'Cost of goods sold - cultivation', '2024-01-15', '280e_deductible', TRUE),
(1, 'expense', 'cultivation_expenses', 2000.00, 'Seeds and growing supplies', '2024-01-10', '280e_deductible', TRUE),
(1, 'expense', 'rent', 5000.00, 'Facility rent - January', '2024-01-01', '280e_nondeductible', FALSE),
(1, 'expense', 'utilities', 1200.00, 'Electricity and water', '2024-01-05', '280e_nondeductible', FALSE),
(1, 'expense', 'compliance_costs', 800.00, 'State licensing fees', '2024-01-03', '280e_nondeductible', FALSE),
(1, 'expense', 'legal_fees', 3000.00, 'Attorney consultation', '2024-01-20', '280e_nondeductible', FALSE),
(1, 'expense', 'insurance', 1500.00, 'Business insurance premium', '2024-01-01', '280e_nondeductible', FALSE);

INSERT IGNORE INTO inventory_transactions (user_id, strain_id, transaction_type, quantity, unit_cost, batch_number) VALUES
(1, 1, 'purchase', 1000.0, 4.50, 'BATCH001'),
(1, 2, 'purchase', 800.0, 4.00, 'BATCH002'),
(1, 3, 'purchase', 1200.0, 3.75, 'BATCH003'),
(1, 1, 'sale', -150.0, 4.50, 'BATCH001'),
(1, 2, 'sale', -200.0, 4.00, 'BATCH002');

INSERT IGNORE INTO compliance_tracking (user_id, transaction_type, strain_id, quantity, compliance_id, batch_number, transaction_date) VALUES
(1, 'plant', 1, 50.0, 'NY-PLANT-001', 'BATCH001', '2024-01-01'),
(1, 'harvest', 1, 1000.0, 'NY-HARVEST-001', 'BATCH001', '2024-01-15'),
(1, 'sale', 1, 150.0, 'NY-SALE-001', 'BATCH001', '2024-01-20'),
(1, 'waste', 1, 25.0, 'NY-WASTE-001', 'BATCH001', '2024-01-22');

-- Indexes for performance optimization
CREATE INDEX idx_transactions_user_date_category ON accounting_transactions(user_id, transaction_date, category);
CREATE INDEX idx_inventory_user_strain_date ON inventory_transactions(user_id, strain_id, transaction_date);
CREATE INDEX idx_compliance_user_type_date ON compliance_tracking(user_id, transaction_type, transaction_date);

-- Triggers for automatic COGS calculation
DELIMITER //

CREATE TRIGGER tr_auto_cogs_calculation
AFTER INSERT ON inventory_transactions
FOR EACH ROW
BEGIN
    IF NEW.transaction_type = 'sale' AND NEW.quantity < 0 THEN
        INSERT INTO accounting_transactions (user_id, transaction_type, category, amount, description, transaction_date, tax_category, is_280e_deductible)
        VALUES (NEW.user_id, 'expense', 'cogs', ABS(NEW.quantity) * NEW.unit_cost, 
                CONCAT('Auto COGS - Sale of ', ABS(NEW.quantity), 'g'), NEW.transaction_date, '280e_deductible', TRUE);
    END IF;
END //

DELIMITER ;
