<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user_id = authenticate();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));
$endpoint = end($path_parts);

try {
    switch ($method) {
        case 'GET':
            if ($endpoint === 'financial-dashboard') {
                getFinancialDashboard();
            } elseif ($endpoint === 'transactions') {
                getTransactions();
            } elseif ($endpoint === 'tax-reports') {
                getTaxReports();
            } elseif ($endpoint === 'compliance-reports') {
                getComplianceReports();
            } elseif ($endpoint === 'inventory-valuation') {
                getInventoryValuation();
            } elseif ($endpoint === 'cogs-analysis') {
                getCOGSAnalysis();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
        case 'POST':
            if ($endpoint === 'record-transaction') {
                recordTransaction();
            } elseif ($endpoint === 'generate-tax-report') {
                generateTaxReport();
            } elseif ($endpoint === 'calculate-280e') {
                calculate280E();
            } elseif ($endpoint === 'inventory-adjustment') {
                recordInventoryAdjustment();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
        case 'PUT':
            if ($endpoint === 'update-transaction') {
                updateTransaction();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function getFinancialDashboard() {
    global $pdo, $user_id;
    
    $period = $_GET['period'] ?? 'current_month';
    $date_range = getDateRange($period);
    
    // Revenue metrics
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN transaction_type = 'revenue' THEN amount ELSE 0 END) as total_revenue,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expenses,
            COUNT(CASE WHEN transaction_type = 'revenue' THEN 1 END) as revenue_transactions,
            COUNT(CASE WHEN transaction_type = 'expense' THEN 1 END) as expense_transactions
        FROM accounting_transactions 
        WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $date_range['start'], $date_range['end']]);
    $financial_summary = $stmt->fetch();
    
    // Cannabis-specific metrics
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN category = 'cannabis_sales' THEN amount ELSE 0 END) as cannabis_revenue,
            SUM(CASE WHEN category = 'cogs' THEN amount ELSE 0 END) as cogs,
            SUM(CASE WHEN category = '280e_deductible' THEN amount ELSE 0 END) as deductible_expenses,
            SUM(CASE WHEN category = '280e_nondeductible' THEN amount ELSE 0 END) as nondeductible_expenses
        FROM accounting_transactions 
        WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $date_range['start'], $date_range['end']]);
    $cannabis_metrics = $stmt->fetch();
    
    // Tax calculations
    $gross_profit = $cannabis_metrics['cannabis_revenue'] - $cannabis_metrics['cogs'];
    $net_income_280e = $gross_profit - $cannabis_metrics['deductible_expenses'];
    $effective_tax_rate = calculateEffectiveTaxRate($cannabis_metrics);
    
    echo json_encode([
        'success' => true,
        'dashboard' => [
            'period' => $period,
            'financial_summary' => $financial_summary,
            'cannabis_metrics' => $cannabis_metrics,
            'calculated_metrics' => [
                'gross_profit' => $gross_profit,
                'net_income_280e' => $net_income_280e,
                'effective_tax_rate' => $effective_tax_rate,
                'profit_margin' => $cannabis_metrics['cannabis_revenue'] > 0 ? 
                    ($gross_profit / $cannabis_metrics['cannabis_revenue']) * 100 : 0
            ]
        ]
    ]);
}

function recordTransaction() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $transaction_type = $input['transaction_type'] ?? '';
    $category = $input['category'] ?? '';
    $amount = $input['amount'] ?? 0;
    $description = $input['description'] ?? '';
    $transaction_date = $input['transaction_date'] ?? date('Y-m-d');
    $tax_category = $input['tax_category'] ?? '';
    $is_280e_deductible = $input['is_280e_deductible'] ?? false;
    
    if (empty($transaction_type) || empty($category) || $amount <= 0) {
        throw new Exception('Transaction type, category, and amount are required');
    }
    
    // Validate cannabis-specific categories
    $valid_categories = [
        'cannabis_sales', 'cogs', 'cultivation_expenses', 'manufacturing_expenses',
        'retail_expenses', 'compliance_costs', 'legal_fees', 'insurance',
        'rent', 'utilities', 'payroll', 'marketing', 'equipment', 'other'
    ];
    
    if (!in_array($category, $valid_categories)) {
        throw new Exception('Invalid transaction category');
    }
    
    // Auto-determine 280E deductibility
    if (empty($tax_category)) {
        $tax_category = determine280ECategory($category, $is_280e_deductible);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO accounting_transactions (user_id, transaction_type, category, amount, 
                                           description, transaction_date, tax_category, 
                                           is_280e_deductible, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id, $transaction_type, $category, $amount, $description, 
        $transaction_date, $tax_category, $is_280e_deductible
    ]);
    
    $transaction_id = $pdo->lastInsertId();
    
    // Update inventory if applicable
    if (in_array($category, ['cannabis_sales', 'cogs'])) {
        updateInventoryFromTransaction($user_id, $category, $amount, $input);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaction recorded successfully',
        'transaction_id' => $transaction_id
    ]);
}

