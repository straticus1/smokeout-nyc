<?php
require_once 'config/database.php';
require_once 'auth_helper.php';
require_once 'models/Game.php';

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
array_shift($segments); // remove 'multiplayer-game'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'guilds':
            handleGuildEndpoints($method, $id, $user['id']);
            break;
        case 'coop-operations':
            handleCoopOperationEndpoints($method, $id, $user['id']);
            break;
        case 'player-trades':
            handlePlayerTradeEndpoints($method, $id, $user['id']);
            break;
        case 'competitions':
            handleCompetitionEndpoints($method, $id, $user['id']);
            break;
        case 'social':
            handleSocialEndpoints($method, $id, $user['id']);
            break;
        case 'leaderboards':
            handleLeaderboardEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Multiplayer endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ====================================
// Guild Management Functions
// ====================================

function handleGuildEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id === 'my-guild') {
                // GET /api/multiplayer-game/guilds/my-guild - Get user's guild
                $guild = getUserGuild($user_id);
                echo json_encode(['guild' => $guild]);
            } elseif ($id === 'available') {
                // GET /api/multiplayer-game/guilds/available - Get available public guilds
                $guilds = getAvailableGuilds($user_id);
                echo json_encode(['guilds' => $guilds]);
            } elseif ($id) {
                // GET /api/multiplayer-game/guilds/{id} - Get specific guild details
                $guild = getGuildDetails($id, $user_id);
                echo json_encode(['guild' => $guild]);
            } else {
                // GET /api/multiplayer-game/guilds - Search guilds
                $search = $_GET['search'] ?? '';
                $guild_type = $_GET['type'] ?? '';
                $guilds = searchGuilds($search, $guild_type, $user_id);
                echo json_encode(['guilds' => $guilds]);
            }
            break;
            
        case 'POST':
            if ($id === 'create') {
                // POST /api/multiplayer-game/guilds/create - Create new guild
                $data = json_decode(file_get_contents('php://input'), true);
                $result = createGuild($user_id, $data);
                echo json_encode($result);
            } elseif ($id) {
                $action = $segments[2] ?? '';
                switch ($action) {
                    case 'join':
                        // POST /api/multiplayer-game/guilds/{id}/join - Join guild
                        $result = joinGuild($user_id, $id);
                        echo json_encode($result);
                        break;
                    case 'leave':
                        // POST /api/multiplayer-game/guilds/{id}/leave - Leave guild
                        $result = leaveGuild($user_id, $id);
                        echo json_encode($result);
                        break;
                    case 'invite':
                        // POST /api/multiplayer-game/guilds/{id}/invite - Invite player
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = inviteToGuild($user_id, $id, $data['player_username']);
                        echo json_encode($result);
                        break;
                    default:
                        http_response_code(404);
                        echo json_encode(['error' => 'Guild action not found']);
                }
            }
            break;
            
        case 'PUT':
            if ($id) {
                // PUT /api/multiplayer-game/guilds/{id} - Update guild
                $data = json_decode(file_get_contents('php://input'), true);
                $result = updateGuild($user_id, $id, $data);
                echo json_encode($result);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                // DELETE /api/multiplayer-game/guilds/{id} - Delete guild (leaders only)
                $result = deleteGuild($user_id, $id);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Cooperative Operations Functions
// ====================================

function handleCoopOperationEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id === 'available') {
                // GET /api/multiplayer-game/coop-operations/available - Get operations user can join
                $operations = getAvailableCoopOperations($user_id);
                echo json_encode(['operations' => $operations]);
            } elseif ($id === 'my-operations') {
                // GET /api/multiplayer-game/coop-operations/my-operations - Get user's operations
                $operations = getUserCoopOperations($user_id);
                echo json_encode(['operations' => $operations]);
            } elseif ($id) {
                // GET /api/multiplayer-game/coop-operations/{id} - Get specific operation
                $operation = getCoopOperationDetails($id, $user_id);
                echo json_encode(['operation' => $operation]);
            } else {
                // GET /api/multiplayer-game/coop-operations - Search operations
                $operations = searchCoopOperations($user_id, $_GET);
                echo json_encode(['operations' => $operations]);
            }
            break;
            
        case 'POST':
            if ($id === 'create') {
                // POST /api/multiplayer-game/coop-operations/create - Create new operation
                $data = json_decode(file_get_contents('php://input'), true);
                $result = createCoopOperation($user_id, $data);
                echo json_encode($result);
            } elseif ($id) {
                $action = $segments[2] ?? '';
                switch ($action) {
                    case 'join':
                        // POST /api/multiplayer-game/coop-operations/{id}/join - Join operation
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = joinCoopOperation($user_id, $id, $data);
                        echo json_encode($result);
                        break;
                    case 'invest':
                        // POST /api/multiplayer-game/coop-operations/{id}/invest - Add investment
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = investInOperation($user_id, $id, $data);
                        echo json_encode($result);
                        break;
                    case 'harvest':
                        // POST /api/multiplayer-game/coop-operations/{id}/harvest - Harvest operation
                        $result = harvestCoopOperation($user_id, $id);
                        echo json_encode($result);
                        break;
                    default:
                        http_response_code(404);
                        echo json_encode(['error' => 'Operation action not found']);
                }
            }
            break;
            
        case 'PUT':
            if ($id) {
                // PUT /api/multiplayer-game/coop-operations/{id} - Update operation
                $data = json_decode(file_get_contents('php://input'), true);
                $result = updateCoopOperation($user_id, $id, $data);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Player Trading Functions
// ====================================

function handlePlayerTradeEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id === 'marketplace') {
                // GET /api/multiplayer-game/player-trades/marketplace - Browse marketplace
                $trades = getMarketplaceListings($_GET);
                echo json_encode(['marketplace' => $trades]);
            } elseif ($id === 'my-trades') {
                // GET /api/multiplayer-game/player-trades/my-trades - Get user's trades
                $trades = getUserTrades($user_id);
                echo json_encode(['trades' => $trades]);
            } elseif ($id === 'my-purchases') {
                // GET /api/multiplayer-game/player-trades/my-purchases - Get user's purchases
                $purchases = getUserPurchases($user_id);
                echo json_encode(['purchases' => $purchases]);
            } elseif ($id) {
                // GET /api/multiplayer-game/player-trades/{id} - Get specific trade
                $trade = getTradeDetails($id, $user_id);
                echo json_encode(['trade' => $trade]);
            }
            break;
            
        case 'POST':
            if ($id === 'list-item') {
                // POST /api/multiplayer-game/player-trades/list-item - List item for sale
                $data = json_decode(file_get_contents('php://input'), true);
                $result = listItemForSale($user_id, $data);
                echo json_encode($result);
            } elseif ($id) {
                $action = $segments[2] ?? '';
                switch ($action) {
                    case 'purchase':
                        // POST /api/multiplayer-game/player-trades/{id}/purchase - Buy item
                        $result = purchaseItem($user_id, $id);
                        echo json_encode($result);
                        break;
                    case 'make-offer':
                        // POST /api/multiplayer-game/player-trades/{id}/make-offer - Make counter-offer
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = makeOffer($user_id, $id, $data);
                        echo json_encode($result);
                        break;
                    case 'cancel':
                        // POST /api/multiplayer-game/player-trades/{id}/cancel - Cancel listing
                        $result = cancelTrade($user_id, $id);
                        echo json_encode($result);
                        break;
                    default:
                        http_response_code(404);
                        echo json_encode(['error' => 'Trade action not found']);
                }
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Competition Functions
// ====================================

function handleCompetitionEndpoints($method, $id, $user_id) {
    global $pdo;
    
    switch ($method) {
        case 'GET':
            if ($id === 'active') {
                // GET /api/multiplayer-game/competitions/active - Get active competitions
                $competitions = getActiveCompetitions();
                echo json_encode(['competitions' => $competitions]);
            } elseif ($id === 'upcoming') {
                // GET /api/multiplayer-game/competitions/upcoming - Get upcoming competitions
                $competitions = getUpcomingCompetitions();
                echo json_encode(['competitions' => $competitions]);
            } elseif ($id === 'my-competitions') {
                // GET /api/multiplayer-game/competitions/my-competitions - Get user's competitions
                $competitions = getUserCompetitions($user_id);
                echo json_encode(['competitions' => $competitions]);
            } elseif ($id) {
                // GET /api/multiplayer-game/competitions/{id} - Get specific competition
                $competition = getCompetitionDetails($id, $user_id);
                echo json_encode(['competition' => $competition]);
            } else {
                // GET /api/multiplayer-game/competitions - Search competitions
                $competitions = searchCompetitions($_GET);
                echo json_encode(['competitions' => $competitions]);
            }
            break;
            
        case 'POST':
            if ($id) {
                $action = $segments[2] ?? '';
                switch ($action) {
                    case 'register':
                        // POST /api/multiplayer-game/competitions/{id}/register - Register for competition
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = registerForCompetition($user_id, $id, $data);
                        echo json_encode($result);
                        break;
                    case 'submit-entry':
                        // POST /api/multiplayer-game/competitions/{id}/submit-entry - Submit competition entry
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = submitCompetitionEntry($user_id, $id, $data);
                        echo json_encode($result);
                        break;
                    default:
                        http_response_code(404);
                        echo json_encode(['error' => 'Competition action not found']);
                }
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Social Features Functions
// ====================================

function handleSocialEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id === 'friends') {
                // GET /api/multiplayer-game/social/friends - Get friends list
                $friends = getPlayerFriends($user_id);
                echo json_encode(['friends' => $friends]);
            } elseif ($id === 'online-players') {
                // GET /api/multiplayer-game/social/online-players - Get online players
                $online = getOnlinePlayers($user_id);
                echo json_encode(['online_players' => $online]);
            } elseif ($id === 'player-status') {
                // GET /api/multiplayer-game/social/player-status - Get user's current status
                $status = getPlayerStatus($user_id);
                echo json_encode(['status' => $status]);
            }
            break;
            
        case 'POST':
            if ($id === 'update-status') {
                // POST /api/multiplayer-game/social/update-status - Update player status
                $data = json_decode(file_get_contents('php://input'), true);
                $result = updatePlayerStatus($user_id, $data);
                echo json_encode($result);
            } elseif ($id === 'send-friend-request') {
                // POST /api/multiplayer-game/social/send-friend-request - Send friend request
                $data = json_decode(file_get_contents('php://input'), true);
                $result = sendFriendRequest($user_id, $data['username']);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Leaderboard Functions
// ====================================

function handleLeaderboardEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id === 'top-growers') {
                $leaderboard = getTopGrowersLeaderboard();
                echo json_encode(['leaderboard' => $leaderboard]);
            } elseif ($id === 'top-traders') {
                $leaderboard = getTopTradersLeaderboard();
                echo json_encode(['leaderboard' => $leaderboard]);
            } elseif ($id === 'top-guilds') {
                $leaderboard = getTopGuildsLeaderboard();
                echo json_encode(['leaderboard' => $leaderboard]);
            } elseif ($id === 'weekly-champions') {
                $leaderboard = getWeeklyChampions();
                echo json_encode(['leaderboard' => $leaderboard]);
            } else {
                // Default to overall leaderboard
                $leaderboard = getOverallLeaderboard();
                echo json_encode(['leaderboard' => $leaderboard]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Guild Implementation Functions
// ====================================

function getUserGuild($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT g.*, gm.role, gm.joined_at, gm.contribution_points
        FROM game_guilds g
        JOIN game_guild_members gm ON g.id = gm.guild_id
        WHERE gm.player_id = (SELECT id FROM game_players WHERE user_id = ?)
    ");
    $stmt->execute([$user_id]);
    $guild = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($guild) {
        // Get guild members
        $members_stmt = $pdo->prepare("
            SELECT gm.*, u.username, gp.level, gp.reputation
            FROM game_guild_members gm
            JOIN game_players gp ON gm.player_id = gp.id
            JOIN users u ON gp.user_id = u.id
            WHERE gm.guild_id = ?
            ORDER BY gm.role DESC, gm.contribution_points DESC
        ");
        $members_stmt->execute([$guild['id']]);
        $guild['members'] = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $guild;
}

function getAvailableGuilds($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT g.*, u.username as leader_name,
               (SELECT COUNT(*) FROM game_guild_members WHERE guild_id = g.id) as member_count
        FROM game_guilds g
        JOIN users u ON g.created_by = u.id
        WHERE g.is_public = true 
        AND g.current_members < g.max_members
        AND g.id NOT IN (
            SELECT guild_id FROM game_guild_members gm
            JOIN game_players gp ON gm.player_id = gp.id
            WHERE gp.user_id = ?
        )
        ORDER BY g.current_members DESC, g.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createGuild($user_id, $data) {
    global $pdo;
    
    // Validate required fields
    if (empty($data['name']) || strlen($data['name']) < 3) {
        http_response_code(400);
        return ['error' => 'Guild name must be at least 3 characters'];
    }
    
    // Check if user is already in a guild
    $player = GamePlayer::getByUserId($user_id);
    $existing_guild = getUserGuild($user_id);
    if ($existing_guild) {
        http_response_code(400);
        return ['error' => 'You are already a member of a guild'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create guild
        $stmt = $pdo->prepare("
            INSERT INTO game_guilds (name, description, guild_type, max_members, is_public, join_requirements, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $join_requirements = json_encode([
            'min_level' => $data['min_level'] ?? 1,
            'min_reputation' => $data['min_reputation'] ?? 0
        ]);
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['guild_type'] ?? 'casual',
            $data['max_members'] ?? 50,
            $data['is_public'] ?? true,
            $join_requirements,
            $user_id
        ]);
        
        $guild_id = $pdo->lastInsertId();
        
        // Add creator as guild leader
        $stmt = $pdo->prepare("
            INSERT INTO game_guild_members (guild_id, player_id, role)
            VALUES (?, ?, 'leader')
        ");
        $stmt->execute([$guild_id, $player->id]);
        
        $pdo->commit();
        
        // Send notifications to potential members if guild is public
        if ($data['is_public'] ?? true) {
            queueNotification([
                'type' => 'guild_created',
                'guild_id' => $guild_id,
                'guild_name' => $data['name']
            ]);
        }
        
        return [
            'success' => true,
            'guild_id' => $guild_id,
            'message' => 'Guild created successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            http_response_code(400);
            return ['error' => 'Guild name already exists'];
        }
        
        throw $e;
    }
}

function joinGuild($user_id, $guild_id) {
    global $pdo;
    
    $player = GamePlayer::getByUserId($user_id);
    
    // Check if already in a guild
    $existing_guild = getUserGuild($user_id);
    if ($existing_guild) {
        http_response_code(400);
        return ['error' => 'Already a member of a guild'];
    }
    
    // Get guild details
    $guild_stmt = $pdo->prepare("SELECT * FROM game_guilds WHERE id = ?");
    $guild_stmt->execute([$guild_id]);
    $guild = $guild_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$guild) {
        http_response_code(404);
        return ['error' => 'Guild not found'];
    }
    
    if ($guild['current_members'] >= $guild['max_members']) {
        http_response_code(400);
        return ['error' => 'Guild is full'];
    }
    
    // Check join requirements
    $requirements = json_decode($guild['join_requirements'], true) ?? [];
    if (($requirements['min_level'] ?? 0) > $player->level) {
        http_response_code(400);
        return ['error' => "Minimum level required: {$requirements['min_level']}"];
    }
    
    if (($requirements['min_reputation'] ?? 0) > $player->reputation) {
        http_response_code(400);
        return ['error' => "Minimum reputation required: {$requirements['min_reputation']}"];
    }
    
    try {
        // Add member to guild
        $stmt = $pdo->prepare("
            INSERT INTO game_guild_members (guild_id, player_id, role)
            VALUES (?, ?, 'member')
        ");
        $stmt->execute([$guild_id, $player->id]);
        
        // Send notification to guild leaders
        queueGuildNotification($guild_id, [
            'type' => 'new_member',
            'player_name' => getPlayerName($user_id),
            'guild_name' => $guild['name']
        ]);
        
        return [
            'success' => true,
            'message' => "Successfully joined {$guild['name']}"
        ];
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            http_response_code(400);
            return ['error' => 'Already a member of this guild'];
        }
        throw $e;
    }
}

// ====================================
// Cooperative Operations Implementation
// ====================================

function createCoopOperation($user_id, $data) {
    global $pdo;
    
    $player = GamePlayer::getByUserId($user_id);
    
    // Validate required fields
    if (empty($data['name']) || empty($data['location_id']) || empty($data['operation_type'])) {
        http_response_code(400);
        return ['error' => 'Name, location, and operation type are required'];
    }
    
    // Check if player has enough tokens for initial investment
    $initial_investment = $data['initial_investment'] ?? 100;
    if ($player->tokens < $initial_investment) {
        http_response_code(400);
        return ['error' => 'Insufficient tokens for initial investment'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create cooperative operation
        $stmt = $pdo->prepare("
            INSERT INTO game_coop_operations 
            (name, guild_id, operation_type, location_id, total_investment, expected_yield)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $guild_id = null;
        $user_guild = getUserGuild($user_id);
        if ($user_guild) {
            $guild_id = $user_guild['id'];
        }
        
        $expected_yield = calculateExpectedYield($data['operation_type'], $initial_investment);
        
        $stmt->execute([
            $data['name'],
            $guild_id,
            $data['operation_type'],
            $data['location_id'],
            $initial_investment,
            $expected_yield
        ]);
        
        $operation_id = $pdo->lastInsertId();
        
        // Add creator as manager and investor
        $stmt = $pdo->prepare("
            INSERT INTO game_coop_participants 
            (operation_id, player_id, role, investment_amount, profit_share_percentage)
            VALUES (?, ?, 'manager', ?, ?)
        ");
        $stmt->execute([$operation_id, $player->id, $initial_investment, 60.0]); // Manager gets 60% initially
        
        // Deduct tokens from player
        $player->spendTokens($initial_investment);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'operation_id' => $operation_id,
            'message' => 'Cooperative operation created successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function joinCoopOperation($user_id, $operation_id, $data) {
    global $pdo;
    
    $player = GamePlayer::getByUserId($user_id);
    $role = $data['role'] ?? 'investor';
    $investment = $data['investment_amount'] ?? 0;
    
    // Get operation details
    $operation = getCoopOperationDetails($operation_id, $user_id);
    if (!$operation) {
        http_response_code(404);
        return ['error' => 'Operation not found'];
    }
    
    if ($operation['status'] !== 'planning') {
        http_response_code(400);
        return ['error' => 'Operation is no longer accepting participants'];
    }
    
    // Check if already a participant
    $existing = $pdo->prepare("
        SELECT id FROM game_coop_participants 
        WHERE operation_id = ? AND player_id = ?
    ");
    $existing->execute([$operation_id, $player->id]);
    
    if ($existing->fetch()) {
        http_response_code(400);
        return ['error' => 'Already a participant in this operation'];
    }
    
    // Check investment amount
    if ($investment > 0 && $player->tokens < $investment) {
        http_response_code(400);
        return ['error' => 'Insufficient tokens for investment'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Calculate profit share based on investment
        $total_investment = $operation['total_investment'] + $investment;
        $profit_share = $total_investment > 0 ? ($investment / $total_investment) * 100 : 0;
        
        // Add participant
        $stmt = $pdo->prepare("
            INSERT INTO game_coop_participants 
            (operation_id, player_id, role, investment_amount, profit_share_percentage)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$operation_id, $player->id, $role, $investment, $profit_share]);
        
        // Update operation total investment
        $pdo->prepare("
            UPDATE game_coop_operations 
            SET total_investment = total_investment + ?
            WHERE id = ?
        ")->execute([$investment, $operation_id]);
        
        // Deduct tokens
        if ($investment > 0) {
            $player->spendTokens($investment);
        }
        
        // Recalculate all profit shares
        recalculateProfitShares($operation_id);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Successfully joined cooperative operation',
            'profit_share_percentage' => $profit_share
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ====================================
// Player Trading Implementation
// ====================================

function listItemForSale($user_id, $data) {
    global $pdo;
    
    $player = GamePlayer::getByUserId($user_id);
    
    // Validate required fields
    if (empty($data['item_type']) || empty($data['asking_price'])) {
        http_response_code(400);
        return ['error' => 'Item type and price are required'];
    }
    
    // Verify player owns the item
    if (!verifyItemOwnership($player->id, $data['item_type'], $data['item_id'])) {
        http_response_code(400);
        return ['error' => 'You do not own this item'];
    }
    
    try {
        $expiry_hours = $data['duration_hours'] ?? 168; // Default 7 days
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));
        
        $stmt = $pdo->prepare("
            INSERT INTO game_player_trades 
            (seller_id, trade_type, item_type, item_id, quantity, asking_price, expires_at, trade_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $player->id,
            $data['trade_type'] ?? 'marketplace',
            $data['item_type'],
            $data['item_id'] ?? null,
            $data['quantity'] ?? 1,
            $data['asking_price'],
            $expires_at,
            $data['notes'] ?? null
        ]);
        
        $trade_id = $pdo->lastInsertId();
        
        // Mark item as listed for sale
        markItemAsListed($data['item_type'], $data['item_id']);
        
        return [
            'success' => true,
            'trade_id' => $trade_id,
            'expires_at' => $expires_at,
            'message' => 'Item listed successfully'
        ];
        
    } catch (Exception $e) {
        throw $e;
    }
}

function purchaseItem($buyer_user_id, $trade_id) {
    global $pdo;
    
    $buyer_player = GamePlayer::getByUserId($buyer_user_id);
    
    // Get trade details
    $trade_stmt = $pdo->prepare("
        SELECT t.*, sp.user_id as seller_user_id
        FROM game_player_trades t
        JOIN game_players sp ON t.seller_id = sp.id
        WHERE t.id = ? AND t.status = 'listed' AND t.expires_at > NOW()
    ");
    $trade_stmt->execute([$trade_id]);
    $trade = $trade_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trade) {
        http_response_code(404);
        return ['error' => 'Trade not found or expired'];
    }
    
    if ($trade['seller_id'] == $buyer_player->id) {
        http_response_code(400);
        return ['error' => 'Cannot buy your own items'];
    }
    
    if ($buyer_player->tokens < $trade['asking_price']) {
        http_response_code(400);
        return ['error' => 'Insufficient tokens'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Transfer tokens
        $buyer_player->spendTokens($trade['asking_price']);
        
        $seller_player = GamePlayer::getByUserId($trade['seller_user_id']);
        $seller_player->addTokens($trade['asking_price']);
        
        // Transfer item ownership
        transferItemOwnership(
            $trade['item_type'], 
            $trade['item_id'], 
            $trade['seller_id'], 
            $buyer_player->id
        );
        
        // Update trade status
        $pdo->prepare("
            UPDATE game_player_trades 
            SET buyer_id = ?, final_price = ?, status = 'completed', completed_at = NOW()
            WHERE id = ?
        ")->execute([$buyer_player->id, $trade['asking_price'], $trade_id]);
        
        $pdo->commit();
        
        // Send notifications
        queueNotification([
            'user_id' => $trade['seller_user_id'],
            'type' => 'trade_completed',
            'data' => [
                'item_type' => $trade['item_type'],
                'final_price' => $trade['asking_price'],
                'buyer_name' => getPlayerName($buyer_user_id)
            ]
        ]);
        
        queueNotification([
            'user_id' => $buyer_user_id,
            'type' => 'purchase_completed',
            'data' => [
                'item_type' => $trade['item_type'],
                'final_price' => $trade['asking_price'],
                'seller_name' => getPlayerName($trade['seller_user_id'])
            ]
        ]);
        
        return [
            'success' => true,
            'message' => 'Purchase completed successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ====================================
// Utility Functions
// ====================================

function calculateExpectedYield($operation_type, $investment) {
    $yield_multipliers = [
        'small_grow' => 2.5,
        'medium_grow' => 3.0,
        'large_grow' => 3.5,
        'processing_facility' => 4.0
    ];
    
    $multiplier = $yield_multipliers[$operation_type] ?? 2.5;
    return $investment * $multiplier;
}

function recalculateProfitShares($operation_id) {
    global $pdo;
    
    // Get total investment
    $total_stmt = $pdo->prepare("SELECT SUM(investment_amount) as total FROM game_coop_participants WHERE operation_id = ?");
    $total_stmt->execute([$operation_id]);
    $total_investment = $total_stmt->fetchColumn() ?? 0;
    
    if ($total_investment > 0) {
        // Update profit shares proportionally
        $pdo->prepare("
            UPDATE game_coop_participants 
            SET profit_share_percentage = (investment_amount / ?) * 100
            WHERE operation_id = ?
        ")->execute([$total_investment, $operation_id]);
    }
}

function verifyItemOwnership($player_id, $item_type, $item_id) {
    global $pdo;
    
    switch ($item_type) {
        case 'plant':
            $stmt = $pdo->prepare("SELECT id FROM plants WHERE id = ? AND player_id = ? AND status = 'harvested'");
            break;
        case 'product':
            $stmt = $pdo->prepare("SELECT id FROM game_products WHERE id = ? AND player_id = ? AND status = 'available'");
            break;
        default:
            return false;
    }
    
    $stmt->execute([$item_id, $player_id]);
    return $stmt->fetch() !== false;
}

function markItemAsListed($item_type, $item_id) {
    global $pdo;
    
    switch ($item_type) {
        case 'product':
            $pdo->prepare("UPDATE game_products SET status = 'listed' WHERE id = ?")
                ->execute([$item_id]);
            break;
    }
}

function transferItemOwnership($item_type, $item_id, $from_player_id, $to_player_id) {
    global $pdo;
    
    switch ($item_type) {
        case 'plant':
            $pdo->prepare("UPDATE plants SET player_id = ? WHERE id = ? AND player_id = ?")
                ->execute([$to_player_id, $item_id, $from_player_id]);
            break;
        case 'product':
            $pdo->prepare("UPDATE game_products SET player_id = ?, status = 'available' WHERE id = ? AND player_id = ?")
                ->execute([$to_player_id, $item_id, $from_player_id]);
            break;
    }
}

function getPlayerName($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?? 'Unknown Player';
}

function queueNotification($data) {
    // Implementation would queue notification using the notification system
    // For now, just return success
    return true;
}

function queueGuildNotification($guild_id, $data) {
    // Queue notification to all guild members with officer/leader roles
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT gp.user_id 
        FROM game_guild_members gm
        JOIN game_players gp ON gm.player_id = gp.id
        WHERE gm.guild_id = ? AND gm.role IN ('officer', 'leader')
    ");
    $stmt->execute([$guild_id]);
    
    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($recipients as $user_id) {
        $notification_data = array_merge($data, ['user_id' => $user_id]);
        queueNotification($notification_data);
    }
}

// Additional implementation functions would continue here...
// This includes functions like getMarketplaceListings(), getUserTrades(), 
// getActiveCompetitions(), etc.

?>
