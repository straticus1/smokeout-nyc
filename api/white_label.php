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
array_shift($segments); // remove 'white-label'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'licenses':
            handleLicenseEndpoints($method, $id, $user['id']);
            break;
        case 'configurations':
            handleConfigurationEndpoints($method, $id, $user['id']);
            break;
        case 'deployments':
            handleDeploymentEndpoints($method, $id, $user['id']);
            break;
        case 'analytics':
            handleWhiteLabelAnalyticsEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'White-label endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleLicenseEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /api/white-label/licenses/{id} - Get specific license
                $stmt = $pdo->prepare("
                    SELECT wl.*, wlt.tier_name, wlt.features, wlt.revenue_share_percentage
                    FROM white_label_licenses wl
                    JOIN white_label_tiers wlt ON wl.tier_id = wlt.id
                    WHERE wl.id = ? AND wl.licensee_user_id = ?
                ");
                $stmt->execute([$id, $user_id]);
                $license = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($license) {
                    $license['features'] = json_decode($license['features'], true);
                    $license['custom_config'] = json_decode($license['custom_config'], true);
                }
                
                echo json_encode(['license' => $license]);
            } else {
                // GET /api/white-label/licenses - Get all user licenses
                $stmt = $pdo->prepare("
                    SELECT wl.*, wlt.tier_name, wlt.features, wlt.revenue_share_percentage
                    FROM white_label_licenses wl
                    JOIN white_label_tiers wlt ON wl.tier_id = wlt.id
                    WHERE wl.licensee_user_id = ?
                    ORDER BY wl.created_at DESC
                ");
                $stmt->execute([$user_id]);
                $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($licenses as &$license) {
                    $license['features'] = json_decode($license['features'], true);
                    $license['custom_config'] = json_decode($license['custom_config'], true);
                }
                
                echo json_encode(['licenses' => $licenses]);
            }
            break;
            
        case 'POST':
            // POST /api/white-label/licenses - Apply for new license
            $data = json_decode(file_get_contents('php://input'), true);
            $tier_id = $data['tier_id'];
            $business_name = $data['business_name'];
            $target_market = $data['target_market']; // state/country
            $domain_name = $data['domain_name'];
            $business_details = $data['business_details'];
            
            // Get tier details
            $tier_stmt = $pdo->prepare("SELECT * FROM white_label_tiers WHERE id = ? AND is_active = TRUE");
            $tier_stmt->execute([$tier_id]);
            $tier = $tier_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tier) {
                http_response_code(404);
                echo json_encode(['error' => 'License tier not found']);
                return;
            }
            
            // Check if domain is available
            $domain_check = $pdo->prepare("SELECT COUNT(*) FROM white_label_licenses WHERE domain_name = ? AND status != 'rejected'");
            $domain_check->execute([$domain_name]);
            if ($domain_check->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Domain name already taken']);
                return;
            }
            
            // Create license application
            $license_key = generateLicenseKey();
            $stmt = $pdo->prepare("
                INSERT INTO white_label_licenses 
                (licensee_user_id, tier_id, business_name, target_market, domain_name, 
                 license_key, business_details, status, applied_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $user_id, $tier_id, $business_name, $target_market, 
                $domain_name, $license_key, json_encode($business_details)
            ]);
            
            $license_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'license_id' => $license_id,
                'license_key' => $license_key,
                'status' => 'pending',
                'message' => 'License application submitted for review'
            ]);
            break;
            
        case 'PUT':
            // PUT /api/white-label/licenses/{id} - Update license configuration
            $license_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verify ownership
            $owner_check = $pdo->prepare("SELECT id FROM white_label_licenses WHERE id = ? AND licensee_user_id = ?");
            $owner_check->execute([$license_id, $user_id]);
            if (!$owner_check->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['custom_config'])) {
                $updates[] = "custom_config = ?";
                $params[] = json_encode($data['custom_config']);
            }
            
            if (isset($data['branding_config'])) {
                $updates[] = "branding_config = ?";
                $params[] = json_encode($data['branding_config']);
            }
            
            if (isset($data['domain_name'])) {
                $updates[] = "domain_name = ?";
                $params[] = $data['domain_name'];
            }
            
            if (!empty($updates)) {
                $params[] = $license_id;
                $stmt = $pdo->prepare("
                    UPDATE white_label_licenses 
                    SET " . implode(', ', $updates) . ", updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute($params);
            }
            
            echo json_encode(['success' => true, 'message' => 'License updated successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleConfigurationEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/white-label/configurations - Get available tiers
            $stmt = $pdo->prepare("
                SELECT * FROM white_label_tiers 
                WHERE is_active = TRUE 
                ORDER BY monthly_fee ASC
            ");
            $stmt->execute();
            $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tiers as &$tier) {
                $tier['features'] = json_decode($tier['features'], true);
                $tier['limitations'] = json_decode($tier['limitations'], true);
            }
            
            echo json_encode(['tiers' => $tiers]);
            break;
            
        case 'POST':
            // POST /api/white-label/configurations/{license_id} - Configure white-label instance
            $license_id = $id;
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verify license ownership
            $license_stmt = $pdo->prepare("
                SELECT wl.*, wlt.features 
                FROM white_label_licenses wl
                JOIN white_label_tiers wlt ON wl.tier_id = wlt.id
                WHERE wl.id = ? AND wl.licensee_user_id = ? AND wl.status = 'active'
            ");
            $license_stmt->execute([$license_id, $user_id]);
            $license = $license_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$license) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid or inactive license']);
                return;
            }
            
            $allowed_features = json_decode($license['features'], true);
            $config = [
                'branding' => [
                    'logo_url' => $data['logo_url'] ?? '',
                    'primary_color' => $data['primary_color'] ?? '#4CAF50',
                    'secondary_color' => $data['secondary_color'] ?? '#2196F3',
                    'company_name' => $data['company_name'] ?? $license['business_name'],
                    'favicon_url' => $data['favicon_url'] ?? ''
                ],
                'features' => array_intersect_key($data['features'] ?? [], $allowed_features),
                'game_config' => [
                    'starting_tokens' => min($data['starting_tokens'] ?? 100, 1000),
                    'token_packages' => $data['token_packages'] ?? [],
                    'custom_strains' => $data['custom_strains'] ?? [],
                    'local_market_data' => $data['local_market_data'] ?? true
                ],
                'integrations' => [
                    'payment_processor' => $data['payment_processor'] ?? 'stripe',
                    'analytics_id' => $data['analytics_id'] ?? '',
                    'custom_apis' => $data['custom_apis'] ?? []
                ]
            ];
            
            // Update license configuration
            $stmt = $pdo->prepare("
                UPDATE white_label_licenses 
                SET custom_config = ?, branding_config = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($config), 
                json_encode($config['branding']), 
                $license_id
            ]);
            
            echo json_encode([
                'success' => true,
                'configuration' => $config,
                'deployment_ready' => true
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleDeploymentEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/white-label/deployments - Get deployment status
            $stmt = $pdo->prepare("
                SELECT wl.*, wld.deployment_status, wld.deployment_url, wld.last_deployed
                FROM white_label_licenses wl
                LEFT JOIN white_label_deployments wld ON wl.id = wld.license_id
                WHERE wl.licensee_user_id = ?
                ORDER BY wl.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $deployments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['deployments' => $deployments]);
            break;
            
        case 'POST':
            // POST /api/white-label/deployments/{license_id} - Deploy white-label instance
            $license_id = $id;
            
            // Verify license
            $license_stmt = $pdo->prepare("
                SELECT * FROM white_label_licenses 
                WHERE id = ? AND licensee_user_id = ? AND status = 'active'
            ");
            $license_stmt->execute([$license_id, $user_id]);
            $license = $license_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$license) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid license']);
                return;
            }
            
            // Generate deployment URL
            $subdomain = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $license['business_name']));
            $deployment_url = "https://{$subdomain}.smokeoutnetwork.com";
            
            // Create or update deployment record
            $stmt = $pdo->prepare("
                INSERT INTO white_label_deployments 
                (license_id, deployment_url, deployment_status, last_deployed)
                VALUES (?, ?, 'deploying', NOW())
                ON DUPLICATE KEY UPDATE 
                deployment_status = 'deploying', last_deployed = NOW()
            ");
            $stmt->execute([$license_id, $deployment_url]);
            
            // Simulate deployment process (would integrate with actual deployment system)
            $deployment_id = $pdo->lastInsertId() ?: $pdo->prepare("SELECT id FROM white_label_deployments WHERE license_id = ?")->execute([$license_id]);
            
            // Update to deployed status (in real system, this would be done by deployment webhook)
            sleep(2); // Simulate deployment time
            $pdo->prepare("
                UPDATE white_label_deployments 
                SET deployment_status = 'deployed', deployed_at = NOW()
                WHERE license_id = ?
            ")->execute([$license_id]);
            
            echo json_encode([
                'success' => true,
                'deployment_url' => $deployment_url,
                'status' => 'deployed',
                'message' => 'White-label instance deployed successfully'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleWhiteLabelAnalyticsEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            // GET /api/white-label/analytics/{license_id} - Get license analytics
            $license_id = $id;
            
            // Verify ownership
            $license_stmt = $pdo->prepare("
                SELECT wl.*, wlt.revenue_share_percentage
                FROM white_label_licenses wl
                JOIN white_label_tiers wlt ON wl.tier_id = wlt.id
                WHERE wl.id = ? AND wl.licensee_user_id = ?
            ");
            $license_stmt->execute([$license_id, $user_id]);
            $license = $license_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$license) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            // Get analytics data
            $analytics_stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT u.id) as total_users,
                    COUNT(DISTINCT gp.id) as active_players,
                    SUM(gt.amount) as total_revenue,
                    AVG(gp.level) as avg_player_level,
                    COUNT(DISTINCT DATE(u.created_at)) as active_days
                FROM users u
                LEFT JOIN game_players gp ON u.id = gp.user_id
                LEFT JOIN game_transactions gt ON gp.id = gt.player_id
                WHERE u.white_label_license_id = ?
                AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $analytics_stmt->execute([$license_id]);
            $analytics = $analytics_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate revenue share
            $licensee_revenue = $analytics['total_revenue'] * (1 - $license['revenue_share_percentage'] / 100);
            $platform_revenue = $analytics['total_revenue'] * ($license['revenue_share_percentage'] / 100);
            
            // Get daily user activity
            $activity_stmt = $pdo->prepare("
                SELECT 
                    DATE(u.last_login) as date,
                    COUNT(DISTINCT u.id) as daily_active_users
                FROM users u
                WHERE u.white_label_license_id = ?
                AND u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(u.last_login)
                ORDER BY date DESC
            ");
            $activity_stmt->execute([$license_id]);
            $daily_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'analytics' => $analytics,
                'revenue_breakdown' => [
                    'total_revenue' => $analytics['total_revenue'],
                    'licensee_share' => $licensee_revenue,
                    'platform_share' => $platform_revenue,
                    'revenue_share_percentage' => $license['revenue_share_percentage']
                ],
                'daily_activity' => $daily_activity,
                'license_info' => [
                    'business_name' => $license['business_name'],
                    'target_market' => $license['target_market'],
                    'status' => $license['status']
                ]
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function generateLicenseKey() {
    return 'WL-' . strtoupper(bin2hex(random_bytes(8))) . '-' . date('Y');
}
?>