function determine280ECategory($category, $is_280e_deductible) {
    // IRS Section 280E - cannabis businesses can only deduct COGS
    $cogs_categories = ['cogs', 'cultivation_expenses', 'manufacturing_expenses'];
    
    if (in_array($category, $cogs_categories)) {
        return '280e_deductible';
    } else {
        return $is_280e_deductible ? '280e_deductible' : '280e_nondeductible';
    }
}

function calculate280E() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $year = $input['year'] ?? date('Y');
    
    // Get annual totals
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN category = 'cannabis_sales' THEN amount ELSE 0 END) as gross_receipts,
            SUM(CASE WHEN tax_category = '280e_deductible' THEN amount ELSE 0 END) as deductible_expenses,
            SUM(CASE WHEN tax_category = '280e_nondeductible' THEN amount ELSE 0 END) as nondeductible_expenses,
            SUM(CASE WHEN category = 'cogs' THEN amount ELSE 0 END) as total_cogs
        FROM accounting_transactions 
        WHERE user_id = ? AND YEAR(transaction_date) = ?
    ");
    $stmt->execute([$user_id, $year]);
    $annual_data = $stmt->fetch();
    
    // 280E calculations
    $gross_income = $annual_data['gross_receipts'] - $annual_data['total_cogs'];
    $taxable_income_280e = $gross_income - $annual_data['deductible_expenses'];
    $disallowed_deductions = $annual_data['nondeductible_expenses'];
    
    // Estimated tax impact
    $federal_tax_rate = 0.21; // Corporate rate
    $additional_tax_280e = $disallowed_deductions * $federal_tax_rate;
    
    $calculation = [
        'year' => $year,
        'gross_receipts' => $annual_data['gross_receipts'],
        'total_cogs' => $annual_data['total_cogs'],
        'gross_income' => $gross_income,
        'deductible_expenses' => $annual_data['deductible_expenses'],
        'nondeductible_expenses' => $annual_data['nondeductible_expenses'],
        'taxable_income_280e' => $taxable_income_280e,
        'disallowed_deductions' => $disallowed_deductions,
        'additional_tax_280e' => $additional_tax_280e,
        'effective_tax_rate' => $gross_income > 0 ? ($additional_tax_280e / $gross_income) * 100 : 0
    ];
    
    // Store calculation
    $stmt = $pdo->prepare("
        INSERT INTO tax_calculations (user_id, calculation_type, tax_year, calculation_data, created_at)
        VALUES (?, '280e', ?, ?, NOW())
        ON DUPLICATE KEY UPDATE calculation_data = VALUES(calculation_data), updated_at = NOW()
    ");
    $stmt->execute([$user_id, $year, json_encode($calculation)]);
    
    echo json_encode([
        'success' => true,
        'calculation' => $calculation
    ]);
}

