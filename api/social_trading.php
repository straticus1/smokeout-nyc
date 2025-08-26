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
array_shift($segments); // remove 'social-trading'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'strategies':
            handleStrategyEndpoints($method, $id, $user['id']);
            break;
        case 'copy':
            handleCopyTradingEndpoints($method, $id, $user['id']);
            break;
        case 'leaderboard':
            handleLeaderboardEndpoints($method, $id, $user['id']);
            break;
        case 'portfolio':
            handlePortfolioEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Social trading endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleStrategyEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/social-trading/strategies/{id} - Get specific strategy
                $stmt = $pdo->prepare("
                    SELECT ts.*, u.username as creator_name, gp.level as creator_level,
                           COUNT(DISTINCT tc.id) as total_copiers,
                           AVG(tc.performance_rating) as avg_rating,
                           ts.total_profit / NULLIF(ts.total_invested, 0) * 100 as roi_percentage
                    FROM trading_strategies ts
                    JOIN users u ON ts.creator_user_id = u.id
                    JOIN game_players gp ON u.id = gp.user_id
                    LEFT JOIN trading_copiers tc ON ts.id = tc.strategy_id AND tc.status = 'active'
                    WHERE ts.id = ? AND ts.is_public = TRUE
                    GROUP BY ts.id
                ");
                $stmt->execute([$id]);
                $strategy = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($strategy) {
                    $strategy['strategy_config'] = json_decode($strategy['strategy_config'], true);
                    $strategy['performance_history'] = json_decode($strategy['performance_history'], true);
                    
                    // Get recent trades
                    $trades_stmt = $pdo->prepare("
                        SELECT st.*, s.strain_name, l.location_name
                        FROM strategy_trades st
                        LEFT JOIN strains s ON st.strain_id = s.id
                        LEFT JOIN locations l ON st.location_id = l.id
                        WHERE st.strategy_id = ?
                        ORDER BY st.executed_at DESC
                        LIMIT 20
                    ");
                    $trades_stmt->execute([$id]);
                    $strategy['recent_trades'] = $trades_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                echo json_encode(['strategy' => $strategy]);
            } else {
                // GET /api/social-trading/strategies - Get all public strategies
                $sort_by = $_GET['sort'] ?? 'performance'; // performance, popularity, recent
                $category = $_GET['category'] ?? 'all';
                $min_roi = $_GET['min_roi'] ?? 0;
                
                $where_conditions = ["ts.is_public = TRUE"];
                $params = [];
                
                if ($category !== 'all') {
                    $where_conditions[] = "ts.strategy_category = ?";
                    $params[] = $category;
                }
                
                if ($min_roi > 0) {
                    $where_conditions[] = "(ts.total_profit / NULLIF(ts.total_invested, 0) * 100) >= ?";
                    $params[] = $min_roi;
                }
                
                $order_clause = match($sort_by) {
                    'performance' => 'ORDER BY roi_percentage DESC',
                    'popularity' => 'ORDER BY total_copiers DESC',
                    'recent' => 'ORDER BY ts.created_at DESC',
                    default => 'ORDER BY roi_percentage DESC'
                };
                
                $stmt = $pdo->prepare("
                    SELECT ts.*, u.username as creator_name, gp.level as creator_level,
                           COUNT(DISTINCT tc.id) as total_copiers,
                           AVG(tc.performance_rating) as avg_rating,
                           ts.total_profit / NULLIF(ts.total_invested, 0) * 100 as roi_percentage
                    FROM trading_strategies ts
                    JOIN users u ON ts.creator_user_id = u.id
                    JOIN game_players gp ON u.id = gp.user_id
                    LEFT JOIN trading_copiers tc ON ts.id = tc.strategy_id AND tc.status = 'active'
                    WHERE " . implode(' AND ', $where_conditions) . "
                    GROUP BY ts.id
                    {$order_clause}
                    LIMIT 50
                ");
                $stmt->execute($params);
                $strategies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($strategies as &$strategy) {
                    $strategy['strategy_config'] = json_decode($strategy['strategy_config'], true);
                }
                
                echo json_encode(['strategies' => $strategies]);
            }
            break;
            
        case 'POST':
            // POST /api/social-trading/strategies - Create new strategy
            $data = json_decode(file_get_contents('php://input'), true);
            
            $strategy_config = [
                'strain_preferences' => $data['strain_preferences'] ?? [],
                'location_preferences' => $data['location_preferences'] ?? [],
                'risk_tolerance' => $data['risk_tolerance'] ?? 'medium',
                'investment_limits' => $data['investment_limits'] ?? [],
                'timing_rules' => $data['timing_rules'] ?? [],
                'profit_targets' => $data['profit_targets'] ?? [],
                'stop_loss_rules' => $data['stop_loss_rules'] ?? []
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO trading_strategies 
                (creator_user_id, strategy_name, description, strategy_category, 
                 strategy_config, is_public, copy_fee_percentage)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $data['strategy_name'],
                $data['description'],
                $data['category'],
                json_encode($strategy_config),
                $data['is_public'] ?? true,
                $data['copy_fee'] ?? 5.0
            ]);
            
            $strategy_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'strategy_id' => $strategy_id,
                'message' => 'Trading strategy created successfully'
            ]);
            break;
            
        case 'PUT':
            // PUT /api/social-trading/strategies/{id} - Update strategy
            $strategy_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verify ownership
            $owner_check = $pdo->prepare("SELECT creator_user_id FROM trading_strategies WHERE id = ?");
            $owner_check->execute([$strategy_id]);
            $strategy = $owner_check->fetch();
            
            if (!$strategy || $strategy['creator_user_id'] != $user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = $data['description'];
            }
            
            if (isset($data['strategy_config'])) {
                $updates[] = "strategy_config = ?";
                $params[] = json_encode($data['strategy_config']);
            }
            
            if (isset($data['is_public'])) {
                $updates[] = "is_public = ?";
                $params[] = $data['is_public'];
            }
            
            if (isset($data['copy_fee'])) {
                $updates[] = "copy_fee_percentage = ?";
                $params[] = $data['copy_fee'];
            }
            
            if (!empty($updates)) {
                $params[] = $strategy_id;
                $stmt = $pdo->prepare("
                    UPDATE trading_strategies 
                    SET " . implode(', ', $updates) . ", updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute($params);
            }
            
            echo json_encode(['success' => true, 'message' => 'Strategy updated']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleCopyTradingEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/social-trading/copy - Get user's copy trading positions
            $stmt = $pdo->prepare("
                SELECT tc.*, ts.strategy_name, u.username as creator_name,
                       tc.total_invested, tc.current_value, tc.total_profit,
                       (tc.current_value - tc.total_invested) as unrealized_pnl
                FROM trading_copiers tc
                JOIN trading_strategies ts ON tc.strategy_id = ts.id
                JOIN users u ON ts.creator_user_id = u.id
                WHERE tc.copier_user_id = ? AND tc.status = 'active'
                ORDER BY tc.started_at DESC
            ");
            $stmt->execute([$user_id]);
            $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['copy_positions' => $positions]);
            break;
            
        case 'POST':
            // POST /api/social-trading/copy/{strategy_id} - Start copying strategy
            $strategy_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            $investment_amount = $data['investment_amount'];
            $copy_percentage = $data['copy_percentage'] ?? 100; // % of trades to copy
            
            // Get strategy details
            $strategy_stmt = $pdo->prepare("
                SELECT ts.*, u.username as creator_name
                FROM trading_strategies ts
                JOIN users u ON ts.creator_user_id = u.id
                WHERE ts.id = ? AND ts.is_public = TRUE
            ");
            $strategy_stmt->execute([$strategy_id]);
            $strategy = $strategy_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$strategy) {
                http_response_code(404);
                echo json_encode(['error' => 'Strategy not found']);
                return;
            }
            
            // Check if user has enough tokens
            $player_stmt = $pdo->prepare("SELECT * FROM game_players WHERE user_id = ?");
            $player_stmt->execute([$user_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player || $player['tokens'] < $investment_amount) {
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient tokens']);
                return;
            }
            
            // Check if already copying this strategy
            $existing_stmt = $pdo->prepare("
                SELECT id FROM trading_copiers 
                WHERE copier_user_id = ? AND strategy_id = ? AND status = 'active'
            ");
            $existing_stmt->execute([$user_id, $strategy_id]);
            if ($existing_stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Already copying this strategy']);
                return;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Deduct investment amount
                $pdo->prepare("
                    UPDATE game_players 
                    SET tokens = tokens - ? 
                    WHERE user_id = ?
                ")->execute([$investment_amount, $user_id]);
                
                // Create copy trading position
                $stmt = $pdo->prepare("
                    INSERT INTO trading_copiers 
                    (copier_user_id, strategy_id, investment_amount, copy_percentage, 
                     total_invested, current_value, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $user_id, $strategy_id, $investment_amount, $copy_percentage,
                    $investment_amount, $investment_amount
                ]);
                
                $copier_id = $pdo->lastInsertId();
                
                // Log transaction
                $pdo->prepare("
                    INSERT INTO game_transactions 
                    (player_id, transaction_type, item_type, item_id, amount, currency_type)
                    VALUES (?, 'copy_trading', 'strategy', ?, ?, 'tokens')
                ")->execute([$player['id'], $strategy_id, $investment_amount]);
                
                // Pay copy fee to strategy creator
                $copy_fee = $investment_amount * ($strategy['copy_fee_percentage'] / 100);
                if ($copy_fee > 0) {
                    $creator_stmt = $pdo->prepare("SELECT id FROM game_players WHERE user_id = ?");
                    $creator_stmt->execute([$strategy['creator_user_id']]);
                    $creator = $creator_stmt->fetch();
                    
                    if ($creator) {
                        $pdo->prepare("
                            UPDATE game_players 
                            SET tokens = tokens + ? 
                            WHERE id = ?
                        ")->execute([$copy_fee, $creator['id']]);
                        
                        // Log creator fee
                        $pdo->prepare("
                            INSERT INTO game_transactions 
                            (player_id, transaction_type, item_type, item_id, amount, currency_type)
                            VALUES (?, 'copy_fee_earned', 'strategy', ?, ?, 'tokens')
                        ")->execute([$creator['id'], $strategy_id, $copy_fee]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'copier_id' => $copier_id,
                    'strategy_name' => $strategy['strategy_name'],
                    'investment_amount' => $investment_amount,
                    'copy_fee_paid' => $copy_fee
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        case 'DELETE':
            // DELETE /api/social-trading/copy/{copier_id} - Stop copying strategy
            $copier_id = $id;
            
            // Get copier details
            $copier_stmt = $pdo->prepare("
                SELECT tc.*, ts.strategy_name
                FROM trading_copiers tc
                JOIN trading_strategies ts ON tc.strategy_id = ts.id
                WHERE tc.id = ? AND tc.copier_user_id = ? AND tc.status = 'active'
            ");
            $copier_stmt->execute([$copier_id, $user_id]);
            $copier = $copier_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$copier) {
                http_response_code(404);
                echo json_encode(['error' => 'Copy position not found']);
                return;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Return current value to user
                $player_stmt = $pdo->prepare("SELECT id FROM game_players WHERE user_id = ?");
                $player_stmt->execute([$user_id]);
                $player = $player_stmt->fetch();
                
                $pdo->prepare("
                    UPDATE game_players 
                    SET tokens = tokens + ? 
                    WHERE id = ?
                ")->execute([$copier['current_value'], $player['id']]);
                
                // Close copy position
                $pdo->prepare("
                    UPDATE trading_copiers 
                    SET status = 'closed', 
                        final_value = current_value,
                        closed_at = NOW()
                    WHERE id = ?
                ")->execute([$copier_id]);
                
                // Log transaction
                $pdo->prepare("
                    INSERT INTO game_transactions 
                    (player_id, transaction_type, item_type, item_id, amount, currency_type)
                    VALUES (?, 'copy_trading_close', 'strategy', ?, ?, 'tokens')
                ")->execute([$player['id'], $copier['strategy_id'], $copier['current_value']]);
                
                $pdo->commit();
                
                $profit_loss = $copier['current_value'] - $copier['total_invested'];
                
                echo json_encode([
                    'success' => true,
                    'strategy_name' => $copier['strategy_name'],
                    'final_value' => $copier['current_value'],
                    'profit_loss' => $profit_loss,
                    'roi_percentage' => round(($profit_loss / $copier['total_invested']) * 100, 2)
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

function handleLeaderboardEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            $timeframe = $_GET['timeframe'] ?? '30d'; // 7d, 30d, 90d, all
            $category = $_GET['category'] ?? 'all';
            
            $date_filter = match($timeframe) {
                '7d' => 'AND ts.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                '30d' => 'AND ts.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                '90d' => 'AND ts.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)',
                default => ''
            };
            
            $category_filter = $category !== 'all' ? 'AND ts.strategy_category = ?' : '';
            $params = $category !== 'all' ? [$category] : [];
            
            $stmt = $pdo->prepare("
                SELECT ts.id, ts.strategy_name, ts.strategy_category,
                       u.username as creator_name, gp.level as creator_level,
                       ts.total_profit / NULLIF(ts.total_invested, 0) * 100 as roi_percentage,
                       ts.total_trades, ts.win_rate,
                       COUNT(DISTINCT tc.id) as total_copiers,
                       AVG(tc.performance_rating) as avg_rating,
                       ts.created_at,
                       RANK() OVER (ORDER BY ts.total_profit / NULLIF(ts.total_invested, 0) DESC) as rank_position
                FROM trading_strategies ts
                JOIN users u ON ts.creator_user_id = u.id
                JOIN game_players gp ON u.id = gp.user_id
                LEFT JOIN trading_copiers tc ON ts.id = tc.strategy_id AND tc.status = 'active'
                WHERE ts.is_public = TRUE 
                AND ts.total_trades >= 5 
                {$date_filter} 
                {$category_filter}
                GROUP BY ts.id
                ORDER BY roi_percentage DESC
                LIMIT 100
            ");
            $stmt->execute($params);
            $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'leaderboard' => $leaderboard,
                'timeframe' => $timeframe,
                'category' => $category
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handlePortfolioEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/social-trading/portfolio - Get user's trading portfolio
            
            // Get created strategies
            $strategies_stmt = $pdo->prepare("
                SELECT ts.*, 
                       COUNT(DISTINCT tc.id) as total_copiers,
                       SUM(tc.investment_amount) as total_copied_amount,
                       AVG(tc.performance_rating) as avg_rating
                FROM trading_strategies ts
                LEFT JOIN trading_copiers tc ON ts.id = tc.strategy_id AND tc.status = 'active'
                WHERE ts.creator_user_id = ?
                GROUP BY ts.id
                ORDER BY ts.created_at DESC
            ");
            $strategies_stmt->execute([$user_id]);
            $created_strategies = $strategies_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get copy positions
            $positions_stmt = $pdo->prepare("
                SELECT tc.*, ts.strategy_name, u.username as creator_name,
                       (tc.current_value - tc.total_invested) as unrealized_pnl,
                       (tc.current_value - tc.total_invested) / tc.total_invested * 100 as roi_percentage
                FROM trading_copiers tc
                JOIN trading_strategies ts ON tc.strategy_id = ts.id
                JOIN users u ON ts.creator_user_id = u.id
                WHERE tc.copier_user_id = ?
                ORDER BY tc.started_at DESC
            ");
            $positions_stmt->execute([$user_id]);
            $copy_positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate portfolio summary
            $total_invested = array_sum(array_column($copy_positions, 'total_invested'));
            $current_value = array_sum(array_column($copy_positions, 'current_value'));
            $total_profit = $current_value - $total_invested;
            $portfolio_roi = $total_invested > 0 ? ($total_profit / $total_invested) * 100 : 0;
            
            // Get earnings from created strategies
            $earnings_stmt = $pdo->prepare("
                SELECT SUM(gt.amount) as total_copy_fees
                FROM game_transactions gt
                JOIN game_players gp ON gt.player_id = gp.id
                WHERE gp.user_id = ? AND gt.transaction_type = 'copy_fee_earned'
            ");
            $earnings_stmt->execute([$user_id]);
            $earnings = $earnings_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'portfolio_summary' => [
                    'total_invested' => $total_invested,
                    'current_value' => $current_value,
                    'total_profit' => $total_profit,
                    'roi_percentage' => round($portfolio_roi, 2),
                    'copy_fees_earned' => $earnings['total_copy_fees'] ?? 0
                ],
                'created_strategies' => $created_strategies,
                'copy_positions' => $copy_positions
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}
?>
