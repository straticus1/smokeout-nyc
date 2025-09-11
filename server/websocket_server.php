<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/models/EnhancedGamingSystem.php';
require_once __DIR__ . '/../config/database.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class GameWebSocketServer implements MessageComponentInterface {
    protected $clients;
    protected $gameRooms;
    protected $userConnections;
    protected $pdo;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->gameRooms = [];
        $this->userConnections = [];
        
        // Database connection
        $config = require __DIR__ . '/../config/database.php';
        $this->pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        echo "WebSocket Gaming Server Started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            $this->sendError($from, 'Invalid message format');
            return;
        }

        try {
            switch ($data['type']) {
                case 'auth':
                    $this->handleAuth($from, $data);
                    break;
                case 'join_room':
                    $this->handleJoinRoom($from, $data);
                    break;
                case 'leave_room':
                    $this->handleLeaveRoom($from, $data);
                    break;
                case 'plant_action':
                    $this->handlePlantAction($from, $data);
                    break;
                case 'trade_request':
                    $this->handleTradeRequest($from, $data);
                    break;
                case 'market_update':
                    $this->handleMarketUpdate($from, $data);
                    break;
                case 'weather_sync':
                    $this->handleWeatherSync($from, $data);
                    break;
                case 'genetics_bred':
                    $this->handleGeneticsBred($from, $data);
                    break;
                default:
                    $this->sendError($from, 'Unknown message type');
            }
        } catch (Exception $e) {
            error_log("WebSocket error: " . $e->getMessage());
            $this->sendError($from, 'Server error occurred');
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from all rooms and user connections
        foreach ($this->gameRooms as $roomId => $room) {
            if (isset($room['connections'][$conn->resourceId])) {
                unset($room['connections'][$conn->resourceId]);
                $this->broadcastToRoom($roomId, [
                    'type' => 'player_left',
                    'player_id' => $room['players'][$conn->resourceId] ?? null
                ]);
            }
        }
        
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleAuth($conn, $data) {
        if (!isset($data['token']) || !isset($data['user_id'])) {
            $this->sendError($conn, 'Missing authentication data');
            return;
        }

        // Verify JWT token
        $stmt = $this->pdo->prepare("SELECT id, username FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$data['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->sendError($conn, 'Invalid user');
            return;
        }

        $this->userConnections[$user['id']] = $conn;
        $conn->user_id = $user['id'];
        $conn->username = $user['username'];

        $this->send($conn, [
            'type' => 'auth_success',
            'user_id' => $user['id'],
            'username' => $user['username']
        ]);

        echo "User {$user['username']} authenticated on connection {$conn->resourceId}\n";
    }

    private function handleJoinRoom($conn, $data) {
        if (!isset($conn->user_id)) {
            $this->sendError($conn, 'Not authenticated');
            return;
        }

        $roomId = $data['room_id'] ?? 'global';
        
        if (!isset($this->gameRooms[$roomId])) {
            $this->gameRooms[$roomId] = [
                'connections' => [],
                'players' => [],
                'created_at' => time()
            ];
        }

        $this->gameRooms[$roomId]['connections'][$conn->resourceId] = $conn;
        $this->gameRooms[$roomId]['players'][$conn->resourceId] = $conn->user_id;

        // Log room join
        $stmt = $this->pdo->prepare("
            INSERT INTO websocket_connections (user_id, room_id, connection_id, connected_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE connected_at = NOW()
        ");
        $stmt->execute([$conn->user_id, $roomId, $conn->resourceId]);

        $this->send($conn, [
            'type' => 'room_joined',
            'room_id' => $roomId,
            'players_count' => count($this->gameRooms[$roomId]['players'])
        ]);

        $this->broadcastToRoom($roomId, [
            'type' => 'player_joined',
            'player_id' => $conn->user_id,
            'username' => $conn->username,
            'players_count' => count($this->gameRooms[$roomId]['players'])
        ], $conn);

        echo "User {$conn->username} joined room {$roomId}\n";
    }

    private function handleLeaveRoom($conn, $data) {
        $roomId = $data['room_id'] ?? 'global';
        
        if (isset($this->gameRooms[$roomId]['connections'][$conn->resourceId])) {
            unset($this->gameRooms[$roomId]['connections'][$conn->resourceId]);
            unset($this->gameRooms[$roomId]['players'][$conn->resourceId]);

            $this->broadcastToRoom($roomId, [
                'type' => 'player_left',
                'player_id' => $conn->user_id,
                'players_count' => count($this->gameRooms[$roomId]['players'])
            ]);
        }
    }

    private function handlePlantAction($conn, $data) {
        if (!isset($conn->user_id)) {
            $this->sendError($conn, 'Not authenticated');
            return;
        }

        $action = $data['action'];
        $plantId = $data['plant_id'];
        $roomId = $data['room_id'] ?? 'global';

        // Process plant action through EnhancedGamingSystem
        $gamingSystem = new EnhancedGamingSystem();
        
        switch ($action) {
            case 'water':
                $result = $gamingSystem->waterPlant($plantId, $conn->user_id);
                break;
            case 'harvest':
                $result = $gamingSystem->harvestPlant($plantId, $conn->user_id);
                break;
            case 'fertilize':
                $result = $gamingSystem->fertilizePlant($plantId, $conn->user_id, $data['fertilizer_type'] ?? 'basic');
                break;
            default:
                $this->sendError($conn, 'Unknown plant action');
                return;
        }

        // Broadcast action to room
        $this->broadcastToRoom($roomId, [
            'type' => 'plant_action_result',
            'action' => $action,
            'plant_id' => $plantId,
            'player_id' => $conn->user_id,
            'result' => $result,
            'timestamp' => time()
        ]);
    }

    private function handleTradeRequest($conn, $data) {
        if (!isset($conn->user_id)) {
            $this->sendError($conn, 'Not authenticated');
            return;
        }

        $targetUserId = $data['target_user_id'];
        $tradeItems = $data['items'];
        $roomId = $data['room_id'] ?? 'global';

        // Find target user connection
        $targetConn = $this->userConnections[$targetUserId] ?? null;
        if (!$targetConn) {
            $this->sendError($conn, 'Target user not online');
            return;
        }

        $tradeRequest = [
            'type' => 'trade_request',
            'from_user_id' => $conn->user_id,
            'from_username' => $conn->username,
            'items' => $tradeItems,
            'trade_id' => uniqid('trade_', true),
            'timestamp' => time()
        ];

        $this->send($targetConn, $tradeRequest);
        
        $this->send($conn, [
            'type' => 'trade_request_sent',
            'target_user_id' => $targetUserId,
            'trade_id' => $tradeRequest['trade_id']
        ]);
    }

    private function handleMarketUpdate($conn, $data) {
        $roomId = $data['room_id'] ?? 'global';
        
        // Get current market data
        $gamingSystem = new EnhancedGamingSystem();
        $marketData = $gamingSystem->getMarketPrices();
        
        $this->broadcastToRoom($roomId, [
            'type' => 'market_prices_update',
            'prices' => $marketData,
            'timestamp' => time()
        ]);
    }

    private function handleWeatherSync($conn, $data) {
        $roomId = $data['room_id'] ?? 'global';
        
        // Get current weather effects
        $stmt = $this->pdo->prepare("
            SELECT * FROM weather_effects 
            WHERE is_active = 1 AND end_time > NOW() 
            ORDER BY start_time DESC LIMIT 5
        ");
        $stmt->execute();
        $weatherEffects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->broadcastToRoom($roomId, [
            'type' => 'weather_update',
            'effects' => $weatherEffects,
            'timestamp' => time()
        ]);
    }

    private function handleGeneticsBred($conn, $data) {
        if (!isset($conn->user_id)) {
            $this->sendError($conn, 'Not authenticated');
            return;
        }

        $roomId = $data['room_id'] ?? 'global';
        $parent1Id = $data['parent1_id'];
        $parent2Id = $data['parent2_id'];
        
        // Process breeding through EnhancedGamingSystem
        $gamingSystem = new EnhancedGamingSystem();
        $breedingResult = $gamingSystem->breedGenetics($parent1Id, $parent2Id, $conn->user_id);
        
        $this->broadcastToRoom($roomId, [
            'type' => 'genetics_bred',
            'player_id' => $conn->user_id,
            'parent1_id' => $parent1Id,
            'parent2_id' => $parent2Id,
            'result' => $breedingResult,
            'timestamp' => time()
        ]);
    }

    private function broadcastToRoom($roomId, $message, $excludeConn = null) {
        if (!isset($this->gameRooms[$roomId])) {
            return;
        }

        foreach ($this->gameRooms[$roomId]['connections'] as $conn) {
            if ($excludeConn && $conn === $excludeConn) {
                continue;
            }
            $this->send($conn, $message);
        }
    }

    private function send($conn, $data) {
        $conn->send(json_encode($data));
    }

    private function sendError($conn, $message) {
        $this->send($conn, [
            'type' => 'error',
            'message' => $message,
            'timestamp' => time()
        ]);
    }
}

// Start the WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new GameWebSocketServer()
        )
    ),
    8080
);

echo "WebSocket Gaming Server listening on port 8080\n";
$server->run();