function getInventoryValuation() {
    global $pdo, $user_id;
    
    $method = $_GET['method'] ?? 'fifo'; // fifo, lifo, weighted_average
    
    $stmt = $pdo->prepare("
        SELECT 
            strain_id,
            s.name as strain_name,
            SUM(quantity) as total_quantity,
            AVG(unit_cost) as avg_unit_cost,
            SUM(quantity * unit_cost) as total_value
        FROM inventory_transactions it
        JOIN strains s ON it.strain_id = s.id
        WHERE it.user_id = ? AND it.transaction_type = 'purchase'
        GROUP BY strain_id, s.name
        HAVING total_quantity > 0
    ");
    $stmt->execute([$user_id]);
    $inventory_items = $stmt->fetchAll();
    
    $total_inventory_value = 0;
    foreach ($inventory_items as &$item) {
        $item['valuation_method'] = $method;
        $item['market_value'] = calculateMarketValue($item['strain_id'], $item['total_quantity']);
        $total_inventory_value += $item['total_value'];
    }
    
    echo json_encode([
        'success' => true,
        'inventory_valuation' => [
            'method' => $method,
            'total_value' => $total_inventory_value,
            'items' => $inventory_items,
            'valuation_date' => date('Y-m-d H:i:s')
        ]
    ]);
}

function getCOGSAnalysis() {
    global $pdo, $user_id;
    
    $period = $_GET['period'] ?? 'current_month';
    $date_range = getDateRange($period);
    
    $stmt = $pdo->prepare("
        SELECT 
            category,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_count,
            AVG(amount) as avg_transaction
        FROM accounting_transactions 
        WHERE user_id = ? 
        AND transaction_date BETWEEN ? AND ?
        AND tax_category = '280e_deductible'
        GROUP BY category
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$user_id, $date_range['start'], $date_range['end']]);
    $cogs_breakdown = $stmt->fetchAll();
    
    // Calculate COGS ratios
    $total_cogs = array_sum(array_column($cogs_breakdown, 'total_amount'));
    
    foreach ($cogs_breakdown as &$item) {
        $item['percentage_of_cogs'] = $total_cogs > 0 ? ($item['total_amount'] / $total_cogs) * 100 : 0;
    }
    
    echo json_encode([
        'success' => true,
        'cogs_analysis' => [
            'period' => $period,
            'total_cogs' => $total_cogs,
            'breakdown' => $cogs_breakdown
        ]
    ]);
}

function getTaxReports() {
    global $pdo, $user_id;
    
    $report_type = $_GET['type'] ?? 'quarterly';
    $year = $_GET['year'] ?? date('Y');
    $quarter = $_GET['quarter'] ?? ceil(date('n') / 3);
    
    if ($report_type === 'quarterly') {
        $date_range = getQuarterDateRange($year, $quarter);
    } else {
        $date_range = ['start' => "$year-01-01", 'end' => "$year-12-31"];
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            tax_category,
            category,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_count
        FROM accounting_transactions 
        WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
        GROUP BY tax_category, category
        ORDER BY tax_category, total_amount DESC
    ");
    $stmt->execute([$user_id, $date_range['start'], $date_range['end']]);
    $tax_data = $stmt->fetchAll();
    
    // Organize by tax category
    $organized_data = [];
    foreach ($tax_data as $item) {
        $organized_data[$item['tax_category']][] = $item;
    }
    
    echo json_encode([
        'success' => true,
        'tax_report' => [
            'type' => $report_type,
            'period' => $report_type === 'quarterly' ? "Q$quarter $year" : $year,
            'data' => $organized_data
        ]
    ]);
}

function getComplianceReports() {
    global $pdo, $user_id;
    
    $report_type = $_GET['type'] ?? 'seed_to_sale';
    
    if ($report_type === 'seed_to_sale') {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as report_date,
                SUM(CASE WHEN transaction_type = 'plant' THEN 1 ELSE 0 END) as plants_tracked,
                SUM(CASE WHEN transaction_type = 'harvest' THEN quantity ELSE 0 END) as total_harvested,
                SUM(CASE WHEN transaction_type = 'sale' THEN quantity ELSE 0 END) as total_sold,
                SUM(CASE WHEN transaction_type = 'waste' THEN quantity ELSE 0 END) as total_waste
            FROM compliance_tracking 
            WHERE user_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY report_date DESC
        ");
        $stmt->execute([$user_id]);
        $compliance_data = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'compliance_report' => [
            'type' => $report_type,
            'data' => $compliance_data ?? []
        ]
    ]);
}

function getTransactions() {
    global $pdo, $user_id;
    
    $category = $_GET['category'] ?? '';
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    $sql = "
        SELECT * FROM accounting_transactions 
        WHERE user_id = ?
    ";
    $params = [$user_id];
    
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY transaction_date DESC, created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions
    ]);
}

