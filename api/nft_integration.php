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
array_shift($segments); // remove 'nft'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'genetics':
            handleGeneticsNftEndpoints($method, $id, $user['id']);
            break;
        case 'marketplace':
            handleNftMarketplaceEndpoints($method, $id, $user['id']);
            break;
        case 'collection':
            handleNftCollectionEndpoints($method, $id, $user['id']);
            break;
        case 'breeding':
            handleNftBreedingEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'NFT endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGeneticsNftEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT gn.*, s.strain_name, s.genetics_info,
                           CASE WHEN ugn.id IS NOT NULL THEN TRUE ELSE FALSE END as owned,
                           ugn.acquired_at, ugn.breeding_count
                    FROM genetics_nfts gn
                    JOIN strains s ON gn.strain_id = s.id
                    LEFT JOIN user_genetics_nfts ugn ON gn.id = ugn.genetics_nft_id AND ugn.user_id = ?
                    WHERE gn.id = ?
                ");
                $stmt->execute([$user_id, $id]);
                $nft = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($nft) {
                    $nft['genetic_traits'] = json_decode($nft['genetic_traits'], true);
                    $nft['metadata'] = json_decode($nft['metadata'], true);
                }
                
                echo json_encode(['genetics_nft' => $nft]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT gn.*, s.strain_name,
                           CASE WHEN ugn.id IS NOT NULL THEN TRUE ELSE FALSE END as owned
                    FROM genetics_nfts gn
                    JOIN strains s ON gn.strain_id = s.id
                    LEFT JOIN user_genetics_nfts ugn ON gn.id = ugn.genetics_nft_id AND ugn.user_id = ?
                    WHERE gn.is_active = TRUE
                    ORDER BY gn.rarity_level DESC, gn.generation_number ASC
                ");
                $stmt->execute([$user_id]);
                $nfts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['genetics_nfts' => $nfts]);
            }
            break;
            
        case 'POST':
            // Mint genetics NFT
            $genetics_nft_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            
            $nft_stmt = $pdo->prepare("SELECT * FROM genetics_nfts WHERE id = ?");
            $nft_stmt->execute([$genetics_nft_id]);
            $genetics_nft = $nft_stmt->fetch(PDO::FETCH_ASSOC);
            
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player || $player['tokens'] < $genetics_nft['mint_cost']) {
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient tokens']);
                return;
            }
            
            try {
                $pdo->beginTransaction();
                
                $pdo->prepare("UPDATE game_players SET tokens = tokens - ? WHERE user_id = ?")->execute([$genetics_nft['mint_cost'], $user_id]);
                
                $token_id = 'GEN-' . strtoupper(bin2hex(random_bytes(8)));
                
                $pdo->prepare("
                    INSERT INTO user_genetics_nfts (user_id, genetics_nft_id, token_id, acquired_at)
                    VALUES (?, ?, ?, NOW())
                ")->execute([$user_id, $genetics_nft_id, $token_id]);
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'token_id' => $token_id]);
                
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

function handleNftMarketplaceEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("
                SELECT nm.*, gn.genetics_name, gn.rarity_level, u.username as seller_name
                FROM nft_marketplace nm
                JOIN user_genetics_nfts ugn ON nm.user_nft_id = ugn.id
                JOIN genetics_nfts gn ON ugn.genetics_nft_id = gn.id
                JOIN users u ON nm.seller_user_id = u.id
                WHERE nm.status = 'active' AND nm.expires_at > NOW()
                ORDER BY nm.price ASC
                LIMIT 100
            ");
            $stmt->execute();
            $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['marketplace_listings' => $listings]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $user_nft_id = $data['user_nft_id'];
            $price = $data['price'];
            
            $ownership_stmt = $pdo->prepare("SELECT * FROM user_genetics_nfts WHERE id = ? AND user_id = ?");
            $ownership_stmt->execute([$user_nft_id, $user_id]);
            
            if (!$ownership_stmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'NFT not owned']);
                return;
            }
            
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            $stmt = $pdo->prepare("
                INSERT INTO nft_marketplace (seller_user_id, user_nft_id, price, expires_at, status)
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$user_id, $user_nft_id, $price, $expires_at]);
            
            echo json_encode(['success' => true, 'listing_id' => $pdo->lastInsertId()]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleNftCollectionEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("
                SELECT ugn.*, gn.genetics_name, gn.rarity_level, gn.genetic_traits
                FROM user_genetics_nfts ugn
                JOIN genetics_nfts gn ON ugn.genetics_nft_id = gn.id
                WHERE ugn.user_id = ?
                ORDER BY gn.rarity_level DESC, ugn.acquired_at DESC
            ");
            $stmt->execute([$user_id]);
            $collection = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['collection' => $collection]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleNftBreedingEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $parent_nft_ids = $data['parent_nft_ids'];
            
            if (count($parent_nft_ids) !== 2) {
                http_response_code(400);
                echo json_encode(['error' => 'Exactly 2 parent NFTs required']);
                return;
            }
            
            $breeding_cost = 200;
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch();
            
            if (!$player || $player['tokens'] < $breeding_cost) {
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient tokens']);
                return;
            }
            
            $success = rand(1, 100) <= 70; // 70% success rate
            
            try {
                $pdo->beginTransaction();
                
                $pdo->prepare("UPDATE game_players SET tokens = tokens - ? WHERE user_id = ?")->execute([$breeding_cost, $user_id]);
                
                $pdo->prepare("
                    INSERT INTO nft_breeding_records (parent_nft_ids, breeding_success, breeding_cost)
                    VALUES (?, ?, ?)
                ")->execute([json_encode($parent_nft_ids), $success, $breeding_cost]);
                
                $pdo->commit();
                
                echo json_encode(['success' => $success, 'breeding_cost' => $breeding_cost]);
                
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
?>
