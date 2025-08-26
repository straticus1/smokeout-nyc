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
            if ($endpoint === 'ai-attorney-match') {
                getAIAttorneyRecommendations();
            } elseif ($endpoint === 'attorneys') {
                getLegalAttorneys();
            } elseif ($endpoint === 'consultations') {
                getUserConsultations();
            } elseif ($endpoint === 'attorney-analytics') {
                getAttorneyAnalytics();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
        case 'POST':
            if ($endpoint === 'request-consultation') {
                requestLegalConsultation();
            } elseif ($endpoint === 'ai-case-analysis') {
                performAICaseAnalysis();
            } elseif ($endpoint === 'submit-review') {
                submitAttorneyReview();
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

function getAIAttorneyRecommendations() {
    global $pdo, $user_id;
    
    if (!checkPremiumAccess($user_id)) {
        throw new Exception('AI attorney matching is available to premium members only');
    }
    
    $case_type = $_GET['case_type'] ?? '';
    $state = $_GET['state'] ?? '';
    $budget_range = $_GET['budget_range'] ?? '';
    
    if (empty($case_type) || empty($state)) {
        throw new Exception('Case type and state are required for AI matching');
    }
    
    $recommendations = generateAIAttorneyRecommendations($case_type, $state, $budget_range);
    
    logAIUsage($user_id, 'attorney_matching', [
        'case_type' => $case_type,
        'state' => $state,
        'recommendations_count' => count($recommendations)
    ]);
    
    echo json_encode([
        'success' => true,
        'recommendations' => $recommendations,
        'ai_confidence' => calculateRecommendationConfidence($recommendations)
    ]);
}

function generateAIAttorneyRecommendations($case_type, $state, $budget_range) {
    global $pdo;
    
    $sql = "
        SELECT 
            a.*,
            lf.name as firm_name,
            (
                (CASE WHEN JSON_CONTAINS(a.specialties, ?) THEN 40 ELSE 0 END) +
                (a.success_rate * 0.25) +
                (a.client_rating * 4) +
                (LEAST(a.cannabis_experience_years, 10) * 1) +
                (CASE WHEN a.availability_status = 'available' THEN 5 ELSE 0 END)
            ) as ai_score
        FROM legal_attorneys a
        LEFT JOIN legal_firms lf ON a.firm_id = lf.id
        WHERE a.is_active = TRUE
        AND JSON_CONTAINS(a.states_licensed, ?)
        AND (a.cannabis_experience_years > 0 OR JSON_CONTAINS(a.specialties, '\"cannabis\"'))
    ";
    
    $params = [json_encode($case_type), json_encode($state)];
    $sql .= " ORDER BY ai_score DESC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attorneys = $stmt->fetchAll();
    
    foreach ($attorneys as &$attorney) {
        $attorney['ai_insights'] = generateAttorneyInsights($attorney, $case_type);
        $attorney['match_reasons'] = generateMatchReasons($attorney, $case_type);
    }
    
    return $attorneys;
}

function generateAttorneyInsights($attorney, $case_type) {
    $insights = [];
    
    if ($attorney['cannabis_experience_years'] >= 5) {
        $insights[] = [
            'type' => 'experience',
            'message' => "Extensive cannabis law experience ({$attorney['cannabis_experience_years']} years)",
            'impact' => 'positive'
        ];
    }
    
    if ($attorney['success_rate'] >= 85) {
        $insights[] = [
            'type' => 'performance',
            'message' => "High success rate ({$attorney['success_rate']}%) in similar cases",
            'impact' => 'positive'
        ];
    }
    
    return $insights;
}

function generateMatchReasons($attorney, $case_type) {
    $reasons = [];
    $specialties = json_decode($attorney['specialties'], true) ?? [];
    
    if (in_array($case_type, $specialties)) {
        $reasons[] = "Specializes in {$case_type} cases";
    }
    
    if (in_array('cannabis', $specialties)) {
        $reasons[] = "Cannabis law specialist";
    }
    
    if ($attorney['success_rate'] >= 90) {
        $reasons[] = "Exceptional success rate";
    }
    
    return $reasons;
}

function calculateRecommendationConfidence($recommendations) {
    if (empty($recommendations)) {
        return 0;
    }
    
    $total_score = 0;
    foreach ($recommendations as $rec) {
        $total_score += $rec['ai_score'];
    }
    
    $avg_score = $total_score / count($recommendations);
    return round(($avg_score / 100) * 100, 1);
}

function performAICaseAnalysis() {
    global $pdo, $user_id;
    
    if (!checkPremiumAccess($user_id)) {
        throw new Exception('AI case analysis is available to premium members only');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $case_description = $input['case_description'] ?? '';
    $case_type = $input['case_type'] ?? '';
    $state = $input['state'] ?? '';
    
    if (empty($case_description)) {
        throw new Exception('Case description is required');
    }
    
    $analysis = analyzeCase($case_description, $case_type, $state);
    
    $stmt = $pdo->prepare("
        INSERT INTO ai_case_analyses (user_id, case_description, case_type, state, 
                                    analysis_results, confidence_score, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $case_description,
        $case_type,
        $state,
        json_encode($analysis),
        $analysis['confidence_score']
    ]);
    
    logAIUsage($user_id, 'case_analysis', ['case_type' => $case_type]);
    
    echo json_encode([
        'success' => true,
        'analysis' => $analysis
    ]);
}

function analyzeCase($case_description, $case_type, $state) {
    $analysis = [
        'case_complexity' => 'medium',
        'estimated_duration' => '3-6 months',
        'success_probability' => 75,
        'estimated_cost_range' => ['min' => 5000, 'max' => 15000],
        'confidence_score' => 0.8
    ];
    
    $complexity_indicators = [
        'high' => ['federal', 'criminal', 'appeal'],
        'medium' => ['compliance', 'licensing', 'contract'],
        'low' => ['consultation', 'review', 'formation']
    ];
    
    $description_lower = strtolower($case_description);
    
    foreach ($complexity_indicators as $level => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($description_lower, $keyword) !== false) {
                $analysis['case_complexity'] = $level;
                break 2;
            }
        }
    }
    
    switch ($analysis['case_complexity']) {
        case 'high':
            $analysis['estimated_duration'] = '12-24 months';
            $analysis['success_probability'] = 60;
            $analysis['estimated_cost_range'] = ['min' => 25000, 'max' => 100000];
            break;
        case 'low':
            $analysis['estimated_duration'] = '1-3 months';
            $analysis['success_probability'] = 85;
            $analysis['estimated_cost_range'] = ['min' => 1000, 'max' => 8000];
            break;
    }
    
    return $analysis;
}

function getLegalAttorneys() {
    global $pdo;
    
    $state = $_GET['state'] ?? '';
    $specialty = $_GET['specialty'] ?? '';
    
    $sql = "
        SELECT a.*, lf.name as firm_name,
               (SELECT AVG(rating) FROM attorney_reviews WHERE attorney_id = a.id) as avg_review_rating
        FROM legal_attorneys a
        LEFT JOIN legal_firms lf ON a.firm_id = lf.id
        WHERE a.is_active = TRUE
    ";
    $params = [];
    
    if (!empty($state)) {
        $sql .= " AND JSON_CONTAINS(a.states_licensed, ?)";
        $params[] = json_encode($state);
    }
    
    if (!empty($specialty)) {
        $sql .= " AND JSON_CONTAINS(a.specialties, ?)";
        $params[] = json_encode($specialty);
    }
    
    $sql .= " ORDER BY a.client_rating DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attorneys = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'attorneys' => $attorneys
    ]);
}

