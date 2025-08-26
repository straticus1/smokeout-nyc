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

// Verify authentication
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
            if ($endpoint === 'providers') {
                getInsuranceProviders();
            } elseif ($endpoint === 'products') {
                getInsuranceProducts();
            } elseif ($endpoint === 'quotes') {
                getUserQuotes();
            } elseif ($endpoint === 'policies') {
                getUserPolicies();
            } elseif ($endpoint === 'claims') {
                getUserClaims();
            } elseif ($endpoint === 'coverage-calculator') {
                getCoverageRecommendations();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
            
        case 'POST':
            if ($endpoint === 'request-quote') {
                requestInsuranceQuote();
            } elseif ($endpoint === 'purchase-policy') {
                purchaseInsurancePolicy();
            } elseif ($endpoint === 'file-claim') {
                fileInsuranceClaim();
            } elseif ($endpoint === 'compare-quotes') {
                compareInsuranceQuotes();
            } elseif ($endpoint === 'schedule-consultation') {
                scheduleConsultation();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
            
        case 'PUT':
            if ($endpoint === 'update-policy') {
                updateInsurancePolicy();
            } elseif ($endpoint === 'update-claim') {
                updateInsuranceClaim();
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

function getInsuranceProviders() {
    global $pdo;
    
    $state = $_GET['state'] ?? '';
    $insurance_type = $_GET['type'] ?? '';
    
    $sql = "SELECT * FROM insurance_providers WHERE is_active = TRUE";
    $params = [];
    
    if (!empty($state)) {
        $sql .= " AND JSON_CONTAINS(states_covered, ?)";
        $params[] = json_encode($state);
    }
    
    if (!empty($insurance_type)) {
        $sql .= " AND JSON_CONTAINS(insurance_types, ?)";
        $params[] = json_encode($insurance_type);
    }
    
    $sql .= " ORDER BY rating DESC, name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $providers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'providers' => $providers
    ]);
}

function getInsuranceProducts() {
    global $pdo;
    
    $provider_id = $_GET['provider_id'] ?? '';
    $business_type = $_GET['business_type'] ?? '';
    $state = $_GET['state'] ?? '';
    
    $sql = "
        SELECT ip.*, prov.name as provider_name, prov.rating as provider_rating
        FROM insurance_products ip
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        WHERE ip.is_active = TRUE
    ";
    $params = [];
    
    if (!empty($provider_id)) {
        $sql .= " AND ip.provider_id = ?";
        $params[] = $provider_id;
    }
    
    if (!empty($business_type)) {
        $sql .= " AND JSON_CONTAINS(ip.eligible_business_types, ?)";
        $params[] = json_encode($business_type);
    }
    
    if (!empty($state)) {
        $sql .= " AND JSON_CONTAINS(prov.states_covered, ?)";
        $params[] = json_encode($state);
    }
    
    $sql .= " ORDER BY ip.coverage_type, ip.base_premium ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
}

function requestInsuranceQuote() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = $input['product_id'] ?? 0;
    $business_info = $input['business_info'] ?? [];
    $coverage_details = $input['coverage_details'] ?? [];
    $additional_info = $input['additional_info'] ?? '';
    
    if (!$product_id) {
        throw new Exception('Product ID is required');
    }
    
    // Validate product exists
    $stmt = $pdo->prepare("SELECT * FROM insurance_products WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Invalid product ID');
    }
    
    // Calculate estimated premium
    $estimated_premium = calculateEstimatedPremium($product, $business_info, $coverage_details);
    
    // Create quote request
    $stmt = $pdo->prepare("
        INSERT INTO insurance_quotes (user_id, product_id, business_info, coverage_details, 
                                    estimated_premium, additional_info, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $user_id,
        $product_id,
        json_encode($business_info),
        json_encode($coverage_details),
        $estimated_premium,
        $additional_info
    ]);
    
    $quote_id = $pdo->lastInsertId();
    
    // Notify provider (in real implementation, this would trigger external API call)
    notifyInsuranceProvider($quote_id, $product['provider_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quote request submitted successfully',
        'quote_id' => $quote_id,
        'estimated_premium' => $estimated_premium
    ]);
}

