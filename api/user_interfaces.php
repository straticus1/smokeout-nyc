<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verify authentication
$user_id = authenticate();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));
$endpoint = end($path_parts);

try {
    switch ($method) {
        case 'GET':
            if ($endpoint === 'profile-types') {
                getUserProfileTypes();
            } elseif ($endpoint === 'dashboard') {
                getPersonalizedDashboard();
            } elseif ($endpoint === 'recommendations') {
                getPersonalizedRecommendations();
            } elseif ($endpoint === 'interface-config') {
                getInterfaceConfiguration();
            } elseif ($endpoint === 'family-connections') {
                getFamilyConnections();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
            
        case 'POST':
            if ($endpoint === 'set-profile-type') {
                setUserProfileType();
            } elseif ($endpoint === 'customize-interface') {
                customizeInterface();
            } elseif ($endpoint === 'connect-family') {
                connectFamilyMember();
            } elseif ($endpoint === 'create-group') {
                createUserGroup();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
            
        case 'PUT':
            if ($endpoint === 'update-preferences') {
                updateUserPreferences();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function getUserProfileTypes() {
    $profile_types = [
        'casual_gamer' => [
            'name' => 'Casual Gamer',
            'description' => 'Enjoys the game mechanics and virtual growing experience',
            'features' => ['Simple game interface', 'Achievement system', 'Leaderboards', 'Virtual rewards'],
            'dashboard_layout' => 'game_focused',
            'primary_color' => '#4CAF50'
        ],
        'business_owner' => [
            'name' => 'Business Owner',
            'description' => 'Cannabis business owner or aspiring entrepreneur',
            'features' => ['Market analytics', 'Business tools', 'Compliance tracking', 'Financial management'],
            'dashboard_layout' => 'business_focused',
            'primary_color' => '#2196F3'
        ],
        'investor' => [
            'name' => 'Investor',
            'description' => 'Interested in cannabis industry investments and market trends',
            'features' => ['Investment tracking', 'Market intelligence', 'Portfolio management', 'Risk analysis'],
            'dashboard_layout' => 'finance_focused',
            'primary_color' => '#FF9800'
        ],
        'family_member' => [
            'name' => 'Family Member',
            'description' => 'Family member involved in or supporting cannabis business',
            'features' => ['Family dashboard', 'Shared business insights', 'Communication tools', 'Educational resources'],
            'dashboard_layout' => 'family_focused',
            'primary_color' => '#9C27B0'
        ],
        'collector' => [
            'name' => 'Collector',
            'description' => 'Interested in rare strains, genetics, and NFT collecting',
            'features' => ['NFT marketplace', 'Genetics collection', 'Breeding system', 'Rarity tracking'],
            'dashboard_layout' => 'collection_focused',
            'primary_color' => '#E91E63'
        ],
        'social_user' => [
            'name' => 'Social User',
            'description' => 'Enjoys the social and community aspects of the platform',
            'features' => ['Social trading', 'Community forums', 'Friend connections', 'Group activities'],
            'dashboard_layout' => 'social_focused',
            'primary_color' => '#FF5722'
        ]
    ];
    
    echo json_encode(['success' => true, 'profile_types' => $profile_types]);
}

function setUserProfileType() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $profile_type = $input['profile_type'] ?? '';
    $secondary_interests = $input['secondary_interests'] ?? [];
    
    if (empty($profile_type)) {
        throw new Exception('Profile type is required');
    }
    
    $valid_types = ['casual_gamer', 'business_owner', 'investor', 'family_member', 'collector', 'social_user'];
    if (!in_array($profile_type, $valid_types)) {
        throw new Exception('Invalid profile type');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO user_profiles (user_id, profile_type, secondary_interests, created_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        profile_type = VALUES(profile_type),
        secondary_interests = VALUES(secondary_interests),
        updated_at = NOW()
    ");
    $stmt->execute([$user_id, $profile_type, json_encode($secondary_interests)]);
    
    setDefaultInterfaceConfig($user_id, $profile_type);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile type updated successfully',
        'profile_type' => $profile_type
    ]);
}

function setDefaultInterfaceConfig($user_id, $profile_type) {
    global $pdo;
    
    $interface_configs = [
        'casual_gamer' => [
            'layout' => 'game_focused',
            'widgets' => ['plant_status', 'achievements', 'leaderboard', 'daily_challenges'],
            'theme' => 'green'
        ],
        'business_owner' => [
            'layout' => 'business_focused',
            'widgets' => ['market_analytics', 'compliance_alerts', 'financial_summary', 'industry_news'],
            'theme' => 'blue'
        ],
        'investor' => [
            'layout' => 'finance_focused',
            'widgets' => ['portfolio_performance', 'market_trends', 'investment_opportunities', 'risk_analysis'],
            'theme' => 'orange'
        ],
        'family_member' => [
            'layout' => 'family_focused',
            'widgets' => ['family_dashboard', 'shared_insights', 'communication_hub', 'educational_resources'],
            'theme' => 'purple'
        ],
        'collector' => [
            'layout' => 'collection_focused',
            'widgets' => ['nft_collection', 'genetics_library', 'marketplace_activity', 'breeding_lab'],
            'theme' => 'pink'
        ],
        'social_user' => [
            'layout' => 'social_focused',
            'widgets' => ['social_feed', 'friend_activity', 'group_challenges', 'community_forums'],
            'theme' => 'red'
        ]
    ];
    
    $config = $interface_configs[$profile_type] ?? $interface_configs['casual_gamer'];
    
    $stmt = $pdo->prepare("
        INSERT INTO user_interface_configs (user_id, layout_type, widgets, theme, settings, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        layout_type = VALUES(layout_type),
        widgets = VALUES(widgets),
        theme = VALUES(theme),
        updated_at = NOW()
    ");
    $stmt->execute([
        $user_id,
        $config['layout'],
        json_encode($config['widgets']),
        $config['theme'],
        json_encode(['sidebar_collapsed' => false])
    ]);
}

function getPersonalizedDashboard() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("
        SELECT up.profile_type, up.secondary_interests,
               uic.layout_type, uic.widgets, uic.theme, uic.settings
        FROM user_profiles up
        LEFT JOIN user_interface_configs uic ON up.user_id = uic.user_id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user_config = $stmt->fetch();
    
    if (!$user_config) {
        $dashboard_data = [
            'layout' => 'default',
            'theme' => 'green',
            'widgets' => [
                ['type' => 'plant_status', 'title' => 'Plant Status'],
                ['type' => 'achievements', 'title' => 'Recent Achievements']
            ],
            'onboarding_required' => true
        ];
    } else {
        $widgets = json_decode($user_config['widgets'] ?? '[]', true);
        $dashboard_data = [
            'layout' => $user_config['layout_type'],
            'theme' => $user_config['theme'],
            'widgets' => array_map(function($widget) {
                return ['type' => $widget, 'title' => ucwords(str_replace('_', ' ', $widget))];
            }, $widgets)
        ];
    }
    
    echo json_encode(['success' => true, 'dashboard' => $dashboard_data]);
}

function getPersonalizedRecommendations() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("SELECT profile_type FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        echo json_encode([
            'success' => true,
            'recommendations' => [],
            'message' => 'Complete your profile setup to get personalized recommendations'
        ]);
        return;
    }
    
    $recommendations = generateRecommendations($profile['profile_type']);
    echo json_encode(['success' => true, 'recommendations' => $recommendations]);
}

function generateRecommendations($profile_type) {
    $recommendations_map = [
        'casual_gamer' => [
            ['type' => 'feature', 'title' => 'Try Voice Commands', 'description' => 'Control your plants with voice commands'],
            ['type' => 'challenge', 'title' => 'Weekly Growing Challenge', 'description' => 'Compete with other growers']
        ],
        'business_owner' => [
            ['type' => 'tool', 'title' => 'Market Analytics Pro', 'description' => 'Advanced market intelligence tools'],
            ['type' => 'service', 'title' => 'Compliance Monitoring', 'description' => 'Stay updated with regulatory changes']
        ],
        'investor' => [
            ['type' => 'data', 'title' => 'Investment Opportunities', 'description' => 'Discover new cannabis investment opportunities'],
            ['type' => 'analysis', 'title' => 'Risk Assessment Tools', 'description' => 'Analyze investment risks and returns']
        ],
        'collector' => [
            ['type' => 'nft', 'title' => 'Rare Genetics NFT', 'description' => 'New legendary strain genetics available'],
            ['type' => 'breeding', 'title' => 'Breeding Lab', 'description' => 'Create unique hybrid genetics']
        ]
    ];
    
    return $recommendations_map[$profile_type] ?? [];
}

function getInterfaceConfiguration() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("SELECT * FROM user_interface_configs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $config = $stmt->fetch();
    
    if (!$config) {
        $config = [
            'layout_type' => 'default',
            'widgets' => json_encode(['plant_status', 'achievements']),
            'theme' => 'green',
            'settings' => json_encode(['sidebar_collapsed' => false])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'configuration' => [
            'layout' => $config['layout_type'],
            'widgets' => json_decode($config['widgets'], true),
            'theme' => $config['theme'],
            'settings' => json_decode($config['settings'], true)
        ]
    ]);
}

function customizeInterface() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $layout = $input['layout'] ?? 'default';
    $widgets = $input['widgets'] ?? [];
    $theme = $input['theme'] ?? 'green';
    $settings = $input['settings'] ?? [];
    
    $stmt = $pdo->prepare("
        INSERT INTO user_interface_configs (user_id, layout_type, widgets, theme, settings, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        layout_type = VALUES(layout_type),
        widgets = VALUES(widgets),
        theme = VALUES(theme),
        settings = VALUES(settings),
        updated_at = NOW()
    ");
    $stmt->execute([$user_id, $layout, json_encode($widgets), $theme, json_encode($settings)]);
    
    echo json_encode(['success' => true, 'message' => 'Interface customized successfully']);
}

function connectFamilyMember() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $family_email = $input['email'] ?? '';
    $relationship = $input['relationship'] ?? '';
    
    if (empty($family_email) || empty($relationship)) {
        throw new Exception('Email and relationship are required');
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$family_email]);
    $family_user = $stmt->fetch();
    
    if (!$family_user) {
        throw new Exception('User not found with that email address');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO family_connections (user_id, connected_user_id, relationship, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$user_id, $family_user['id'], $relationship]);
    
    echo json_encode(['success' => true, 'message' => 'Family connection request sent successfully']);
}

function getFamilyConnections() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("
        SELECT fc.*, u.username, u.email
        FROM family_connections fc
        JOIN users u ON fc.connected_user_id = u.id
        WHERE fc.user_id = ?
        ORDER BY fc.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $connections = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'connections' => $connections]);
}

function createUserGroup() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $group_name = $input['name'] ?? '';
    $description = $input['description'] ?? '';
    $group_type = $input['type'] ?? 'general';
    
    if (empty($group_name)) {
        throw new Exception('Group name is required');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO user_groups (name, description, group_type, creator_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$group_name, $description, $group_type, $user_id]);
    $group_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("
        INSERT INTO user_group_members (group_id, user_id, role, joined_at)
        VALUES (?, ?, 'admin', NOW())
    ");
    $stmt->execute([$group_id, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Group created successfully', 'group_id' => $group_id]);
}

function updateUserPreferences() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $preferences = $input['preferences'] ?? [];
    
    $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
    $stmt->execute([json_encode($preferences), $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Preferences updated successfully']);
}
?>
