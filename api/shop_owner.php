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
array_shift($segments); // remove 'shop'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'profile':
            handleShopProfileEndpoints($method, $id, $user['id']);
            break;
        case 'locations':
            handleLocationEndpoints($method, $id, $user['id']);
            break;
        case 'properties':
            handlePropertyEndpoints($method, $id, $user['id']);
            break;
        case 'street-selling':
            handleStreetSellingEndpoints($method, $id, $user['id']);
            break;
        case 'revenue':
            handleRevenueEndpoints($method, $id, $user['id']);
            break;
        case 'risk-assessment':
            handleRiskAssessmentEndpoints($method, $id, $user['id']);
            break;
        case 'market-data':
            handleMarketDataEndpoints($method, $id, $user['id']);
            break;
        case 'compliance':
            handleComplianceEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Shop endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleShopProfileEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/shop/profile - Get shop owner profile
            $stmt = $pdo->prepare("
                SELECT so.*, mt.tier_name, mt.features, um.expires_at as membership_expires
                FROM shop_owners so
                LEFT JOIN user_memberships um ON so.user_id = um.user_id AND um.status = 'active'
                LEFT JOIN membership_tiers mt ON um.tier_id = mt.id
                WHERE so.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$profile) {
                echo json_encode(['shop_owner' => null, 'is_shop_owner' => false]);
                return;
            }
            
            // Get location count and revenue stats
            $stats_stmt = $pdo->prepare("
                SELECT 
                    COUNT(sl.id) as location_count,
                    COALESCE(SUM(rt.net_amount), 0) as total_revenue,
                    COALESCE(SUM(rt.platform_fee_amount), 0) as total_fees_paid
                FROM shop_locations sl
                LEFT JOIN revenue_transactions rt ON sl.shop_owner_id = rt.shop_owner_id
                WHERE sl.shop_owner_id = ? AND sl.status = 'active'
            ");
            $stats_stmt->execute([$profile['id']]);
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'shop_owner' => $profile,
                'stats' => $stats,
                'is_shop_owner' => true
            ]);
            break;
            
        case 'POST':
            // POST /api/shop/profile - Create shop owner profile
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Check if user already has shop profile
            $check_stmt = $pdo->prepare("SELECT id FROM shop_owners WHERE user_id = ?");
            $check_stmt->execute([$user_id]);
            if ($check_stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Shop owner profile already exists']);
                return;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO shop_owners 
                (user_id, business_name, business_type, license_number, business_address, 
                 phone_number, email, website_url, established_date, employee_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $data['business_name'],
                $data['business_type'],
                $data['license_number'] ?? null,
                $data['business_address'] ?? null,
                $data['phone_number'] ?? null,
                $data['email'] ?? null,
                $data['website_url'] ?? null,
                $data['established_date'] ?? null,
                $data['employee_count'] ?? 1
            ]);
            
            $shop_owner_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'shop_owner_id' => $shop_owner_id,
                'message' => 'Shop owner profile created successfully'
            ]);
            break;
            
        case 'PUT':
            // PUT /api/shop/profile - Update shop owner profile
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $pdo->prepare("
                UPDATE shop_owners SET
                    business_name = COALESCE(?, business_name),
                    business_type = COALESCE(?, business_type),
                    license_number = COALESCE(?, license_number),
                    business_address = COALESCE(?, business_address),
                    phone_number = COALESCE(?, phone_number),
                    email = COALESCE(?, email),
                    website_url = COALESCE(?, website_url),
                    employee_count = COALESCE(?, employee_count)
                WHERE user_id = ?
            ");
            
            $stmt->execute([
                $data['business_name'] ?? null,
                $data['business_type'] ?? null,
                $data['license_number'] ?? null,
                $data['business_address'] ?? null,
                $data['phone_number'] ?? null,
                $data['email'] ?? null,
                $data['website_url'] ?? null,
                $data['employee_count'] ?? null,
                $user_id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleLocationEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/shop/locations/{id} - Get specific location
                $stmt = $pdo->prepare("
                    SELECT sl.*, pl.address as property_address, pl.square_footage,
                           ra.risk_score, ra.risk_factors
                    FROM shop_locations sl
                    LEFT JOIN property_listings pl ON sl.property_listing_id = pl.id
                    LEFT JOIN risk_assessments ra ON ra.location_id = sl.id
                    JOIN shop_owners so ON sl.shop_owner_id = so.id
                    WHERE sl.id = ? AND so.user_id = ?
                ");
                $stmt->execute([$id, $user_id]);
                $location = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$location) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Location not found']);
                    return;
                }
                
                echo json_encode(['location' => $location]);
            } else {
                // GET /api/shop/locations - Get all user's locations
                $stmt = $pdo->prepare("
                    SELECT sl.*, pl.address as property_address
                    FROM shop_locations sl
                    LEFT JOIN property_listings pl ON sl.property_listing_id = pl.id
                    JOIN shop_owners so ON sl.shop_owner_id = so.id
                    WHERE so.user_id = ?
                    ORDER BY sl.opened_at DESC
                ");
                $stmt->execute([$user_id]);
                $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['locations' => $locations]);
            }
            break;
            
        case 'POST':
            // POST /api/shop/locations - Purchase/rent a location
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Get shop owner
            $shop_stmt = $pdo->prepare("SELECT id FROM shop_owners WHERE user_id = ?");
            $shop_stmt->execute([$user_id]);
            $shop_owner = $shop_stmt->fetch();
            
            if (!$shop_owner) {
                http_response_code(400);
                echo json_encode(['error' => 'Must be a shop owner to purchase locations']);
                return;
            }
            
            // Get property details
            $prop_stmt = $pdo->prepare("SELECT * FROM property_listings WHERE id = ? AND is_available = TRUE");
            $prop_stmt->execute([$data['property_listing_id']]);
            $property = $prop_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$property) {
                http_response_code(404);
                echo json_encode(['error' => 'Property not available']);
                return;
            }
            
            // Check if user can afford it (simplified - would integrate with payment system)
            $cost = $data['transaction_type'] === 'purchase' ? $property['purchase_price'] : $property['monthly_rent'];
            
            try {
                $pdo->beginTransaction();
                
                // Create location
                $location_stmt = $pdo->prepare("
                    INSERT INTO shop_locations 
                    (shop_owner_id, property_listing_id, location_name, location_type, 
                     address, latitude, longitude, purchase_price, monthly_operating_cost)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $location_stmt->execute([
                    $shop_owner['id'],
                    $data['property_listing_id'],
                    $data['location_name'],
                    $data['location_type'] ?? 'physical_store',
                    $property['address'],
                    $property['latitude'],
                    $property['longitude'],
                    $data['transaction_type'] === 'purchase' ? $cost : 0,
                    $data['transaction_type'] === 'rent' ? $cost : 0
                ]);
                
                $location_id = $pdo->lastInsertId();
                
                // Process payment transaction
                $pdo->prepare("CALL ProcessRevenueTransaction(?, ?, ?, ?, ?, @transaction_id)")
                    ->execute([
                        $shop_owner['id'],
                        $data['transaction_type'] === 'purchase' ? 'purchase' : 'rent',
                        $cost,
                        null,
                        $location_id
                    ]);
                
                // Mark property as unavailable if purchased
                if ($data['transaction_type'] === 'purchase') {
                    $pdo->prepare("UPDATE property_listings SET is_available = FALSE WHERE id = ?")
                        ->execute([$data['property_listing_id']]);
                }
                
                // Generate AI risk assessment
                $pdo->prepare("CALL CalculateAIRiskScore(?, ?, ?, ?, @risk_score)")
                    ->execute([$property['latitude'], $property['longitude'], $property['city'], $property['state']]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'location_id' => $location_id,
                    'cost' => $cost,
                    'message' => 'Location acquired successfully'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handlePropertyEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/shop/properties/{id} - Get specific property
                $stmt = $pdo->prepare("
                    SELECT pl.*, ra.risk_score, ra.enforcement_history
                    FROM property_listings pl
                    LEFT JOIN risk_assessments ra ON ra.latitude = pl.latitude AND ra.longitude = pl.longitude
                    WHERE pl.id = ? AND pl.is_available = TRUE
                ");
                $stmt->execute([$id]);
                $property = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['property' => $property]);
            } else {
                // GET /api/shop/properties - Search available properties
                $filters = $_GET;
                $where_conditions = ['pl.is_available = TRUE'];
                $params = [];
                
                if (!empty($filters['city'])) {
                    $where_conditions[] = 'pl.city = ?';
                    $params[] = $filters['city'];
                }
                
                if (!empty($filters['property_type'])) {
                    $where_conditions[] = 'pl.property_type = ?';
                    $params[] = $filters['property_type'];
                }
                
                if (!empty($filters['max_price'])) {
                    $where_conditions[] = '(pl.purchase_price <= ? OR pl.monthly_rent <= ?)';
                    $params[] = $filters['max_price'];
                    $params[] = $filters['max_price'];
                }
                
                if (!empty($filters['cannabis_eligible'])) {
                    $where_conditions[] = 'pl.cannabis_license_eligible = TRUE';
                }
                
                $where_clause = implode(' AND ', $where_conditions);
                
                $stmt = $pdo->prepare("
                    SELECT pl.*, ra.risk_score
                    FROM property_listings pl
                    LEFT JOIN risk_assessments ra ON ra.latitude = pl.latitude AND ra.longitude = pl.longitude
                    WHERE {$where_clause}
                    ORDER BY pl.market_score DESC, pl.listed_at DESC
                    LIMIT 50
                ");
                
                $stmt->execute($params);
                $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['properties' => $properties]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleStreetSellingEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id === 'spots') {
                // GET /api/shop/street-selling/spots - Get available street spots
                $stmt = $pdo->prepare("
                    SELECT sss.*, 
                           COUNT(active_sessions.id) as active_sellers
                    FROM street_selling_spots sss
                    LEFT JOIN street_selling_sessions active_sessions ON sss.id = active_sessions.spot_id 
                        AND active_sessions.status = 'active'
                    WHERE sss.is_active = TRUE
                    GROUP BY sss.id
                    ORDER BY sss.profit_potential DESC, sss.bust_risk ASC
                ");
                $stmt->execute();
                $spots = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['spots' => $spots]);
            } else {
                // GET /api/shop/street-selling - Get user's street selling sessions
                $stmt = $pdo->prepare("
                    SELECT sss.*, spot.name as spot_name, spot.neighborhood
                    FROM street_selling_sessions sss
                    JOIN street_selling_spots spot ON sss.spot_id = spot.id
                    JOIN game_players gp ON sss.player_id = gp.id
                    WHERE gp.user_id = ?
                    ORDER BY sss.started_at DESC
                    LIMIT 20
                ");
                $stmt->execute([$user_id]);
                $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['sessions' => $sessions]);
            }
            break;
            
        case 'POST':
            // POST /api/shop/street-selling - Start street selling session
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Get player
            $player_stmt = $pdo->prepare("SELECT id FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch();
            
            if (!$player) {
                http_response_code(400);
                echo json_encode(['error' => 'Game player profile required']);
                return;
            }
            
            // Check if spot is available
            $spot_stmt = $pdo->prepare("
                SELECT sss.*, COUNT(active.id) as active_count
                FROM street_selling_spots sss
                LEFT JOIN street_selling_sessions active ON sss.id = active.spot_id AND active.status = 'active'
                WHERE sss.id = ? AND sss.is_active = TRUE
                GROUP BY sss.id
            ");
            $spot_stmt->execute([$data['spot_id']]);
            $spot = $spot_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$spot) {
                http_response_code(404);
                echo json_encode(['error' => 'Street spot not found']);
                return;
            }
            
            // Check competition limit (max 3 sellers per spot)
            if ($spot['active_count'] >= 3) {
                http_response_code(400);
                echo json_encode(['error' => 'Spot is too crowded']);
                return;
            }
            
            // Start session
            $session_stmt = $pdo->prepare("
                INSERT INTO street_selling_sessions (player_id, spot_id, status)
                VALUES (?, ?, 'active')
            ");
            $session_stmt->execute([$player['id'], $data['spot_id']]);
            
            $session_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'session_id' => $session_id,
                'spot' => $spot,
                'message' => 'Street selling session started'
            ]);
            break;
            
        case 'PUT':
            // PUT /api/shop/street-selling/{id} - End street selling session
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Get session
            $session_stmt = $pdo->prepare("
                SELECT sss.*, gp.user_id
                FROM street_selling_sessions sss
                JOIN game_players gp ON sss.player_id = gp.id
                WHERE sss.id = ? AND sss.status = 'active'
            ");
            $session_stmt->execute([$id]);
            $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session || $session['user_id'] != $user_id) {
                http_response_code(404);
                echo json_encode(['error' => 'Session not found']);
                return;
            }
            
            // Calculate results (simplified)
            $duration_hours = (time() - strtotime($session['started_at'])) / 3600;
            $base_revenue = $duration_hours * rand(50, 200); // Random revenue
            $expenses = $base_revenue * 0.1; // 10% expenses
            $net_profit = $base_revenue - $expenses;
            $platform_fee = $net_profit * 0.1; // 10% platform fee
            $final_profit = $net_profit - $platform_fee;
            
            // Update session
            $update_stmt = $pdo->prepare("
                UPDATE street_selling_sessions SET
                    ended_at = NOW(),
                    gross_revenue = ?,
                    expenses = ?,
                    net_profit = ?,
                    platform_fee = ?,
                    status = 'completed'
                WHERE id = ?
            ");
            $update_stmt->execute([$base_revenue, $expenses, $net_profit, $platform_fee, $id]);
            
            // Add tokens to player
            $pdo->prepare("UPDATE game_players SET tokens = tokens + ? WHERE id = ?")
                ->execute([$final_profit, $session['player_id']]);
            
            echo json_encode([
                'success' => true,
                'results' => [
                    'duration_hours' => round($duration_hours, 2),
                    'gross_revenue' => $base_revenue,
                    'expenses' => $expenses,
                    'platform_fee' => $platform_fee,
                    'net_profit' => $final_profit
                ]
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleRiskAssessmentEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/shop/risk-assessment - Get risk assessment for location
            $lat = $_GET['lat'] ?? null;
            $lng = $_GET['lng'] ?? null;
            $city = $_GET['city'] ?? null;
            $state = $_GET['state'] ?? 'NY';
            
            if (!$lat || !$lng) {
                http_response_code(400);
                echo json_encode(['error' => 'Latitude and longitude required']);
                return;
            }
            
            // Check for existing recent assessment
            $existing_stmt = $pdo->prepare("
                SELECT * FROM risk_assessments 
                WHERE ABS(latitude - ?) < 0.001 AND ABS(longitude - ?) < 0.001
                AND assessment_type = 'dispensary_risk'
                AND expires_at > NOW()
                ORDER BY last_updated DESC
                LIMIT 1
            ");
            $existing_stmt->execute([$lat, $lng]);
            $existing = $existing_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                echo json_encode(['risk_assessment' => $existing]);
                return;
            }
            
            // Generate new assessment
            $pdo->prepare("CALL CalculateAIRiskScore(?, ?, ?, ?, @risk_score)")
                ->execute([$lat, $lng, $city, $state]);
            
            // Get the new assessment
            $new_stmt = $pdo->prepare("
                SELECT * FROM risk_assessments 
                WHERE latitude = ? AND longitude = ? 
                ORDER BY last_updated DESC 
                LIMIT 1
            ");
            $new_stmt->execute([$lat, $lng]);
            $assessment = $new_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['risk_assessment' => $assessment]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleMarketDataEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/shop/market-data - Get market data for region
            $region = $_GET['region'] ?? 'New York';
            $data_type = $_GET['type'] ?? 'price';
            
            $stmt = $pdo->prepare("
                SELECT * FROM market_data 
                WHERE region = ? AND data_type = ?
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY recorded_at DESC
                LIMIT 50
            ");
            $stmt->execute([$region, $data_type]);
            $market_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['market_data' => $market_data]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleRevenueEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/shop/revenue - Get revenue analytics
            $shop_stmt = $pdo->prepare("SELECT id FROM shop_owners WHERE user_id = ?");
            $shop_stmt->execute([$user_id]);
            $shop_owner = $shop_stmt->fetch();
            
            if (!$shop_owner) {
                echo json_encode(['revenue' => [], 'analytics' => null]);
                return;
            }
            
            // Get revenue transactions
            $revenue_stmt = $pdo->prepare("
                SELECT rt.*, sl.location_name
                FROM revenue_transactions rt
                LEFT JOIN shop_locations sl ON rt.location_id = sl.id
                WHERE rt.shop_owner_id = ?
                ORDER BY rt.processed_at DESC
                LIMIT 100
            ");
            $revenue_stmt->execute([$shop_owner['id']]);
            $transactions = $revenue_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get analytics
            $analytics_stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_transactions,
                    SUM(gross_amount) as total_gross,
                    SUM(net_amount) as total_net,
                    SUM(platform_fee_amount) as total_fees,
                    AVG(gross_amount) as avg_transaction,
                    DATE(processed_at) as date,
                    COUNT(*) as daily_count
                FROM revenue_transactions 
                WHERE shop_owner_id = ? AND processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(processed_at)
                ORDER BY date DESC
            ");
            $analytics_stmt->execute([$shop_owner['id']]);
            $analytics = $analytics_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'transactions' => $transactions,
                'analytics' => $analytics
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleComplianceEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/shop/compliance - Get compliance records
            $shop_stmt = $pdo->prepare("SELECT id FROM shop_owners WHERE user_id = ?");
            $shop_stmt->execute([$user_id]);
            $shop_owner = $shop_stmt->fetch();
            
            if (!$shop_owner) {
                echo json_encode(['compliance_records' => []]);
                return;
            }
            
            $stmt = $pdo->prepare("
                SELECT * FROM compliance_records 
                WHERE shop_owner_id = ?
                ORDER BY due_date ASC
            ");
            $stmt->execute([$shop_owner['id']]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['compliance_records' => $records]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}
?>
