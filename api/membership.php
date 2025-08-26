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
array_shift($segments); // remove 'membership'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'tiers':
            handleTierEndpoints($method, $id, $user['id']);
            break;
        case 'subscribe':
            handleSubscriptionEndpoints($method, $id, $user['id']);
            break;
        case 'status':
            handleStatusEndpoints($method, $id, $user['id']);
            break;
        case 'analytics':
            handleAnalyticsEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Membership endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleTierEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/membership/tiers - Get all membership tiers
            $stmt = $pdo->prepare("
                SELECT mt.*, 
                       CASE WHEN um.id IS NOT NULL THEN TRUE ELSE FALSE END as is_current_tier
                FROM membership_tiers mt
                LEFT JOIN user_memberships um ON mt.id = um.tier_id 
                    AND um.user_id = ? AND um.status = 'active'
                WHERE mt.is_active = TRUE
                ORDER BY mt.monthly_price ASC
            ");
            $stmt->execute([$user_id]);
            $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON fields
            foreach ($tiers as &$tier) {
                $tier['features'] = json_decode($tier['features'], true);
                $tier['limits_config'] = json_decode($tier['limits_config'], true);
            }
            
            echo json_encode(['tiers' => $tiers]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleSubscriptionEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'POST':
            // POST /api/membership/subscribe - Subscribe to membership tier
            $data = json_decode(file_get_contents('php://input'), true);
            $tier_id = $data['tier_id'];
            $payment_method = $data['payment_method'] ?? 'credit_card';
            $billing_cycle = $data['billing_cycle'] ?? 'monthly'; // monthly or annual
            
            // Get tier details
            $tier_stmt = $pdo->prepare("SELECT * FROM membership_tiers WHERE id = ? AND is_active = TRUE");
            $tier_stmt->execute([$tier_id]);
            $tier = $tier_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tier) {
                http_response_code(404);
                echo json_encode(['error' => 'Membership tier not found']);
                return;
            }
            
            // Calculate price and expiration
            $price = $billing_cycle === 'annual' ? $tier['annual_price'] : $tier['monthly_price'];
            $duration = $billing_cycle === 'annual' ? '1 YEAR' : '1 MONTH';
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration}"));
            
            // Check if user already has active membership
            $existing_stmt = $pdo->prepare("
                SELECT id FROM user_memberships 
                WHERE user_id = ? AND status = 'active'
            ");
            $existing_stmt->execute([$user_id]);
            $existing = $existing_stmt->fetch();
            
            try {
                $pdo->beginTransaction();
                
                // Cancel existing membership
                if ($existing) {
                    $pdo->prepare("
                        UPDATE user_memberships 
                        SET status = 'cancelled' 
                        WHERE id = ?
                    ")->execute([$existing['id']]);
                }
                
                // Create new membership
                $membership_stmt = $pdo->prepare("
                    INSERT INTO user_memberships 
                    (user_id, tier_id, expires_at, payment_method, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $membership_stmt->execute([$user_id, $tier_id, $expires_at, $payment_method]);
                
                $membership_id = $pdo->lastInsertId();
                
                // Process payment (simplified - would integrate with payment processor)
                if ($payment_method === 'tokens') {
                    // Deduct tokens from game player
                    $token_cost = $price * 10; // 1 dollar = 10 tokens
                    $player_stmt = $pdo->prepare("
                        UPDATE game_players 
                        SET tokens = tokens - ?
                        WHERE user_id = ? AND tokens >= ?
                    ");
                    $affected = $player_stmt->execute([$token_cost, $user_id, $token_cost]);
                    
                    if ($player_stmt->rowCount() === 0) {
                        throw new Exception('Insufficient tokens for membership');
                    }
                }
                
                // Log revenue transaction
                $pdo->prepare("
                    INSERT INTO revenue_transactions 
                    (shop_owner_id, transaction_type, gross_amount, platform_fee_rate, 
                     platform_fee_amount, net_amount, payment_method, status)
                    SELECT so.id, 'membership_fee', ?, 0.0000, 0.00, ?, ?, 'completed'
                    FROM shop_owners so WHERE so.user_id = ?
                ")->execute([$price, $price, $payment_method, $user_id]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'membership_id' => $membership_id,
                    'tier_name' => $tier['tier_name'],
                    'expires_at' => $expires_at,
                    'price_paid' => $price
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        case 'DELETE':
            // DELETE /api/membership/subscribe - Cancel membership
            $stmt = $pdo->prepare("
                UPDATE user_memberships 
                SET status = 'cancelled', auto_renew = FALSE
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Membership cancelled successfully'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleStatusEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/membership/status - Get current membership status
            $stmt = $pdo->prepare("
                SELECT um.*, mt.tier_name, mt.features, mt.limits_config,
                       DATEDIFF(um.expires_at, NOW()) as days_remaining
                FROM user_memberships um
                JOIN membership_tiers mt ON um.tier_id = mt.id
                WHERE um.user_id = ? AND um.status = 'active'
            ");
            $stmt->execute([$user_id]);
            $membership = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($membership) {
                $membership['features'] = json_decode($membership['features'], true);
                $membership['limits_config'] = json_decode($membership['limits_config'], true);
                
                // Get usage statistics
                $usage_stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT ra.id) as ai_assessments_used,
                        COUNT(DISTINCT rt.id) as api_calls_today,
                        COUNT(DISTINCT sl.id) as locations_tracked
                    FROM user_memberships um
                    LEFT JOIN shop_owners so ON um.user_id = so.user_id
                    LEFT JOIN risk_assessments ra ON ra.last_updated >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                    LEFT JOIN revenue_transactions rt ON rt.shop_owner_id = so.id AND DATE(rt.processed_at) = CURDATE()
                    LEFT JOIN shop_locations sl ON sl.shop_owner_id = so.id AND sl.status = 'active'
                    WHERE um.id = ?
                ");
                $usage_stmt->execute([$membership['id']]);
                $usage = $usage_stmt->fetch(PDO::FETCH_ASSOC);
                
                $membership['usage'] = $usage;
            }
            
            echo json_encode([
                'membership' => $membership,
                'has_active_membership' => $membership !== false
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleAnalyticsEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/membership/analytics - Get membership analytics (admin only)
            // Check if user has admin privileges (simplified check)
            $admin_stmt = $pdo->prepare("
                SELECT COUNT(*) as is_admin 
                FROM users u
                JOIN shop_owners so ON u.id = so.user_id
                WHERE u.id = ? AND so.business_type = 'admin'
            ");
            $admin_stmt->execute([$user_id]);
            $is_admin = $admin_stmt->fetchColumn() > 0;
            
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                return;
            }
            
            // Get membership analytics
            $analytics_stmt = $pdo->prepare("
                SELECT 
                    mt.tier_name,
                    COUNT(um.id) as active_subscribers,
                    SUM(CASE WHEN um.payment_method = 'tokens' THEN mt.monthly_price * 10 ELSE mt.monthly_price END) as monthly_revenue,
                    AVG(DATEDIFF(um.expires_at, um.started_at)) as avg_subscription_length,
                    COUNT(CASE WHEN um.auto_renew = TRUE THEN 1 END) as auto_renew_count
                FROM membership_tiers mt
                LEFT JOIN user_memberships um ON mt.id = um.tier_id AND um.status = 'active'
                WHERE mt.is_active = TRUE
                GROUP BY mt.id, mt.tier_name
                ORDER BY mt.monthly_price ASC
            ");
            $analytics_stmt->execute();
            $tier_analytics = $analytics_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get revenue trends
            $revenue_stmt = $pdo->prepare("
                SELECT 
                    DATE(um.started_at) as date,
                    COUNT(*) as new_subscriptions,
                    SUM(mt.monthly_price) as daily_revenue
                FROM user_memberships um
                JOIN membership_tiers mt ON um.tier_id = mt.id
                WHERE um.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(um.started_at)
                ORDER BY date DESC
            ");
            $revenue_stmt->execute();
            $revenue_trends = $revenue_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'tier_analytics' => $tier_analytics,
                'revenue_trends' => $revenue_trends,
                'total_active_subscribers' => array_sum(array_column($tier_analytics, 'active_subscribers')),
                'total_monthly_revenue' => array_sum(array_column($tier_analytics, 'monthly_revenue'))
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}
?>