function calculateEstimatedPremium($product, $business_info, $coverage_details) {
    $base_premium = $product['base_premium'];
    $multiplier = 1.0;
    
    // Business type risk adjustment
    $business_type = $business_info['business_type'] ?? '';
    $risk_multipliers = [
        'cultivation' => 1.5,
        'manufacturing' => 1.8,
        'dispensary' => 1.2,
        'delivery' => 1.4,
        'testing_lab' => 1.1,
        'consulting' => 0.9
    ];
    
    if (isset($risk_multipliers[$business_type])) {
        $multiplier *= $risk_multipliers[$business_type];
    }
    
    // Coverage amount adjustment
    $coverage_amount = $coverage_details['coverage_amount'] ?? 1000000;
    if ($coverage_amount > 1000000) {
        $multiplier *= (1 + ($coverage_amount - 1000000) / 10000000);
    }
    
    // Location risk (simplified)
    $state = $business_info['state'] ?? '';
    $high_risk_states = ['CA', 'NY', 'FL'];
    if (in_array($state, $high_risk_states)) {
        $multiplier *= 1.2;
    }
    
    // Years in business discount
    $years_in_business = $business_info['years_in_business'] ?? 0;
    if ($years_in_business >= 3) {
        $multiplier *= 0.9;
    } elseif ($years_in_business >= 1) {
        $multiplier *= 0.95;
    }
    
    return round($base_premium * $multiplier, 2);
}

function notifyInsuranceProvider($quote_id, $provider_id) {
    // In real implementation, this would:
    // 1. Send API request to insurance provider
    // 2. Send email notification
    // 3. Create task in provider dashboard
    // For now, we'll just log it
    
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO insurance_notifications (quote_id, provider_id, notification_type, status, created_at)
        VALUES (?, ?, 'quote_request', 'sent', NOW())
    ");
    $stmt->execute([$quote_id, $provider_id]);
}

function getUserQuotes() {
    global $pdo, $user_id;
    
    $status = $_GET['status'] ?? '';
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    $sql = "
        SELECT iq.*, ip.name as product_name, ip.coverage_type,
               prov.name as provider_name, prov.logo_url as provider_logo
        FROM insurance_quotes iq
        JOIN insurance_products ip ON iq.product_id = ip.id
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        WHERE iq.user_id = ?
    ";
    $params = [$user_id];
    
    if (!empty($status)) {
        $sql .= " AND iq.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY iq.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $quotes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'quotes' => $quotes
    ]);
}

function compareInsuranceQuotes() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $quote_ids = $input['quote_ids'] ?? [];
    
    if (empty($quote_ids) || count($quote_ids) < 2) {
        throw new Exception('At least 2 quote IDs are required for comparison');
    }
    
    $placeholders = str_repeat('?,', count($quote_ids) - 1) . '?';
    $params = array_merge([$user_id], $quote_ids);
    
    $stmt = $pdo->prepare("
        SELECT iq.*, ip.name as product_name, ip.coverage_type, ip.features,
               prov.name as provider_name, prov.rating as provider_rating,
               prov.customer_service_rating, prov.claims_processing_time
        FROM insurance_quotes iq
        JOIN insurance_products ip ON iq.product_id = ip.id
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        WHERE iq.user_id = ? AND iq.id IN ($placeholders)
        ORDER BY iq.final_premium ASC
    ");
    $stmt->execute($params);
    $quotes = $stmt->fetchAll();
    
    // Generate comparison analysis
    $comparison = generateQuoteComparison($quotes);
    
    echo json_encode([
        'success' => true,
        'quotes' => $quotes,
        'comparison' => $comparison
    ]);
}

