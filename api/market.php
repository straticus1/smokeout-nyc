<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'auth_helper.php';
require_once 'services/MarketDynamicsService.php';

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
    error_log("Market API error: " . $e->getMessage());
    sendJsonResponse(['error' => 'Internal server error'], 500);
}

function handleGet($uri_parts) {
    $action = $uri_parts[2] ?? 'prices';
    
    switch ($action) {
        case 'prices':
            getMarketPrices();
            break;
        case 'trends':
            getMarketTrends();
            break;
        case 'history':
            getPriceHistory();
            break;
        case 'locations':
            getMarketLocations();
            break;
        case 'events':
            getMarketEvents();
            break;
        case 'analytics':
            getMarketAnalytics();
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
        case 'update-cycle':
            updateMarketCycle();
            break;
        case 'sell':
            sellToMarket($user_id, $input);
            break;
        case 'buy':
            buyFromMarket($user_id, $input);
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
    
    sendJsonResponse(['error' => 'PUT operations not supported'], 405);
}

function handleDelete($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    sendJsonResponse(['error' => 'Delete operations not supported'], 405);
}

function getMarketPrices() {
    try {
        $location_id = $_GET['location_id'] ?? null;
        $strain_id = $_GET['strain_id'] ?? null;
        
        $prices = MarketDynamicsService::getMarketPrices($location_id);
        
        // Filter by strain if specified
        if ($strain_id) {
            $prices = array_filter($prices, function($price) use ($strain_id) {
                return $price['strain_id'] == $strain_id;
            });
        }
        
        // Add real-time market data
        foreach ($prices as &$price) {
            $price['current_price'] = calculateCurrentPrice($price);
            $price['price_change_24h'] = calculatePriceChange($price['strain_id'], $price['location_id']);
            $price['market_cap'] = calculateMarketCap($price);
        }
        
        sendJsonResponse([
            'prices' => array_values($prices),
            'location_filter' => $location_id,
            'strain_filter' => $strain_id,
            'last_updated' => date('c'),
            'market_open' => true
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get market prices: ' . $e->getMessage()], 500);
    }
}

function getMarketTrends() {
    try {
        $trends = MarketDynamicsService::getMarketTrends();
        
        // Add additional trend data
        $trends['market_sentiment'] = calculateMarketSentiment();
        $trends['volatility_index'] = calculateVolatilityIndex();
        $trends['trading_volume_24h'] = getTradingVolume24h();
        
        sendJsonResponse([
            'trends' => $trends,
            'generated_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get market trends: ' . $e->getMessage()], 500);
    }
}

function getPriceHistory() {
    try {
        $strain_id = $_GET['strain_id'] ?? null;
        $location_id = $_GET['location_id'] ?? null;
        $days = (int)($_GET['days'] ?? 7);
        
        if (!$strain_id) {
            sendJsonResponse(['error' => 'Strain ID required'], 400);
            return;
        }
        
        $history = MarketDynamicsService::getPriceHistory($strain_id, $location_id, $days);
        
        // Calculate additional metrics
        $metrics = calculatePriceMetrics($history);
        
        sendJsonResponse([
            'history' => $history,
            'metrics' => $metrics,
            'strain_id' => $strain_id,
            'location_id' => $location_id,
            'period_days' => $days
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get price history: ' . $e->getMessage()], 500);
    }
}

function getMarketLocations() {
    try {
        $db = DB::getInstance();
        
        $locations = $db->fetchAll(
            "SELECT l.*, 
                    COUNT(mc.id) as active_markets,
                    AVG(mc.price_modifier) as avg_price_modifier,
                    AVG(mc.demand_level) as avg_demand,
                    AVG(mc.supply_level) as avg_supply
             FROM growing_locations l
             LEFT JOIN market_conditions mc ON l.id = mc.location_id
             WHERE l.is_active = 1
             GROUP BY l.id
             ORDER BY avg_price_modifier DESC"
        );
        
        sendJsonResponse([
            'locations' => $locations,
            'total_locations' => count($locations)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get market locations: ' . $e->getMessage()], 500);
    }
}

function getMarketEvents() {
    try {
        $db = DB::getInstance();
        
        // Get active events
        $active_events = $db->fetchAll(
            "SELECT * FROM market_events 
             WHERE is_active = 1 AND end_time > NOW()
             ORDER BY start_time DESC"
        );
        
        // Get recent events
        $recent_events = $db->fetchAll(
            "SELECT * FROM market_events 
             WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY start_time DESC
             LIMIT 10"
        );
        
        sendJsonResponse([
            'active_events' => $active_events,
            'recent_events' => $recent_events,
            'event_count' => count($active_events)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get market events: ' . $e->getMessage()], 500);
    }
}

function getMarketAnalytics() {
    try {
        $db = DB::getInstance();
        
        // Top performing strains
        $top_strains = $db->fetchAll(
            "SELECT g.name, g.rarity,
                    AVG(mc.price_modifier) as avg_price,
                    AVG(mc.demand_level) as avg_demand,
                    COUNT(DISTINCT mc.location_id) as market_presence
             FROM genetics g
             JOIN market_conditions mc ON g.id = mc.strain_id
             GROUP BY g.id
             ORDER BY avg_price DESC
             LIMIT 10"
        );
        
        // Market volume by location
        $location_volume = $db->fetchAll(
            "SELECT l.name as location_name,
                    COUNT(s.id) as sales_count,
                    AVG(s.final_price) as avg_price,
                    SUM(s.quantity) as total_quantity
             FROM growing_locations l
             LEFT JOIN sales s ON l.id = s.location_id AND s.sold_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE l.is_active = 1
             GROUP BY l.id
             ORDER BY sales_count DESC"
        );
        
        // Market health indicators
        $health_metrics = [
            'active_traders' => $db->fetchOne(
                "SELECT COUNT(DISTINCT player_id) as count FROM sales 
                 WHERE sold_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )['count'] ?? 0,
            'total_volume_7d' => $db->fetchOne(
                "SELECT COUNT(*) as count, SUM(final_price) as volume FROM sales 
                 WHERE sold_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            ),
            'avg_transaction_value' => $db->fetchOne(
                "SELECT AVG(final_price) as avg FROM sales 
                 WHERE sold_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )['avg'] ?? 0
        ];
        
        sendJsonResponse([
            'top_performing_strains' => $top_strains,
            'location_volume' => $location_volume,
            'health_metrics' => $health_metrics,
            'generated_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get market analytics: ' . $e->getMessage()], 500);
    }
}

function updateMarketCycle() {
    try {
        AutoMarketSystem::processMarketCycle();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Market cycle updated successfully',
            'updated_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to update market cycle: ' . $e->getMessage()], 500);
    }
}

function sellToMarket($user_id, $input) {
    try {
        $plant_id = $input['plant_id'] ?? null;
        $location_id = $input['location_id'] ?? null;
        $asking_price = $input['asking_price'] ?? null;
        
        if (!$plant_id || !$location_id) {
            sendJsonResponse(['error' => 'Plant ID and location ID required'], 400);
            return;
        }
        
        $db = DB::getInstance();
        
        // Get plant and verify ownership
        $plant = $db->fetchOne(
            "SELECT p.*, gp.user_id FROM plants p
             JOIN game_players gp ON p.player_id = gp.id
             WHERE p.id = ? AND gp.user_id = ? AND p.stage = 'harvested'",
            [$plant_id, $user_id]
        );
        
        if (!$plant) {
            sendJsonResponse(['error' => 'Plant not found or not ready for sale'], 404);
            return;
        }
        
        // Get market price
        $market_price = calculateCurrentPrice([
            'strain_id' => $plant['strain_id'],
            'location_id' => $location_id
        ]);
        
        // Use asking price or market price
        $final_price = $asking_price ?? $market_price;
        
        // Create sale record
        $sale_id = $db->query(
            "INSERT INTO sales 
             (player_id, plant_id, location_id, strain_id, quantity, quality, 
              base_price, final_price, sold_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $plant['player_id'],
                $plant_id,
                $location_id,
                $plant['strain_id'],
                $plant['final_weight'],
                $plant['final_quality'],
                $market_price,
                $final_price
            ]
        );
        
        // Award tokens to player
        $tokens_earned = round($final_price);
        $db->query(
            "UPDATE game_players SET tokens = tokens + ? WHERE user_id = ?",
            [$tokens_earned, $user_id]
        );
        
        // Update plant status
        $db->query(
            "UPDATE plants SET stage = 'sold', updated_at = NOW() WHERE id = ?",
            [$plant_id]
        );
        
        sendJsonResponse([
            'success' => true,
            'sale_id' => $sale_id,
            'final_price' => $final_price,
            'tokens_earned' => $tokens_earned,
            'market_price' => $market_price
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Sale failed: ' . $e->getMessage()], 500);
    }
}

function buyFromMarket($user_id, $input) {
    // Note: This would be for a future feature where players can buy from other players
    sendJsonResponse(['error' => 'Player-to-player trading not yet implemented'], 501);
}

// Helper functions
function calculateCurrentPrice($price_data) {
    $base_price = 50; // Base price per gram
    $modifier = $price_data['price_modifier'] ?? 1.0;
    $location_modifier = $price_data['market_modifier'] ?? 1.0;
    
    return round($base_price * $modifier * $location_modifier, 2);
}

function calculatePriceChange($strain_id, $location_id) {
    $db = DB::getInstance();
    
    $current = $db->fetchOne(
        "SELECT price_modifier FROM market_conditions 
         WHERE strain_id = ? AND location_id = ?",
        [$strain_id, $location_id]
    );
    
    $yesterday = $db->fetchOne(
        "SELECT price_modifier FROM price_history 
         WHERE strain_id = ? AND location_id = ? 
         AND recorded_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
         ORDER BY recorded_at ASC
         LIMIT 1",
        [$strain_id, $location_id]
    );
    
    if ($current && $yesterday) {
        $change = (($current['price_modifier'] - $yesterday['price_modifier']) / $yesterday['price_modifier']) * 100;
        return round($change, 2);
    }
    
    return 0;
}

function calculateMarketCap($price_data) {
    // Simplified market cap calculation
    $price = calculateCurrentPrice($price_data);
    $estimated_supply = ($price_data['supply_level'] ?? 50) * 10; // Rough estimate
    
    return round($price * $estimated_supply);
}

function calculateMarketSentiment() {
    $db = DB::getInstance();
    
    $sentiment_data = $db->fetchOne(
        "SELECT 
            AVG(CASE WHEN demand_level > supply_level THEN 1 ELSE -1 END) as sentiment_score,
            COUNT(*) as total_markets
         FROM market_conditions"
    );
    
    $score = $sentiment_data['sentiment_score'] ?? 0;
    
    if ($score > 0.3) return 'bullish';
    if ($score < -0.3) return 'bearish';
    return 'neutral';
}

function calculateVolatilityIndex() {
    $db = DB::getInstance();
    
    $volatility = $db->fetchOne(
        "SELECT AVG(volatility) as avg_volatility FROM market_conditions"
    )['avg_volatility'] ?? 0;
    
    return round($volatility * 100, 1);
}

function getTradingVolume24h() {
    $db = DB::getInstance();
    
    return $db->fetchOne(
        "SELECT COUNT(*) as trades, SUM(final_price) as volume FROM sales 
         WHERE sold_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
    );
}

function calculatePriceMetrics($history) {
    if (empty($history)) {
        return ['min' => 0, 'max' => 0, 'avg' => 0, 'volatility' => 0];
    }
    
    $prices = array_column($history, 'price_modifier');
    
    return [
        'min' => min($prices),
        'max' => max($prices),
        'avg' => array_sum($prices) / count($prices),
        'volatility' => calculateArrayStdDev($prices)
    ];
}

function calculateArrayStdDev($array) {
    $avg = array_sum($array) / count($array);
    $variance = array_sum(array_map(function($x) use ($avg) {
        return pow($x - $avg, 2);
    }, $array)) / count($array);
    
    return sqrt($variance);
}

?>