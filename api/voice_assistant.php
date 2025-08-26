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
        case 'POST':
            if ($endpoint === 'process-command') {
                processVoiceCommand();
            } elseif ($endpoint === 'speech-to-text') {
                processSpeechToText();
            } elseif ($endpoint === 'text-to-speech') {
                processTextToSpeech();
            } elseif ($endpoint === 'train-voice') {
                trainVoiceProfile();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
            
        case 'GET':
            if ($endpoint === 'commands') {
                getAvailableCommands();
            } elseif ($endpoint === 'voice-profile') {
                getVoiceProfile();
            } elseif ($endpoint === 'command-history') {
                getCommandHistory();
            } elseif ($endpoint === 'voice-settings') {
                getVoiceSettings();
            } else {
                throw new Exception('Invalid endpoint');
            }
            break;
            
        case 'PUT':
            if ($endpoint === 'voice-settings') {
                updateVoiceSettings();
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

function processVoiceCommand() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $command_text = $input['command'] ?? '';
    $confidence = $input['confidence'] ?? 0.0;
    
    if (empty($command_text)) {
        throw new Exception('Command text is required');
    }
    
    // Parse and execute voice command
    $command_result = parseAndExecuteCommand($command_text, $user_id);
    
    // Log command usage
    $stmt = $pdo->prepare("
        INSERT INTO voice_command_history (user_id, command_text, parsed_action, success, confidence_score, response_text)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $command_text,
        $command_result['action'],
        $command_result['success'],
        $confidence,
        $command_result['response']
    ]);
    
    echo json_encode([
        'success' => $command_result['success'],
        'action' => $command_result['action'],
        'response' => $command_result['response'],
        'data' => $command_result['data'] ?? null
    ]);
}

function parseAndExecuteCommand($command_text, $user_id) {
    global $pdo;
    
    $command_lower = strtolower(trim($command_text));
    $response = '';
    $action = 'unknown';
    $success = false;
    $data = null;
    
    try {
        // Plant management commands
        if (preg_match('/water\s+(my\s+)?plants?/i', $command_lower)) {
            $action = 'water_plants';
            $result = waterAllPlants($user_id);
            $success = $result['success'];
            $response = $result['message'];
            $data = $result['data'];
            
        } elseif (preg_match('/harvest\s+(my\s+)?plants?/i', $command_lower)) {
            $action = 'harvest_plants';
            $result = harvestReadyPlants($user_id);
            $success = $result['success'];
            $response = $result['message'];
            $data = $result['data'];
            
        } elseif (preg_match('/plant\s+(\w+)\s*seeds?/i', $command_lower, $matches)) {
            $action = 'plant_seeds';
            $strain_name = $matches[1];
            $result = plantSeeds($user_id, $strain_name);
            $success = $result['success'];
            $response = $result['message'];
            $data = $result['data'];
            
        } elseif (preg_match('/check\s+(my\s+)?(plants?|garden)/i', $command_lower)) {
            $action = 'check_plants';
            $result = checkPlantStatus($user_id);
            $success = true;
            $response = $result['message'];
            $data = $result['data'];
            
        // Game status commands
        } elseif (preg_match('/check\s+(my\s+)?(stats?|status|profile)/i', $command_lower)) {
            $action = 'check_stats';
            $result = getPlayerStats($user_id);
            $success = true;
            $response = $result['message'];
            $data = $result['data'];
            
        } elseif (preg_match('/how\s+much\s+(money|cash|tokens?)/i', $command_lower)) {
            $action = 'check_balance';
            $result = getPlayerBalance($user_id);
            $success = true;
            $response = $result['message'];
            $data = $result['data'];
            
        // Market commands
        } elseif (preg_match('/sell\s+(all\s+)?(\w+)/i', $command_lower, $matches)) {
            $action = 'sell_product';
            $product = $matches[2];
            $sell_all = !empty($matches[1]);
            $result = sellProduct($user_id, $product, $sell_all);
            $success = $result['success'];
            $response = $result['message'];
            $data = $result['data'];
            
        } elseif (preg_match('/buy\s+(\d+)?\s*(\w+)/i', $command_lower, $matches)) {
            $action = 'buy_item';
            $quantity = !empty($matches[1]) ? (int)$matches[1] : 1;
            $item = $matches[2];
            $result = buyItem($user_id, $item, $quantity);
            $success = $result['success'];
            $response = $result['message'];
            $data = $result['data'];
            
        // Navigation commands
        } elseif (preg_match('/go\s+to\s+(\w+)/i', $command_lower, $matches)) {
            $action = 'navigate';
            $location = $matches[1];
            $success = true;
            $response = "Navigating to {$location}";
            $data = ['location' => $location];
            
        // Help command
        } elseif (preg_match('/help|what\s+can\s+i\s+(do|say)/i', $command_lower)) {
            $action = 'help';
            $success = true;
            $response = "Available commands: water plants, harvest plants, plant seeds, check plants, check stats, sell products, buy items, and more. Say 'list commands' for a full list.";
            
        } elseif (preg_match('/list\s+commands/i', $command_lower)) {
            $action = 'list_commands';
            $result = getAvailableCommandsList();
            $success = true;
            $response = $result['message'];
            $data = $result['data'];
            
        } else {
            $action = 'unknown';
            $response = "I didn't understand that command. Try saying 'help' for available commands.";
        }
        
    } catch (Exception $e) {
        $success = false;
        $response = "Sorry, I couldn't complete that action: " . $e->getMessage();
    }
    
    return [
        'success' => $success,
        'action' => $action,
        'response' => $response,
        'data' => $data
    ];
}