function generateQuoteComparison($quotes) {
    if (empty($quotes)) {
        return [];
    }
    
    $comparison = [
        'best_price' => null,
        'best_coverage' => null,
        'best_provider_rating' => null,
        'recommendations' => []
    ];
    
    $lowest_premium = PHP_FLOAT_MAX;
    $highest_coverage = 0;
    $highest_rating = 0;
    
    foreach ($quotes as $quote) {
        $premium = $quote['final_premium'] ?? $quote['estimated_premium'];
        $coverage_details = json_decode($quote['coverage_details'], true);
        $coverage_amount = $coverage_details['coverage_amount'] ?? 0;
        $provider_rating = $quote['provider_rating'] ?? 0;
        
        if ($premium < $lowest_premium) {
            $lowest_premium = $premium;
            $comparison['best_price'] = $quote;
        }
        
        if ($coverage_amount > $highest_coverage) {
            $highest_coverage = $coverage_amount;
            $comparison['best_coverage'] = $quote;
        }
        
        if ($provider_rating > $highest_rating) {
            $highest_rating = $provider_rating;
            $comparison['best_provider_rating'] = $quote;
        }
    }
    
    // Generate recommendations
    $comparison['recommendations'] = [
        'budget_conscious' => $comparison['best_price']['id'] ?? null,
        'maximum_protection' => $comparison['best_coverage']['id'] ?? null,
        'trusted_provider' => $comparison['best_provider_rating']['id'] ?? null
    ];
    
    return $comparison;
}

function purchaseInsurancePolicy() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $quote_id = $input['quote_id'] ?? 0;
    $payment_method = $input['payment_method'] ?? '';
    $billing_frequency = $input['billing_frequency'] ?? 'monthly';
    
    if (!$quote_id) {
        throw new Exception('Quote ID is required');
    }
    
    // Get quote details
    $stmt = $pdo->prepare("
        SELECT iq.*, ip.name as product_name, prov.name as provider_name
        FROM insurance_quotes iq
        JOIN insurance_products ip ON iq.product_id = ip.id
        JOIN insurance_providers prov ON ip.provider_id = prov.id
        WHERE iq.id = ? AND iq.user_id = ? AND iq.status = 'approved'
    ");
    $stmt->execute([$quote_id, $user_id]);
    $quote = $stmt->fetch();
    
    if (!$quote) {
        throw new Exception('Quote not found or not approved');
    }
    
    // Process payment (simplified - in real implementation, integrate with payment processor)
    $payment_result = processInsurancePayment($quote, $payment_method, $billing_frequency);
    
    if (!$payment_result['success']) {
        throw new Exception('Payment processing failed: ' . $payment_result['error']);
    }
    
    // Create insurance policy
    $policy_number = generatePolicyNumber();
    $effective_date = date('Y-m-d');
    $expiration_date = date('Y-m-d', strtotime('+1 year'));
    
    $stmt = $pdo->prepare("
        INSERT INTO insurance_policies (user_id, quote_id, policy_number, provider_id, product_id,
                                      premium_amount, billing_frequency, effective_date, expiration_date,
                                      coverage_details, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->execute([
        $user_id,
        $quote_id,
        $policy_number,
        $quote['provider_id'],
        $quote['product_id'],
        $quote['final_premium'],
        $billing_frequency,
        $effective_date,
        $expiration_date,
        $quote['coverage_details']
    ]);
    
    $policy_id = $pdo->lastInsertId();
    
    // Update quote status
    $stmt = $pdo->prepare("UPDATE insurance_quotes SET status = 'purchased' WHERE id = ?");
    $stmt->execute([$quote_id]);
    
    // Send confirmation (in real implementation)
    sendPolicyConfirmation($policy_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Insurance policy purchased successfully',
        'policy_id' => $policy_id,
        'policy_number' => $policy_number
    ]);
}

function processInsurancePayment($quote, $payment_method, $billing_frequency) {
    // Simplified payment processing
    // In real implementation, integrate with Stripe, PayPal, etc.
    
    $premium = $quote['final_premium'];
    
    // Calculate first payment amount based on billing frequency
    $payment_amounts = [
        'monthly' => $premium / 12,
        'quarterly' => $premium / 4,
        'semi_annual' => $premium / 2,
        'annual' => $premium
    ];
    
    $payment_amount = $payment_amounts[$billing_frequency] ?? $premium;
    
    // Mock payment processing
    if ($payment_method === 'test_fail') {
        return ['success' => false, 'error' => 'Test payment failure'];
    }
    
    return [
        'success' => true,
        'transaction_id' => 'TXN_' . uniqid(),
        'amount' => $payment_amount
    ];
}

function generatePolicyNumber() {
    return 'POL-' . date('Y') . '-' . strtoupper(uniqid());
}

function sendPolicyConfirmation($policy_id) {
    // In real implementation, send email with policy documents
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO insurance_notifications (policy_id, notification_type, status, created_at)
        VALUES (?, 'policy_confirmation', 'sent', NOW())
    ");
    $stmt->execute([$policy_id]);
}

function getUserPolicies() {
    global $pdo, $user_id;
    
    $status = $_GET['status'] ?? '';
    
    $sql = "
        SELECT pol.*, ip.name as product_name, ip.coverage_type,
               prov.name as provider_name, prov.contact_phone, prov.contact_email
        FROM insurance_policies pol
        JOIN insurance_products ip ON pol.product_id = ip.id
        JOIN insurance_providers prov ON pol.provider_id = prov.id
        WHERE pol.user_id = ?
    ";
    $params = [$user_id];
    
    if (!empty($status)) {
        $sql .= " AND pol.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY pol.effective_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $policies = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'policies' => $policies
    ]);
}

