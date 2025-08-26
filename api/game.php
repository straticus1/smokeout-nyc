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

// Require authentication for all game endpoints
$user = authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

// Remove 'api' and 'game' from segments
array_shift($segments); // remove 'api'
array_shift($segments); // remove 'game'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'player':
            handlePlayerEndpoints($method, $id, $user['id']);
            break;
            
        case 'strains':
            handleStrainEndpoints($method, $id, $user['id']);
            break;
            
        case 'plants':
            handlePlantEndpoints($method, $id, $user['id']);
            break;
            
        case 'locations':
            handleLocationEndpoints($method, $id, $user['id']);
            break;
            
        case 'sales':
            handleSalesEndpoints($method, $id, $user['id']);
            break;
            
        case 'shop':
            handleShopEndpoints($method, $id, $user['id']);
            break;
            
        case 'achievements':
            handleAchievementEndpoints($method, $id, $user['id']);
            break;
            
        case 'market':
            handleMarketEndpoints($method, $id, $user['id']);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handlePlayerEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/player - Get player profile
            $player = GamePlayer::getByUserId($user_id);
            if (!$player) {
                // Create new player if doesn't exist
                $player = GamePlayer::create($user_id);
            }
            echo json_encode([
                'player' => $player,
                'stats' => [
                    'total_plants_grown' => Plant::getTotalGrown($user_id),
                    'total_sales' => Sale::getTotalSales($user_id),
                    'total_earnings' => Sale::getTotalEarnings($user_id),
                    'achievements_count' => PlayerAchievement::getCount($user_id)
                ]
            ]);
            break;
            
        case 'PUT':
            // PUT /api/game/player - Update player (for admin use)
            $data = json_decode(file_get_contents('php://input'), true);
            $player = GamePlayer::getByUserId($user_id);
            $player->update($data);
            echo json_encode(['success' => true, 'player' => $player]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleStrainEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/game/strains/{id} - Get specific strain
                $strain = Strain::getById($id);
                echo json_encode(['strain' => $strain]);
            } else {
                // GET /api/game/strains - Get available strains for player level
                $player = GamePlayer::getByUserId($user_id);
                $strains = Strain::getAvailableForLevel($player->level);
                echo json_encode(['strains' => $strains]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handlePlantEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/game/plants/{id} - Get specific plant
                $plant = Plant::getById($id);
                if ($plant->player_id !== $user_id) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied']);
                    return;
                }
                echo json_encode(['plant' => $plant]);
            } else {
                // GET /api/game/plants - Get all player's plants
                $plants = Plant::getByPlayerId($user_id);
                echo json_encode(['plants' => $plants]);
            }
            break;
            
        case 'POST':
            // POST /api/game/plants - Plant new seed
            $data = json_decode(file_get_contents('php://input'), true);
            $strain_id = $data['strain_id'];
            $location_id = $data['location_id'];
            
            $player = GamePlayer::getByUserId($user_id);
            $strain = Strain::getById($strain_id);
            
            // Check if player can afford seed
            if ($player->tokens < $strain->seed_cost) {
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient tokens']);
                return;
            }
            
            // Check if player has required level
            if ($player->level < $strain->required_level) {
                http_response_code(400);
                echo json_encode(['error' => 'Level requirement not met']);
                return;
            }
            
            // Plant the seed
            $plant = Plant::plant($user_id, $strain_id, $location_id);
            $player->spendTokens($strain->seed_cost);
            
            // Log transaction
            GameTransaction::log($user_id, 'seed_purchase', -$strain->seed_cost, 
                "Purchased {$strain->name} seed", $strain_id);
            
            echo json_encode(['success' => true, 'plant' => $plant]);
            break;
            
        case 'PUT':
            // PUT /api/game/plants/{id} - Harvest plant
            $plant = Plant::getById($id);
            if ($plant->player_id !== $user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            if (!$plant->isReady()) {
                http_response_code(400);
                echo json_encode(['error' => 'Plant not ready for harvest']);
                return;
            }
            
            $harvest = $plant->harvest();
            echo json_encode(['success' => true, 'harvest' => $harvest]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleLocationEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/game/locations/{id} - Get specific location
                $location = Location::getById($id);
                echo json_encode(['location' => $location]);
            } else {
                // GET /api/game/locations - Get available locations for player
                $locations = Location::getAvailableForPlayer($user_id);
                echo json_encode(['locations' => $locations]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleSalesEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/sales - Get player's sales history
            $sales = Sale::getByPlayerId($user_id);
            echo json_encode(['sales' => $sales]);
            break;
            
        case 'POST':
            // POST /api/game/sales - Sell harvested plant
            $data = json_decode(file_get_contents('php://input'), true);
            $plant_id = $data['plant_id'];
            $location_id = $data['location_id'];
            
            $plant = Plant::getById($plant_id);
            if ($plant->player_id !== $user_id || $plant->status !== 'harvested') {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid plant for sale']);
                return;
            }
            
            $sale = Sale::create($user_id, $plant_id, $location_id);
            $player = GamePlayer::getByUserId($user_id);
            $player->addTokens($sale->final_price);
            $player->addExperience($sale->experience_gained);
            
            // Check for achievements
            Achievement::checkPlayerAchievements($user_id);
            
            echo json_encode(['success' => true, 'sale' => $sale]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleShopEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/shop - Get shop items (token packages, etc.)
            $packages = [
                ['id' => 1, 'name' => 'Starter Pack', 'tokens' => 100, 'price' => 4.99],
                ['id' => 2, 'name' => 'Grower Pack', 'tokens' => 250, 'price' => 9.99],
                ['id' => 3, 'name' => 'Dealer Pack', 'tokens' => 500, 'price' => 19.99],
                ['id' => 4, 'name' => 'Kingpin Pack', 'tokens' => 1000, 'price' => 34.99]
            ];
            echo json_encode(['packages' => $packages]);
            break;
            
        case 'POST':
            // POST /api/game/shop - Purchase tokens (would integrate with payment processor)
            $data = json_decode(file_get_contents('php://input'), true);
            $package_id = $data['package_id'];
            
            // For demo purposes, just add tokens without payment
            $packages = [
                1 => 100, 2 => 250, 3 => 500, 4 => 1000
            ];
            
            if (!isset($packages[$package_id])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid package']);
                return;
            }
            
            $tokens = $packages[$package_id];
            $player = GamePlayer::getByUserId($user_id);
            $player->addTokens($tokens);
            
            GameTransaction::log($user_id, 'token_purchase', $tokens, 
                "Purchased token package {$package_id}");
            
            echo json_encode(['success' => true, 'tokens_added' => $tokens]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleAchievementEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/game/achievements - Get player's achievements
            $achievements = PlayerAchievement::getByPlayerId($user_id);
            $available = Achievement::getAvailable($user_id);
            echo json_encode([
                'earned' => $achievements,
                'available' => $available
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleMarketEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/game/market/{location_id} - Get market prices for location
                $prices = Market::getCurrentPrices($id);
                echo json_encode(['market' => $prices]);
            } else {
                // GET /api/game/market - Get all market data
                $markets = Market::getAllMarkets();
                echo json_encode(['markets' => $markets]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}
?>