function waterAllPlants($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as plant_count
        FROM plants 
        WHERE user_id = ? AND status = 'growing' AND last_watered < DATE_SUB(NOW(), INTERVAL 6 HOUR)
    ");
    $stmt->execute([$user_id]);
    $needs_water = $stmt->fetch()['plant_count'];
    
    if ($needs_water == 0) {
        return [
            'success' => true,
            'message' => "All your plants are already well watered!",
            'data' => ['plants_watered' => 0]
        ];
    }
    
    $stmt = $pdo->prepare("
        UPDATE plants 
        SET last_watered = NOW(), health = LEAST(health + 10, 100)
        WHERE user_id = ? AND status = 'growing' AND last_watered < DATE_SUB(NOW(), INTERVAL 6 HOUR)
    ");
    $stmt->execute([$user_id]);
    
    return [
        'success' => true,
        'message' => "Watered {$needs_water} plants. They look much happier now!",
        'data' => ['plants_watered' => $needs_water]
    ];
}

function harvestReadyPlants($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, s.name as strain_name
        FROM plants p
        JOIN strains s ON p.strain_id = s.id
        WHERE p.user_id = ? AND p.status = 'ready' AND p.growth_stage = 'flowering'
        AND p.planted_at <= DATE_SUB(NOW(), INTERVAL s.flowering_time DAY)
    ");
    $stmt->execute([$user_id]);
    $ready_plants = $stmt->fetchAll();
    
    if (empty($ready_plants)) {
        return [
            'success' => true,
            'message' => "No plants are ready for harvest yet. Keep waiting!",
            'data' => ['plants_harvested' => 0, 'total_yield' => 0]
        ];
    }
    
    $total_yield = 0;
    $harvested_count = 0;
    
    foreach ($ready_plants as $plant) {
        $yield = calculatePlantYield($plant);
        $total_yield += $yield;
        
        // Update plant status and add to inventory
        $stmt = $pdo->prepare("UPDATE plants SET status = 'harvested', yield = ? WHERE id = ?");
        $stmt->execute([$yield, $plant['id']]);
        
        $stmt = $pdo->prepare("
            INSERT INTO inventory (user_id, strain_id, quantity, quality, acquired_date)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([$user_id, $plant['strain_id'], $yield, $plant['quality']]);
        
        $harvested_count++;
    }
    
    return [
        'success' => true,
        'message' => "Harvested {$harvested_count} plants for a total yield of {$total_yield}g!",
        'data' => ['plants_harvested' => $harvested_count, 'total_yield' => $total_yield]
    ];
}

function plantSeeds($user_id, $strain_name) {
    global $pdo;
    
    // Find strain
    $stmt = $pdo->prepare("SELECT * FROM strains WHERE name LIKE ? LIMIT 1");
    $stmt->execute(["%{$strain_name}%"]);
    $strain = $stmt->fetch();
    
    if (!$strain) {
        return [
            'success' => false,
            'message' => "I couldn't find seeds for '{$strain_name}'. Try a different strain name."
        ];
    }
    
    // Check if user has seeds
    $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE user_id = ? AND strain_id = ? AND quantity > 0");
    $stmt->execute([$user_id, $strain['id']]);
    $seeds = $stmt->fetch();
    
    if (!$seeds || $seeds['quantity'] < 1) {
        return [
            'success' => false,
            'message' => "You don't have any {$strain['name']} seeds to plant."
        ];
    }
    
    // Plant the seed
    $stmt = $pdo->prepare("
        INSERT INTO plants (user_id, strain_id, growth_stage, health, planted_at, last_watered)
        VALUES (?, ?, 'seedling', 100, NOW(), NOW())
    ");
    $stmt->execute([$user_id, $strain['id']]);
    
    // Remove seed from inventory
    $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - 1 WHERE user_id = ? AND strain_id = ?");
    $stmt->execute([$user_id, $strain['id']]);
    
    return [
        'success' => true,
        'message' => "Planted one {$strain['name']} seed. Happy growing!",
        'data' => ['strain_planted' => $strain['name']]
    ];
}

function checkPlantStatus($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_plants,
            SUM(CASE WHEN status = 'growing' THEN 1 ELSE 0 END) as growing,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready,
            AVG(health) as avg_health
        FROM plants 
        WHERE user_id = ? AND status IN ('growing', 'ready')
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    $message = "You have {$stats['total_plants']} active plants. ";
    $message .= "{$stats['growing']} are still growing, {$stats['ready']} are ready to harvest. ";
    $message .= "Average health: " . round($stats['avg_health'], 1) . "%";
    
    return [
        'success' => true,
        'message' => $message,
        'data' => $stats
    ];
}

function getPlayerStats($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT gp.*, u.username 
        FROM game_players gp 
        JOIN users u ON gp.user_id = u.id 
        WHERE gp.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $player = $stmt->fetch();
    
    $message = "Level {$player['level']} grower with {$player['experience']} XP. ";
    $message .= "You have {$player['tokens']} tokens and ${$player['cash']} cash.";
    
    return [
        'success' => true,
        'message' => $message,
        'data' => $player
    ];
}

function getPlayerBalance($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT tokens, cash FROM game_players WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetch();
    
    $message = "You have {$balance['tokens']} tokens and ${$balance['cash']} cash.";
    
    return [
        'success' => true,
        'message' => $message,
        'data' => $balance
    ];
}

function sellProduct($user_id, $product, $sell_all = false) {
    global $pdo;
    
    // Find product in inventory
    $stmt = $pdo->prepare("
        SELECT i.*, s.name as strain_name, s.base_price 
        FROM inventory i
        JOIN strains s ON i.strain_id = s.id
        WHERE i.user_id = ? AND s.name LIKE ? AND i.quantity > 0
        LIMIT 1
    ");
    $stmt->execute([$user_id, "%{$product}%"]);
    $item = $stmt->fetch();
    
    if (!$item) {
        return [
            'success' => false,
            'message' => "You don't have any {$product} to sell."
        ];
    }
    
    $quantity_to_sell = $sell_all ? $item['quantity'] : 1;
    $price_per_unit = $item['base_price'] * ($item['quality'] / 100);
    $total_earnings = $quantity_to_sell * $price_per_unit;
    
    // Update inventory
    $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
    $stmt->execute([$quantity_to_sell, $item['id']]);
    
    // Add cash to player
    $stmt = $pdo->prepare("UPDATE game_players SET cash = cash + ? WHERE user_id = ?");
    $stmt->execute([$total_earnings, $user_id]);
    
    $message = "Sold {$quantity_to_sell}g of {$item['strain_name']} for $" . number_format($total_earnings, 2);
    
    return [
        'success' => true,
        'message' => $message,
        'data' => ['quantity_sold' => $quantity_to_sell, 'earnings' => $total_earnings]
    ];
}

function buyItem($user_id, $item, $quantity) {
    // Simplified buy logic - would need full shop system
    return [
        'success' => false,
        'message' => "Shopping feature coming soon! Use the in-game store for now."
    ];
}

function calculatePlantYield($plant) {
    $base_yield = 10; // Base yield in grams
    $health_modifier = $plant['health'] / 100;
    $quality_modifier = $plant['quality'] / 100;
    
    return round($base_yield * $health_modifier * $quality_modifier);
}

function getAvailableCommandsList() {
    $commands = [
        'Plant Management' => [
            'water plants' => 'Water all your plants',
            'harvest plants' => 'Harvest ready plants',
            'plant [strain] seeds' => 'Plant seeds of a specific strain',
            'check plants' => 'Check status of all plants'
        ],
        'Game Status' => [
            'check stats' => 'View your player statistics',
            'how much money' => 'Check your balance',
            'check profile' => 'View your profile information'
        ],
        'Market' => [
            'sell [product]' => 'Sell a product from inventory',
            'sell all [product]' => 'Sell all of a specific product',
            'buy [item]' => 'Purchase items (coming soon)'
        ],
        'Navigation' => [
            'go to [location]' => 'Navigate to different areas',
            'help' => 'Get help with commands',
            'list commands' => 'Show all available commands'
        ]
    ];
    
    $message = "Here are all available voice commands organized by category.";
    
    return [
        'success' => true,
        'message' => $message,
        'data' => $commands
    ];
}

function processSpeechToText() {
    // Placeholder for speech-to-text processing
    // In production, this would integrate with services like Google Speech-to-Text, Azure Speech, etc.
    
    $input = json_decode(file_get_contents('php://input'), true);
    $audio_data = $input['audio'] ?? '';
    
    if (empty($audio_data)) {
        throw new Exception('Audio data is required');
    }
    
    // Mock response - replace with actual speech recognition service
    echo json_encode([
        'success' => true,
        'text' => 'water my plants',
        'confidence' => 0.95,
        'language' => 'en-US'
    ]);
}

function processTextToSpeech() {
    global $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $text = $input['text'] ?? '';
    $voice = $input['voice'] ?? 'default';
    
    if (empty($text)) {
        throw new Exception('Text is required');
    }
    
    // Mock response - replace with actual TTS service
    echo json_encode([
        'success' => true,
        'audio_url' => '/api/tts/audio/' . md5($text . $user_id) . '.mp3',
        'duration' => strlen($text) * 0.1, // Rough estimate
        'voice_used' => $voice
    ]);
}

function trainVoiceProfile() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $training_phrases = $input['phrases'] ?? [];
    $audio_samples = $input['audio_samples'] ?? [];
    
    if (empty($training_phrases) || empty($audio_samples)) {
        throw new Exception('Training phrases and audio samples are required');
    }
    
    // Store voice training data
    $stmt = $pdo->prepare("
        INSERT INTO voice_profiles (user_id, training_data, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        training_data = JSON_MERGE_PATCH(training_data, VALUES(training_data)),
        updated_at = NOW()
    ");
    
    $training_data = json_encode([
        'phrases' => $training_phrases,
        'audio_samples' => $audio_samples,
        'training_session' => date('Y-m-d H:i:s')
    ]);
    
    $stmt->execute([$user_id, $training_data]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Voice profile training completed successfully',
        'phrases_trained' => count($training_phrases)
    ]);
}

function getAvailableCommands() {
    $commands = getAvailableCommandsList();
    echo json_encode($commands);
}

function getVoiceProfile() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("SELECT * FROM voice_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        echo json_encode([
            'success' => false,
            'message' => 'No voice profile found. Consider training your voice for better recognition.'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'profile' => [
            'trained' => true,
            'created_at' => $profile['created_at'],
            'updated_at' => $profile['updated_at'],
            'accuracy_score' => $profile['accuracy_score'] ?? 0.0
        ]
    ]);
}

function getCommandHistory() {
    global $pdo, $user_id;
    
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT command_text, parsed_action, success, confidence_score, response_text, created_at
        FROM voice_command_history 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    $history = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'history' => $history,
        'total_count' => count($history)
    ]);
}

function getVoiceSettings() {
    global $pdo, $user_id;
    
    $stmt = $pdo->prepare("SELECT voice_settings FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch()['voice_settings'] ?? '{}';
    
    $default_settings = [
        'enabled' => true,
        'language' => 'en-US',
        'voice_type' => 'default',
        'speech_rate' => 1.0,
        'volume' => 0.8,
        'wake_word' => 'hey smokeout',
        'confirmation_sounds' => true,
        'privacy_mode' => false
    ];
    
    $user_settings = array_merge($default_settings, json_decode($settings, true) ?: []);
    
    echo json_encode([
        'success' => true,
        'settings' => $user_settings
    ]);
}

function updateVoiceSettings() {
    global $pdo, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $new_settings = $input['settings'] ?? [];
    
    if (empty($new_settings)) {
        throw new Exception('Settings data is required');
    }
    
    $stmt = $pdo->prepare("UPDATE users SET voice_settings = ? WHERE id = ?");
    $stmt->execute([json_encode($new_settings), $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Voice settings updated successfully',
        'settings' => $new_settings
    ]);
}
?>
