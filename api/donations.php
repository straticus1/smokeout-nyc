<?php
/**
 * Donations API Endpoints
 * Political Memes XYZ
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/models/Donation.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Politician.php';
require_once __DIR__ . '/config/database.php';

$donation = new Donation();
$user = new User();
$politician = new Politician();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Get authenticated user
function getAuthenticatedUser() {
    global $user;
    $sessionToken = getBearerToken();
    if (!$sessionToken) {
        return null;
    }
    return $user->validateSession($sessionToken);
}

try {
    switch ($method) {
        case 'POST':
            if (end($pathParts) === 'donate') {
                handleCreateDonation();
            } elseif (end($pathParts) === 'process') {
                handleProcessPayment();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        case 'GET':
            if (end($pathParts) === 'settings' && isset($pathParts[count($pathParts) - 2])) {
                $politicianId = $pathParts[count($pathParts) - 2];
                handleGetDonationSettings($politicianId);
            } elseif (end($pathParts) === 'history') {
                handleGetDonationHistory();
            } elseif (end($pathParts) === 'stats' && isset($pathParts[count($pathParts) - 2])) {
                $politicianId = $pathParts[count($pathParts) - 2];
                handleGetDonationStats($politicianId);
            } elseif (end($pathParts) === 'recent') {
                handleGetRecentDonations();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        case 'PUT':
            if (end($pathParts) === 'settings' && isset($pathParts[count($pathParts) - 2])) {
                $politicianId = $pathParts[count($pathParts) - 2];
                handleUpdateDonationSettings($politicianId);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleCreateDonation() {
    global $donation, $politician;
    
    $session = getAuthenticatedUser();
    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['politician_id'], $data['amount'], $data['payment_method'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    // Validate politician exists and accepts donations
    if (!$donation->canAcceptDonations($data['politician_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'This politician is not accepting donations']);
        return;
    }

    // Validate donation amount
    $validation = $donation->validateDonationAmount($data['politician_id'], $data['amount']);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $validation['error']]);
        return;
    }

    // Create donation record
    $donationData = [
        'user_id' => $session['user_id'],
        'politician_id' => $data['politician_id'],
        'amount_usd' => $data['amount'],
        'payment_method' => $data['payment_method'],
        'donor_name' => $data['donor_name'] ?? null,
        'donor_email' => $data['donor_email'] ?? $session['email'],
        'donor_address' => $data['donor_address'] ?? null,
        'is_anonymous' => $data['is_anonymous'] ?? false
    ];

    $donationId = $donation->create($donationData);

    // In a real implementation, you would integrate with payment processors here
    // For now, we'll simulate payment processing
    $paymentResult = simulatePaymentProcessing($data['payment_method'], $data['amount']);

    if ($paymentResult['success']) {
        $donation->processPayment($donationId, $paymentResult['reference']);
        
        echo json_encode([
            'success' => true,
            'donation_id' => $donationId,
            'message' => 'Donation processed successfully',
            'payment_reference' => $paymentResult['reference']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $paymentResult['error']
        ]);
    }
}

function handleProcessPayment() {
    global $donation;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['donation_id'], $data['payment_reference'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    $result = $donation->processPayment($data['donation_id'], $data['payment_reference']);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to process payment']);
    }
}

function handleGetDonationSettings($politicianId) {
    global $donation;
    
    $settings = $donation->getDonationSettings($politicianId);
    
    if ($settings) {
        echo json_encode(['success' => true, 'settings' => $settings]);
    } else {
        // Return default settings
        echo json_encode([
            'success' => true,
            'settings' => [
                'donations_enabled' => false,
                'min_donation_amount' => 5.00,
                'max_donation_amount' => 2800.00,
                'processing_fee_percent' => 3.00
            ]
        ]);
    }
}

function handleUpdateDonationSettings($politicianId) {
    global $donation;
    
    $session = getAuthenticatedUser();
    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    // In a real implementation, you'd check if the user has permission to update this politician's settings
    // For now, we'll allow any authenticated user (admin functionality would be added later)

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        return;
    }

    $result = $donation->updateDonationSettings($politicianId, $data);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Donation settings updated']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to update settings']);
    }
}

function handleGetDonationHistory() {
    global $donation;
    
    $session = getAuthenticatedUser();
    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $donations = $donation->getUserDonations($session['user_id'], $limit);
    
    echo json_encode(['success' => true, 'donations' => $donations]);
}

function handleGetDonationStats($politicianId) {
    global $donation;
    
    $stats = $donation->getDonationStats($politicianId);
    $monthlyTotals = $donation->getMonthlyDonationTotals($politicianId);
    $topDonors = $donation->getTopDonors($politicianId);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'monthly_totals' => $monthlyTotals,
        'top_donors' => $topDonors
    ]);
}

function handleGetRecentDonations() {
    global $donation;
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $donations = $donation->getRecentDonations($limit);
    
    echo json_encode(['success' => true, 'donations' => $donations]);
}

// Helper functions

function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        return str_replace('Bearer ', '', $headers['Authorization']);
    }
    return null;
}

function simulatePaymentProcessing($paymentMethod, $amount) {
    // Simulate payment processing delay
    usleep(500000); // 0.5 second delay
    
    // Simulate 95% success rate
    $success = (rand(1, 100) <= 95);
    
    if ($success) {
        return [
            'success' => true,
            'reference' => 'PAY_' . strtoupper(uniqid())
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Payment processing failed. Please try again.'
        ];
    }
}
?>
