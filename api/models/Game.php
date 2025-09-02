<?php

require_once __DIR__ . '/../../config/database.php';

class GamePlayer {
    public $id;
    public $user_id;
    public $tokens;
    public $experience;
    public $level;
    public $reputation;
    public $current_impairment;
    public $created_at;
    public $updated_at;
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function getByUserId($user_id) {
        $db = self::getDB();
        $result = $db->fetchOne(
            "SELECT * FROM game_players WHERE user_id = ?",
            [$user_id]
        );
        
        if (!$result) {
            // Auto-create player if doesn't exist
            return self::create($user_id);
        }
        
        $player = new self();
        foreach ($result as $key => $value) {
            $player->$key = $value;
        }
        
        return $player;
    }
    
    public static function create($user_id) {
        $db = self::getDB();
        
        $stmt = $db->query(
            "INSERT INTO game_players (user_id, level, experience, tokens, reputation, current_impairment, created_at, updated_at) 
             VALUES (?, 1, 0, 100, 100, 0.000, NOW(), NOW())",
            [$user_id]
        );
        
        $player_id = $db->lastInsertId();
        return self::getByUserId($user_id);
    }
    
    public function addTokens($amount) {
        $db = self::getDB();
        $db->execute(
            "UPDATE game_players SET tokens = tokens + ?, updated_at = NOW() WHERE id = ?",
            [$amount, $this->id]
        );
        $this->tokens += $amount;
        
        // Log transaction
        GameTransaction::log($this->id, 'token_purchase', $amount, "Tokens purchased");
    }
    
    public function spendTokens($amount) {
        if ($this->tokens < $amount) {
            return false;
        }
        
        $db = self::getDB();
        $db->execute(
            "UPDATE game_players SET tokens = tokens - ?, updated_at = NOW() WHERE id = ?",
            [$amount, $this->id]
        );
        $this->tokens -= $amount;
        
        return true;
    }
    
    public function addExperience($points) {
        $db = self::getDB();
        $old_level = $this->level;
        
        $this->experience += $points;
        
        // Calculate new level (every 1000 XP = 1 level)
        $new_level = floor($this->experience / 1000) + 1;
        
        $db->execute(
            "UPDATE game_players SET experience = ?, level = ?, updated_at = NOW() WHERE id = ?",
            [$this->experience, $new_level, $this->id]
        );
        
        // Level up rewards
        if ($new_level > $old_level) {
            $this->level = $new_level;
            $token_reward = ($new_level - $old_level) * 50; // 50 tokens per level
            $this->addTokens($token_reward);
            
            return ['leveled_up' => true, 'new_level' => $new_level, 'token_reward' => $token_reward];
        }
        
        return ['leveled_up' => false];
    }
    
    public function updateImpairment($new_level) {
        $db = self::getDB();
        $db->execute(
            "UPDATE game_players SET current_impairment = ?, updated_at = NOW() WHERE id = ?",
            [$new_level, $this->id]
        );
        $this->current_impairment = $new_level;
    }
}

class Strain {
    public $id;
    public $name;
    public $type;
    public $thc_min;
    public $thc_max;
    public $flowering_time_min;
    public $flowering_time_max;
    public $difficulty;
    public $unlock_level;
    public $seed_price;
    public $rarity;
    public $description;
    public $created_at;
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function getAvailableForLevel($level) {
        $db = self::getDB();
        return $db->fetchAll(
            "SELECT * FROM strains WHERE unlock_level <= ? AND is_active = 1 ORDER BY unlock_level, seed_price",
            [$level]
        );
    }
    
    public static function getById($id) {
        $db = self::getDB();
        return $db->fetchOne(
            "SELECT * FROM strains WHERE id = ? AND is_active = 1",
            [$id]
        );
    }
}

class Plant {
    public $id;
    public $player_id;
    public $strain_id;
    public $location_id;
    public $stage;
    public $health;
    public $planted_at;
    public $harvest_ready_at;
    public $final_weight;
    public $final_quality;
    public $created_at;
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function getByPlayerId($player_id) {
        $db = self::getDB();
        return $db->fetchAll(
            "SELECT p.*, s.name as strain_name, s.type as strain_type, l.name as location_name
             FROM plants p
             JOIN strains s ON p.strain_id = s.id
             JOIN growing_locations l ON p.location_id = l.id
             WHERE p.player_id = ?
             ORDER BY p.created_at DESC",
            [$player_id]
        );
    }
    
    public static function create($player_id, $strain_id, $location_id) {
        $db = self::getDB();
        
        // Get strain info for timing
        $strain = Strain::getById($strain_id);
        if (!$strain) {
            throw new Exception("Invalid strain ID");
        }
        
        // Calculate harvest ready time (random within strain's flowering window)
        $flowering_days = rand($strain['flowering_time_min'], $strain['flowering_time_max']);
        $harvest_ready = date('Y-m-d H:i:s', strtotime("+$flowering_days days"));
        
        $db->execute(
            "INSERT INTO plants (player_id, strain_id, location_id, stage, health, harvest_ready_at, created_at, updated_at)
             VALUES (?, ?, ?, 'germination', 1.000, ?, NOW(), NOW())",
            [$player_id, $strain_id, $location_id, $harvest_ready]
        );
        
        return $db->lastInsertId();
    }
    
    public static function getById($id) {
        $db = self::getDB();
        return $db->fetchOne(
            "SELECT * FROM plants WHERE id = ?",
            [$id]
        );
    }
    
