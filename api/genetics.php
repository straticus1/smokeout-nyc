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
    error_log("Genetics API error: " . $e->getMessage());
    sendJsonResponse(['error' => 'Internal server error'], 500);
}

function handleGet($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    $action = $uri_parts[2] ?? 'list';
    
    switch ($action) {
        case 'list':
            getPlayerGenetics($user_id);
            break;
        case 'available':
            getAvailableGenetics($user_id);
            break;
        case 'breeding-history':
            getBreedingHistory($user_id);
            break;
        case 'rarities':
            getGeneticsRarities();
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
        case 'breed':
            breedGenetics($user_id, $input);
            break;
        case 'discover':
            discoverWildGenetics($user_id, $input);
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

function getPlayerGenetics($user_id) {
    try {
        $gamingSystem = new EnhancedGamingSystem();
        
        // Get genetics owned by player
        $db = DB::getInstance();
        $genetics = $db->fetchAll(
            "SELECT g.*, pg.acquired_at, pg.generation_bred
             FROM genetics g
             JOIN player_genetics pg ON g.id = pg.genetics_id
             WHERE pg.player_id = (SELECT id FROM game_players WHERE user_id = ?)
             ORDER BY g.rarity DESC, g.name ASC",
            [$user_id]
        );
        
        // Add breeding information
        foreach ($genetics as &$genetic) {
            $genetic['bred_by_player'] = !empty($genetic['generation_bred']);
            $genetic['breeding_success_rate'] = calculateBreedingSuccessRate($genetic);
        }
        
        sendJsonResponse([
            'genetics' => $genetics,
            'total_count' => count($genetics),
            'bred_count' => count(array_filter($genetics, fn($g) => $g['bred_by_player'])),
            'natural_count' => count(array_filter($genetics, fn($g) => !$g['bred_by_player']))
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get genetics: ' . $e->getMessage()], 500);
    }
}

function getAvailableGenetics($user_id) {
    try {
        $db = DB::getInstance();
        
        // Get all available genetics (not owned by player)
        $genetics = $db->fetchAll(
            "SELECT g.*
             FROM genetics g
             WHERE g.id NOT IN (
                 SELECT pg.genetics_id 
                 FROM player_genetics pg 
                 JOIN game_players gp ON pg.player_id = gp.id 
                 WHERE gp.user_id = ?
             )
             AND g.is_discoverable = 1
             ORDER BY g.rarity DESC, g.name ASC",
            [$user_id]
        );
        
        sendJsonResponse([
            'available_genetics' => $genetics,
            'count' => count($genetics)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get available genetics: ' . $e->getMessage()], 500);
    }
}

function getBreedingHistory($user_id) {
    try {
        $db = DB::getInstance();
        
        $history = $db->fetchAll(
            "SELECT bh.*, g.name as offspring_name, g.rarity as offspring_rarity,
                    p1.name as parent1_name, p2.name as parent2_name
             FROM breeding_history bh
             JOIN genetics g ON bh.offspring_genetics_id = g.id
             JOIN genetics p1 ON bh.parent1_genetics_id = p1.id
             JOIN genetics p2 ON bh.parent2_genetics_id = p2.id
             JOIN game_players gp ON bh.player_id = gp.id
             WHERE gp.user_id = ?
             ORDER BY bh.bred_at DESC
             LIMIT 50",
            [$user_id]
        );
        
        sendJsonResponse([
            'breeding_history' => $history,
            'total_breedings' => count($history)
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get breeding history: ' . $e->getMessage()], 500);
    }
}

function getGeneticsRarities() {
    try {
        $rarities = [
            'common' => [
                'name' => 'Common',
                'color' => '#6B7280',
                'breeding_bonus' => 0,
                'base_success_rate' => 70
            ],
            'uncommon' => [
                'name' => 'Uncommon',
                'color' => '#10B981',
                'breeding_bonus' => 5,
                'base_success_rate' => 60
            ],
            'rare' => [
                'name' => 'Rare',
                'color' => '#3B82F6',
                'breeding_bonus' => 10,
                'base_success_rate' => 45
            ],
            'epic' => [
                'name' => 'Epic',
                'color' => '#8B5CF6',
                'breeding_bonus' => 15,
                'base_success_rate' => 30
            ],
            'legendary' => [
                'name' => 'Legendary',
                'color' => '#F59E0B',
                'breeding_bonus' => 25,
                'base_success_rate' => 15
            ]
        ];
        
        sendJsonResponse([
            'rarities' => $rarities
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get rarities: ' . $e->getMessage()], 500);
    }
}

function breedGenetics($user_id, $input) {
    try {
        $parent1_id = $input['parent1_id'] ?? null;
        $parent2_id = $input['parent2_id'] ?? null;
        
        if (!$parent1_id || !$parent2_id) {
            sendJsonResponse(['error' => 'Both parent genetics IDs required'], 400);
            return;
        }
        
        if ($parent1_id === $parent2_id) {
            sendJsonResponse(['error' => 'Cannot breed genetics with itself'], 400);
            return;
        }
        
        $gamingSystem = new EnhancedGamingSystem();
        $result = $gamingSystem->breedGenetics($parent1_id, $parent2_id, $user_id);
        
        sendJsonResponse($result);
        
    } catch (Exception $e) {
        sendJsonResponse([
            'success' => false,
            'failure_reason' => 'Breeding failed: ' . $e->getMessage()
        ], 500);
    }
}

function discoverWildGenetics($user_id, $input) {
    try {
        $location_id = $input['location_id'] ?? null;
        $search_effort = $input['search_effort'] ?? 'medium';
        
        if (!$location_id) {
            sendJsonResponse(['error' => 'Location ID required'], 400);
            return;
        }
        
        $db = DB::getInstance();
        $player = $db->fetchOne(
            "SELECT * FROM game_players WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$player) {
            sendJsonResponse(['error' => 'Player not found'], 404);
            return;
        }
        
        // Discovery chance based on effort and player level
        $discovery_rates = [
            'low' => 0.1,
            'medium' => 0.2,
            'high' => 0.35
        ];
        
        $base_rate = $discovery_rates[$search_effort] ?? 0.2;
        $level_bonus = $player['level'] * 0.02;
        $final_rate = min(0.8, $base_rate + $level_bonus);
        
        $random = mt_rand() / mt_getrandmax();
        
        if ($random < $final_rate) {
            // Discovery successful - get random available genetics
            $available_genetics = $db->fetchAll(
                "SELECT g.* FROM genetics g
                 WHERE g.id NOT IN (
                     SELECT pg.genetics_id 
                     FROM player_genetics pg 
                     JOIN game_players gp ON pg.player_id = gp.id 
                     WHERE gp.user_id = ?
                 )
                 AND g.is_discoverable = 1
                 ORDER BY RAND()
                 LIMIT 1",
                [$user_id]
            );
            
            if (!empty($available_genetics)) {
                $discovered_genetics = $available_genetics[0];
                
                // Award to player
                $db->execute(
                    "INSERT INTO player_genetics (player_id, genetics_id, acquired_at, acquired_method)
                     VALUES (?, ?, NOW(), 'wild_discovery')",
                    [$player['id'], $discovered_genetics['id']]
                );
                
                // Award experience
                $exp_reward = 50 + ($discovered_genetics['rarity_value'] ?? 1) * 25;
                $db->execute(
                    "UPDATE game_players SET experience = experience + ? WHERE id = ?",
                    [$exp_reward, $player['id']]
                );
                
                sendJsonResponse([
                    'success' => true,
                    'discovered_genetics' => $discovered_genetics,
                    'experience_gained' => $exp_reward,
                    'discovery_method' => 'wild_discovery'
                ]);
            } else {
                sendJsonResponse([
                    'success' => false,
                    'reason' => 'No new genetics available to discover'
                ]);
            }
        } else {
            sendJsonResponse([
                'success' => false,
                'reason' => 'Discovery attempt failed',
                'discovery_rate' => round($final_rate * 100, 1)
            ]);
        }
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Discovery failed: ' . $e->getMessage()], 500);
    }
}

function calculateBreedingSuccessRate($genetics) {
    // Base rate by rarity
    $base_rates = [
        'common' => 70,
        'uncommon' => 60,
        'rare' => 45,
        'epic' => 30,
        'legendary' => 15
    ];
    
    $base_rate = $base_rates[$genetics['rarity']] ?? 50;
    
    // Stability and vigor bonuses
    $stability_bonus = ($genetics['stability'] ?? 0.5) * 20;
    $vigor_bonus = ($genetics['vigor'] ?? 0.5) * 10;
    
    return min(90, max(10, $base_rate + $stability_bonus + $vigor_bonus));
}

?>