function fileInsuranceClaim() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $policy_id = $input['policy_id'] ?? 0;
    $claim_type = $input['claim_type'] ?? '';
    $incident_date = $input['incident_date'] ?? '';
    $description = $input['description'] ?? '';
    $estimated_loss = $input['estimated_loss'] ?? 0;
    $supporting_documents = $input['supporting_documents'] ?? [];
    
    if (!$policy_id || empty($claim_type) || empty($incident_date)) {
        throw new Exception('Policy ID, claim type, and incident date are required');
    }
    
    // Verify policy ownership and active status
    $stmt = $pdo->prepare("
        SELECT * FROM insurance_policies 
        WHERE id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->execute([$policy_id, $user_id]);
    $policy = $stmt->fetch();
    
    if (!$policy) {
        throw new Exception('Invalid policy or policy not active');
    }
    
    // Create claim
    $claim_number = generateClaimNumber();
    
    $stmt = $pdo->prepare("
        INSERT INTO insurance_claims (user_id, policy_id, claim_number, claim_type, 
                                    incident_date, description, estimated_loss, 
                                    supporting_documents, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())
    ");
    $stmt->execute([
        $user_id,
        $policy_id,
        $claim_number,
        $claim_type,
        $incident_date,
        $description,
        $estimated_loss,
        json_encode($supporting_documents)
    ]);
    
    $claim_id = $pdo->lastInsertId();
    
    // Notify provider
    notifyProviderOfClaim($claim_id, $policy['provider_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Insurance claim filed successfully',
        'claim_id' => $claim_id,
        'claim_number' => $claim_number
    ]);
}

function generateClaimNumber() {
    return 'CLM-' . date('Y') . '-' . strtoupper(uniqid());
}

function notifyProviderOfClaim($claim_id, $provider_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO insurance_notifications (claim_id, provider_id, notification_type, status, created_at)
        VALUES (?, ?, 'claim_filed', 'sent', NOW())
    ");
    $stmt->execute([$claim_id, $provider_id]);
}

function getUserClaims() {
    global $pdo, $user_id;
    
    $status = $_GET['status'] ?? '';
    
    $sql = "
        SELECT ic.*, pol.policy_number, ip.name as product_name,
               prov.name as provider_name
        FROM insurance_claims ic
        JOIN insurance_policies pol ON ic.policy_id = pol.id
        JOIN insurance_products ip ON pol.product_id = ip.id
        JOIN insurance_providers prov ON pol.provider_id = prov.id
        WHERE ic.user_id = ?
    ";
    $params = [$user_id];
    
    if (!empty($status)) {
        $sql .= " AND ic.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY ic.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $claims = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'claims' => $claims
    ]);
}