function updateInventoryFromTransaction($user_id, $category, $amount, $transaction_data) {
    global $pdo;
    
    if ($category === 'cannabis_sales') {
        // Record inventory reduction
        $strain_id = $transaction_data['strain_id'] ?? 1;
        $quantity_sold = $transaction_data['quantity'] ?? 1;
        
        $stmt = $pdo->prepare("
            INSERT INTO inventory_transactions (user_id, strain_id, transaction_type, quantity, unit_cost, created_at)
            VALUES (?, ?, 'sale', ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $strain_id, -$quantity_sold, $amount / $quantity_sold]);
    }
}

function calculateMarketValue($strain_id, $quantity) {
    // Simplified market value calculation
    $market_rates = [
        1 => 3500, // OG Kush
        2 => 3200, // Blue Dream
        3 => 3000, // White Widow
        4 => 2800, // Green Crack
        5 => 3800  // Purple Haze
    ];
    
    $rate_per_pound = $market_rates[$strain_id] ?? 3000;
    return ($quantity / 453.592) * $rate_per_pound; // Convert grams to pounds
}

function calculateEffectiveTaxRate($metrics) {
    $gross_income = $metrics['cannabis_revenue'] - $metrics['cogs'];
    if ($gross_income <= 0) return 0;
    
    $federal_rate = 0.21;
    $additional_280e_tax = $metrics['nondeductible_expenses'] * $federal_rate;
    
    return ($additional_280e_tax / $gross_income) * 100;
}

function getDateRange($period) {
    switch ($period) {
        case 'current_month':
            return [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-t')
            ];
        case 'last_month':
            return [
                'start' => date('Y-m-01', strtotime('last month')),
                'end' => date('Y-m-t', strtotime('last month'))
            ];
        case 'current_year':
            return [
                'start' => date('Y-01-01'),
                'end' => date('Y-12-31')
            ];
        case 'last_year':
            return [
                'start' => date('Y-01-01', strtotime('last year')),
                'end' => date('Y-12-31', strtotime('last year'))
            ];
        default:
            return [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-t')
            ];
    }
}

function getQuarterDateRange($year, $quarter) {
    $quarters = [
        1 => ['01-01', '03-31'],
        2 => ['04-01', '06-30'],
        3 => ['07-01', '09-30'],
        4 => ['10-01', '12-31']
    ];
    
    $dates = $quarters[$quarter] ?? $quarters[1];
    
    return [
        'start' => "$year-{$dates[0]}",
        'end' => "$year-{$dates[1]}"
    ];
}

function recordInventoryAdjustment() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $strain_id = $input['strain_id'] ?? 0;
    $adjustment_type = $input['adjustment_type'] ?? ''; // shrinkage, waste, theft, recount
    $quantity_change = $input['quantity_change'] ?? 0;
    $reason = $input['reason'] ?? '';
    
    if (!$strain_id || !$adjustment_type) {
        throw new Exception('Strain ID and adjustment type are required');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO inventory_adjustments (user_id, strain_id, adjustment_type, 
                                         quantity_change, reason, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $strain_id, $adjustment_type, $quantity_change, $reason]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Inventory adjustment recorded successfully'
    ]);
}

function updateTransaction() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $transaction_id = $input['transaction_id'] ?? 0;
    $amount = $input['amount'] ?? null;
    $description = $input['description'] ?? null;
    $category = $input['category'] ?? null;
    
    if (!$transaction_id) {
        throw new Exception('Transaction ID is required');
    }
    
    $updates = [];
    $params = [];
    
    if ($amount !== null) {
        $updates[] = "amount = ?";
        $params[] = $amount;
    }
    
    if ($description !== null) {
        $updates[] = "description = ?";
        $params[] = $description;
    }
    
    if ($category !== null) {
        $updates[] = "category = ?";
        $params[] = $category;
    }
    
    if (empty($updates)) {
        throw new Exception('No fields to update');
    }
    
    $params[] = $user_id;
    $params[] = $transaction_id;
    
    $sql = "UPDATE accounting_transactions SET " . implode(', ', $updates) . 
           ", updated_at = NOW() WHERE user_id = ? AND id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaction updated successfully'
    ]);
}
?>
