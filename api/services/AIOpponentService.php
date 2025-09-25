<?php
require_once '../config.php';

class AIOpponentService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get AI opponent by ID
     */
    public function getAIOpponent($ai_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ai_opponents 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$ai_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get available AI opponents by difficulty
     */
    public function getAvailableAIs($difficulty = null) {
        $sql = "SELECT * FROM ai_opponents WHERE is_active = 1";
        $params = [];
        
        if ($difficulty) {
            $sql .= " AND difficulty_level = ?";
            $params[] = $difficulty;
        }
        
        $sql .= " ORDER BY difficulty_level, reputation_score DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * AI Decision Making Engine
     * Returns the AI's next action based on game state and personality
     */
    public function makeAIDecision($ai_id, $session_id, $game_state) {
        $ai = $this->getAIOpponent($ai_id);
        if (!$ai) {
            throw new Exception('AI opponent not found');
        }
        
        $behavior = json_decode($ai['behavior_patterns'], true);
        $specialties = json_decode($ai['specialties'], true);
        $weaknesses = json_decode($ai['weaknesses'], true);
        
        // Get current game state
        $current_stats = $this->getAIGameStats($session_id, $ai_id);
        $opponent_stats = $this->getOpponentStats($session_id, $ai_id);
        $available_actions = $this->getAvailableActions($session_id, $ai_id);
        
        // Decision making based on personality type
        switch ($ai['personality_type']) {
            case 'aggressive':
                return $this->makeAggressiveDecision($ai, $current_stats, $opponent_stats, $available_actions);
            case 'defensive':
                return $this->makeDefensiveDecision($ai, $current_stats, $opponent_stats, $available_actions);
            case 'opportunistic':
                return $this->makeOpportunisticDecision($ai, $current_stats, $opponent_stats, $available_actions);
            case 'cooperative':
                return $this->makeCooperativeDecision($ai, $current_stats, $opponent_stats, $available_actions);
            case 'unpredictable':
                return $this->makeUnpredictableDecision($ai, $current_stats, $opponent_stats, $available_actions);
            default:
                return $this->makeDefaultDecision($available_actions);
        }
    }
    
    private function makeAggressiveDecision($ai, $current_stats, $opponent_stats, $available_actions) {
        // Aggressive AI prioritizes attack and expansion
        $behavior = json_decode($ai['behavior_patterns'], true);
        
        // Look for attack opportunities
        foreach ($available_actions as $action) {
            if ($action['type'] === 'attack' && rand(1, 100) <= ($behavior['aggression'] * 100)) {
                return [
                    'action_type' => 'attack',
                    'target_player_id' => $action['target_player_id'],
                    'target_resource' => $action['target_resource'],
                    'reasoning' => 'Aggressive expansion strategy'
                ];
            }
        }
        
        // If no attack available, expand territory
        foreach ($available_actions as $action) {
            if ($action['type'] === 'build' && $action['subtype'] === 'territory') {
                return [
                    'action_type' => 'build',
                    'target_resource' => $action['target_resource'],
                    'reasoning' => 'Territory expansion for future attacks'
                ];
            }
        }
        
        return $this->makeDefaultDecision($available_actions);
    }
    
    private function makeDefensiveDecision($ai, $current_stats, $opponent_stats, $available_actions) {
        // Defensive AI prioritizes protection and resource building
        $behavior = json_decode($ai['behavior_patterns'], true);
        
        // Check if under threat
        $threat_level = $this->assessThreatLevel($current_stats, $opponent_stats);
        
        if ($threat_level > 0.6) {
            // High threat - defensive actions
            foreach ($available_actions as $action) {
                if ($action['type'] === 'build' && $action['subtype'] === 'defense') {
                    return [
                        'action_type' => 'build',
                        'target_resource' => $action['target_resource'],
                        'reasoning' => 'Defensive fortification due to high threat level'
                    ];
                }
            }
        }
        
        // Medium threat - resource building
        foreach ($available_actions as $action) {
            if ($action['type'] === 'build' && $action['subtype'] === 'economic') {
                return [
                    'action_type' => 'build',
                    'target_resource' => $action['target_resource'],
                    'reasoning' => 'Economic development for long-term stability'
                ];
            }
        }
        
        return $this->makeDefaultDecision($available_actions);
    }
    
    private function makeOpportunisticDecision($ai, $current_stats, $opponent_stats, $available_actions) {
        // Opportunistic AI looks for weak targets and good deals
        $behavior = json_decode($ai['behavior_patterns'], true);
        
        // Look for weakened opponents
        foreach ($opponent_stats as $opponent) {
            if ($opponent['resources'] < $current_stats['resources'] * 0.7) {
                // Found weak opponent - attack if possible
                foreach ($available_actions as $action) {
                    if ($action['type'] === 'attack' && $action['target_player_id'] === $opponent['player_id']) {
                        return [
                            'action_type' => 'attack',
                            'target_player_id' => $action['target_player_id'],
                            'target_resource' => $action['target_resource'],
                            'reasoning' => 'Opportunistic strike against weakened opponent'
                        ];
                    }
                }
            }
        }
        
        // Look for trade opportunities
        foreach ($available_actions as $action) {
            if ($action['type'] === 'trade' && $action['benefit_ratio'] > 1.2) {
                return [
                    'action_type' => 'trade',
                    'target_player_id' => $action['target_player_id'],
                    'action_data' => $action['trade_data'],
                    'reasoning' => 'Profitable trade opportunity identified'
                ];
            }
        }
        
        return $this->makeDefaultDecision($available_actions);
    }
    
    private function makeCooperativeDecision($ai, $current_stats, $opponent_stats, $available_actions) {
        // Cooperative AI prefers trade and mutual benefit
        $behavior = json_decode($ai['behavior_patterns'], true);
        
        // Look for mutually beneficial trades
        foreach ($available_actions as $action) {
            if ($action['type'] === 'trade' && $action['mutual_benefit'] === true) {
                return [
                    'action_type' => 'trade',
                    'target_player_id' => $action['target_player_id'],
                    'action_data' => $action['trade_data'],
                    'reasoning' => 'Mutually beneficial cooperation'
                ];
            }
        }
        
        // Look for negotiation opportunities
        foreach ($available_actions as $action) {
            if ($action['type'] === 'negotiate') {
                return [
                    'action_type' => 'negotiate',
                    'target_player_id' => $action['target_player_id'],
                    'action_data' => $action['negotiation_terms'],
                    'reasoning' => 'Diplomatic resolution preferred'
                ];
            }
        }
        
        return $this->makeDefaultDecision($available_actions);
    }
    
    private function makeUnpredictableDecision($ai, $current_stats, $opponent_stats, $available_actions) {
        // Unpredictable AI makes random decisions with some logic
        $behavior = json_decode($ai['behavior_patterns'], true);
        
        // 30% chance of completely random action
        if (rand(1, 100) <= 30) {
            $random_action = $available_actions[array_rand($available_actions)];
            return [
                'action_type' => $random_action['type'],
                'target_player_id' => $random_action['target_player_id'] ?? null,
                'target_resource' => $random_action['target_resource'] ?? null,
                'action_data' => $random_action['action_data'] ?? null,
                'reasoning' => 'Unpredictable wildcard move'
            ];
        }
        
        // Otherwise use weighted random based on current situation
        $weights = [];
        foreach ($available_actions as $i => $action) {
            switch ($action['type']) {
                case 'attack':
                    $weights[$i] = 0.4;
                    break;
                case 'trade':
                    $weights[$i] = 0.3;
                    break;
                case 'build':
                    $weights[$i] = 0.2;
                    break;
                case 'special_ability':
                    $weights[$i] = 0.6; // Unpredictable AI loves special abilities
                    break;
                default:
                    $weights[$i] = 0.1;
            }
        }
        
        $chosen_index = $this->weightedRandom($weights);
        $chosen_action = $available_actions[$chosen_index];
        
        return [
            'action_type' => $chosen_action['type'],
            'target_player_id' => $chosen_action['target_player_id'] ?? null,
            'target_resource' => $chosen_action['target_resource'] ?? null,
            'action_data' => $chosen_action['action_data'] ?? null,
            'reasoning' => 'Calculated chaos strategy'
        ];
    }
    
    private function makeDefaultDecision($available_actions) {
        if (empty($available_actions)) {
            return [
                'action_type' => 'move',
                'reasoning' => 'No available actions - default move'
            ];
        }
        
        // Simple default: pick first available action
        $action = $available_actions[0];
        return [
            'action_type' => $action['type'],
            'target_player_id' => $action['target_player_id'] ?? null,
            'target_resource' => $action['target_resource'] ?? null,
            'action_data' => $action['action_data'] ?? null,
            'reasoning' => 'Default action selection'
        ];
    }
    
    private function getAIGameStats($session_id, $ai_id) {
        $stmt = $this->pdo->prepare("
            SELECT current_stats, score, status 
            FROM session_players 
            WHERE session_id = ? AND is_ai = 1 
            AND ai_personality = (SELECT name FROM ai_opponents WHERE id = ?)
        ");
        $stmt->execute([$session_id, $ai_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? json_decode($result['current_stats'], true) : [];
    }
    
    private function getOpponentStats($session_id, $ai_id) {
        $stmt = $this->pdo->prepare("
            SELECT user_id, current_stats, score, status 
            FROM session_players 
            WHERE session_id = ? AND (is_ai = 0 OR ai_personality != (SELECT name FROM ai_opponents WHERE id = ?))
        ");
        $stmt->execute([$session_id, $ai_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getAvailableActions($session_id, $ai_id) {
        // This would be implemented based on specific game rules
        // For now, return mock available actions
        return [
            [
                'type' => 'build',
                'subtype' => 'territory',
                'target_resource' => 'new_territory',
                'cost' => 1000
            ],
            [
                'type' => 'attack',
                'target_player_id' => 1, // Example opponent
                'target_resource' => 'territory_1',
                'success_chance' => 0.7
            ],
            [
                'type' => 'trade',
                'target_player_id' => 1,
                'trade_data' => ['offer' => 'money', 'request' => 'territory'],
                'benefit_ratio' => 1.3,
                'mutual_benefit' => true
            ]
        ];
    }
    
    private function assessThreatLevel($current_stats, $opponent_stats) {
        if (empty($opponent_stats)) return 0;
        
        $max_opponent_score = max(array_column($opponent_stats, 'score'));
        $current_score = $current_stats['score'] ?? 0;
        
        if ($current_score == 0) return 1.0; // Maximum threat if we have no score
        
        return min(1.0, $max_opponent_score / $current_score);
    }
    
    private function weightedRandom($weights) {
        $total = array_sum($weights);
        $random = mt_rand(1, $total * 1000) / 1000;
        
        $current = 0;
        foreach ($weights as $index => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $index;
            }
        }
        
        return array_key_last($weights); // Fallback
    }
    
    /**
     * Update AI statistics after game completion
     */
    public function updateAIStats($ai_id, $won = false, $score = 0) {
        $this->pdo->prepare("
            UPDATE ai_opponents 
            SET games_played = games_played + 1,
                win_rate = (win_rate * games_played + ?) / (games_played + 1)
            WHERE id = ?
        ")->execute([$won ? 1 : 0, $ai_id]);
    }
}
?>