function getCoverageRecommendations() {
    global $pdo, $user_id;
    
    $business_type = $_GET['business_type'] ?? '';
    $annual_revenue = $_GET['annual_revenue'] ?? 0;
    $employee_count = $_GET['employee_count'] ?? 0;
    $state = $_GET['state'] ?? '';
    
    $recommendations = generateCoverageRecommendations($business_type, $annual_revenue, $employee_count, $state);
    
    echo json_encode([
        'success' => true,
        'recommendations' => $recommendations
    ]);
}

function generateCoverageRecommendations($business_type, $annual_revenue, $employee_count, $state) {
    $recommendations = [
        'essential_coverage' => [],
        'recommended_coverage' => [],
        'optional_coverage' => [],
        'estimated_total_premium' => 0
    ];
    
    // Essential coverage based on business type
    switch ($business_type) {
        case 'cultivation':
            $recommendations['essential_coverage'] = [
                'General Liability' => ['min_amount' => 1000000, 'estimated_premium' => 2500],
                'Product Liability' => ['min_amount' => 2000000, 'estimated_premium' => 3500],
                'Crop Insurance' => ['min_amount' => 500000, 'estimated_premium' => 4000]
            ];
            break;
        case 'dispensary':
            $recommendations['essential_coverage'] = [
                'General Liability' => ['min_amount' => 1000000, 'estimated_premium' => 2000],
                'Product Liability' => ['min_amount' => 2000000, 'estimated_premium' => 3000],
                'Commercial Property' => ['min_amount' => 500000, 'estimated_premium' => 2500]
            ];
            break;
        case 'manufacturing':
            $recommendations['essential_coverage'] = [
                'General Liability' => ['min_amount' => 2000000, 'estimated_premium' => 3500],
                'Product Liability' => ['min_amount' => 5000000, 'estimated_premium' => 6000],
                'Equipment Coverage' => ['min_amount' => 1000000, 'estimated_premium' => 3000]
            ];
            break;
    }
    
    // Recommended coverage
    $recommendations['recommended_coverage'] = [
        'Cyber Liability' => ['min_amount' => 1000000, 'estimated_premium' => 1500],
        'Employment Practices' => ['min_amount' => 1000000, 'estimated_premium' => 1200]
    ];
    
    if ($employee_count > 0) {
        $recommendations['recommended_coverage']['Workers Compensation'] = [
            'required' => true,
            'estimated_premium' => $employee_count * 800
        ];
    }
    
    // Optional coverage
    $recommendations['optional_coverage'] = [
        'Business Interruption' => ['min_amount' => 500000, 'estimated_premium' => 1800],
        'Directors & Officers' => ['min_amount' => 1000000, 'estimated_premium' => 2200],
        'Key Person Life Insurance' => ['min_amount' => 1000000, 'estimated_premium' => 1000]
    ];
    
    // Calculate estimated total
    $total = 0;
    foreach (['essential_coverage', 'recommended_coverage'] as $category) {
        foreach ($recommendations[$category] as $coverage) {
            $total += $coverage['estimated_premium'];
        }
    }
    $recommendations['estimated_total_premium'] = $total;
    
    return $recommendations;
}

function scheduleConsultation() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $provider_id = $input['provider_id'] ?? 0;
    $consultation_type = $input['consultation_type'] ?? 'general';
    $preferred_date = $input['preferred_date'] ?? '';
    $preferred_time = $input['preferred_time'] ?? '';
    $contact_method = $input['contact_method'] ?? 'phone';
    $notes = $input['notes'] ?? '';
    
    if (!$provider_id) {
        throw new Exception('Provider ID is required');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO insurance_consultations (user_id, provider_id, consultation_type,
                                           preferred_date, preferred_time, contact_method,
                                           notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'requested', NOW())
    ");
    $stmt->execute([
        $user_id,
        $provider_id,
        $consultation_type,
        $preferred_date,
        $preferred_time,
        $contact_method,
        $notes
    ]);
    
    $consultation_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Consultation request submitted successfully',
        'consultation_id' => $consultation_id
    ]);
}
?>
