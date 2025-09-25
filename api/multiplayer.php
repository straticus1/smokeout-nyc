<?php
require_once 'config.php';
require_once 'auth_check.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
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
        // Session Management
        case 'create_session':
            createGameSession($user_id);
            break;
        case 'join_session':
            joinGameSession($user_id);
            break;
        case 'auto_match':
            autoMatchmaking($user_id);
            break;
        case 'get_available_sessions':
            getAvailableSessions($user_id);
            break;
        case 'get_activity_stats':
            getGameActivityStats();
            break;
        case 'challenge_ai':
            challengeAI($user_id);
            break;
        case 'get_ai_opponents':
            getAIOpponents();
            break;
        case 'get_game_state':
            getGameState($user_id);
            break;
        case 'make_move':
            makeGameMove($user_id);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Create a game session with dynamic AI scaling based on player activity
 */
function createGameSession($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $session_type = $input['session_type'] ?? 'p2p';
    $game_mode = $input['game_mode'] ?? 'territory_wars';
    $max_players = intval($input['max_players'] ?? 2);
    $stakes = floatval($input['stakes'] ?? 0);
    
    // Dynamic AI scaling based on current player activity
    $activity_level = getPlayerActivityLevel();
    $ai_difficulty = adjustAIDifficultyByActivity($activity_level, $input['ai_difficulty'] ?? 'medium');
    
    $pdo->beginTransaction();
    
    try {
        // Create session
        $stmt = $pdo->prepare("
            INSERT INTO game_sessions 
            (session_type, game_mode, max_players, current_players, host_user_id, stakes, prize_pool) 
            VALUES (?, ?, ?, 1, ?, ?, ?)
        ");
        $stmt->execute([$session_type, $game_mode, $max_players, $user_id, $stakes, $stakes * $max_players]);
        $session_id = $pdo->lastInsertId();
        
        // Add host as first player
        $resources = json_encode(['money' => 10000, 'territories' => [], 'reputation' => 100]);
        $stmt = $pdo->prepare("
            INSERT INTO session_players 
            (session_id, user_id, player_position, status, resources) 
            VALUES (?, ?, 1, 'ready', ?)
        ");
        $stmt->execute([$session_id, $user_id, $resources]);
        
        // If PvC, add AI opponents based on activity level
        if ($session_type === 'pvc') {
            // High activity = more AI opponents for richer gameplay
            $ai_count = ($activity_level === 'high') ? rand(2, 3) : 1;
            
            for ($i = 0; $i < $ai_count; $i++) {
                $ai_opponent = getRandomAI($ai_difficulty);
                if ($ai_opponent) {
                    $stmt = $pdo->prepare("
                        INSERT INTO session_players 
                        (session_id, is_ai, ai_difficulty, ai_personality, player_position, status, resources) 
                        VALUES (?, 1, ?, ?, ?, 'ready', ?)
                    ");
                    $stmt->execute([$session_id, $ai_difficulty, $ai_opponent['name'], ($i + 2), $resources]);
                }
            }
            
            // Start PvC game immediately
            $total_players = 1 + $ai_count;
            $pdo->prepare("UPDATE game_sessions SET current_players = ?, status = 'active', started_at = datetime('now') WHERE id = ?")
                ->execute([$total_players, $session_id]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'session_id' => $session_id,
            'activity_level' => $activity_level,
            'ai_difficulty' => $ai_difficulty,
            'ai_count' => $session_type === 'pvc' ? $ai_count : 0,
            'message' => 'Game session created with adaptive AI scaling'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

/**
 * Auto-matchmaking with intelligent AI fallback
 */
function autoMatchmaking($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $preferred_mode = $input['game_mode'] ?? 'territory_wars';
    $max_wait_time = intval($input['max_wait_seconds'] ?? 30);
    
    $activity_level = getPlayerActivityLevel();
    
    // Try to find human opponent first
    $stmt = $pdo->prepare("
        SELECT gs.* FROM game_sessions gs
        WHERE gs.session_type = 'p2p' 
        AND gs.game_mode = ?
        AND gs.status = 'waiting'
        AND gs.current_players < gs.max_players
        AND gs.host_user_id != ?
        AND gs.created_at > datetime('now', '-' || ? || ' seconds')
        ORDER BY gs.created_at ASC
        LIMIT 1
    ");
    $stmt->execute([$preferred_mode, $user_id, $max_wait_time]);
    $available_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($available_session) {
        echo json_encode([
            'success' => true,
            'match_type' => 'p2p',
            'session_id' => $available_session['id'],
            'message' => 'Matched with human player!'
        ]);
        return;
    }
    
    // No human opponents - create AI match with activity-based scaling
    $ai_difficulty = adjustAIDifficultyByActivity($activity_level, 'medium');
    
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO game_sessions 
            (session_type, game_mode, max_players, current_players, host_user_id, status, started_at) 
            VALUES ('pvc', ?, 2, 2, ?, 'active', datetime('now'))
        ");
        $stmt->execute([$preferred_mode, $user_id]);
        $session_id = $pdo->lastInsertId();
        
        // Add human player
        $resources = json_encode(['money' => 10000, 'territories' => [], 'reputation' => 100]);
        $pdo->prepare("
            INSERT INTO session_players 
            (session_id, user_id, player_position, status, resources) 
            VALUES (?, ?, 1, 'active', ?)
        ")->execute([$session_id, $user_id, $resources]);
        
        // Add AI with activity-adjusted difficulty
        $ai_opponent = getRandomAI($ai_difficulty);
        $pdo->prepare("
            INSERT INTO session_players 
            (session_id, is_ai, ai_difficulty, ai_personality, player_position, status, resources) 
            VALUES (?, 1, ?, ?, 2, 'active', ?)
        ")->execute([$session_id, $ai_difficulty, $ai_opponent['name'], $resources]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'match_type' => 'pvc',
            'session_id' => $session_id,
            'ai_opponent' => $ai_opponent,
            'ai_difficulty' => $ai_difficulty,
            'activity_level' => $activity_level,
            'message' => "Matched with {$ai_difficulty} AI due to {$activity_level} player activity!"
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

/**
 * Calculate current player activity level
 */
function getPlayerActivityLevel() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT gs.host_user_id) as active_players,
            COUNT(gs.id) as total_sessions,
            COUNT(CASE WHEN gs.status = 'active' THEN 1 END) as active_sessions,
            COUNT(CASE WHEN sp.is_ai = 0 THEN 1 END) as human_players_online
        FROM game_sessions gs
        LEFT JOIN session_players sp ON gs.id = sp.session_id AND sp.status = 'active'
        WHERE gs.created_at > datetime('now', '-24 hours')
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $active_players = intval($stats['active_players']);
    $total_sessions = intval($stats['total_sessions']);
    $active_sessions = intval($stats['active_sessions']);
    $human_players_online = intval($stats['human_players_online']);
    
    // Determine activity level
    if ($active_players >= 20 && $total_sessions >= 50 && $human_players_online >= 15) {
        return 'high';
    } elseif ($active_players >= 8 && $total_sessions >= 15 && $human_players_online >= 6) {
        return 'medium';
    } else {
        return 'low';
    }
}

/**
 * Adjust AI difficulty based on player activity
 * High activity = harder AI to challenge experienced players
 * Low activity = easier AI to keep new players engaged
 */
function adjustAIDifficultyByActivity($activity_level, $base_difficulty = 'medium') {
    switch ($activity_level) {
        case 'high':
            // High activity = more challenging AI opponents
            $difficulties = ['hard', 'expert'];
            return $difficulties[array_rand($difficulties)];
            
        case 'medium':
            // Medium activity = balanced AI difficulty
            return 'medium';
            
        case 'low':
        default:
            // Low activity = easier AI to encourage new players
            $difficulties = ['easy', 'medium'];
            return $difficulties[array_rand($difficulties)];
    }
}

/**
 * Challenge specific AI opponent with activity-based difficulty override
 */
function challengeAI($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ai_id = intval($input['ai_id'] ?? 0);
    $game_mode = $input['game_mode'] ?? 'territory_wars';
    $stakes = floatval($input['stakes'] ?? 0);
    
    $activity_level = getPlayerActivityLevel();
    
    if (!$ai_id) {
        // Auto-select AI based on activity
        $target_difficulty = adjustAIDifficultyByActivity($activity_level);
        $ai = getRandomAI($target_difficulty);
        if (!$ai) {
            throw new Exception('No suitable AI opponents available');
        }
        $ai_id = $ai['id'];
    } else {
        // Get specific AI
        $stmt = $pdo->prepare("SELECT * FROM ai_opponents WHERE id = ? AND is_active = 1");
        $stmt->execute([$ai_id]);
        $ai = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ai) {
            throw new Exception('AI opponent not found');
        }
    }
    
    // Override AI difficulty based on current activity
    $adjusted_difficulty = adjustAIDifficultyByActivity($activity_level, $ai['difficulty_level']);
    
    $pdo->beginTransaction();
    
    try {
        // Create PvC session
        $stmt = $pdo->prepare("
            INSERT INTO game_sessions 
            (session_type, game_mode, max_players, current_players, host_user_id, stakes, status, started_at) 
            VALUES ('pvc', ?, 2, 2, ?, ?, 'active', datetime('now'))
        ");
        $stmt->execute([$game_mode, $user_id, $stakes]);
        $session_id = $pdo->lastInsertId();
        
        // Add players
        $resources = json_encode(['money' => 10000, 'territories' => [], 'reputation' => 100]);
        
        $pdo->prepare("
            INSERT INTO session_players 
            (session_id, user_id, player_position, status, resources) 
            VALUES (?, ?, 1, 'active', ?)
        ")->execute([$session_id, $user_id, $resources]);
        
        $pdo->prepare("
            INSERT INTO session_players 
            (session_id, is_ai, ai_difficulty, ai_personality, player_position, status, resources) 
            VALUES (?, 1, ?, ?, 2, 'active', ?)
        ")->execute([$session_id, $adjusted_difficulty, $ai['name'], $resources]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'session_id' => $session_id,
            'ai_opponent' => $ai,
            'original_difficulty' => $ai['difficulty_level'],
            'adjusted_difficulty' => $adjusted_difficulty,
            'activity_level' => $activity_level,
            'message' => "Challenge started! AI difficulty adjusted to {$adjusted_difficulty} based on {$activity_level} player activity."
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

/**
 * Get available AI opponents
 */
function getAIOpponents() {
    global $pdo;
    
    $activity_level = getPlayerActivityLevel();
    $recommended_difficulty = adjustAIDifficultyByActivity($activity_level);
    
    $stmt = $pdo->prepare("
        SELECT *, 
               CASE WHEN difficulty_level = ? THEN 1 ELSE 0 END as is_recommended
        FROM ai_opponents 
        WHERE is_active = 1 
        ORDER BY is_recommended DESC, difficulty_level, reputation_score DESC
    ");
    $stmt->execute([$recommended_difficulty]);
    $opponents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'opponents' => $opponents,
        'activity_level' => $activity_level,
        'recommended_difficulty' => $recommended_difficulty,
        'scaling_info' => [
            'high_activity' => 'Challenging Hard/Expert AI opponents recommended',
            'medium_activity' => 'Balanced Medium difficulty AI recommended', 
            'low_activity' => 'Easier Easy/Medium AI to build confidence'
        ]
    ]);
}

/**
 * Get available P2P sessions
 */
function getAvailableSessions($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT gs.*, u.username as host_username,
               COUNT(sp.id) as current_players_count
        FROM game_sessions gs
        JOIN users u ON gs.host_user_id = u.id
        LEFT JOIN session_players sp ON gs.id = sp.session_id
        WHERE gs.status = 'waiting' 
        AND gs.host_user_id != ?
        AND gs.session_type = 'p2p'
        GROUP BY gs.id
        HAVING current_players_count < gs.max_players
        ORDER BY gs.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $activity_level = getPlayerActivityLevel();
    
    echo json_encode([
        'sessions' => $sessions,
        'activity_level' => $activity_level,
        'total_available' => count($sessions),
        'recommendation' => count($sessions) > 0 ? 'Human opponents available!' : 'Try AI opponents for instant play'
    ]);
}

/**
 * Get detailed game activity statistics
 */
function getGameActivityStats() {
    global $pdo;
    
    // Current activity
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN sp.is_ai = 0 THEN sp.user_id END) as online_players,
            COUNT(CASE WHEN gs.status = 'active' THEN 1 END) as active_games,
            COUNT(CASE WHEN gs.status = 'waiting' THEN 1 END) as waiting_games,
            COUNT(CASE WHEN sp.is_ai = 1 THEN 1 END) as ai_players_active,
            COUNT(CASE WHEN gs.session_type = 'p2p' THEN 1 END) as p2p_games,
            COUNT(CASE WHEN gs.session_type = 'pvc' THEN 1 END) as pvc_games
        FROM game_sessions gs
        LEFT JOIN session_players sp ON gs.id = sp.session_id
        WHERE gs.created_at > datetime('now', '-1 hour')
    ");
    $stmt->execute();
    $current_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $activity_level = getPlayerActivityLevel();
    $current_ai_difficulty = adjustAIDifficultyByActivity($activity_level);
    
    echo json_encode([
        'current_stats' => $current_stats,
        'activity_level' => $activity_level,
        'current_ai_difficulty' => $current_ai_difficulty,
        'ai_scaling_active' => true,
        'scaling_logic' => [
            'high_activity' => 'More AI opponents, harder difficulty',
            'medium_activity' => 'Balanced AI difficulty', 
            'low_activity' => 'Easier AI to encourage engagement'
        ]
    ]);
}

/**
 * Get current game state
 */
function getGameState($user_id) {
    global $pdo;
    
    $session_id = intval($_GET['session_id'] ?? 0);
    
    if (!$session_id) {
        throw new Exception('Session ID required');
    }
    
    // Get session info
    $stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        throw new Exception('Session not found');
    }
    
    // Get players
    $stmt = $pdo->prepare("
        SELECT sp.*, u.username 
        FROM session_players sp 
        LEFT JOIN users u ON sp.user_id = u.id 
        WHERE sp.session_id = ? 
        ORDER BY sp.player_position
    ");
    $stmt->execute([$session_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON fields
    foreach ($players as &$player) {
        $player['resources'] = json_decode($player['resources'] ?? '{}', true);
        $player['current_stats'] = json_decode($player['current_stats'] ?? '{}', true);
    }
    
    echo json_encode([
        'session' => $session,
        'players' => $players,
        'is_player_in_game' => in_array($user_id, array_column($players, 'user_id'))
    ]);
}

/**
 * Make a game move
 */
function makeGameMove($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = intval($input['session_id'] ?? 0);
    $action_type = $input['action_type'] ?? '';
    $target_resource = $input['target_resource'] ?? null;
    
    // Get player ID in session
    $stmt = $pdo->prepare("SELECT id FROM session_players WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$session_id, $user_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        throw new Exception('Not in this game session');
    }
    
    // Record action (simplified)
    $stmt = $pdo->prepare("
        INSERT INTO game_actions 
        (session_id, player_id, action_type, target_resource, action_data, processed) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $session_id, $player['id'], $action_type, 
        $target_resource, json_encode($input)
    ]);
    
    $action_id = $pdo->lastInsertId();
    
    // Process AI turns
    processAITurns($session_id);
    
    echo json_encode([
        'success' => true,
        'action_id' => $action_id,
        'message' => 'Move processed successfully'
    ]);
}

/**
 * Process AI turns for active AI players
 */
function processAITurns($session_id) {
    global $pdo;
    
    // Get AI players in this session
    $stmt = $pdo->prepare("
        SELECT sp.* 
        FROM session_players sp 
        WHERE sp.session_id = ? AND sp.is_ai = 1 AND sp.status = 'active'
    ");
    $stmt->execute([$session_id]);
    $ai_players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ai_players as $ai_player) {
        // Simple AI decision making
        $actions = ['build', 'attack', 'trade'];
        $chosen_action = $actions[array_rand($actions)];
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO game_actions 
                (session_id, player_id, action_type, action_data, processed) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $session_id,
                $ai_player['id'],
                $chosen_action,
                json_encode(['ai_move' => true, 'reasoning' => 'AI decision'])
            ]);
            
        } catch (Exception $e) {
            error_log("AI move error for session $session_id: " . $e->getMessage());
        }
    }
}

/**
 * Join existing game session
 */
function joinGameSession($user_id) {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = intval($input['session_id'] ?? 0);
    
    if (!$session_id) {
        throw new Exception('Session ID required');
    }
    
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM game_sessions 
            WHERE id = ? AND status = 'waiting' AND current_players < max_players
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            throw new Exception('Session not found or full');
        }
        
        // Check if already in session
        $stmt = $pdo->prepare("SELECT id FROM session_players WHERE session_id = ? AND user_id = ?");
        $stmt->execute([$session_id, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Already in this session');
        }
        
        // Add player
        $position = $session['current_players'] + 1;
        $resources = json_encode(['money' => 10000, 'territories' => [], 'reputation' => 100]);
        
        $stmt = $pdo->prepare("
            INSERT INTO session_players 
            (session_id, user_id, player_position, status, resources) 
            VALUES (?, ?, ?, 'ready', ?)
        ");
        $stmt->execute([$session_id, $user_id, $position, $resources]);
        
        // Update session
        $new_count = $session['current_players'] + 1;
        $pdo->prepare("UPDATE game_sessions SET current_players = ? WHERE id = ?")
            ->execute([$new_count, $session_id]);
        
        // Start if full
        if ($new_count >= $session['max_players']) {
            $pdo->prepare("UPDATE game_sessions SET status = 'active', started_at = datetime('now') WHERE id = ?")
                ->execute([$session_id]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Joined session successfully',
            'game_started' => ($new_count >= $session['max_players'])
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

/**
 * Get random AI opponent by difficulty
 */
function getRandomAI($difficulty) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM ai_opponents 
        WHERE difficulty_level = ? AND is_active = 1 
        ORDER BY RANDOM() 
        LIMIT 1
    ");
    $stmt->execute([$difficulty]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

?>