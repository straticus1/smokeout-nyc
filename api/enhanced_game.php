<?php
/**
 * Enhanced Gaming API with Advanced Features
 * Integrates sophisticated gaming mechanics including genetics, weather, multiplayer
 */

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/models/Game.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $user = authenticate();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    $endpoint = $pathParts[count($pathParts) - 1] ?? '';

    // Log API access
    logApiAccess($user, "enhanced_game/{$endpoint}", $method);

    // Rate limiting
    checkRateLimit($user['id'] ?? $_SERVER['REMOTE_ADDR'], 200, 60); // 200 requests per hour

    switch ($method) {
        case 'GET':
            handleGetRequest($endpoint, $user);
            break;
        case 'POST':
            handlePostRequest($endpoint, $user);
            break;
        case 'PUT':
            handlePutRequest($endpoint, $user);
            break;
        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }

} catch (Exception $e) {
    error_log("Enhanced Gaming API Error: " . $e->getMessage());
    sendJsonResponse(['error' => 'Internal server error'], 500);
}

/**
 * Handle GET requests for enhanced gaming features
 */
function handleGetRequest($endpoint, $user) {
    global $pdo;

    switch ($endpoint) {
        case 'genetics':
            getAdvancedGenetics($user['id']);
            break;
        case 'weather':
            getWeatherEffects($user['id']);
            break;
        case 'multiplayer':
            getMultiplayerOptions($user['id']);
            break;
        case 'market':
            getAdvancedMarketData($user['id']);
            break;
        case 'session':
            getCurrentSession($user['id']);
            break;
        case 'achievements':
            getAdvancedAchievements($user['id']);
            break;
        case 'analytics':
            getGameAnalytics($user['id']);
            break;
        default:
            sendJsonResponse(['error' => 'Endpoint not found'], 404);
    }
}

/**
 * Handle POST requests for enhanced gaming actions
 */
function handlePostRequest($endpoint, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($endpoint) {
        case 'crossbreed':
            createCrossbreedStrain($user['id'], $input);
            break;
        case 'multiplayer-room':
            createMultiplayerRoom($user['id'], $input);
            break;
        case 'join-room':
            joinMultiplayerRoom($user['id'], $input);
            break;
        case 'trade':
            createAdvancedTrade($user['id'], $input);
            break;
        case 'weather-forecast':
            generateWeatherForecast($user['id'], $input);
            break;
        case 'genetics-analysis':
            analyzeGenetics($user['id'], $input);
            break;
        case 'start-session':
            startEnhancedSession($user['id'], $input);
            break;
        default:
            sendJsonResponse(['error' => 'Endpoint not found'], 404);
    }
}

/**
 * Get advanced genetics data for user's strains
 */
