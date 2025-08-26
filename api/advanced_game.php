<?php
require_once 'config/database.php';
require_once 'models/Game.php';
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
array_shift($segments); // remove 'game'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'consume':
            handleConsumptionEndpoints($method, $id, $user['id']);
            break;
            
        case 'products':
            handleProductEndpoints($method, $id, $user['id']);
            break;
            
        case 'smokeshops':
            handleSmokeShopEndpoints($method, $id, $user['id']);
            break;
            
        case 'dealers':
            handleDealerEndpoints($method, $id, $user['id']);
            break;
            
        case 'premium':
            handlePremiumEndpoints($method, $id, $user['id']);
            break;
            
        case 'challenges':
            handleChallengeEndpoints($method, $id, $user['id']);
            break;
            
        case 'loyalty':
            handleLoyaltyEndpoints($method, $id, $user['id']);
            break;
            
        case 'mistakes':
            handleMistakeEndpoints($method, $id, $user['id']);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Advanced endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleConsumptionEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/consume - Get current impairment status
            $impairment = getCurrentImpairment($user_id);
            $activeEffects = getActiveEffects($user_id);
            echo json_encode([
                'impairment_level' => $impairment,
                'active_effects' => $activeEffects,
                'is_impaired' => $impairment > 0.1
            ]);
            break;
            
        case 'POST':
            // POST /api/game/consume - Consume product
            $data = json_decode(file_get_contents('php://input'), true);
            $product_id = $data['product_id'];
            $consumption_method = $data['method']; // smoke, vape, eat, dab
            
            $product = getProductById($product_id);
            if ($product['player_id'] !== $user_id || $product['status'] !== 'available') {
                http_response_code(400);
                echo json_encode(['error' => 'Product not available for consumption']);
                return;
            }
            
            // Calculate impairment based on potency and method
            $impairment = calculateImpairmentLevel($product, $consumption_method);
            $duration = calculateEffectDuration($product, $consumption_method);
            
            // Record consumption
            $consumption_id = recordConsumption($user_id, $product, $consumption_method, $impairment, $duration);
            
            // Update product status
            updateProductStatus($product_id, 'consumed');
            
            // Update player impairment
            updatePlayerImpairment($user_id);
            
            echo json_encode([
                'success' => true,
                'consumption_id' => $consumption_id,
                'impairment_added' => $impairment,
                'duration_minutes' => $duration,
                'warning' => $impairment > 0.3 ? 'High impairment - increased mistake risk!' : null
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleProductEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/products - Get player's products
            $products = getPlayerProducts($user_id);
            echo json_encode(['products' => $products]);
            break;
            
        case 'POST':
            // POST /api/game/products - Create product from harvested plant
            $data = json_decode(file_get_contents('php://input'), true);
            $plant_id = $data['plant_id'];
            $product_type = $data['product_type']; // flower, edible, concentrate, pre_roll
            
            $plant = getPlantById($plant_id);
            if ($plant['player_id'] !== $user_id || $plant['status'] !== 'harvested') {
                http_response_code(400);
                echo json_encode(['error' => 'Plant not available for processing']);
                return;
            }
            
            // Check if player is impaired (affects product quality)
            $player_impairment = getCurrentImpairment($user_id);
            $quality_penalty = $player_impairment * 0.2; // Up to 20% quality loss
            
            // Check for mistakes during processing
            if (shouldTriggerMistake($user_id, 'process_product')) {
                triggerProcessingMistake($user_id, $plant_id, $player_impairment);
                echo json_encode(['error' => 'Processing failed due to impairment mistake']);
                return;
            }
            
            $product = createProduct($user_id, $plant_id, $product_type, $quality_penalty);
            
            echo json_encode(['success' => true, 'product' => $product]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleSmokeShopEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/game/smokeshops/{id} - Get specific smoke shop
                $shop = getSmokeShopById($id);
                $canSell = canSellToSmokeShop($user_id, $id);
                echo json_encode(['shop' => $shop, 'can_sell' => $canSell]);
            } else {
                // GET /api/game/smokeshops - Get available smoke shops
                $shops = getAvailableSmokeShops($user_id);
                echo json_encode(['smoke_shops' => $shops]);
            }
            break;
            
        case 'POST':
            // POST /api/game/smokeshops/{id}/sell - Sell to smoke shop
            $data = json_decode(file_get_contents('php://input'), true);
            $products = $data['products']; // Array of product IDs and quantities
            
            if (!canSellToSmokeShop($user_id, $id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Not qualified to sell to this smoke shop']);
                return;
            }
            
            // Check for impairment mistakes
            if (shouldTriggerMistake($user_id, 'sell_bulk')) {
                triggerSaleMistake($user_id, 'smokeshop', getCurrentImpairment($user_id));
                echo json_encode(['error' => 'Sale failed due to impairment']);
                return;
            }
            
            $sale_result = processBulkSale($user_id, $id, $products, 'smokeshop');
            echo json_encode($sale_result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleDealerEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/game/dealers/{id} - Get specific dealer
                $dealer = getDealerById($id);
                $canSell = canSellToDealer($user_id, $id);
                echo json_encode(['dealer' => $dealer, 'can_sell' => $canSell]);
            } else {
                // GET /api/game/dealers - Get available dealers
                $dealers = getAvailableDealers($user_id);
                echo json_encode(['dealers' => $dealers]);
            }
            break;
            
        case 'POST':
            // POST /api/game/dealers/{id}/sell - Sell to dealer
            $data = json_decode(file_get_contents('php://input'), true);
            $products = $data['products'];
            
            $dealer = getDealerById($id);
            
            // Check for bust risk (higher when impaired)
            $impairment = getCurrentImpairment($user_id);
            $bust_risk = $dealer['bust_probability'] * (1 + $impairment);
            
            if (rand(0, 100) / 100 < $bust_risk) {
                triggerBustEvent($user_id, $id, $impairment);
                echo json_encode(['error' => 'Deal went bad - lost products and reputation']);
                return;
            }
            
            $sale_result = processDealerSale($user_id, $id, $products);
            echo json_encode($sale_result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handlePremiumEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/premium - Get available premium features
            $features = getAvailablePremiumFeatures();
            $playerFeatures = getPlayerPremiumFeatures($user_id);
            echo json_encode([
                'available_features' => $features,
                'owned_features' => $playerFeatures
            ]);
            break;
            
        case 'POST':
            // POST /api/game/premium - Purchase premium feature
            $data = json_decode(file_get_contents('php://input'), true);
            $feature_id = $data['feature_id'];
            $payment_method = $data['payment_method']; // tokens or real_money
            
            $feature = getPremiumFeatureById($feature_id);
            $player = getPlayerById($user_id);
            
            if ($payment_method === 'tokens') {
                if ($player['tokens'] < $feature['cost_tokens']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Insufficient tokens']);
                    return;
                }
                
                // Deduct tokens
                updatePlayerTokens($user_id, -$feature['cost_tokens']);
            }
            
            // Grant premium feature
            $expires_at = $feature['is_permanent'] ? null : 
                date('Y-m-d H:i:s', strtotime("+{$feature['duration_days']} days"));
            
            grantPremiumFeature($user_id, $feature_id, $expires_at);
            
            echo json_encode(['success' => true, 'expires_at' => $expires_at]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleChallengeEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/challenges - Get active challenges and progress
            $challenges = getActiveChallenges();
            $progress = getPlayerChallengeProgress($user_id);
            echo json_encode([
                'active_challenges' => $challenges,
                'player_progress' => $progress
            ]);
            break;
            
        case 'POST':
            // POST /api/game/challenges/{id}/claim - Claim challenge reward
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Challenge ID required']);
                return;
            }
            
            $result = claimChallengeReward($user_id, $id);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleLoyaltyEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id === 'rewards') {
                // GET /api/game/loyalty/rewards - Get loyalty rewards catalog
                $rewards = getLoyaltyRewards($user_id);
                echo json_encode(['rewards' => $rewards]);
            } else {
                // GET /api/game/loyalty - Get loyalty status
                $loyalty = getPlayerLoyaltyStatus($user_id);
                echo json_encode(['loyalty' => $loyalty]);
            }
            break;
            
        case 'POST':
            // POST /api/game/loyalty/redeem - Redeem loyalty points
            $data = json_decode(file_get_contents('php://input'), true);
            $reward_id = $data['reward_id'];
            
            $result = redeemLoyaltyReward($user_id, $reward_id);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleMistakeEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/mistakes - Get player's mistake history
            $mistakes = getPlayerMistakes($user_id);
            $stats = getMistakeStats($user_id);
            echo json_encode([
                'mistakes' => $mistakes,
                'stats' => $stats
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// Helper functions for advanced game mechanics

function getCurrentImpairment($player_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(impairment_level), 0.0) as total_impairment
        FROM player_consumption 
        WHERE player_id = ? AND expires_at > NOW()
    ");
    $stmt->execute([$player_id]);
    return min($stmt->fetchColumn(), 1.0); // Cap at 1.0
}

function shouldTriggerMistake($player_id, $action_type) {
    $impairment = getCurrentImpairment($player_id);
    $mistake_chance = $impairment * 0.3; // Max 30% chance
    return (rand(0, 100) / 100) < $mistake_chance;
}

function calculateImpairmentLevel($product, $method) {
    $base_impairment = $product['potency'] / 100; // Convert percentage to decimal
    
    $method_multipliers = [
        'smoke' => 1.0,
        'vape' => 0.8,
        'eat' => 1.5,
        'dab' => 2.0
    ];
    
    return min($base_impairment * ($method_multipliers[$method] ?? 1.0), 1.0);
}

function calculateEffectDuration($product, $method) {
    $base_duration = [
        'smoke' => 120, // 2 hours
        'vape' => 90,   // 1.5 hours
        'eat' => 300,   // 5 hours
        'dab' => 180    // 3 hours
    ];
    
    return $base_duration[$method] ?? 120;
}

function recordConsumption($player_id, $product, $method, $impairment, $duration) {
    global $pdo;
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} minutes"));
    
    $stmt = $pdo->prepare("
        INSERT INTO player_consumption 
        (player_id, product_type, strain_id, quantity, potency, consumption_method, 
         impairment_level, duration_minutes, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $player_id, $product['product_type'], $product['strain_id'],
        $product['quantity'], $product['potency'], $method,
        $impairment, $duration, $expires_at
    ]);
    
    return $pdo->lastInsertId();
}

function updatePlayerImpairment($player_id) {
    global $pdo;
    $pdo->prepare("CALL CalculateImpairment(?)")->execute([$player_id]);
}

function triggerProcessingMistake($player_id, $plant_id, $impairment) {
    global $pdo;
    $loss_amount = $impairment * 50; // Scale loss with impairment
    
    $stmt = $pdo->prepare("
        INSERT INTO game_mistakes 
        (player_id, mistake_type, description, loss_amount, loss_type, 
         caused_by_impairment, impairment_level)
        VALUES (?, 'quality_loss', 'Ruined product during processing', ?, 'tokens', TRUE, ?)
    ");
    
    $stmt->execute([$player_id, $loss_amount, $impairment]);
    
    // Apply loss
    $pdo->prepare("
        UPDATE game_players 
        SET tokens = GREATEST(tokens - ?, 0), mistakes_count = mistakes_count + 1
        WHERE id = ?
    ")->execute([$loss_amount, $player_id]);
}

// Additional helper functions would continue here...
?>