function checkPremiumAccess($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT tier FROM memberships 
        WHERE user_id = ? AND status = 'active' 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$user_id]);
    $membership = $stmt->fetch();
    
    return $membership && in_array($membership['tier'], ['premium', 'enterprise']);
}

function logAIUsage($user_id, $feature_type, $metadata) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO ai_usage_logs (user_id, feature_type, metadata, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $feature_type, json_encode($metadata)]);
}

function requestLegalConsultation() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $attorney_id = $input['attorney_id'] ?? 0;
    $case_description = $input['case_description'] ?? '';
    $preferred_date = $input['preferred_date'] ?? '';
    
    if (!$attorney_id) {
        throw new Exception('Attorney ID is required');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO legal_consultations (user_id, attorney_id, case_description, 
                                       preferred_date, status, created_at)
        VALUES (?, ?, ?, ?, 'requested', NOW())
    ");
    $stmt->execute([$user_id, $attorney_id, $case_description, $preferred_date]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Consultation request submitted successfully'
    ]);
}

function getUserConsultations() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("
        SELECT lc.*, a.name as attorney_name, lf.name as firm_name
        FROM legal_consultations lc
        JOIN legal_attorneys a ON lc.attorney_id = a.id
        LEFT JOIN legal_firms lf ON a.firm_id = lf.id
        WHERE lc.user_id = ?
        ORDER BY lc.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $consultations = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'consultations' => $consultations
    ]);
}

function submitAttorneyReview() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $attorney_id = $input['attorney_id'] ?? 0;
    $rating = $input['rating'] ?? 0;
    $review_text = $input['review_text'] ?? '';
    
    if (!$attorney_id || !$rating) {
        throw new Exception('Attorney ID and rating are required');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO attorney_reviews (user_id, attorney_id, rating, review_text, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $attorney_id, $rating, $review_text]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully'
    ]);
}

function getAttorneyAnalytics() {
    global $pdo, $user_id;
    
    if (!checkPremiumAccess($user_id)) {
        throw new Exception('Attorney analytics are available to premium members only');
    }
    
    $attorney_id = $_GET['attorney_id'] ?? 0;
    
    if (!$attorney_id) {
        throw new Exception('Attorney ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_cases,
            AVG(outcome_rating) as avg_outcome_rating,
            COUNT(CASE WHEN outcome_rating >= 4 THEN 1 END) as successful_cases
        FROM legal_case_outcomes 
        WHERE attorney_id = ?
    ");
    $stmt->execute([$attorney_id]);
    $analytics = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'analytics' => $analytics
    ]);
}
?>
