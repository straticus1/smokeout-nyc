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
array_shift($segments); // remove 'data-service'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'subscriptions':
            handleDataSubscriptionEndpoints($method, $id, $user['id']);
            break;
        case 'market-intelligence':
            handleMarketIntelligenceEndpoints($method, $id, $user['id']);
            break;
        case 'analytics':
            handleAnalyticsDataEndpoints($method, $id, $user['id']);
            break;
        case 'export':
            handleDataExportEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Data service endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleDataSubscriptionEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/data-service/subscriptions - Get available data packages
            $stmt = $pdo->prepare("
                SELECT dp.*, 
                       CASE WHEN ds.id IS NOT NULL THEN TRUE ELSE FALSE END as subscribed,
                       ds.expires_at, ds.api_calls_used, ds.api_calls_limit
                FROM data_packages dp
                LEFT JOIN data_subscriptions ds ON dp.id = ds.package_id 
                    AND ds.subscriber_user_id = ? AND ds.status = 'active'
                WHERE dp.is_active = TRUE
                ORDER BY dp.monthly_price ASC
            ");
            $stmt->execute([$user_id]);
            $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($packages as &$package) {
                $package['data_types'] = json_decode($package['data_types'], true);
                $package['access_levels'] = json_decode($package['access_levels'], true);
            }
            
            echo json_encode(['data_packages' => $packages]);
            break;
            
        case 'POST':
            // POST /api/data-service/subscriptions - Subscribe to data package
            $data = json_decode(file_get_contents('php://input'), true);
            $package_id = $data['package_id'];
            $billing_cycle = $data['billing_cycle'] ?? 'monthly'; // monthly or annual
            
            // Get package details
            $package_stmt = $pdo->prepare("SELECT * FROM data_packages WHERE id = ? AND is_active = TRUE");
            $package_stmt->execute([$package_id]);
            $package = $package_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$package) {
                http_response_code(404);
                echo json_encode(['error' => 'Data package not found']);
                return;
            }
            
            // Calculate price and expiration
            $price = $billing_cycle === 'annual' ? $package['annual_price'] : $package['monthly_price'];
            $duration = $billing_cycle === 'annual' ? '1 YEAR' : '1 MONTH';
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration}"));
            
            // Generate API key
            $api_key = 'ds_' . bin2hex(random_bytes(16));
            
            try {
                $pdo->beginTransaction();
                
                // Create subscription
                $stmt = $pdo->prepare("
                    INSERT INTO data_subscriptions 
                    (subscriber_user_id, package_id, api_key, expires_at, api_calls_limit, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $user_id, $package_id, $api_key, $expires_at, $package['api_calls_per_month']
                ]);
                
                $subscription_id = $pdo->lastInsertId();
                
                // Log revenue transaction
                $pdo->prepare("
                    INSERT INTO revenue_transactions 
                    (shop_owner_id, transaction_type, gross_amount, platform_fee_rate, 
                     platform_fee_amount, net_amount, payment_method, status)
                    SELECT so.id, 'data_subscription', ?, 0.0000, 0.00, ?, 'credit_card', 'completed'
                    FROM shop_owners so WHERE so.user_id = ?
                ")->execute([$price, $price, $user_id]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'subscription_id' => $subscription_id,
                    'api_key' => $api_key,
                    'package_name' => $package['package_name'],
                    'expires_at' => $expires_at,
                    'price_paid' => $price
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

function handleMarketIntelligenceEndpoints($method, $id, $user_id) {
    global $pdo;
    
    // Verify data subscription
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    if (!$api_key || !verifyDataSubscription($api_key, $user_id, 'market_intelligence')) {
        http_response_code(403);
        echo json_encode(['error' => 'Valid data subscription required']);
        return;
    }
    
    switch ($method) {
        case 'GET':
            $data_type = $_GET['type'] ?? 'overview';
            $region = $_GET['region'] ?? 'all';
            $timeframe = $_GET['timeframe'] ?? '30d';
            
            switch ($data_type) {
                case 'pricing':
                    $data = getMarketPricingData($region, $timeframe);
                    break;
                case 'demand':
                    $data = getMarketDemandData($region, $timeframe);
                    break;
                case 'competition':
                    $data = getCompetitionAnalysis($region, $timeframe);
                    break;
                case 'trends':
                    $data = getMarketTrends($region, $timeframe);
                    break;
                case 'regulatory':
                    $data = getRegulatoryIntelligence($region);
                    break;
                default:
                    $data = getMarketOverview($region, $timeframe);
            }
            
            // Log API usage
            logApiUsage($api_key, 'market_intelligence', $data_type);
            
            echo json_encode([
                'data_type' => $data_type,
                'region' => $region,
                'timeframe' => $timeframe,
                'data' => $data,
                'generated_at' => date('c')
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleAnalyticsDataEndpoints($method, $id, $user_id) {
    global $pdo;
    
    // Verify data subscription
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    if (!$api_key || !verifyDataSubscription($api_key, $user_id, 'analytics')) {
        http_response_code(403);
        echo json_encode(['error' => 'Valid analytics subscription required']);
        return;
    }
    
    switch ($method) {
        case 'GET':
            $metric_type = $_GET['metric'] ?? 'user_behavior';
            $aggregation = $_GET['aggregation'] ?? 'daily';
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');
            
            switch ($metric_type) {
                case 'user_behavior':
                    $data = getUserBehaviorAnalytics($start_date, $end_date, $aggregation);
                    break;
                case 'game_metrics':
                    $data = getGameMetricsAnalytics($start_date, $end_date, $aggregation);
                    break;
                case 'revenue_analytics':
                    $data = getRevenueAnalytics($start_date, $end_date, $aggregation);
                    break;
                case 'geographic':
                    $data = getGeographicAnalytics($start_date, $end_date);
                    break;
                case 'conversion_funnel':
                    $data = getConversionFunnelAnalytics($start_date, $end_date);
                    break;
                default:
                    $data = getUserBehaviorAnalytics($start_date, $end_date, $aggregation);
            }
            
            // Log API usage
            logApiUsage($api_key, 'analytics', $metric_type);
            
            echo json_encode([
                'metric_type' => $metric_type,
                'aggregation' => $aggregation,
                'date_range' => ['start' => $start_date, 'end' => $end_date],
                'data' => $data,
                'generated_at' => date('c')
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleDataExportEndpoints($method, $id, $user_id) {
    global $pdo;
    
    // Verify data subscription
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    if (!$api_key || !verifyDataSubscription($api_key, $user_id, 'export')) {
        http_response_code(403);
        echo json_encode(['error' => 'Valid export subscription required']);
        return;
    }
    
    switch ($method) {
        case 'POST':
            // POST /api/data-service/export - Request data export
            $data = json_decode(file_get_contents('php://input'), true);
            $export_type = $data['export_type']; // 'market_data', 'user_analytics', 'game_metrics'
            $format = $data['format'] ?? 'json'; // json, csv, xlsx
            $filters = $data['filters'] ?? [];
            
            // Create export job
            $export_id = createExportJob($user_id, $export_type, $format, $filters);
            
            // Process export (in real system, this would be queued)
            $export_data = processDataExport($export_type, $filters, $format);
            
            // Store export file
            $filename = "export_{$export_id}_{$export_type}." . $format;
            $file_path = "/tmp/exports/" . $filename;
            
            if (!is_dir('/tmp/exports')) {
                mkdir('/tmp/exports', 0755, true);
            }
            
            file_put_contents($file_path, $export_data);
            
            // Update export job status
            $pdo->prepare("
                UPDATE data_exports 
                SET status = 'completed', file_path = ?, completed_at = NOW()
                WHERE id = ?
            ")->execute([$file_path, $export_id]);
            
            // Log API usage
            logApiUsage($api_key, 'export', $export_type);
            
            echo json_encode([
                'success' => true,
                'export_id' => $export_id,
                'download_url' => "/api/data-service/export/{$export_id}/download",
                'filename' => $filename,
                'status' => 'completed'
            ]);
            break;
            
        case 'GET':
            if ($id && strpos($id, '/download') !== false) {
                // GET /api/data-service/export/{id}/download - Download export file
                $export_id = str_replace('/download', '', $id);
                
                $export_stmt = $pdo->prepare("
                    SELECT * FROM data_exports 
                    WHERE id = ? AND requester_user_id = ? AND status = 'completed'
                ");
                $export_stmt->execute([$export_id, $user_id]);
                $export = $export_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$export || !file_exists($export['file_path'])) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Export file not found']);
                    return;
                }
                
                // Serve file download
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($export['file_path']) . '"');
                header('Content-Length: ' . filesize($export['file_path']));
                readfile($export['file_path']);
                exit;
                
            } else {
                // GET /api/data-service/export - Get export history
                $stmt = $pdo->prepare("
                    SELECT id, export_type, format, status, created_at, completed_at
                    FROM data_exports 
                    WHERE requester_user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([$user_id]);
                $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['exports' => $exports]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// Data Intelligence Functions

function getMarketPricingData($region, $timeframe) {
    global $pdo;
    
    $days = getTimeframeDays($timeframe);
    
    $stmt = $pdo->prepare("
        SELECT 
            s.strain_name,
            AVG(gs.sale_price) as avg_price,
            MIN(gs.sale_price) as min_price,
            MAX(gs.sale_price) as max_price,
            COUNT(*) as transaction_count,
            AVG(gs.quantity) as avg_quantity
        FROM game_sales gs
        JOIN plants p ON gs.plant_id = p.id
        JOIN strains s ON p.strain_id = s.id
        JOIN locations l ON gs.location_id = l.id
        WHERE gs.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        " . ($region !== 'all' ? "AND l.city = ?" : "") . "
        GROUP BY s.strain_name
        ORDER BY transaction_count DESC
        LIMIT 50
    ");
    
    $params = [$days];
    if ($region !== 'all') $params[] = $region;
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMarketDemandData($region, $timeframe) {
    global $pdo;
    
    $days = getTimeframeDays($timeframe);
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(gs.sale_date) as date,
            COUNT(*) as daily_transactions,
            SUM(gs.quantity) as daily_volume,
            AVG(gs.sale_price) as avg_daily_price
        FROM game_sales gs
        JOIN locations l ON gs.location_id = l.id
        WHERE gs.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        " . ($region !== 'all' ? "AND l.city = ?" : "") . "
        GROUP BY DATE(gs.sale_date)
        ORDER BY date DESC
    ");
    
    $params = [$days];
    if ($region !== 'all') $params[] = $region;
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCompetitionAnalysis($region, $timeframe) {
    global $pdo;
    
    $days = getTimeframeDays($timeframe);
    
    $stmt = $pdo->prepare("
        SELECT 
            l.location_name,
            l.city,
            COUNT(DISTINCT gs.player_id) as unique_sellers,
            COUNT(*) as total_transactions,
            SUM(gs.quantity) as total_volume,
            AVG(gs.sale_price) as avg_price
        FROM game_sales gs
        JOIN locations l ON gs.location_id = l.id
        WHERE gs.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        " . ($region !== 'all' ? "AND l.city = ?" : "") . "
        GROUP BY l.id, l.location_name, l.city
        ORDER BY total_transactions DESC
        LIMIT 25
    ");
    
    $params = [$days];
    if ($region !== 'all') $params[] = $region;
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMarketTrends($region, $timeframe) {
    global $pdo;
    
    $days = getTimeframeDays($timeframe);
    
    // Price trends
    $price_stmt = $pdo->prepare("
        SELECT 
            DATE(gs.sale_date) as date,
            AVG(gs.sale_price) as avg_price,
            COUNT(*) as transaction_count
        FROM game_sales gs
        JOIN locations l ON gs.location_id = l.id
        WHERE gs.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        " . ($region !== 'all' ? "AND l.city = ?" : "") . "
        GROUP BY DATE(gs.sale_date)
        ORDER BY date ASC
    ");
    
    $params = [$days];
    if ($region !== 'all') $params[] = $region;
    
    $price_stmt->execute($params);
    $price_trends = $price_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Strain popularity trends
    $strain_stmt = $pdo->prepare("
        SELECT 
            s.strain_name,
            COUNT(*) as sales_count,
            RANK() OVER (ORDER BY COUNT(*) DESC) as popularity_rank
        FROM game_sales gs
        JOIN plants p ON gs.plant_id = p.id
        JOIN strains s ON p.strain_id = s.id
        JOIN locations l ON gs.location_id = l.id
        WHERE gs.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        " . ($region !== 'all' ? "AND l.city = ?" : "") . "
        GROUP BY s.strain_name
        ORDER BY sales_count DESC
        LIMIT 20
    ");
    
    $strain_stmt->execute($params);
    $strain_trends = $strain_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'price_trends' => $price_trends,
        'strain_popularity' => $strain_trends
    ];
}

function getRegulatoryIntelligence($region) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            ra.assessment_type,
            ra.risk_score,
            ra.city,
            ra.state,
            COUNT(*) as assessment_count,
            AVG(ra.risk_score) as avg_risk_score,
            MAX(ra.last_updated) as latest_update
        FROM risk_assessments ra
        WHERE ra.last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        " . ($region !== 'all' ? "AND ra.city = ?" : "") . "
        GROUP BY ra.assessment_type, ra.city, ra.state
        ORDER BY avg_risk_score DESC
    ");
    
    $params = [];
    if ($region !== 'all') $params[] = $region;
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMarketOverview($region, $timeframe) {
    return [
        'pricing' => getMarketPricingData($region, $timeframe),
        'demand' => getMarketDemandData($region, $timeframe),
        'trends' => getMarketTrends($region, $timeframe),
        'regulatory' => getRegulatoryIntelligence($region)
    ];
}

// Analytics Functions

function getUserBehaviorAnalytics($start_date, $end_date, $aggregation) {
    global $pdo;
    
    $date_format = $aggregation === 'daily' ? '%Y-%m-%d' : '%Y-%m';
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(u.last_login, ?) as period,
            COUNT(DISTINCT u.id) as active_users,
            AVG(TIMESTAMPDIFF(MINUTE, u.last_login, NOW())) as avg_session_length,
            COUNT(DISTINCT gp.id) as active_players
        FROM users u
        LEFT JOIN game_players gp ON u.id = gp.user_id
        WHERE DATE(u.last_login) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(u.last_login, ?)
        ORDER BY period ASC
    ");
    
    $stmt->execute([$date_format, $start_date, $end_date, $date_format]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGameMetricsAnalytics($start_date, $end_date, $aggregation) {
    global $pdo;
    
    $date_format = $aggregation === 'daily' ? '%Y-%m-%d' : '%Y-%m';
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(gt.transaction_date, ?) as period,
            COUNT(*) as total_transactions,
            SUM(gt.amount) as total_revenue,
            AVG(gt.amount) as avg_transaction_value,
            COUNT(DISTINCT gt.player_id) as active_players
        FROM game_transactions gt
        WHERE DATE(gt.transaction_date) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(gt.transaction_date, ?)
        ORDER BY period ASC
    ");
    
    $stmt->execute([$date_format, $start_date, $end_date, $date_format]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRevenueAnalytics($start_date, $end_date, $aggregation) {
    global $pdo;
    
    $date_format = $aggregation === 'daily' ? '%Y-%m-%d' : '%Y-%m';
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(rt.processed_at, ?) as period,
            SUM(rt.gross_amount) as gross_revenue,
            SUM(rt.platform_fee_amount) as platform_revenue,
            SUM(rt.net_amount) as net_revenue,
            COUNT(*) as transaction_count,
            rt.transaction_type
        FROM revenue_transactions rt
        WHERE DATE(rt.processed_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(rt.processed_at, ?), rt.transaction_type
        ORDER BY period ASC, rt.transaction_type
    ");
    
    $stmt->execute([$date_format, $start_date, $end_date, $date_format]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGeographicAnalytics($start_date, $end_date) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            l.city,
            l.state,
            COUNT(DISTINCT gs.player_id) as unique_players,
            COUNT(*) as total_transactions,
            SUM(gs.quantity) as total_volume,
            AVG(gs.sale_price) as avg_price
        FROM game_sales gs
        JOIN locations l ON gs.location_id = l.id
        WHERE DATE(gs.sale_date) BETWEEN ? AND ?
        GROUP BY l.city, l.state
        ORDER BY total_transactions DESC
    ");
    
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getConversionFunnelAnalytics($start_date, $end_date) {
    global $pdo;
    
    // Simplified conversion funnel
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_signups,
            COUNT(DISTINCT gp.id) as players_created,
            COUNT(DISTINCT CASE WHEN gt.id IS NOT NULL THEN gp.user_id END) as paying_users,
            COUNT(DISTINCT CASE WHEN gs.id IS NOT NULL THEN gs.player_id END) as active_sellers
        FROM users u
        LEFT JOIN game_players gp ON u.id = gp.user_id
        LEFT JOIN game_transactions gt ON gp.id = gt.player_id
        LEFT JOIN game_sales gs ON gp.id = gs.player_id
        WHERE DATE(u.created_at) BETWEEN ? AND ?
    ");
    
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Utility Functions

function verifyDataSubscription($api_key, $user_id, $required_access) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT ds.*, dp.access_levels
        FROM data_subscriptions ds
        JOIN data_packages dp ON ds.package_id = dp.id
        WHERE ds.api_key = ? AND ds.subscriber_user_id = ? 
        AND ds.status = 'active' AND ds.expires_at > NOW()
        AND ds.api_calls_used < ds.api_calls_limit
    ");
    $stmt->execute([$api_key, $user_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) return false;
    
    $access_levels = json_decode($subscription['access_levels'], true);
    return in_array($required_access, $access_levels);
}

function logApiUsage($api_key, $endpoint, $data_type) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE data_subscriptions 
        SET api_calls_used = api_calls_used + 1,
            last_used = NOW()
        WHERE api_key = ?
    ");
    $stmt->execute([$api_key]);
    
    // Log detailed usage
    $pdo->prepare("
        INSERT INTO data_usage_logs (api_key, endpoint, data_type, used_at)
        VALUES (?, ?, ?, NOW())
    ")->execute([$api_key, $endpoint, $data_type]);
}

function getTimeframeDays($timeframe) {
    switch ($timeframe) {
        case '7d': return 7;
        case '30d': return 30;
        case '90d': return 90;
        case '1y': return 365;
        default: return 30;
    }
}

function createExportJob($user_id, $export_type, $format, $filters) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO data_exports 
        (requester_user_id, export_type, format, filters, status)
        VALUES (?, ?, ?, ?, 'processing')
    ");
    $stmt->execute([$user_id, $export_type, $format, json_encode($filters)]);
    
    return $pdo->lastInsertId();
}

function processDataExport($export_type, $filters, $format) {
    // Simplified export processing - would be more complex in real system
    $data = [];
    
    switch ($export_type) {
        case 'market_data':
            $data = getMarketPricingData($filters['region'] ?? 'all', $filters['timeframe'] ?? '30d');
            break;
        case 'user_analytics':
            $data = getUserBehaviorAnalytics(
                $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
                $filters['end_date'] ?? date('Y-m-d'),
                $filters['aggregation'] ?? 'daily'
            );
            break;
        case 'game_metrics':
            $data = getGameMetricsAnalytics(
                $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
                $filters['end_date'] ?? date('Y-m-d'),
                $filters['aggregation'] ?? 'daily'
            );
            break;
    }
    
    if ($format === 'csv') {
        return arrayToCsv($data);
    } else {
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}

function arrayToCsv($data) {
    if (empty($data)) return '';
    
    $output = fopen('php://temp', 'w');
    
    // Write headers
    fputcsv($output, array_keys($data[0]));
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}
?>
