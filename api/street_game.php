<?php
require_once 'config.php';
require_once 'auth_check.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user_id = get_authenticated_user_id();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_player_stats':
            getPlayerStats($user_id);
            break;
        case 'get_level_info':
            getLevelInfo($user_id);
            break;
        case 'add_experience':
            addExperience($user_id);
            break;
        case 'get_active_dealers':
            getActiveDealers($user_id);
            break;
        case 'get_dealer_actions':
            getDealerActions($user_id);
            break;
        case 'respond_to_dealer':
            respondToDealer($user_id);
            break;
        case 'get_corrupt_cops':
            getCorruptCops($user_id);
            break;
        case 'bribe_cop':
            bribeCop($user_id);
            break;
        case 'get_territories':
            getTerritories($user_id);
            break;
        case 'expand_territory':
            expandTerritory($user_id);
            break;
        case 'get_street_events':
            getStreetEvents($user_id);
            break;
        case 'resolve_event':
            resolveStreetEvent($user_id);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function initializePlayerLevel($user_id) {
    global $pdo;
    
    // Check if player level exists
    $stmt = $pdo->prepare("SELECT id FROM player_levels WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    if (!$stmt->fetch()) {
        // Initialize player level and security
        $pdo->prepare("INSERT INTO player_levels (user_id) VALUES (?)")->execute([$user_id]);
        $pdo->prepare("INSERT INTO player_security (user_id) VALUES (?)")->execute([$user_id]);
    }
}

function getPlayerStats($user_id) {
    global $pdo;
    
    initializePlayerLevel($user_id);
    
    $stmt = $pdo->prepare("
        SELECT 
            pl.*,
            ps.bodyguard_level,
            ps.security_budget,
            ps.corrupt_cop_network,
            ps.street_informants,
            COALESCE(tc_count.territory_count, 0) as territories_controlled,
            COALESCE(tc_revenue.daily_revenue, 0) as daily_territory_revenue
        FROM player_levels pl
        LEFT JOIN player_security ps ON pl.user_id = ps.user_id
        LEFT JOIN (
            SELECT user_id, COUNT(*) as territory_count 
            FROM territory_control 
            WHERE control_percentage > 50 
            GROUP BY user_id
        ) tc_count ON pl.user_id = tc_count.user_id
        LEFT JOIN (
            SELECT user_id, SUM(revenue_per_day) as daily_revenue 
            FROM territory_control 
            WHERE control_percentage > 50 
            GROUP BY user_id
        ) tc_revenue ON pl.user_id = tc_revenue.user_id
        WHERE pl.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($stats);
}

function getLevelInfo($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT current_level FROM player_levels WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $current_level = $stmt->fetchColumn() ?: 1;
    
    // Get current and next level requirements
    $stmt = $pdo->prepare("
        SELECT * FROM level_requirements 
        WHERE level IN (?, ?) 
        ORDER BY level
    ");
    $stmt->execute([$current_level, $current_level + 1]);
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'current_level' => $current_level,
        'current_level_info' => $levels[0] ?? null,
        'next_level_info' => $levels[1] ?? null
    ]);
}