function getAdvancedGenetics($userId) {
    global $pdo;
    
    try {
        // Get user's strain genetics
        $stmt = $pdo->prepare("
            SELECT sg.*, s.name as strain_name, s.type as strain_type,
                   p1.name as parent1_name, p2.name as parent2_name
            FROM strain_genetics sg
            JOIN strains s ON sg.strain_id = s.id
            LEFT JOIN strains p1 ON sg.parent1_strain_id = p1.id
            LEFT JOIN strains p2 ON sg.parent2_strain_id = p2.id
            WHERE sg.created_by_user_id = ? OR sg.is_public = 1
            ORDER BY sg.rarity_score DESC, sg.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $genetics = $stmt->fetchAll();
        
        // Get crossbreeding opportunities
        $crossbreedingOpportunities = getCrossbreedingOpportunities($userId);
        
        sendJsonResponse([
            'genetics' => $genetics,
            'crossbreeding_opportunities' => $crossbreedingOpportunities,
            'breeding_lab_level' => getBreedingLabLevel($userId)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to fetch genetics data'], 500);
    }
}

/**
 * Get current weather effects for user's locations
 */
function getWeatherEffects($userId) {
    global $pdo;
    
    try {
        // Get user's active plants with weather effects
        $stmt = $pdo->prepare("
            SELECT p.id, p.stage, p.health, p.location_id,
                   gl.name as location_name,
                   we.weather_type, we.current_value, we.optimal_min, we.optimal_max,
                   we.stress_factor, we.affects_growth_rate, we.affects_yield
            FROM plants p
            JOIN growing_locations gl ON p.location_id = gl.id
            LEFT JOIN weather_events we ON gl.id = we.location_id 
                AND we.event_end > NOW()
            WHERE p.player_id = (
                SELECT id FROM game_players WHERE user_id = ?
            )
            AND p.stage != 'harvested'
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$userId]);
        $weatherData = $stmt->fetchAll();
        
        // Group by plant and calculate overall weather impact
        $plantsWithWeather = [];
        foreach ($weatherData as $row) {
            $plantId = $row['id'];
            if (!isset($plantsWithWeather[$plantId])) {
                $plantsWithWeather[$plantId] = [
                    'plant_id' => $plantId,
                    'stage' => $row['stage'],
                    'health' => $row['health'],
                    'location' => $row['location_name'],
                    'weather_effects' => [],
                    'overall_stress' => 0.0
                ];
            }
            
            if ($row['weather_type']) {
                $plantsWithWeather[$plantId]['weather_effects'][] = [
                    'type' => $row['weather_type'],
                    'current' => $row['current_value'],
                    'optimal_range' => [$row['optimal_min'], $row['optimal_max']],
                    'stress_factor' => $row['stress_factor'],
                    'affects_growth' => $row['affects_growth_rate'],
                    'affects_yield' => $row['affects_yield']
                ];
                $plantsWithWeather[$plantId]['overall_stress'] += $row['stress_factor'];
            }
        }
        
        sendJsonResponse([
            'plants_weather' => array_values($plantsWithWeather),
            'weather_forecast' => getWeatherForecast($userId)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to fetch weather data'], 500);
    }
}

/**
 * Get available multiplayer options
 */
function getMultiplayerOptions($userId) {
    global $pdo;
    
    try {
        // Get active multiplayer rooms
        $stmt = $pdo->prepare("
            SELECT mr.*, u.username as creator_name,
                   COUNT(mp.id) as participant_count
            FROM multiplayer_rooms mr
            JOIN users u ON mr.created_by_user_id = u.id
            LEFT JOIN multiplayer_participants mp ON mr.id = mp.room_id AND mp.is_active = 1
            WHERE mr.is_active = 1 
            AND (mr.is_private = 0 OR mr.created_by_user_id = ?)
            GROUP BY mr.id
            ORDER BY mr.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        $availableRooms = $stmt->fetchAll();
        
        // Get user's current room participation
        $stmt = $pdo->prepare("
            SELECT mr.*, mp.participant_role, mp.join_time
            FROM multiplayer_participants mp
            JOIN multiplayer_rooms mr ON mp.room_id = mr.id
            WHERE mp.user_id = ? AND mp.is_active = 1 AND mr.is_active = 1
        ");
        $stmt->execute([$userId]);
        $currentRooms = $stmt->fetchAll();
        
        sendJsonResponse([
            'available_rooms' => $availableRooms,
            'current_rooms' => $currentRooms,
            'can_create_room' => canCreateRoom($userId)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to fetch multiplayer data'], 500);
    }
}

/**
 * Get advanced market data with micro-economics
 */
function getAdvancedMarketData($userId) {
    global $pdo;
    
    try {
        // Get current market conditions
        $stmt = $pdo->prepare("
            SELECT mm.*, gl.name as location_name, s.name as strain_name,
                   s.type as strain_type
            FROM market_microeconomics mm
            JOIN growing_locations gl ON mm.location_id = gl.id
            JOIN strains s ON mm.strain_id = s.id
            WHERE mm.expires_at > NOW()
            ORDER BY mm.price_per_gram DESC, mm.data_timestamp DESC
            LIMIT 50
        ");
        $stmt->execute();
        $marketData = $stmt->fetchAll();
        
        // Get user's potential sales analysis
        $salesAnalysis = getUserSalesAnalysis($userId);
        
        // Get market trends
        $marketTrends = getMarketTrends();
        
        sendJsonResponse([
            'current_market' => $marketData,
            'sales_analysis' => $salesAnalysis,
            'market_trends' => $marketTrends,
            'trading_level' => getTradingLevel($userId)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to fetch market data'], 500);
    }
}

/**
 * Create crossbred strain with advanced genetics
 */
function createCrossbreedStrain($userId, $input) {
    global $pdo;
    
    try {
        validateRequired($input, ['parent1_strain_id', 'parent2_strain_id', 'crossbreed_name']);
        
        // Verify user owns or has access to parent strains
        $stmt = $pdo->prepare("
            SELECT sg1.*, sg2.*
            FROM strain_genetics sg1, strain_genetics sg2
            WHERE sg1.strain_id = ? AND sg2.strain_id = ?
            AND (sg1.created_by_user_id = ? OR sg1.is_public = 1)
            AND (sg2.created_by_user_id = ? OR sg2.is_public = 1)
        ");
        $stmt->execute([
            $input['parent1_strain_id'], 
            $input['parent2_strain_id'],
            $userId, $userId
        ]);
        $parents = $stmt->fetch();
        
        if (!$parents) {
            sendJsonResponse(['error' => 'Invalid parent strains or access denied'], 400);
            return;
        }
        
        // Generate crossbred genetics using advanced algorithms
        $newGenetics = generateCrossbredGenetics($parents, $input);
        
        // Create new strain record
        $stmt = $pdo->prepare("
            INSERT INTO strains (name, type, thc_min, thc_max, flowering_time_min, 
                               flowering_time_max, difficulty, unlock_level, seed_price, 
                               rarity, description, created_at, updated_at)
            VALUES (?, 'hybrid', ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $input['crossbreed_name'],
            $newGenetics['thc_range'][0],
            $newGenetics['thc_range'][1],
            $newGenetics['flowering_time'][0],
            $newGenetics['flowering_time'][1],
            $newGenetics['difficulty'],
            $newGenetics['base_price'],
            $newGenetics['rarity'],
            $newGenetics['description']
        ]);
        
        $newStrainId = $pdo->lastInsertId();
        
        // Create strain genetics record
        $stmt = $pdo->prepare("
            INSERT INTO strain_genetics (strain_id, parent1_strain_id, parent2_strain_id,
                                       generation, genetic_profile, stability_rating,
                                       vigor_rating, rarity_score, created_by_user_id,
                                       created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $newStrainId,
            $input['parent1_strain_id'],
            $input['parent2_strain_id'],
            max($parents['sg1_generation'], $parents['sg2_generation']) + 1,
            json_encode($newGenetics),
            $newGenetics['stability_rating'],
            $newGenetics['vigor_rating'],
            $newGenetics['rarity'],
            $userId
        ]);
        
        // Award breeding achievement progress
        updateAchievementProgress($userId, 'genetics', ['stable_hybrids_created' => 1]);
        
        sendJsonResponse([
            'success' => true,
            'new_strain_id' => $newStrainId,
            'genetics' => $newGenetics,
            'message' => 'Successfully created new crossbred strain!'
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to create crossbred strain: ' . $e->getMessage()], 500);
    }
}

/**
 * Create multiplayer room
 */
function createMultiplayerRoom($userId, $input) {
    global $pdo;
    
    try {
        validateRequired($input, ['room_name', 'room_type']);
        
        // Check if user can create room (membership limits)
        if (!canCreateRoom($userId)) {
            sendJsonResponse(['error' => 'Room creation limit reached for your membership tier'], 403);
            return;
        }
        
        // Generate unique room code
        $roomCode = generateRoomCode();
        
        $stmt = $pdo->prepare("
            INSERT INTO multiplayer_rooms (room_code, room_name, room_type, max_participants,
                                         room_settings, game_rules, created_by_user_id,
                                         is_private, password_hash, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $roomCode,
            sanitizeInput($input['room_name']),
            $input['room_type'],
            $input['max_participants'] ?? 8,
            json_encode($input['room_settings'] ?? []),
            json_encode($input['game_rules'] ?? []),
            $userId,
            $input['is_private'] ?? false,
            !empty($input['password']) ? password_hash($input['password'], PASSWORD_ARGON2ID) : null
        ]);
        
        $roomId = $pdo->lastInsertId();
        
        // Add creator as host
        $stmt = $pdo->prepare("
            INSERT INTO multiplayer_participants (room_id, user_id, participant_role, 
                                                join_time, is_active)
            VALUES (?, ?, 'host', NOW(), 1)
        ");
        $stmt->execute([$roomId, $userId]);
        
        sendJsonResponse([
            'success' => true,
            'room_id' => $roomId,
            'room_code' => $roomCode,
            'message' => 'Multiplayer room created successfully!'
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to create room: ' . $e->getMessage()], 500);
    }
}

/**
 * Helper Functions
 */

function getCrossbreedingOpportunities($userId) {
    // Return available parent strains for crossbreeding
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.type, sg.rarity_score, sg.genetic_profile
        FROM strains s
        JOIN strain_genetics sg ON s.id = sg.strain_id
        WHERE sg.created_by_user_id = ? OR sg.is_public = 1
        ORDER BY sg.rarity_score DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function generateCrossbredGenetics($parents, $input) {
    // Advanced genetics algorithm
    $parent1Genetics = json_decode($parents['sg1_genetic_profile'], true);
    $parent2Genetics = json_decode($parents['sg2_genetic_profile'], true);
    
    // Simulate genetic inheritance with Mendelian genetics
    $newGenetics = [
        'thc_range' => [
            (($parent1Genetics['thc_range'][0] + $parent2Genetics['thc_range'][0]) / 2) * (0.9 + rand(0, 20)/100),
            (($parent1Genetics['thc_range'][1] + $parent2Genetics['thc_range'][1]) / 2) * (0.9 + rand(0, 20)/100)
        ],
        'flowering_time' => [
            floor(($parent1Genetics['flowering_time'][0] + $parent2Genetics['flowering_time'][0]) / 2),
            ceil(($parent1Genetics['flowering_time'][1] + $parent2Genetics['flowering_time'][1]) / 2)
        ],
        'difficulty' => min(5, max(1, floor(($parent1Genetics['difficulty'] + $parent2Genetics['difficulty']) / 2) + rand(-1, 1))),
        'stability_rating' => max(0.1, min(1.0, (($parents['sg1_stability_rating'] + $parents['sg2_stability_rating']) / 2) * (0.8 + rand(0, 40)/100))),
        'vigor_rating' => max(0.1, min(1.0, (($parents['sg1_vigor_rating'] + $parents['sg2_vigor_rating']) / 2) * (0.9 + rand(0, 20)/100))),
        'rarity' => min(10, max(1, floor(($parents['sg1_rarity_score'] + $parents['sg2_rarity_score']) / 2) + rand(-1, 2))),
        'base_price' => rand(50, 200),
        'description' => "Crossbred strain combining traits from parent genetics"
    ];
    
    return $newGenetics;
}

function generateRoomCode() {
    return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}

function canCreateRoom($userId) {
    // Check membership limits
    return checkMembershipLimits($userId, 'multiplayer_rooms_per_month');
}

function getBreedingLabLevel($userId) {
    // Calculate breeding lab level based on user's genetics achievements
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as hybrids_created
        FROM strain_genetics 
        WHERE created_by_user_id = ? AND parent1_strain_id IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    $hybridCount = $result['hybrids_created'] ?? 0;
    return min(10, floor($hybridCount / 5) + 1); // Level 1-10 based on hybrids created
}

function updateAchievementProgress($userId, $category, $progress) {
    global $pdo;
    
    // Update achievement progress for specific category
    $stmt = $pdo->prepare("
        SELECT id, completion_criteria FROM advanced_achievements 
        WHERE category = ? AND id NOT IN (
            SELECT achievement_id FROM player_achievement_progress 
            WHERE user_id = ? AND is_completed = 1
        )
    ");
    $stmt->execute([$category, $userId]);
    $achievements = $stmt->fetchAll();
    
    foreach ($achievements as $achievement) {
        $criteria = json_decode($achievement['completion_criteria'], true);
        // Logic to update progress based on criteria and current progress
        // Implementation would check each criteria and update progress accordingly
    }
}

function getUserSalesAnalysis($userId) {
    // Analyze user's potential sales based on current inventory
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as ready_plants, AVG(final_quality) as avg_quality
        FROM plants p
        JOIN game_players gp ON p.player_id = gp.id
        WHERE gp.user_id = ? AND p.stage = 'harvest_ready'
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getMarketTrends() {
    // Calculate market trends over time
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT strain_id, AVG(price_per_gram) as avg_price,
               trend_direction, COUNT(*) as data_points
        FROM market_microeconomics
        WHERE data_timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY strain_id, trend_direction
        ORDER BY avg_price DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getTradingLevel($userId) {
    // Calculate user's trading level based on successful trades
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed_trades
        FROM advanced_trades
        WHERE (seller_user_id = ? OR buyer_user_id = ?) 
        AND trade_status = 'completed'
    ");
    $stmt->execute([$userId, $userId]);
    $result = $stmt->fetch();
    
    $tradeCount = $result['completed_trades'] ?? 0;
    return min(10, floor($tradeCount / 10) + 1); // Level 1-10 based on trades
}

function getWeatherForecast($userId) {
    // Generate weather forecast for user's locations
    // This would integrate with external weather APIs in production
    return [
        'forecast_period' => '24_hours',
        'locations' => [
            ['location' => 'Indoor Setup', 'temperature' => 72, 'humidity' => 55, 'stability' => 'excellent'],
            ['location' => 'Outdoor Plot', 'temperature' => 68, 'humidity' => 65, 'stability' => 'good']
        ]
    ];
}

function getCurrentSession($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM gaming_sessions 
        WHERE user_id = ? AND is_active = 1 
        ORDER BY started_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        sendJsonResponse(['session' => null, 'message' => 'No active session']);
        return;
    }
    
    sendJsonResponse(['session' => $session]);
}

function getAdvancedAchievements($userId) {
    global $pdo;
    
    // Get user's achievement progress
    $stmt = $pdo->prepare("
        SELECT aa.*, pap.progress_percentage, pap.is_completed, pap.completed_at
        FROM advanced_achievements aa
        LEFT JOIN player_achievement_progress pap ON aa.id = pap.achievement_id AND pap.user_id = ?
        ORDER BY aa.category, aa.difficulty, aa.points_reward DESC
    ");
    $stmt->execute([$userId]);
    $achievements = $stmt->fetchAll();
    
    sendJsonResponse(['achievements' => $achievements]);
}

function getGameAnalytics($userId) {
    global $pdo;
    
    // Get user's gaming analytics
    $stmt = $pdo->prepare("
        SELECT metric_type, SUM(metric_value) as total_value, 
               COUNT(*) as data_points, AVG(metric_value) as avg_value
        FROM game_analytics
        WHERE user_id = ? AND recorded_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY metric_type
        ORDER BY total_value DESC
    ");
    $stmt->execute([$userId]);
    $analytics = $stmt->fetchAll();
    
    sendJsonResponse(['analytics' => $analytics, 'period' => '30_days']);
}

function startEnhancedSession($userId, $input) {
    global $pdo;
    
    try {
        // End any existing active sessions
        $stmt = $pdo->prepare("
            UPDATE gaming_sessions SET is_active = 0, ended_at = NOW() 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        
        // Start new enhanced session
        $stmt = $pdo->prepare("
            INSERT INTO gaming_sessions (user_id, session_type, session_data, 
                                       genetics_data, weather_effects, market_conditions,
                                       is_active, started_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())
        ");
        $stmt->execute([
            $userId,
            $input['session_type'] ?? 'single_player',
            json_encode($input['session_data'] ?? []),
            json_encode($input['genetics_data'] ?? []),
            json_encode($input['weather_effects'] ?? []),
            json_encode($input['market_conditions'] ?? [])
        ]);
        
        $sessionId = $pdo->lastInsertId();
        
        sendJsonResponse([
            'success' => true,
            'session_id' => $sessionId,
            'message' => 'Enhanced gaming session started!'
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to start session: ' . $e->getMessage()], 500);
    }
}

?>