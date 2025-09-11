<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'auth_helper.php';
require_once __DIR__ . '/src/models/EnhancedGamingSystem.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri_parts = explode('/', trim($uri, '/'));

try {
    switch ($method) {
        case 'GET':
            handleGet($uri_parts);
            break;
        case 'POST':
            handlePost($uri_parts);
            break;
        case 'PUT':
            handlePut($uri_parts);
            break;
        case 'DELETE':
            handleDelete($uri_parts);
            break;
        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Trading API error: " . $e->getMessage());
    sendJsonResponse(['error' => 'Internal server error'], 500);
}

function handleGet($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    $action = $uri_parts[2] ?? 'offers';
    
    switch ($action) {
        case 'offers':
            getTradeOffers($user_id);
            break;
        case 'my-offers':
            getMyTradeOffers($user_id);
            break;
        case 'history':
            getTradeHistory($user_id);
            break;
        case 'offer':
            getTradeOffer($user_id, $uri_parts[3] ?? null);
            break;
        case 'active-trades':
            getActiveTrades($user_id);
            break;
        default:
            sendJsonResponse(['error' => 'Unknown action'], 400);
    }
}

function handlePost($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    $action = $uri_parts[2] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create-offer':
            createTradeOffer($user_id, $input);
            break;
        case 'accept-offer':
            acceptTradeOffer($user_id, $input);
            break;
        case 'counter-offer':
            createCounterOffer($user_id, $input);
            break;
        case 'direct-trade':
            initiateDirectTrade($user_id, $input);
            break;
        default:
            sendJsonResponse(['error' => 'Unknown action'], 400);
    }
}

function handlePut($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    $action = $uri_parts[2] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update-offer':
            updateTradeOffer($user_id, $input);
            break;
        default:
            sendJsonResponse(['error' => 'Unknown PUT action'], 400);
    }
}

function handleDelete($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    $action = $uri_parts[2] ?? '';
    $offer_id = $uri_parts[3] ?? null;
    
    switch ($action) {
        case 'cancel-offer':
            cancelTradeOffer($user_id, $offer_id);
            break;
        default:
            sendJsonResponse(['error' => 'Unknown DELETE action'], 400);
    }
}

function getTradeOffers($user_id) {
    try {
        $db = DB::getInstance();
        
        $filters = [];
        $params = [$user_id]; // Exclude current user's offers
        
        // Add filters based on query parameters
        if (isset($_GET['strain_id'])) {
            $filters[] = "JSON_CONTAINS(to.items_offered, JSON_OBJECT('strain_id', ?))";
            $params[] = $_GET['strain_id'];
        }
        
        if (isset($_GET['rarity'])) {
            $filters[] = "EXISTS (SELECT 1 FROM JSON_TABLE(to.items_offered, '$[*]' 
                         COLUMNS (strain_id INT PATH '$.strain_id')) AS jt 
                         JOIN genetics g ON jt.strain_id = g.id WHERE g.rarity = ?)";
            $params[] = $_GET['rarity'];
        }
        
        if (isset($_GET['max_price'])) {
            $filters[] = "to.tokens_requested <= ?";
            $params[] = (int)$_GET['max_price'];
        }
        
        $filter_clause = !empty($filters) ? 'AND ' . implode(' AND ', $filters) : '';
        
        $offers = $db->fetchAll(
            "SELECT to.*, 
                    u.username as trader_username,
                    gp.level as trader_level,
                    gp.reputation as trader_reputation,
                    COUNT(th.id) as completed_trades,
                    AVG(tr.rating) as trader_rating
             FROM trade_offers to
             JOIN game_players gp ON to.created_by_player_id = gp.id
             JOIN users u ON gp.user_id = u.id
             LEFT JOIN trade_history th ON gp.id = th.seller_player_id OR gp.id = th.buyer_player_id
             LEFT JOIN trade_ratings tr ON gp.id = tr.rated_player_id
             WHERE to.status = 'active' AND to.expires_at > NOW() 
             AND to.created_by_player_id != (SELECT id FROM game_players WHERE user_id = ?)
             {$filter_clause}
             GROUP BY to.id
             ORDER BY to.created_at DESC
             LIMIT 50",
            $params
        );
        
        // Parse JSON fields and add additional data
        foreach ($offers as &$offer) {
            $offer['items_offered'] = json_decode($offer['items_offered'], true);
            $offer['items_requested'] = json_decode($offer['items_requested'], true);
            
            // Get detailed item information
            $offer['detailed_items_offered'] = getDetailedItems($offer['items_offered']);
            $offer['detailed_items_requested'] = getDetailedItems($offer['items_requested']);
            
            // Calculate offer value
            $offer['estimated_value'] = calculateOfferValue($offer['items_offered']);
        }
        
        sendJsonResponse([
            'offers' => $offers,
            'total_count' => count($offers),
            'filters_applied' => $_GET
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get trade offers: ' . $e->getMessage()], 500);
    }
}

