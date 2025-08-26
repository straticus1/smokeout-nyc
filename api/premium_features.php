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
array_shift($segments); // remove 'premium'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'vip-rooms':
            handleVipRoomEndpoints($method, $id, $user['id']);
            break;
        case 'exclusive-strains':
            handleExclusiveStrainEndpoints($method, $id, $user['id']);
            break;
        case 'boosters':
            handleBoosterEndpoints($method, $id, $user['id']);
            break;
        case 'cosmetics':
            handleCosmeticEndpoints($method, $id, $user['id']);
            break;
        case 'achievements':
            handlePremiumAchievementEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Premium feature endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleVipRoomEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/premium/vip-rooms - Get available VIP rooms
            $stmt = $pdo->prepare("
                SELECT vr.*, 
                       CASE WHEN pvr.id IS NOT NULL THEN TRUE ELSE FALSE END as owned,
                       pvr.expires_at
                FROM vip_rooms vr
                LEFT JOIN player_vip_rooms pvr ON vr.id = pvr.vip_room_id 
                    AND pvr.player_id = (SELECT id FROM game_players WHERE user_id = ?)
                    AND (pvr.expires_at IS NULL OR pvr.expires_at > NOW())
                WHERE vr.is_active = TRUE
                ORDER BY vr.tier_level ASC
            ");
            $stmt->execute([$user_id]);
            $vip_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON fields
            foreach ($vip_rooms as &$room) {
                $room['benefits'] = json_decode($room['benefits'], true);
                $room['requirements'] = json_decode($room['requirements'], true);
            }
            
            echo json_encode(['vip_rooms' => $vip_rooms]);
            break;
            
        case 'POST':
            // POST /api/premium/vip-rooms/{id}/purchase - Purchase VIP room
            $vip_room_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            $payment_method = $data['payment_method'] ?? 'tokens';
            $duration = $data['duration'] ?? 30; // days
            
            // Get VIP room details
            $room_stmt = $pdo->prepare("SELECT * FROM vip_rooms WHERE id = ? AND is_active = TRUE");
            $room_stmt->execute([$vip_room_id]);
            $room = $room_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$room) {
                http_response_code(404);
                echo json_encode(['error' => 'VIP room not found']);
                return;
            }
            
            // Calculate cost
            $daily_cost = $room['token_cost_per_day'];
            $total_cost = $daily_cost * $duration;
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
            
            // Get player
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player || $player['tokens'] < $total_cost) {
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
                ")->execute([$total_cost, $user_id]);
                
                // Grant VIP room access
                $pdo->prepare("
                    INSERT INTO player_vip_rooms (player_id, vip_room_id, expires_at, purchased_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    expires_at = GREATEST(expires_at, ?), purchased_at = NOW()
                ")->execute([$player['id'], $vip_room_id, $expires_at, $expires_at]);
                
                // Log transaction
                $pdo->prepare("
                    INSERT INTO game_transactions 
                    (player_id, transaction_type, item_type, item_id, amount, currency_type)
                    VALUES (?, 'purchase', 'vip_room', ?, ?, 'tokens')
                ")->execute([$player['id'], $vip_room_id, $total_cost]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'vip_room' => $room['room_name'],
                    'expires_at' => $expires_at,
                    'tokens_spent' => $total_cost
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

function handleExclusiveStrainEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/premium/exclusive-strains - Get exclusive strains
            $stmt = $pdo->prepare("
                SELECT s.*, 
                       CASE WHEN ps.id IS NOT NULL THEN TRUE ELSE FALSE END as unlocked,
                       ps.unlocked_at
                FROM strains s
                LEFT JOIN player_strains ps ON s.id = ps.strain_id 
                    AND ps.player_id = (SELECT id FROM game_players WHERE user_id = ?)
                WHERE s.rarity_level IN ('legendary', 'mythic', 'exclusive')
                ORDER BY s.rarity_level DESC, s.base_yield DESC
            ");
            $stmt->execute([$user_id]);
            $exclusive_strains = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['exclusive_strains' => $exclusive_strains]);
            break;
            
        case 'POST':
            // POST /api/premium/exclusive-strains/{id}/unlock - Unlock exclusive strain
            $strain_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            $payment_method = $data['payment_method'] ?? 'tokens';
            
            // Get strain details
            $strain_stmt = $pdo->prepare("SELECT * FROM strains WHERE id = ? AND rarity_level IN ('legendary', 'mythic', 'exclusive')");
            $strain_stmt->execute([$strain_id]);
            $strain = $strain_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$strain) {
                http_response_code(404);
                echo json_encode(['error' => 'Exclusive strain not found']);
                return;
            }
            
            // Calculate unlock cost (premium strains cost more)
            $unlock_cost = $strain['seed_cost'] * 5; // 5x base cost for exclusive unlock
            
            // Get player
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player || $player['tokens'] < $unlock_cost) {
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient tokens for exclusive strain unlock']);
                return;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Deduct tokens
                $pdo->prepare("
                    UPDATE game_players 
                    SET tokens = tokens - ? 
                    WHERE user_id = ?
                ")->execute([$unlock_cost, $user_id]);
                
                // Unlock strain
                $pdo->prepare("
                    INSERT INTO player_strains (player_id, strain_id, unlocked_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE unlocked_at = NOW()
                ")->execute([$player['id'], $strain_id]);
                
                // Log transaction
                $pdo->prepare("
                    INSERT INTO game_transactions 
                    (player_id, transaction_type, item_type, item_id, amount, currency_type)
                    VALUES (?, 'unlock', 'strain', ?, ?, 'tokens')
                ")->execute([$player['id'], $strain_id, $unlock_cost]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'strain' => $strain['strain_name'],
                    'tokens_spent' => $unlock_cost
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

function handleBoosterEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/premium/boosters - Get available boosters
            $stmt = $pdo->prepare("
                SELECT b.*, 
                       COALESCE(pb.quantity, 0) as owned_quantity,
                       pb.last_used
                FROM boosters b
                LEFT JOIN player_boosters pb ON b.id = pb.booster_id 
                    AND pb.player_id = (SELECT id FROM game_players WHERE user_id = ?)
                WHERE b.is_active = TRUE
                ORDER BY b.booster_type, b.token_cost ASC
            ");
            $stmt->execute([$user_id]);
            $boosters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON fields
            foreach ($boosters as &$booster) {
                $booster['effects'] = json_decode($booster['effects'], true);
            }
            
            echo json_encode(['boosters' => $boosters]);
            break;
            
        case 'POST':
            // POST /api/premium/boosters/{id}/purchase - Purchase booster
            $booster_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            $quantity = $data['quantity'] ?? 1;
            
            // Get booster details
            $booster_stmt = $pdo->prepare("SELECT * FROM boosters WHERE id = ? AND is_active = TRUE");
            $booster_stmt->execute([$booster_id]);
            $booster = $booster_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booster) {
                http_response_code(404);
                echo json_encode(['error' => 'Booster not found']);
                return;
            }
            
            $total_cost = $booster['token_cost'] * $quantity;
            
            // Get player
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player || $player['tokens'] < $total_cost) {
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
                ")->execute([$total_cost, $user_id]);
                
                // Add boosters to inventory
                $pdo->prepare("
                    INSERT INTO player_boosters (player_id, booster_id, quantity)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + ?
                ")->execute([$player['id'], $booster_id, $quantity, $quantity]);
                
                // Log transaction
                $pdo->prepare("
                    INSERT INTO game_transactions 
                    (player_id, transaction_type, item_type, item_id, amount, currency_type, quantity)
                    VALUES (?, 'purchase', 'booster', ?, ?, 'tokens', ?)
                ")->execute([$player['id'], $booster_id, $total_cost, $quantity]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'booster' => $booster['booster_name'],
                    'quantity_purchased' => $quantity,
                    'tokens_spent' => $total_cost
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        case 'PUT':
            // PUT /api/premium/boosters/{id}/use - Use booster
            $booster_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            $target_plant_id = $data['plant_id'] ?? null;
            
            // Get player
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if player has booster
            $booster_stmt = $pdo->prepare("
                SELECT pb.*, b.booster_name, b.effects, b.duration_hours
                FROM player_boosters pb
                JOIN boosters b ON pb.booster_id = b.id
                WHERE pb.player_id = ? AND pb.booster_id = ? AND pb.quantity > 0
            ");
            $booster_stmt->execute([$player['id'], $booster_id]);
            $player_booster = $booster_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player_booster) {
                http_response_code(400);
                echo json_encode(['error' => 'Booster not available']);
                return;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Use booster
                $pdo->prepare("
                    UPDATE player_boosters 
                    SET quantity = quantity - 1, last_used = NOW()
                    WHERE player_id = ? AND booster_id = ?
                ")->execute([$player['id'], $booster_id]);
                
                // Apply booster effects
                $effects = json_decode($player_booster['effects'], true);
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$player_booster['duration_hours']} hours"));
                
                if ($target_plant_id) {
                    // Apply to specific plant
                    $pdo->prepare("
                        INSERT INTO active_boosters 
                        (player_id, booster_id, target_type, target_id, effects, expires_at)
                        VALUES (?, ?, 'plant', ?, ?, ?)
                    ")->execute([$player['id'], $booster_id, $target_plant_id, json_encode($effects), $expires_at]);
                } else {
                    // Apply globally to player
                    $pdo->prepare("
                        INSERT INTO active_boosters 
                        (player_id, booster_id, target_type, effects, expires_at)
                        VALUES (?, ?, 'player', ?, ?)
                    ")->execute([$player['id'], $booster_id, json_encode($effects), $expires_at]);
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'booster_used' => $player_booster['booster_name'],
                    'effects' => $effects,
                    'expires_at' => $expires_at
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

function handleCosmeticEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/premium/cosmetics - Get cosmetic items
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       CASE WHEN pc.id IS NOT NULL THEN TRUE ELSE FALSE END as owned,
                       pc.equipped
                FROM cosmetics c
                LEFT JOIN player_cosmetics pc ON c.id = pc.cosmetic_id 
                    AND pc.player_id = (SELECT id FROM game_players WHERE user_id = ?)
                WHERE c.is_active = TRUE
                ORDER BY c.cosmetic_type, c.rarity_level DESC
            ");
            $stmt->execute([$user_id]);
            $cosmetics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['cosmetics' => $cosmetics]);
            break;
            
        case 'POST':
            // POST /api/premium/cosmetics/{id}/purchase - Purchase cosmetic
            $cosmetic_id = $id;
            
            // Get cosmetic details
            $cosmetic_stmt = $pdo->prepare("SELECT * FROM cosmetics WHERE id = ? AND is_active = TRUE");
            $cosmetic_stmt->execute([$cosmetic_id]);
            $cosmetic = $cosmetic_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cosmetic) {
                http_response_code(404);
                echo json_encode(['error' => 'Cosmetic item not found']);
                return;
            }
            
            // Get player
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player || $player['tokens'] < $cosmetic['token_cost']) {
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
                ")->execute([$cosmetic['token_cost'], $user_id]);
                
                // Add cosmetic to collection
                $pdo->prepare("
                    INSERT INTO player_cosmetics (player_id, cosmetic_id, purchased_at)
                    VALUES (?, ?, NOW())
                ")->execute([$player['id'], $cosmetic_id]);
                
                // Log transaction
                $pdo->prepare("
                    INSERT INTO game_transactions 
                    (player_id, transaction_type, item_type, item_id, amount, currency_type)
                    VALUES (?, 'purchase', 'cosmetic', ?, ?, 'tokens')
                ")->execute([$player['id'], $cosmetic_id, $cosmetic['token_cost']]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'cosmetic' => $cosmetic['item_name'],
                    'tokens_spent' => $cosmetic['token_cost']
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

function handlePremiumAchievementEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/premium/achievements - Get premium achievements
            $stmt = $pdo->prepare("
                SELECT a.*, 
                       CASE WHEN pa.id IS NOT NULL THEN TRUE ELSE FALSE END as unlocked,
                       pa.unlocked_at
                FROM achievements a
                LEFT JOIN player_achievements pa ON a.id = pa.achievement_id 
                    AND pa.player_id = (SELECT id FROM game_players WHERE user_id = ?)
                WHERE a.is_premium = TRUE
                ORDER BY a.difficulty_level DESC, a.token_reward DESC
            ");
            $stmt->execute([$user_id]);
            $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON fields
            foreach ($achievements as &$achievement) {
                $achievement['requirements'] = json_decode($achievement['requirements'], true);
                $achievement['rewards'] = json_decode($achievement['rewards'], true);
            }
            
            echo json_encode(['premium_achievements' => $achievements]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}
?>