function addExperience($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $experience_gained = max(0, min(1000, intval($input['experience'] ?? 0))); // Cap at 1000 XP
    $reason = substr($input['reason'] ?? '', 0, 255);
    
    if ($experience_gained <= 0) {
        throw new Exception('Invalid experience amount');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Get current stats
        $stmt = $pdo->prepare("
            SELECT current_level, experience_points, total_experience 
            FROM player_levels 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_experience = $stats['experience_points'] + $experience_gained;
        $new_total = $stats['total_experience'] + $experience_gained;
        $current_level = $stats['current_level'];
        
        // Check for level up
        $level_up = false;
        $stmt = $pdo->prepare("
            SELECT level, experience_needed 
            FROM level_requirements 
            WHERE level > ? AND experience_needed <= ? 
            ORDER BY level DESC 
            LIMIT 1
        ");
        $stmt->execute([$current_level, $new_total]);
        $next_level = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($next_level) {
            $current_level = $next_level['level'];
            $level_up = true;
            
            // Update level up timestamp
            $pdo->prepare("
                UPDATE player_levels 
                SET current_level = ?, experience_points = ?, total_experience = ?, last_level_up = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ")->execute([$current_level, $new_experience, $new_total, $user_id]);
            
            // Spawn dealers if level threshold reached
            if ($current_level >= 10) {
                spawnDealersForLevel($user_id, $current_level);
            }
        } else {
            $pdo->prepare("
                UPDATE player_levels 
                SET experience_points = ?, total_experience = ? 
                WHERE user_id = ?
            ")->execute([$new_experience, $new_total, $user_id]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'experience_gained' => $experience_gained,
            'new_level' => $current_level,
            'level_up' => $level_up,
            'total_experience' => $new_total,
            'reason' => $reason
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function spawnDealersForLevel($user_id, $level) {
    global $pdo;
    
    // Get spawn chance for this level
    $stmt = $pdo->prepare("
        SELECT street_dealer_spawn_chance, max_dealers_per_territory 
        FROM level_requirements 
        WHERE level <= ? 
        ORDER BY level DESC 
        LIMIT 1
    ");
    $stmt->execute([$level]);
    $spawn_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$spawn_info || $spawn_info['street_dealer_spawn_chance'] <= 0) {
        return;
    }
    
    // Get territories with low dealer count
    $stmt = $pdo->prepare("
        SELECT t.id, t.name, COUNT(sd.id) as dealer_count
        FROM territories t
        LEFT JOIN street_dealers sd ON t.id = sd.territory_id AND sd.is_active = 1
        GROUP BY t.id
        HAVING dealer_count < ?
        ORDER BY RANDOM()
        LIMIT 3
    ");
    $stmt->execute([$spawn_info['max_dealers_per_territory']]);
    $territories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $dealer_names = [
        'Big Mike', 'Lil Tony', 'Smooth Charlie', 'Mad Dog', 'Quick Eddie',
        'Street King', 'Corner Boss', 'Heavy D', 'Fast Money', 'Cold Steel'
    ];
    
    $nicknames = [
        'The Bull', 'Ice Cold', 'Lightning', 'Shadow', 'Viper',
        'The Ghost', 'Razor', 'Bullet', 'Storm', 'Wolf'
    ];
    
    foreach ($territories as $territory) {
        if (rand(1, 100) <= ($spawn_info['street_dealer_spawn_chance'] * 100)) {
            $name = $dealer_names[array_rand($dealer_names)];
            $nickname = $nicknames[array_rand($nicknames)];
            
            $pdo->prepare("
                INSERT INTO street_dealers 
                (name, nickname, territory_id, aggression_level, violence_tendency, spawn_level) 
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $name, $nickname, $territory['id'],
                ['passive', 'moderate', 'aggressive'][rand(0, 2)],
                rand(20, 80),
                $level
            ]);
        }
    }
}

function getActiveDealers($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            sd.*,
            t.name as territory_name,
            t.borough,
            t.police_presence,
            COUNT(da.id) as recent_actions
        FROM street_dealers sd
        JOIN territories t ON sd.territory_id = t.id
        LEFT JOIN dealer_actions da ON sd.id = da.dealer_id 
            AND da.occurred_at > datetime('now', '-7 days')
        WHERE sd.is_active = 1
        GROUP BY sd.id
        ORDER BY sd.aggression_level DESC, sd.violence_tendency DESC
    ");
    $stmt->execute();
    $dealers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($dealers);
}

function getDealerActions($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            da.*,
            sd.name as dealer_name,
            sd.nickname,
            t.name as territory_name
        FROM dealer_actions da
        JOIN street_dealers sd ON da.dealer_id = sd.id
        JOIN territories t ON sd.territory_id = t.id
        WHERE da.target_type = 'player' AND da.target_id = ?
        ORDER BY da.occurred_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($actions);
}

function respondToDealer($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action_id = intval($input['action_id'] ?? 0);
    $response = $input['response'] ?? '';
    
    $valid_responses = ['ignore', 'negotiate', 'retaliate', 'call_police', 'bribe_cops', 'flee'];
    if (!in_array($response, $valid_responses)) {
        throw new Exception('Invalid response');
    }
    
    // Get action details
    $stmt = $pdo->prepare("
        SELECT da.*, sd.aggression_level, sd.violence_tendency, pl.reputation_score
        FROM dealer_actions da
        JOIN street_dealers sd ON da.dealer_id = sd.id
        CROSS JOIN player_levels pl
        WHERE da.id = ? AND da.target_id = ? AND pl.user_id = ?
    ");
    $stmt->execute([$action_id, $user_id, $user_id]);
    $action = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$action) {
        throw new Exception('Action not found');
    }
    
    // Calculate outcome based on response and dealer characteristics
    $outcome = calculateDealerResponseOutcome($response, $action);
    
    // Update action with player response
    $pdo->prepare("
        UPDATE dealer_actions 
        SET player_response = ?, outcome_description = ?, 
            money_involved = ?, reputation_change = ?
        WHERE id = ?
    ")->execute([
        $response, 
        $outcome['description'], 
        $outcome['money_change'], 
        $outcome['reputation_change'], 
        $action_id
    ]);
    
    // Update player stats
    if ($outcome['money_change'] != 0) {
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([
            $outcome['money_change'], $user_id
        ]);
    }
    
    if ($outcome['reputation_change'] != 0) {
        $pdo->prepare("UPDATE player_levels SET reputation_score = reputation_score + ? WHERE user_id = ?")->execute([
            $outcome['reputation_change'], $user_id
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'outcome' => $outcome
    ]);
}

function calculateDealerResponseOutcome($response, $action) {
    $outcomes = [];
    
    switch ($response) {
        case 'ignore':
            $outcomes = [
                'description' => 'You ignored the dealer. They may escalate next time.',
                'money_change' => 0,
                'reputation_change' => -1
            ];
            break;
            
        case 'negotiate':
            if ($action['aggression_level'] === 'passive') {
                $outcomes = [
                    'description' => 'You successfully negotiated a peaceful resolution.',
                    'money_change' => -50,
                    'reputation_change' => 2
                ];
            } else {
                $outcomes = [
                    'description' => 'Negotiation failed. The dealer became more aggressive.',
                    'money_change' => -100,
                    'reputation_change' => -2
                ];
            }
            break;
            
        case 'retaliate':
            if ($action['violence_tendency'] < 50) {
                $outcomes = [
                    'description' => 'You scared off the dealer with a show of force.',
                    'money_change' => 0,
                    'reputation_change' => 5
                ];
            } else {
                $outcomes = [
                    'description' => 'Retaliation led to violence. You were injured and lost money.',
                    'money_change' => -200,
                    'reputation_change' => 3
                ];
            }
            break;
            
        case 'call_police':
            $outcomes = [
                'description' => 'Police arrived but the dealer had connections. Nothing happened.',
                'money_change' => 0,
                'reputation_change' => -3
            ];
            break;
            
        case 'bribe_cops':
            $outcomes = [
                'description' => 'You paid corrupt cops to handle the situation.',
                'money_change' => -300,
                'reputation_change' => 1
            ];
            break;
            
        case 'flee':
            $outcomes = [
                'description' => 'You fled the scene. Safe but with damaged reputation.',
                'money_change' => 0,
                'reputation_change' => -5
            ];
            break;
    }
    
    return $outcomes;
}

function getCorruptCops($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            nc.*,
            COALESCE(pcr.relationship_type, 'unknown') as relationship_type,
            COALESCE(pcr.trust_level, 0) as trust_level,
            COALESCE(pcr.total_bribes_paid, 0) as total_bribes_paid,
            COALESCE(pcr.protection_active, 0) as protection_active,
            pcr.protection_expires
        FROM nyc_cops nc
        LEFT JOIN player_cop_relations pcr ON nc.id = pcr.cop_id AND pcr.user_id = ?
        WHERE nc.corruption_level != 'clean'
        ORDER BY nc.corruption_level DESC, nc.bribe_threshold ASC
    ");
    $stmt->execute([$user_id]);
    $cops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($cops);
}

function bribeCop($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $cop_id = intval($input['cop_id'] ?? 0);
    $amount = max(0, floatval($input['amount'] ?? 0));
    $service_type = $input['service_type'] ?? 'protection';
    
    // Get cop and user info
    $stmt = $pdo->prepare("
        SELECT nc.*, u.balance, COALESCE(pcr.trust_level, 0) as trust_level
        FROM nyc_cops nc
        CROSS JOIN users u
        LEFT JOIN player_cop_relations pcr ON nc.id = pcr.cop_id AND pcr.user_id = ?
        WHERE nc.id = ? AND u.id = ?
    ");
    $stmt->execute([$user_id, $cop_id, $user_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        throw new Exception('Cop not found');
    }
    
    if ($data['balance'] < $amount) {
        throw new Exception('Insufficient funds');
    }
    
    if ($amount < $data['bribe_threshold']) {
        throw new Exception('Bribe amount too low. Minimum: $' . $data['bribe_threshold']);
    }
    
    $pdo->beginTransaction();
    
    try {
        // Deduct money
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([
            $amount, $user_id
        ]);
        
        // Update cop relationship - check if exists first
        $existing = $pdo->prepare("SELECT * FROM player_cop_relations WHERE user_id = ? AND cop_id = ?");
        $existing->execute([$user_id, $cop_id]);
        $relation = $existing->fetch();
        
        if ($relation) {
            // Update existing relationship
            $pdo->prepare("
                UPDATE player_cop_relations 
                SET trust_level = MIN(trust_level + 5, 100),
                    total_bribes_paid = total_bribes_paid + ?,
                    protection_active = 1,
                    protection_expires = datetime('now', '+1 month'),
                    last_interaction = CURRENT_TIMESTAMP
                WHERE user_id = ? AND cop_id = ?
            ")->execute([$amount, $user_id, $cop_id]);
        } else {
            // Create new relationship
            $pdo->prepare("
                INSERT INTO player_cop_relations 
                (user_id, cop_id, relationship_type, trust_level, total_bribes_paid, protection_active, protection_expires, last_interaction)
                VALUES (?, ?, 'friendly', 10, ?, 1, datetime('now', '+1 month'), CURRENT_TIMESTAMP)
            ")->execute([$user_id, $cop_id, $amount]);
        }
        
        // Update cop stats
        $pdo->prepare("
            UPDATE nyc_cops 
            SET total_bribes_taken = total_bribes_taken + ?, last_bribe = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$amount, $cop_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cop successfully bribed. Protection active for 1 month.',
            'amount_paid' => $amount,
            'service' => $service_type
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function getTerritories($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            COALESCE(tc.control_percentage, 0) as player_control,
            COALESCE(tc.influence_points, 0) as influence_points,
            COALESCE(tc.revenue_per_day, 0) as daily_revenue,
            COALESCE(tc.status, 'uncontrolled') as control_status,
            COUNT(sd.id) as active_dealers
        FROM territories t
        LEFT JOIN territory_control tc ON t.id = tc.territory_id AND tc.user_id = ?
        LEFT JOIN street_dealers sd ON t.id = sd.territory_id AND sd.is_active = 1
        GROUP BY t.id
        ORDER BY t.borough, t.name
    ");
    $stmt->execute([$user_id]);
    $territories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($territories);
}

function expandTerritory($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $territory_id = intval($input['territory_id'] ?? 0);
    $investment = max(100, floatval($input['investment'] ?? 0));
    
    // Check user balance and level
    $stmt = $pdo->prepare("
        SELECT u.balance, pl.current_level, pl.reputation_score
        FROM users u
        JOIN player_levels pl ON u.id = pl.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data['balance'] < $investment) {
        throw new Exception('Insufficient funds');
    }
    
    if ($user_data['current_level'] < 20) {
        throw new Exception('Must be level 20+ to control territories');
    }
    
    // Get territory info
    $stmt = $pdo->prepare("SELECT * FROM territories WHERE id = ?");
    $stmt->execute([$territory_id]);
    $territory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$territory) {
        throw new Exception('Territory not found');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Calculate influence gained based on investment and territory difficulty
        $base_influence = floor($investment / 100);
        $difficulty_modifier = [
            'minimal' => 1.2, 'light' => 1.0, 'moderate' => 0.8,
            'heavy' => 0.6, 'overwhelming' => 0.4
        ][$territory['police_presence']];
        
        $influence_gained = floor($base_influence * $difficulty_modifier);
        
        // Deduct investment
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([
            $investment, $user_id
        ]);
        
        // Update territory control - check if exists first
        $existing = $pdo->prepare("SELECT * FROM territory_control WHERE territory_id = ? AND user_id = ?");
        $existing->execute([$territory_id, $user_id]);
        $control = $existing->fetch();
        
        if ($control) {
            // Update existing control
            $new_influence = $control['influence_points'] + $influence_gained;
            $new_control_pct = min($control['control_percentage'] + ($influence_gained / 10), 100);
            
            $status = 'expanding';
            if ($new_control_pct >= 75) $status = 'stable';
            elseif ($new_control_pct >= 50) $status = 'contested';
            
            $revenue = ($new_control_pct >= 50) ? $new_control_pct * $territory['customer_demand'] : 0;
            
            $pdo->prepare("
                UPDATE territory_control 
                SET influence_points = ?, control_percentage = ?, status = ?, revenue_per_day = ?
                WHERE territory_id = ? AND user_id = ?
            ")->execute([$new_influence, $new_control_pct, $status, $revenue, $territory_id, $user_id]);
        } else {
            // Create new control
            $control_pct = min($influence_gained, 100);
            $revenue = ($control_pct >= 50) ? $control_pct * $territory['customer_demand'] : 0;
            
            $pdo->prepare("
                INSERT INTO territory_control 
                (territory_id, user_id, influence_points, control_percentage, status, revenue_per_day)
                VALUES (?, ?, ?, ?, 'expanding', ?)
            ")->execute([$territory_id, $user_id, $influence_gained, $control_pct, $revenue]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'influence_gained' => $influence_gained,
            'investment' => $investment,
            'territory' => $territory['name']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function getStreetEvents($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            se.*,
            sd.name as dealer_name,
            nc.name as cop_name,
            t.name as territory_name
        FROM street_events se
        LEFT JOIN street_dealers sd ON se.dealer_id = sd.id
        LEFT JOIN nyc_cops nc ON se.cop_id = nc.id
        LEFT JOIN territories t ON se.territory_id = t.id
        WHERE se.user_id = ? AND se.resolved = 0
        ORDER BY se.occurred_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON fields
    foreach ($events as &$event) {
        $event['choices'] = json_decode($event['choices'], true);
        $event['outcome'] = json_decode($event['outcome'], true);
    }
    
    echo json_encode($events);
}

function resolveStreetEvent($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $event_id = intval($input['event_id'] ?? 0);
    $choice = $input['choice'] ?? '';
    
    // Get event details
    $stmt = $pdo->prepare("
        SELECT * FROM street_events 
        WHERE id = ? AND user_id = ? AND resolved = 0
    ");
    $stmt->execute([$event_id, $user_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Event not found or already resolved');
    }
    
    $choices = json_decode($event['choices'], true);
    if (!isset($choices[$choice])) {
        throw new Exception('Invalid choice');
    }
    
    // Calculate outcome based on choice
    $outcome = calculateEventOutcome($event, $choice, $user_id);
    
    $pdo->beginTransaction();
    
    try {
        // Update event as resolved
        $pdo->prepare("
            UPDATE street_events 
            SET resolved = 1, selected_choice = ?, outcome = ?, 
                money_impact = ?, reputation_impact = ?, heat_impact = ?, 
                experience_gained = ?, resolved_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([
            $choice, 
            json_encode($outcome), 
            $outcome['money_change'], 
            $outcome['reputation_change'], 
            $outcome['heat_change'], 
            $outcome['experience'], 
            $event_id
        ]);
        
        // Apply effects to player
        if ($outcome['money_change'] != 0) {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([
                $outcome['money_change'], $user_id
            ]);
        }
        
        if ($outcome['reputation_change'] != 0) {
            $pdo->prepare("UPDATE player_levels SET reputation_score = reputation_score + ? WHERE user_id = ?")->execute([
                $outcome['reputation_change'], $user_id
            ]);
        }
        
        if ($outcome['experience'] > 0) {
            $pdo->prepare("UPDATE player_levels SET experience_points = experience_points + ?, total_experience = total_experience + ? WHERE user_id = ?")->execute([
                $outcome['experience'], $outcome['experience'], $user_id
            ]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'outcome' => $outcome
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function calculateEventOutcome($event, $choice, $user_id) {
    // This is a simplified outcome calculator - in a real game this would be much more complex
    $base_outcomes = [
        'dealer_robbery' => [
            'pay_up' => ['money_change' => -200, 'reputation_change' => -3, 'heat_change' => 0, 'experience' => 10],
            'fight_back' => ['money_change' => -100, 'reputation_change' => 5, 'heat_change' => 10, 'experience' => 25],
            'run_away' => ['money_change' => 0, 'reputation_change' => -10, 'heat_change' => 0, 'experience' => 5]
        ],
        'police_shakedown' => [
            'pay_bribe' => ['money_change' => -300, 'reputation_change' => 0, 'heat_change' => -5, 'experience' => 15],
            'refuse' => ['money_change' => 0, 'reputation_change' => 3, 'heat_change' => 15, 'experience' => 20],
            'lawyer_up' => ['money_change' => -500, 'reputation_change' => 2, 'heat_change' => -10, 'experience' => 30]
        ]
    ];
    
    $event_type = $event['event_type'];
    $default_outcome = ['money_change' => 0, 'reputation_change' => 0, 'heat_change' => 0, 'experience' => 10];
    
    return $base_outcomes[$event_type][$choice] ?? $default_outcome;
}

?>