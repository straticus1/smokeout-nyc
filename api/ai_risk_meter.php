<?php
require_once 'config/database.php';
require_once 'auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user = authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

array_shift($segments); // remove 'api'
array_shift($segments); // remove 'ai-risk'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'dispensary':
            handleDispensaryRiskEndpoints($method, $id, $user['id']);
            break;
        case 'enforcement':
            handleEnforcementRiskEndpoints($method, $id, $user['id']);
            break;
        case 'nationwide':
            handleNationwideAnalysisEndpoints($method, $id, $user['id']);
            break;
        case 'realtime':
            handleRealtimeDataEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'AI Risk endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleDispensaryRiskEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/ai-risk/dispensary - Get dispensary risk assessment
            $lat = $_GET['lat'] ?? null;
            $lng = $_GET['lng'] ?? null;
            $address = $_GET['address'] ?? null;
            $city = $_GET['city'] ?? null;
            $state = $_GET['state'] ?? 'NY';
            
            if (!$lat || !$lng) {
                http_response_code(400);
                echo json_encode(['error' => 'Latitude and longitude required']);
                return;
            }
            
            // Check membership limits
            if (!checkMembershipLimits($user_id, 'ai_assessments_per_month')) {
                http_response_code(429);
                echo json_encode(['error' => 'Monthly AI assessment limit reached. Upgrade membership.']);
                return;
            }
            
            // Get comprehensive risk assessment
            $risk_data = calculateDispensaryRisk($lat, $lng, $city, $state, $address);
            
            // Log usage for membership tracking
            logMembershipUsage($user_id, 'ai_assessment');
            
            echo json_encode([
                'risk_assessment' => $risk_data,
                'generated_at' => date('c'),
                'expires_at' => date('c', strtotime('+24 hours'))
            ]);
            break;
            
        case 'POST':
            // POST /api/ai-risk/dispensary - Batch risk assessment
            $data = json_decode(file_get_contents('php://input'), true);
            $locations = $data['locations'] ?? [];
            
            if (count($locations) > 10) {
                http_response_code(400);
                echo json_encode(['error' => 'Maximum 10 locations per batch']);
                return;
            }
            
            $results = [];
            foreach ($locations as $location) {
                if (!checkMembershipLimits($user_id, 'ai_assessments_per_month')) {
                    break; // Stop if limit reached
                }
                
                $risk_data = calculateDispensaryRisk(
                    $location['lat'], 
                    $location['lng'], 
                    $location['city'] ?? null,
                    $location['state'] ?? 'NY',
                    $location['address'] ?? null
                );
                
                $results[] = [
                    'location' => $location,
                    'risk_assessment' => $risk_data
                ];
                
                logMembershipUsage($user_id, 'ai_assessment');
            }
            
            echo json_encode(['batch_results' => $results]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleEnforcementRiskEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/ai-risk/enforcement - Get real-time enforcement risk
            $lat = $_GET['lat'] ?? null;
            $lng = $_GET['lng'] ?? null;
            $radius = $_GET['radius'] ?? 5; // miles
            $city = $_GET['city'] ?? null;
            $state = $_GET['state'] ?? 'NY';
            
            if (!$lat || !$lng) {
                http_response_code(400);
                echo json_encode(['error' => 'Latitude and longitude required']);
                return;
            }
            
            $enforcement_data = calculateEnforcementRisk($lat, $lng, $radius, $city, $state);
            
            echo json_encode([
                'enforcement_risk' => $enforcement_data,
                'location' => ['lat' => $lat, 'lng' => $lng, 'radius' => $radius],
                'last_updated' => date('c')
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleNationwideAnalysisEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/ai-risk/nationwide - Get nationwide risk analysis
            $analysis_type = $_GET['type'] ?? 'overview'; // overview, heatmap, trends
            
            switch ($analysis_type) {
                case 'overview':
                    $data = getNationwideOverview();
                    break;
                case 'heatmap':
                    $data = getNationwideHeatmap();
                    break;
                case 'trends':
                    $data = getNationwideTrends();
                    break;
                default:
                    $data = getNationwideOverview();
            }
            
            echo json_encode([
                'analysis_type' => $analysis_type,
                'data' => $data,
                'generated_at' => date('c')
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleRealtimeDataEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/ai-risk/realtime - Get real-time risk updates
            $data_sources = [
                'news_alerts' => getNewsAlerts(),
                'enforcement_activity' => getEnforcementActivity(),
                'regulatory_changes' => getRegulatoryChanges(),
                'market_conditions' => getMarketConditions()
            ];
            
            echo json_encode([
                'realtime_data' => $data_sources,
                'last_updated' => date('c'),
                'next_update' => date('c', strtotime('+15 minutes'))
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// AI Risk Calculation Functions

function calculateDispensaryRisk($lat, $lng, $city, $state, $address) {
    global $pdo;
    
    // Multi-factor risk assessment
    $risk_factors = [
        'location_risk' => calculateLocationRisk($lat, $lng, $city, $state),
        'regulatory_risk' => calculateRegulatoryRisk($city, $state),
        'enforcement_risk' => calculateEnforcementRisk($lat, $lng, 2, $city, $state),
        'market_risk' => calculateMarketRisk($lat, $lng, $city, $state),
        'competition_risk' => calculateCompetitionRisk($lat, $lng),
        'demographic_risk' => calculateDemographicRisk($lat, $lng),
        'zoning_risk' => calculateZoningRisk($lat, $lng, $address)
    ];
    
    // Weighted risk calculation
    $weights = [
        'location_risk' => 0.15,
        'regulatory_risk' => 0.25,
        'enforcement_risk' => 0.20,
        'market_risk' => 0.15,
        'competition_risk' => 0.10,
        'demographic_risk' => 0.10,
        'zoning_risk' => 0.05
    ];
    
    $overall_risk = 0;
    foreach ($risk_factors as $factor => $score) {
        $overall_risk += $score * $weights[$factor];
    }
    
    // Risk level classification
    $risk_level = 'low';
    if ($overall_risk > 0.7) $risk_level = 'critical';
    elseif ($overall_risk > 0.5) $risk_level = 'high';
    elseif ($overall_risk > 0.3) $risk_level = 'medium';
    
    // Generate recommendations
    $recommendations = generateRecommendations($risk_factors, $overall_risk);
    
    // Store assessment
    $stmt = $pdo->prepare("
        INSERT INTO risk_assessments 
        (assessment_type, latitude, longitude, city, state, risk_score, 
         risk_factors, ai_model_version, confidence_level, expires_at)
        VALUES ('dispensary_risk', ?, ?, ?, ?, ?, ?, 'v2.1', 0.85, DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ");
    $stmt->execute([
        $lat, $lng, $city, $state, $overall_risk, 
        json_encode($risk_factors)
    ]);
    
    return [
        'overall_risk_score' => round($overall_risk, 3),
        'risk_level' => $risk_level,
        'confidence' => 0.85,
        'risk_factors' => $risk_factors,
        'recommendations' => $recommendations,
        'model_version' => 'v2.1'
    ];
}

function calculateLocationRisk($lat, $lng, $city, $state) {
    // Factors: proximity to schools, churches, parks, crime rates
    $risk_score = 0.3; // Base risk
    
    // Simulate proximity checks (would use real APIs)
    $school_distance = rand(500, 2000); // meters
    $crime_rate = rand(1, 10) / 10; // 0.1 to 1.0
    
    if ($school_distance < 1000) $risk_score += 0.2; // Too close to schools
    $risk_score += $crime_rate * 0.3; // Crime rate factor
    
    return min($risk_score, 1.0);
}

function calculateRegulatoryRisk($city, $state) {
    global $pdo;
    
    // Check regulatory environment
    $regulatory_data = [
        'NY' => ['New York' => 0.2, 'Buffalo' => 0.3, 'Rochester' => 0.25],
        'CA' => ['Los Angeles' => 0.15, 'San Francisco' => 0.1],
        'CO' => ['Denver' => 0.1, 'Boulder' => 0.15]
    ];
    
    return $regulatory_data[$state][$city] ?? 0.5; // Default medium risk
}

function calculateEnforcementRisk($lat, $lng, $radius, $city, $state) {
    global $pdo;
    
    // Check recent enforcement activity in area
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as enforcement_count
        FROM risk_assessments ra
        WHERE ra.assessment_type = 'enforcement_risk'
        AND ST_Distance_Sphere(POINT(ra.longitude, ra.latitude), POINT(?, ?)) <= ? * 1609.34
        AND ra.last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$lng, $lat, $radius]);
    $enforcement_count = $stmt->fetchColumn();
    
    // Base enforcement risk + recent activity
    $base_risk = 0.2;
    $activity_risk = min($enforcement_count * 0.1, 0.5);
    
    return min($base_risk + $activity_risk, 1.0);
}

function calculateMarketRisk($lat, $lng, $city, $state) {
    global $pdo;
    
    // Market saturation and demand factors
    $stmt = $pdo->prepare("
        SELECT AVG(market_score) as avg_market_score
        FROM property_listings
        WHERE city = ? AND state = ?
    ");
    $stmt->execute([$city, $state]);
    $market_score = $stmt->fetchColumn() ?? 0.5;
    
    return 1.0 - $market_score; // Inverse of market attractiveness
}

function calculateCompetitionRisk($lat, $lng) {
    global $pdo;
    
    // Count nearby dispensaries
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as competitor_count
        FROM shop_locations sl
        WHERE sl.location_type = 'physical_store'
        AND ST_Distance_Sphere(POINT(sl.longitude, sl.latitude), POINT(?, ?)) <= 1609.34
        AND sl.status = 'active'
    ");
    $stmt->execute([$lng, $lat]);
    $competitor_count = $stmt->fetchColumn();
    
    return min($competitor_count * 0.15, 1.0);
}

function calculateDemographicRisk($lat, $lng) {
    // Demographic factors (age, income, cannabis acceptance)
    // Simulate demographic data (would use census APIs)
    $median_age = rand(25, 65);
    $median_income = rand(30000, 120000);
    
    $age_risk = abs($median_age - 35) / 35 * 0.3; // Optimal age ~35
    $income_risk = $median_income < 50000 ? 0.2 : 0.1;
    
    return min($age_risk + $income_risk, 1.0);
}

function calculateZoningRisk($lat, $lng, $address) {
    // Zoning compliance risk
    // Simulate zoning check (would use municipal APIs)
    $zoning_compliant = rand(0, 1);
    $buffer_zone_clear = rand(0, 1);
    
    $risk = 0.1; // Base zoning risk
    if (!$zoning_compliant) $risk += 0.4;
    if (!$buffer_zone_clear) $risk += 0.2;
    
    return min($risk, 1.0);
}

function generateRecommendations($risk_factors, $overall_risk) {
    $recommendations = [];
    
    if ($risk_factors['enforcement_risk'] > 0.5) {
        $recommendations[] = [
            'type' => 'enforcement',
            'priority' => 'high',
            'message' => 'High enforcement activity detected. Consider enhanced security measures.',
            'actions' => ['Install advanced security system', 'Hire security personnel', 'Review compliance procedures']
        ];
    }
    
    if ($risk_factors['regulatory_risk'] > 0.4) {
        $recommendations[] = [
            'type' => 'regulatory',
            'priority' => 'medium',
            'message' => 'Regulatory environment requires attention.',
            'actions' => ['Consult cannabis attorney', 'Review local ordinances', 'Engage with local officials']
        ];
    }
    
    if ($risk_factors['competition_risk'] > 0.6) {
        $recommendations[] = [
            'type' => 'market',
            'priority' => 'medium',
            'message' => 'High competition in area. Differentiation strategy needed.',
            'actions' => ['Develop unique product offerings', 'Focus on customer service', 'Consider delivery services']
        ];
    }
    
    if ($overall_risk > 0.7) {
        $recommendations[] = [
            'type' => 'general',
            'priority' => 'critical',
            'message' => 'Overall risk is critical. Consider alternative locations.',
            'actions' => ['Explore other locations', 'Delay opening until conditions improve', 'Increase insurance coverage']
        ];
    }
    
    return $recommendations;
}

function getNationwideOverview() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            state,
            AVG(risk_score) as avg_risk,
            COUNT(*) as assessment_count,
            MAX(last_updated) as last_assessment
        FROM risk_assessments
        WHERE assessment_type = 'dispensary_risk'
        AND last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY state
        ORDER BY avg_risk DESC
    ");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNationwideHeatmap() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            latitude, longitude, risk_score, city, state
        FROM risk_assessments
        WHERE assessment_type = 'dispensary_risk'
        AND expires_at > NOW()
        ORDER BY risk_score DESC
        LIMIT 1000
    ");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNationwideTrends() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(last_updated) as date,
            AVG(risk_score) as avg_risk,
            COUNT(*) as assessment_count
        FROM risk_assessments
        WHERE assessment_type = 'dispensary_risk'
        AND last_updated >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY DATE(last_updated)
        ORDER BY date DESC
    ");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNewsAlerts() {
    // Simulate news API integration
    return [
        [
            'title' => 'New Cannabis Regulations Announced in NY',
            'source' => 'Cannabis Business Times',
            'impact' => 'medium',
            'timestamp' => date('c', strtotime('-2 hours'))
        ],
        [
            'title' => 'DEA Enforcement Activity Increases in Northeast',
            'source' => 'Marijuana Moment',
            'impact' => 'high',
            'timestamp' => date('c', strtotime('-6 hours'))
        ]
    ];
}

function getEnforcementActivity() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT city, state, COUNT(*) as incident_count
        FROM risk_assessments
        WHERE assessment_type = 'enforcement_risk'
        AND last_updated >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY city, state
        ORDER BY incident_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRegulatoryChanges() {
    // Simulate regulatory tracking
    return [
        [
            'jurisdiction' => 'New York State',
            'change_type' => 'licensing',
            'description' => 'New social equity provisions added to licensing requirements',
            'effective_date' => date('Y-m-d', strtotime('+30 days')),
            'impact_level' => 'medium'
        ]
    ];
}

function getMarketConditions() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT region, metric_name, AVG(metric_value) as avg_value
        FROM market_data
        WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY region, metric_name
        ORDER BY region, metric_name
    ");
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Utility Functions

function checkMembershipLimits($user_id, $limit_type) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT mt.limits_config
        FROM user_memberships um
        JOIN membership_tiers mt ON um.tier_id = mt.id
        WHERE um.user_id = ? AND um.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $membership = $stmt->fetch();
    
    if (!$membership) {
        return false; // No membership = no access
    }
    
    $limits = json_decode($membership['limits_config'], true);
    $limit = $limits[$limit_type] ?? 0;
    
    if ($limit === -1) {
        return true; // Unlimited
    }
    
    // Check current usage (simplified)
    return $limit > 0;
}

function logMembershipUsage($user_id, $usage_type) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO revenue_transactions 
        (shop_owner_id, transaction_type, gross_amount, platform_fee_amount, net_amount, status)
        SELECT so.id, 'api_usage', 0.00, 0.00, 0.00, 'completed'
        FROM shop_owners so WHERE so.user_id = ?
    ");
    $stmt->execute([$user_id]);
}
?>