function getMyTradeOffers($user_id) {
    try {
        $db = DB::getInstance();
        
        $player = $db->fetchOne(
            "SELECT id FROM game_players WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$player) {
            sendJsonResponse(['error' => 'Player not found'], 404);
            return;
        }
        
        $offers = $db->fetchAll(
            "SELECT to.*, 
                    COUNT(tr.id) as received_responses
             FROM trade_offers to
             LEFT JOIN trade_responses tr ON to.id = tr.offer_id
             WHERE to.created_by_player_id = ?
             GROUP BY to.id
             ORDER BY to.created_at DESC",
            [$player['id']]
        );
        
        foreach ($offers as &$offer) {
            $offer['items_offered'] = json_decode($offer['items_offered'], true);
            $offer['items_requested'] = json_decode($offer['items_requested'], true);
            $offer['detailed_items_offered'] = getDetailedItems($offer['items_offered']);
            $offer['detailed_items_requested'] = getDetailedItems($offer['items_requested']);
        }
        
        sendJsonResponse([
            'offers' => $offers
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get your trade offers: ' . $e->getMessage()], 500);
    }
}

function getTradeHistory($user_id) {
    try {
        $db = DB::getInstance();
        
        $player = $db->fetchOne(
            "SELECT id FROM game_players WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$player) {
            sendJsonResponse(['error' => 'Player not found'], 404);
            return;
        }
        
        $history = $db->fetchAll(
            "SELECT th.*, 
                    u1.username as seller_username,
                    u2.username as buyer_username,
                    tr.rating, tr.review
             FROM trade_history th
             LEFT JOIN game_players gp1 ON th.seller_player_id = gp1.id
             LEFT JOIN users u1 ON gp1.user_id = u1.id
             LEFT JOIN game_players gp2 ON th.buyer_player_id = gp2.id
             LEFT JOIN users u2 ON gp2.user_id = u2.id
             LEFT JOIN trade_ratings tr ON th.id = tr.trade_id AND tr.rating_by_player_id = ?
             WHERE th.seller_player_id = ? OR th.buyer_player_id = ?
             ORDER BY th.completed_at DESC
             LIMIT 100",
            [$player['id'], $player['id'], $player['id']]
        );
        
        foreach ($history as &$trade) {
            $trade['items_traded'] = json_decode($trade['items_traded'], true);
            $trade['was_seller'] = $trade['seller_player_id'] == $player['id'];
        }
        
        sendJsonResponse([
            'history' => $history,
            'total_trades' => count($history)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get trade history: ' . $e->getMessage()], 500);
    }
}

function createTradeOffer($user_id, $input) {
    try {
        $db = DB::getInstance();
        $db->beginTransaction();
        
        $player = $db->fetchOne(
            "SELECT * FROM game_players WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$player) {
            throw new Exception('Player not found');
        }
        
        $items_offered = $input['items_offered'] ?? [];
        $items_requested = $input['items_requested'] ?? [];
        $tokens_requested = $input['tokens_requested'] ?? 0;
        $expires_hours = $input['expires_hours'] ?? 24;
        $description = $input['description'] ?? '';
        $trade_type = $input['trade_type'] ?? 'public'; // public, private, auction
        
        // Validate items offered
        if (empty($items_offered) && $tokens_requested == 0) {
            throw new Exception('Must offer items or tokens');
        }
        
        // Check if player owns offered items
        foreach ($items_offered as $item) {
            if (!verifyItemOwnership($player['id'], $item)) {
                throw new Exception('You do not own one or more offered items');
            }
        }
        
        // Check if player has enough tokens
        if (isset($items_offered['tokens']) && $items_offered['tokens'] > $player['tokens']) {
            throw new Exception('Insufficient tokens');
        }
        
        // Create trade offer
        $offer_id = $db->query(
            "INSERT INTO trade_offers 
             (created_by_player_id, items_offered, items_requested, tokens_requested, 
              expires_at, description, trade_type, status, created_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?, 'active', NOW())",
            [
                $player['id'],
                json_encode($items_offered),
                json_encode($items_requested),
                $tokens_requested,
                $expires_hours,
                $description,
                $trade_type
            ]
        );
        
        // Lock offered items
        lockTradeItems($player['id'], $items_offered, $offer_id);
        
        $db->commit();
        
        sendJsonResponse([
            'success' => true,
            'offer_id' => $offer_id,
            'expires_at' => date('c', strtotime("+{$expires_hours} hours"))
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        sendJsonResponse(['error' => 'Failed to create trade offer: ' . $e->getMessage()], 500);
    }
}

function acceptTradeOffer($user_id, $input) {
    try {
        $db = DB::getInstance();
        $db->beginTransaction();
        
        $offer_id = $input['offer_id'] ?? null;
        $offered_items = $input['offered_items'] ?? [];
        $offered_tokens = $input['offered_tokens'] ?? 0;
        
        if (!$offer_id) {
            throw new Exception('Offer ID required');
        }
        
        $buyer_player = $db->fetchOne(
            "SELECT * FROM game_players WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$buyer_player) {
            throw new Exception('Player not found');
        }
        
        // Get trade offer
        $offer = $db->fetchOne(
            "SELECT to.*, gp.user_id as seller_user_id 
             FROM trade_offers to
             JOIN game_players gp ON to.created_by_player_id = gp.id
             WHERE to.id = ? AND to.status = 'active' AND to.expires_at > NOW()",
            [$offer_id]
        );
        
        if (!$offer) {
            throw new Exception('Trade offer not found or expired');
        }
        
        if ($offer['seller_user_id'] == $user_id) {
            throw new Exception('Cannot accept your own trade offer');
        }
        
        $seller_player_id = $offer['created_by_player_id'];
        $items_requested = json_decode($offer['items_requested'], true);
        $tokens_requested = $offer['tokens_requested'];
        
        // Verify buyer has requested items/tokens
        if ($tokens_requested > 0 && $buyer_player['tokens'] < $tokens_requested) {
            throw new Exception('Insufficient tokens');
        }
        
        foreach ($items_requested as $item) {
            if (!verifyItemOwnership($buyer_player['id'], $item)) {
                throw new Exception('You do not own required items');
            }
        }
        
        // Execute the trade
        $trade_result = executeSecureTrade($seller_player_id, $buyer_player['id'], $offer);
        
        // Mark offer as completed
        $db->query(
            "UPDATE trade_offers SET status = 'completed', completed_at = NOW() WHERE id = ?",
            [$offer_id]
        );
        
        // Create trade history record
        $db->query(
            "INSERT INTO trade_history 
             (seller_player_id, buyer_player_id, offer_id, items_traded, tokens_traded, completed_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $seller_player_id,
                $buyer_player['id'],
                $offer_id,
                json_encode($trade_result['items_exchanged']),
                $tokens_requested
            ]
        );
        
        // Unlock any remaining locked items
        unlockTradeItems($offer_id);
        
        $db->commit();
        
        sendJsonResponse([
            'success' => true,
            'trade_result' => $trade_result,
            'message' => 'Trade completed successfully'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        sendJsonResponse(['error' => 'Trade failed: ' . $e->getMessage()], 500);
    }
}

function cancelTradeOffer($user_id, $offer_id) {
    try {
        $db = DB::getInstance();
        $db->beginTransaction();
        
        if (!$offer_id) {
            throw new Exception('Offer ID required');
        }
        
        $player = $db->fetchOne(
            "SELECT id FROM game_players WHERE user_id = ?",
            [$user_id]
        );
        
        $offer = $db->fetchOne(
            "SELECT * FROM trade_offers WHERE id = ? AND created_by_player_id = ?",
            [$offer_id, $player['id']]
        );
        
        if (!$offer) {
            throw new Exception('Trade offer not found or not yours');
        }
        
        if ($offer['status'] !== 'active') {
            throw new Exception('Cannot cancel completed or expired offer');
        }
        
        // Update offer status
        $db->query(
            "UPDATE trade_offers SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
            [$offer_id]
        );
        
        // Unlock items
        unlockTradeItems($offer_id);
        
        $db->commit();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Trade offer cancelled successfully'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        sendJsonResponse(['error' => 'Failed to cancel offer: ' . $e->getMessage()], 500);
    }
}

// Helper functions
function verifyItemOwnership($player_id, $item) {
    $db = DB::getInstance();
    
    switch ($item['type']) {
        case 'plant':
            $result = $db->fetchOne(
                "SELECT id FROM plants WHERE id = ? AND player_id = ? AND stage = 'harvested'",
                [$item['id'], $player_id]
            );
            return !empty($result);
            
        case 'genetics':
            $result = $db->fetchOne(
                "SELECT id FROM player_genetics WHERE genetics_id = ? AND player_id = ?",
                [$item['id'], $player_id]
            );
            return !empty($result);
            
        case 'tokens':
            $player = $db->fetchOne(
                "SELECT tokens FROM game_players WHERE id = ?",
                [$player_id]
            );
            return $player && $player['tokens'] >= $item['amount'];
            
        default:
            return false;
    }
}

function lockTradeItems($player_id, $items, $offer_id) {
    $db = DB::getInstance();
    
    foreach ($items as $item) {
        $db->query(
            "INSERT INTO trade_item_locks (player_id, item_type, item_id, offer_id, locked_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$player_id, $item['type'], $item['id'], $offer_id]
        );
    }
}

function unlockTradeItems($offer_id) {
    $db = DB::getInstance();
    
    $db->query(
        "DELETE FROM trade_item_locks WHERE offer_id = ?",
        [$offer_id]
    );
}

function executeSecureTrade($seller_player_id, $buyer_player_id, $offer) {
    $db = DB::getInstance();
    
    $items_offered = json_decode($offer['items_offered'], true);
    $items_requested = json_decode($offer['items_requested'], true);
    $tokens_requested = $offer['tokens_requested'];
    
    $trade_result = [
        'items_exchanged' => [],
        'tokens_exchanged' => $tokens_requested
    ];
    
    // Transfer items from seller to buyer
    foreach ($items_offered as $item) {
        transferItem($seller_player_id, $buyer_player_id, $item);
        $trade_result['items_exchanged']['from_seller'][] = $item;
    }
    
    // Transfer items from buyer to seller
    foreach ($items_requested as $item) {
        transferItem($buyer_player_id, $seller_player_id, $item);
        $trade_result['items_exchanged']['from_buyer'][] = $item;
    }
    
    // Transfer tokens
    if ($tokens_requested > 0) {
        $db->query(
            "UPDATE game_players SET tokens = tokens - ? WHERE id = ?",
            [$tokens_requested, $buyer_player_id]
        );
        
        $db->query(
            "UPDATE game_players SET tokens = tokens + ? WHERE id = ?",
            [$tokens_requested, $seller_player_id]
        );
    }
    
    return $trade_result;
}

function transferItem($from_player_id, $to_player_id, $item) {
    $db = DB::getInstance();
    
    switch ($item['type']) {
        case 'plant':
            $db->query(
                "UPDATE plants SET player_id = ? WHERE id = ? AND player_id = ?",
                [$to_player_id, $item['id'], $from_player_id]
            );
            break;
            
        case 'genetics':
            // Remove from seller
            $db->query(
                "DELETE FROM player_genetics WHERE genetics_id = ? AND player_id = ?",
                [$item['id'], $from_player_id]
            );
            
            // Add to buyer
            $db->query(
                "INSERT INTO player_genetics (player_id, genetics_id, acquired_at, acquired_method)
                 VALUES (?, ?, NOW(), 'trade')",
                [$to_player_id, $item['id']]
            );
            break;
    }
}

function getDetailedItems($items) {
    if (empty($items)) return [];
    
    $db = DB::getInstance();
    $detailed = [];
    
    foreach ($items as $item) {
        switch ($item['type']) {
            case 'plant':
                $plant = $db->fetchOne(
                    "SELECT p.*, s.name as strain_name, s.rarity FROM plants p
                     JOIN genetics s ON p.strain_id = s.id
                     WHERE p.id = ?",
                    [$item['id']]
                );
                if ($plant) {
                    $detailed[] = array_merge($item, $plant);
                }
                break;
                
            case 'genetics':
                $genetics = $db->fetchOne(
                    "SELECT * FROM genetics WHERE id = ?",
                    [$item['id']]
                );
                if ($genetics) {
                    $detailed[] = array_merge($item, $genetics);
                }
                break;
                
            case 'tokens':
                $detailed[] = $item;
                break;
        }
    }
    
    return $detailed;
}

function calculateOfferValue($items) {
    $total_value = 0;
    
    foreach ($items as $item) {
        switch ($item['type']) {
            case 'plant':
                $total_value += 50; // Base plant value
                break;
            case 'genetics':
                $total_value += 100; // Base genetics value
                break;
            case 'tokens':
                $total_value += $item['amount'];
                break;
        }
    }
    
    return $total_value;
}

?>