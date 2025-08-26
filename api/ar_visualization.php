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
array_shift($segments); // remove 'ar'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'plants':
            handleArPlantEndpoints($method, $id, $user['id']);
            break;
        case 'rooms':
            handleArRoomEndpoints($method, $id, $user['id']);
            break;
        case 'sessions':
            handleArSessionEndpoints($method, $id, $user['id']);
            break;
        case 'models':
            handleArModelEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'AR endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleArPlantEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/ar/plants/{id} - Get AR model for specific plant
                $stmt = $pdo->prepare("
                    SELECT p.*, s.strain_name, s.indica_percentage, s.sativa_percentage,
                           ar.model_url, ar.texture_url, ar.animation_data, ar.scale_factor,
                           ar.growth_stage_models, ar.last_updated as ar_updated
                    FROM plants p
                    JOIN strains s ON p.strain_id = s.id
                    JOIN game_players gp ON p.player_id = gp.id
                    LEFT JOIN ar_plant_models ar ON p.id = ar.plant_id
                    WHERE p.id = ? AND gp.user_id = ?
                ");
                $stmt->execute([$id, $user_id]);
                $plant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$plant) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Plant not found']);
                    return;
                }
                
                // Parse JSON fields
                $plant['animation_data'] = json_decode($plant['animation_data'] ?? '{}', true);
                $plant['growth_stage_models'] = json_decode($plant['growth_stage_models'] ?? '{}', true);
                
                // Generate AR visualization data
                $ar_data = generateArVisualization($plant);
                
                echo json_encode([
                    'plant' => $plant,
                    'ar_visualization' => $ar_data
                ]);
            } else {
                // GET /api/ar/plants - Get all AR-enabled plants
                $stmt = $pdo->prepare("
                    SELECT p.id, p.planted_at, p.growth_stage, p.health_percentage,
                           s.strain_name, ar.model_url, ar.last_updated as ar_updated
                    FROM plants p
                    JOIN strains s ON p.strain_id = s.id
                    JOIN game_players gp ON p.player_id = gp.id
                    LEFT JOIN ar_plant_models ar ON p.id = ar.plant_id
                    WHERE gp.user_id = ? AND p.status = 'growing'
                    ORDER BY p.planted_at DESC
                ");
                $stmt->execute([$user_id]);
                $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['ar_plants' => $plants]);
            }
            break;
            
        case 'POST':
            // POST /api/ar/plants/{id}/capture - Capture AR session data
            $plant_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            
            $session_data = [
                'camera_position' => $data['camera_position'],
                'plant_position' => $data['plant_position'],
                'lighting_conditions' => $data['lighting_conditions'],
                'interaction_data' => $data['interactions'] ?? [],
                'session_duration' => $data['duration'] ?? 0,
                'screenshots' => $data['screenshots'] ?? []
            ];
            
            // Store AR session
            $stmt = $pdo->prepare("
                INSERT INTO ar_sessions 
                (user_id, plant_id, session_type, session_data, duration_seconds)
                VALUES (?, ?, 'plant_visualization', ?, ?)
            ");
            $stmt->execute([
                $user_id, $plant_id, 
                json_encode($session_data), 
                $session_data['session_duration']
            ]);
            
            $session_id = $pdo->lastInsertId();
            
            // Update plant interaction stats
            $pdo->prepare("
                UPDATE plants 
                SET ar_interactions = ar_interactions + 1,
                    last_ar_session = NOW()
                WHERE id = ?
            ")->execute([$plant_id]);
            
            echo json_encode([
                'success' => true,
                'session_id' => $session_id,
                'message' => 'AR session captured successfully'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleArRoomEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/ar/rooms - Get AR room environments
            $stmt = $pdo->prepare("
                SELECT ar.*, vr.room_name, vr.description,
                       CASE WHEN pvr.id IS NOT NULL THEN TRUE ELSE FALSE END as owned
                FROM ar_room_environments ar
                LEFT JOIN vip_rooms vr ON ar.vip_room_id = vr.id
                LEFT JOIN player_vip_rooms pvr ON vr.id = pvr.vip_room_id 
                    AND pvr.player_id = (SELECT id FROM game_players WHERE user_id = ?)
                    AND (pvr.expires_at IS NULL OR pvr.expires_at > NOW())
                WHERE ar.is_active = TRUE
                ORDER BY ar.complexity_level ASC
            ");
            $stmt->execute([$user_id]);
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rooms as &$room) {
                $room['environment_data'] = json_decode($room['environment_data'], true);
                $room['lighting_config'] = json_decode($room['lighting_config'], true);
                $room['interactive_elements'] = json_decode($room['interactive_elements'], true);
            }
            
            echo json_encode(['ar_rooms' => $rooms]);
            break;
            
        case 'POST':
            // POST /api/ar/rooms/{id}/customize - Customize AR room
            $room_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verify room ownership
            $ownership_stmt = $pdo->prepare("
                SELECT ar.id
                FROM ar_room_environments ar
                LEFT JOIN vip_rooms vr ON ar.vip_room_id = vr.id
                LEFT JOIN player_vip_rooms pvr ON vr.id = pvr.vip_room_id
                LEFT JOIN game_players gp ON pvr.player_id = gp.id
                WHERE ar.id = ? AND gp.user_id = ?
                AND (pvr.expires_at IS NULL OR pvr.expires_at > NOW())
            ");
            $ownership_stmt->execute([$room_id, $user_id]);
            
            if (!$ownership_stmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Room access denied or not owned']);
                return;
            }
            
            // Save customization
            $customization = [
                'lighting_preferences' => $data['lighting'] ?? [],
                'decoration_placement' => $data['decorations'] ?? [],
                'plant_arrangement' => $data['plant_layout'] ?? [],
                'ambient_effects' => $data['effects'] ?? [],
                'custom_textures' => $data['textures'] ?? []
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO ar_room_customizations 
                (user_id, room_id, customization_data, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                customization_data = ?, updated_at = NOW()
            ");
            $stmt->execute([
                $user_id, $room_id, 
                json_encode($customization),
                json_encode($customization)
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Room customization saved',
                'customization' => $customization
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleArSessionEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/ar/sessions - Get AR session history
            $limit = $_GET['limit'] ?? 50;
            $session_type = $_GET['type'] ?? null;
            
            $where_clause = "WHERE ars.user_id = ?";
            $params = [$user_id];
            
            if ($session_type) {
                $where_clause .= " AND ars.session_type = ?";
                $params[] = $session_type;
            }
            
            $stmt = $pdo->prepare("
                SELECT ars.*, p.id as plant_id, s.strain_name
                FROM ar_sessions ars
                LEFT JOIN plants p ON ars.plant_id = p.id
                LEFT JOIN strains s ON p.strain_id = s.id
                {$where_clause}
                ORDER BY ars.created_at DESC
                LIMIT ?
            ");
            $params[] = (int)$limit;
            $stmt->execute($params);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($sessions as &$session) {
                $session['session_data'] = json_decode($session['session_data'], true);
            }
            
            echo json_encode(['ar_sessions' => $sessions]);
            break;
            
        case 'POST':
            // POST /api/ar/sessions - Start new AR session
            $data = json_decode(file_get_contents('php://input'), true);
            $session_type = $data['session_type']; // 'plant_visualization', 'room_tour', 'tutorial'
            $plant_id = $data['plant_id'] ?? null;
            $room_id = $data['room_id'] ?? null;
            
            $session_data = [
                'device_info' => $data['device_info'] ?? [],
                'ar_capabilities' => $data['ar_capabilities'] ?? [],
                'initial_settings' => $data['settings'] ?? []
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO ar_sessions 
                (user_id, plant_id, room_id, session_type, session_data, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $user_id, $plant_id, $room_id, $session_type, 
                json_encode($session_data)
            ]);
            
            $session_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'session_id' => $session_id,
                'session_token' => generateSessionToken($session_id),
                'ar_config' => getArConfiguration($session_type, $plant_id, $room_id)
            ]);
            break;
            
        case 'PUT':
            // PUT /api/ar/sessions/{id} - Update AR session
            $session_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verify session ownership
            $session_stmt = $pdo->prepare("SELECT user_id FROM ar_sessions WHERE id = ?");
            $session_stmt->execute([$session_id]);
            $session = $session_stmt->fetch();
            
            if (!$session || $session['user_id'] != $user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'Session access denied']);
                return;
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['duration_seconds'])) {
                $updates[] = "duration_seconds = ?";
                $params[] = $data['duration_seconds'];
            }
            
            if (isset($data['session_data'])) {
                $updates[] = "session_data = ?";
                $params[] = json_encode($data['session_data']);
            }
            
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (!empty($updates)) {
                $params[] = $session_id;
                $stmt = $pdo->prepare("
                    UPDATE ar_sessions 
                    SET " . implode(', ', $updates) . ", updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute($params);
            }
            
            echo json_encode(['success' => true, 'message' => 'Session updated']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleArModelEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/ar/models - Get available AR models
            $model_type = $_GET['type'] ?? 'all'; // plant, room, decoration
            
            $where_clause = "WHERE am.is_active = TRUE";
            $params = [];
            
            if ($model_type !== 'all') {
                $where_clause .= " AND am.model_type = ?";
                $params[] = $model_type;
            }
            
            $stmt = $pdo->prepare("
                SELECT am.*, 
                       CASE WHEN uam.id IS NOT NULL THEN TRUE ELSE FALSE END as unlocked
                FROM ar_models am
                LEFT JOIN user_ar_models uam ON am.id = uam.model_id AND uam.user_id = ?
                {$where_clause}
                ORDER BY am.rarity_level DESC, am.model_name ASC
            ");
            array_unshift($params, $user_id);
            $stmt->execute($params);
            $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($models as &$model) {
                $model['model_data'] = json_decode($model['model_data'], true);
                $model['animation_config'] = json_decode($model['animation_config'], true);
            }
            
            echo json_encode(['ar_models' => $models]);
            break;
            
        case 'POST':
            // POST /api/ar/models/{id}/unlock - Unlock AR model
            $model_id = $id;
            
            // Get model details
            $model_stmt = $pdo->prepare("SELECT * FROM ar_models WHERE id = ? AND is_active = TRUE");
            $model_stmt->execute([$model_id]);
            $model = $model_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$model) {
                http_response_code(404);
                echo json_encode(['error' => 'AR model not found']);
                return;
            }
            
            // Check if already unlocked
            $unlock_check = $pdo->prepare("SELECT id FROM user_ar_models WHERE user_id = ? AND model_id = ?");
            $unlock_check->execute([$user_id, $model_id]);
            if ($unlock_check->fetch()) {
                echo json_encode(['success' => true, 'message' => 'Model already unlocked']);
                return;
            }
            
            // Check token cost
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player || $player['tokens'] < $model['unlock_cost']) {
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient tokens']);
                return;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Deduct tokens
                $pdo->prepare("
                    UPDATE game_players 
                    SET tokens = tokens - ? 
                    WHERE user_id = ?
                ")->execute([$model['unlock_cost'], $user_id]);
                
                // Unlock model
                $pdo->prepare("
                    INSERT INTO user_ar_models (user_id, model_id, unlocked_at)
                    VALUES (?, ?, NOW())
                ")->execute([$user_id, $model_id]);
                
                // Log transaction
                $pdo->prepare("
                    INSERT INTO game_transactions 
                    (player_id, transaction_type, item_type, item_id, amount, currency_type)
                    VALUES (?, 'unlock', 'ar_model', ?, ?, 'tokens')
                ")->execute([$player['id'], $model_id, $model['unlock_cost']]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'model_name' => $model['model_name'],
                    'tokens_spent' => $model['unlock_cost']
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

// AR Utility Functions

function generateArVisualization($plant) {
    // Calculate plant visualization parameters based on growth stage and health
    $growth_percentage = calculateGrowthPercentage($plant);
    $health_factor = $plant['health_percentage'] / 100;
    
    // Determine model scale and appearance
    $base_scale = 0.3; // Base scale for seedling
    $max_scale = 1.5;  // Maximum scale for mature plant
    $current_scale = $base_scale + (($max_scale - $base_scale) * $growth_percentage);
    
    // Color variations based on health and strain
    $health_color_modifier = [
        'r' => $health_factor,
        'g' => 1.0,
        'b' => $health_factor * 0.8
    ];
    
    // Animation parameters
    $animation_speed = 1.0 + ($health_factor * 0.5); // Healthier plants sway more
    $leaf_density = intval($growth_percentage * 100);
    
    return [
        'model_scale' => round($current_scale, 2),
        'growth_percentage' => round($growth_percentage * 100, 1),
        'health_visualization' => [
            'color_modifier' => $health_color_modifier,
            'leaf_density' => $leaf_density,
            'stem_thickness' => round($growth_percentage * 0.8, 2)
        ],
        'animation_config' => [
            'sway_speed' => $animation_speed,
            'growth_animation' => $plant['growth_stage'] !== 'mature',
            'particle_effects' => $health_factor > 0.8 ? 'sparkle' : 'none'
        ],
        'interaction_zones' => [
            'water_zone' => ['x' => 0, 'y' => -0.3, 'z' => 0, 'radius' => 0.2],
            'inspect_zone' => ['x' => 0, 'y' => 0.2, 'z' => 0, 'radius' => 0.3],
            'harvest_zone' => $plant['growth_stage'] === 'mature' ? 
                ['x' => 0, 'y' => 0.5, 'z' => 0, 'radius' => 0.4] : null
        ],
        'strain_characteristics' => [
            'indica_traits' => $plant['indica_percentage'] > 50 ? 'bushy' : 'normal',
            'sativa_traits' => $plant['sativa_percentage'] > 50 ? 'tall' : 'normal',
            'leaf_shape' => $plant['indica_percentage'] > $plant['sativa_percentage'] ? 'broad' : 'narrow'
        ]
    ];
}

function calculateGrowthPercentage($plant) {
    $planted_time = strtotime($plant['planted_at']);
    $current_time = time();
    $growth_duration = $current_time - $planted_time;
    
    // Estimate total growth time based on strain (simplified)
    $total_growth_time = 60 * 24 * 60 * 60; // 60 days in seconds
    
    $percentage = min($growth_duration / $total_growth_time, 1.0);
    
    // Adjust based on growth stage
    switch ($plant['growth_stage']) {
        case 'seedling': return min($percentage, 0.2);
        case 'vegetative': return min($percentage, 0.6);
        case 'flowering': return min($percentage, 0.9);
        case 'mature': return 1.0;
        default: return $percentage;
    }
}

function generateSessionToken($session_id) {
    return 'ar_' . $session_id . '_' . bin2hex(random_bytes(8));
}

function getArConfiguration($session_type, $plant_id, $room_id) {
    global $pdo;
    
    $config = [
        'session_type' => $session_type,
        'ar_settings' => [
            'plane_detection' => true,
            'light_estimation' => true,
            'occlusion' => true,
            'people_occlusion' => false
        ]
    ];
    
    if ($plant_id) {
        $plant_stmt = $pdo->prepare("
            SELECT p.*, s.strain_name, ar.model_url
            FROM plants p
            JOIN strains s ON p.strain_id = s.id
            LEFT JOIN ar_plant_models ar ON p.id = ar.plant_id
            WHERE p.id = ?
        ");
        $plant_stmt->execute([$plant_id]);
        $plant = $plant_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($plant) {
            $config['plant_config'] = [
                'model_url' => $plant['model_url'] ?: '/ar/models/default_plant.usdz',
                'scale' => 1.0,
                'animations' => ['idle', 'sway', 'growth']
            ];
        }
    }
    
    if ($room_id) {
        $room_stmt = $pdo->prepare("SELECT * FROM ar_room_environments WHERE id = ?");
        $room_stmt->execute([$room_id]);
        $room = $room_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($room) {
            $config['room_config'] = [
                'environment_url' => $room['environment_model_url'],
                'lighting_config' => json_decode($room['lighting_config'], true),
                'interactive_elements' => json_decode($room['interactive_elements'], true)
            ];
        }
    }
    
    return $config;
}
?>