    public function harvest() {
        if ($this->stage !== 'harvest_ready') {
            throw new Exception("Plant is not ready for harvest");
        }
        
        $db = self::getDB();
        
        // Get strain data for yield calculation
        $strain = Strain::getById($this->strain_id);
        
        // Calculate final weight and quality with random modifiers
        $base_yield = rand($strain['yield_indoor_min'] * 100, $strain['yield_indoor_max'] * 100) / 100;
        $quality_modifier = rand(80, 120) / 100; // 0.8 to 1.2 multiplier
        $health_modifier = $this->health; // Health affects final yield
        
        $final_weight = $base_yield * $health_modifier * $quality_modifier;
        $final_thc = rand($strain['thc_min'] * 100, $strain['thc_max'] * 100) / 100;
        $final_quality = min(1.0, $this->health * $quality_modifier);
        
        // Update plant
        $db->execute(
            "UPDATE plants SET stage = 'harvested', harvested_at = NOW(), 
                            final_weight = ?, final_thc = ?, final_quality = ?, updated_at = NOW()
             WHERE id = ?",
            [$final_weight, $final_thc, $final_quality, $this->id]
        );
        
        return [
            'weight' => $final_weight,
            'thc' => $final_thc,
            'quality' => $final_quality
        ];
    }
    
    public function isReady() {
        return $this->stage === 'harvest_ready' || 
               ($this->harvest_ready_at && strtotime($this->harvest_ready_at) <= time());
    }
    
    public function getTimeRemaining() {
        if (!$this->harvest_ready_at) {
            return null;
        }
        
        $remaining = strtotime($this->harvest_ready_at) - time();
        return max(0, $remaining);
    }
    
    public function updateStage() {
        // Update plant stage based on time elapsed
        $now = time();
        $planted = strtotime($this->planted_at);
        $harvest_ready = strtotime($this->harvest_ready_at);
        
        $elapsed_ratio = ($now - $planted) / ($harvest_ready - $planted);
        
        $new_stage = 'germination';
        if ($elapsed_ratio >= 1.0) {
            $new_stage = 'harvest_ready';
        } elseif ($elapsed_ratio >= 0.7) {
            $new_stage = 'flowering';
        } elseif ($elapsed_ratio >= 0.3) {
            $new_stage = 'vegetative';
        } elseif ($elapsed_ratio >= 0.1) {
            $new_stage = 'seedling';
        }
        
        if ($new_stage !== $this->stage) {
            $db = self::getDB();
            $db->execute(
                "UPDATE plants SET stage = ?, updated_at = NOW() WHERE id = ?",
                [$new_stage, $this->id]
            );
            $this->stage = $new_stage;
        }
    }
}

class Location {
    public $id;
    public $name;
    public $description;
    public $city;
    public $state;
    public $market_modifier; // affects selling prices
    public $required_level;
    public $required_reputation;
    public $max_plants; // growing slots available
    public $is_unlocked_by_default;
    
    public static function getAvailableForPlayer($player_id) {
        // Get locations available to player
    }
    
    public static function getById($id) {
        // Get location by ID
    }
}

class Sale {
    public $id;
    public $player_id;
    public $plant_id;
    public $location_id;
    public $quantity;
    public $quality;
    public $base_price;
    public $final_price;
    public $experience_gained;
    public $reputation_gained;
    public $sold_at;
    
    public static function create($player_id, $plant_id, $location_id) {
        // Create new sale record
    }
    
    public static function getByPlayerId($player_id) {
        // Get sales history for player
    }
    
    public function calculatePrice($base_price, $quality, $location_modifier) {
        // Calculate final sale price
    }
}

class Achievement {
    public $id;
    public $name;
    public $description;
    public $type; // sales_milestone, level_milestone, strain_collection, etc.
    public $requirement_value;
    public $reward_tokens;
    public $reward_experience;
    public $unlock_location_id;
    
    public static function checkPlayerAchievements($player_id) {
        // Check and award new achievements
    }
}

class PlayerAchievement {
    public $id;
    public $player_id;
    public $achievement_id;
    public $earned_at;
    
    public static function getByPlayerId($player_id) {
        // Get player's earned achievements
    }
}

class GameTransaction {
    public $id;
    public $player_id;
    public $type;
    public $amount;
    public $description;
    public $reference_id;
    public $created_at;
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function log($player_id, $type, $amount, $description, $reference_id = null) {
        // Create a transactions table if using the same pattern as other tables
        // For now, we'll just log to a simple transactions table
        $db = self::getDB();
        
        try {
            $db->execute(
                "INSERT INTO game_transactions (player_id, type, amount, description, reference_id, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$player_id, $type, $amount, $description, $reference_id]
            );
        } catch (Exception $e) {
            // If table doesn't exist, just log to error log for now
            error_log("Game transaction log failed: " . $e->getMessage());
        }
    }
    
    public static function getByPlayerId($player_id, $limit = 50) {
        $db = self::getDB();
        
        try {
            return $db->fetchAll(
                "SELECT * FROM game_transactions WHERE player_id = ? ORDER BY created_at DESC LIMIT ?",
                [$player_id, $limit]
            );
        } catch (Exception $e) {
            return [];
        }
    }
}

class Market {
    public $id;
    public $location_id;
    public $strain_id;
    public $demand_level; // affects prices
    public $supply_level;
    public $price_modifier;
    public $updated_at;
    
    public static function updateMarketConditions() {
        // Update market dynamics
    }
    
    public static function getCurrentPrices($location_id) {
        // Get current market prices for location
    }
}
?>
