<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/WeatherEffectsService.php';

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
        // Update plant stage based on time elapsed with weather effects
        $now = time();
        $planted = strtotime($this->planted_at);
        $harvest_ready = strtotime($this->harvest_ready_at);
        
        // Apply weather effects to growth rate
        $base_growth_rate = ($now - $planted) / ($harvest_ready - $planted);
        $weather_adjusted_growth_rate = WeatherEffectsService::applyWeatherEffectsToPlant($this->id, $base_growth_rate);
        
        $elapsed_ratio = $weather_adjusted_growth_rate;
        
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
        
        // Also check for weather-induced health changes
        $this->updateHealthFromWeather();
    }
    
    public function updateHealthFromWeather() {
        $db = self::getDB();
        $activeWeatherEffects = WeatherEffectsService::getActiveWeatherEffects();
        
        $healthChange = 0;
        foreach ($activeWeatherEffects as $effect) {
            // Different weather affects health differently
            switch ($effect['type']) {
                case 'heat_wave':
                    $healthChange -= 0.02 * $effect['severity_multiplier']; // Heat stress
                    break;
                case 'cold_snap':
                    $healthChange -= 0.03 * $effect['severity_multiplier']; // Cold damage
                    break;
                case 'drought':
                    $healthChange -= 0.04 * $effect['severity_multiplier']; // Dehydration
                    break;
                case 'rain_storm':
                    $healthChange -= 0.01 * $effect['severity_multiplier']; // Potential overwatering
                    break;
                case 'sunny':
                    $healthChange += 0.01 * $effect['severity_multiplier']; // Good conditions
                    break;
            }
        }
        
        if ($healthChange != 0) {
            $newHealth = max(0, min(1.0, $this->health + $healthChange));
            
            $db->execute(
                "UPDATE plants SET health = ? WHERE id = ?",
                [$newHealth, $this->id]
            );
            
            $this->health = $newHealth;
        }
    }
    
    public function waterPlant() {
        $db = self::getDB();
        
        // Base watering benefit
        $healthBoost = 0.05;
        
        // Check weather conditions - some weather makes watering more/less effective
        $activeEffects = WeatherEffectsService::getActiveWeatherEffects();
        foreach ($activeEffects as $effect) {
            switch ($effect['type']) {
                case 'drought':
                case 'heat_wave':
                    $healthBoost *= 1.5; // More effective during dry conditions
                    break;
                case 'rain_storm':
                    $healthBoost *= 0.5; // Less effective during rain
                    break;
            }
        }
        
        $newHealth = min(1.0, $this->health + $healthBoost);
        
        $db->execute(
            "UPDATE plants SET health = ?, updated_at = NOW() WHERE id = ?",
            [$newHealth, $this->id]
        );
        
        $this->health = $newHealth;
        
        return [
            'success' => true,
            'health_boost' => $healthBoost,
            'new_health' => $newHealth
        ];
    }
    
    public function getWeatherImpactSummary() {
        $db = self::getDB();
        
        // Get weather impacts for this plant
        $impacts = $db->fetchAll(
            "SELECT * FROM weather_plant_impacts 
             WHERE plant_id = ? 
             ORDER BY recorded_at DESC 
             LIMIT 10",
            [$this->id]
        );
        
        $totalGrowthImpact = 0;
        $totalYieldImpact = 0;
        $diseaseRisk = 0;
        
        foreach ($impacts as $impact) {
            $totalGrowthImpact += $impact['growth_modifier_applied'] - 1.0;
            $totalYieldImpact += $impact['yield_modifier_applied'] - 1.0;
            $diseaseRisk += $impact['disease_risk_applied'];
        }
        
        return [
            'growth_impact' => $totalGrowthImpact,
            'yield_impact' => $totalYieldImpact,
            'disease_risk' => $diseaseRisk,
            'recent_impacts' => $impacts
        ];
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
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function getAvailableForPlayer($player_id) {
        $db = self::getDB();
        
        // Get player info to check level and reputation
        $player = GamePlayer::getByUserId($player_id);
        if (!$player) {
            return [];
        }
        
        return $db->fetchAll(
            "SELECT * FROM growing_locations 
             WHERE (required_level <= ? OR required_level IS NULL) 
             AND (required_reputation <= ? OR required_reputation IS NULL)
             AND is_active = 1
             ORDER BY required_level, name",
            [$player->level, $player->reputation]
        );
    }
    
    public static function getById($id) {
        $db = self::getDB();
        return $db->fetchOne(
            "SELECT * FROM growing_locations WHERE id = ? AND is_active = 1",
            [$id]
        );
    }
    
    public static function getAll() {
        $db = self::getDB();
        return $db->fetchAll(
            "SELECT * FROM growing_locations WHERE is_active = 1 ORDER BY required_level, name"
        );
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
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function create($player_id, $plant_id, $location_id) {
        $db = self::getDB();
        
        // Get plant and location data
        $plant = Plant::getById($plant_id);
        $location = Location::getById($location_id);
        
        if (!$plant || !$location) {
            throw new Exception("Invalid plant or location");
        }
        
        // Calculate pricing
        $base_price = 15.0; // Base price per gram
        $location_modifier = $location['market_modifier'] ?? 1.0;
        $quality_modifier = $plant['final_quality'] ?? 0.8;
        
        $final_price = $base_price * $plant['final_weight'] * $location_modifier * $quality_modifier;
        
        // Calculate rewards
        $experience_gained = floor($final_price * 0.1); // 10% of sale price
        $reputation_gained = rand(1, 5);
        
        // Create sale record
        $db->execute(
            "INSERT INTO sales (player_id, plant_id, location_id, quantity, quality, 
                               base_price, final_price, experience_gained, reputation_gained, sold_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$player_id, $plant_id, $location_id, $plant['final_weight'], $plant['final_quality'],
             $base_price, $final_price, $experience_gained, $reputation_gained]
        );
        
        $sale_id = $db->lastInsertId();
        
        // Update player stats
        $player = GamePlayer::getByUserId($player_id);
        $player->addTokens(floor($final_price));
        $player->addExperience($experience_gained);
        
        // Log transaction
        GameTransaction::log($player->id, 'plant_sale', $final_price, 
                           "Sold plant for " . number_format($final_price, 2) . " tokens", $sale_id);
        
        return $sale_id;
    }
    
    public static function getByPlayerId($player_id) {
        $db = self::getDB();
        return $db->fetchAll(
            "SELECT s.*, p.strain_id, st.name as strain_name, l.name as location_name
             FROM sales s
             JOIN plants p ON s.plant_id = p.id
             JOIN strains st ON p.strain_id = st.id
             JOIN growing_locations l ON s.location_id = l.id
             WHERE s.player_id = ?
             ORDER BY s.sold_at DESC
             LIMIT 50",
            [$player_id]
        );
    }
    
    public static function getById($id) {
        $db = self::getDB();
        return $db->fetchOne("SELECT * FROM sales WHERE id = ?", [$id]);
    }
    
    public function calculatePrice($base_price, $quality, $location_modifier) {
        return $base_price * $quality * $location_modifier;
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
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function getAll() {
        $db = self::getDB();
        return $db->fetchAll(
            "SELECT * FROM achievements WHERE is_active = 1 ORDER BY requirement_value"
        );
    }
    
    public static function checkPlayerAchievements($player_id) {
        $db = self::getDB();
        $player = GamePlayer::getByUserId($player_id);
        
        if (!$player) {
            return [];
        }
        
        $new_achievements = [];
        
        // Get all achievements not yet earned by player
        $achievements = $db->fetchAll(
            "SELECT a.* FROM achievements a
             WHERE a.is_active = 1 
             AND a.id NOT IN (
                 SELECT achievement_id FROM player_achievements 
                 WHERE player_id = ?
             )",
            [$player->id]
        );
        
        foreach ($achievements as $achievement) {
            $earned = false;
            
            switch ($achievement['type']) {
                case 'level_milestone':
                    $earned = $player->level >= $achievement['requirement_value'];
                    break;
                    
                case 'sales_milestone':
                    $total_sales = $db->fetchColumn(
                        "SELECT COUNT(*) FROM sales WHERE player_id = ?",
                        [$player->id]
                    );
                    $earned = $total_sales >= $achievement['requirement_value'];
                    break;
                    
                case 'tokens_earned':
                    $total_tokens = $db->fetchColumn(
                        "SELECT COALESCE(SUM(amount), 0) FROM game_transactions 
                         WHERE player_id = ? AND type IN ('plant_sale', 'token_purchase')",
                        [$player->id]
                    );
                    $earned = $total_tokens >= $achievement['requirement_value'];
                    break;
                    
                case 'strain_collection':
                    $strains_grown = $db->fetchColumn(
                        "SELECT COUNT(DISTINCT strain_id) FROM plants WHERE player_id = ?",
                        [$player->id]
                    );
                    $earned = $strains_grown >= $achievement['requirement_value'];
                    break;
            }
            
            if ($earned) {
                // Award achievement
                PlayerAchievement::create($player->id, $achievement['id']);
                
                // Award rewards
                if ($achievement['reward_tokens'] > 0) {
                    $player->addTokens($achievement['reward_tokens']);
                }
                if ($achievement['reward_experience'] > 0) {
                    $player->addExperience($achievement['reward_experience']);
                }
                
                $new_achievements[] = $achievement;
            }
        }
        
        return $new_achievements;
    }
}

class PlayerAchievement {
    public $id;
    public $player_id;
    public $achievement_id;
    public $earned_at;
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function create($player_id, $achievement_id) {
        $db = self::getDB();
        
        // Check if already earned
        $existing = $db->fetchOne(
            "SELECT id FROM player_achievements WHERE player_id = ? AND achievement_id = ?",
            [$player_id, $achievement_id]
        );
        
        if ($existing) {
            return $existing['id'];
        }
        
        $db->execute(
            "INSERT INTO player_achievements (player_id, achievement_id, earned_at, created_at)
             VALUES (?, ?, NOW(), NOW())",
            [$player_id, $achievement_id]
        );
        
        return $db->lastInsertId();
    }
    
    public static function getByPlayerId($player_id) {
        $db = self::getDB();
        return $db->fetchAll(
            "SELECT pa.*, a.name, a.description, a.reward_tokens, a.reward_experience
             FROM player_achievements pa
             JOIN achievements a ON pa.achievement_id = a.id
             WHERE pa.player_id = ?
             ORDER BY pa.earned_at DESC",
            [$player_id]
        );
    }
    
    public static function hasAchievement($player_id, $achievement_id) {
        $db = self::getDB();
        $result = $db->fetchOne(
            "SELECT id FROM player_achievements WHERE player_id = ? AND achievement_id = ?",
            [$player_id, $achievement_id]
        );
        return !empty($result);
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
    
    private static function getDB() {
        return DB::getInstance();
    }
    
    public static function updateMarketConditions() {
        $db = self::getDB();
        
        // Update market conditions daily
        $locations = Location::getAll();
        $strains = Strain::getAvailableForLevel(100); // Get all strains
        
        foreach ($locations as $location) {
            foreach ($strains as $strain) {
                // Generate random market fluctuations
                $demand_level = rand(1, 100) / 100; // 0.01 to 1.00
                $supply_level = rand(1, 100) / 100;
                
                // Calculate price modifier based on supply/demand
                $price_modifier = ($demand_level / $supply_level) * (rand(80, 120) / 100);
                $price_modifier = max(0.5, min(2.0, $price_modifier)); // Clamp between 0.5x and 2.0x
                
                // Update or insert market condition
                $existing = $db->fetchOne(
                    "SELECT id FROM market_conditions WHERE location_id = ? AND strain_id = ?",
                    [$location['id'], $strain['id']]
                );
                
                if ($existing) {
                    $db->execute(
                        "UPDATE market_conditions SET demand_level = ?, supply_level = ?, 
                         price_modifier = ?, updated_at = NOW() WHERE id = ?",
                        [$demand_level, $supply_level, $price_modifier, $existing['id']]
                    );
                } else {
                    $db->execute(
                        "INSERT INTO market_conditions (location_id, strain_id, demand_level, 
                         supply_level, price_modifier, updated_at, created_at) 
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$location['id'], $strain['id'], $demand_level, $supply_level, $price_modifier]
                    );
                }
            }
        }
    }
    
    public static function getCurrentPrices($location_id) {
        $db = self::getDB();
        return $db->fetchAll(
            "SELECT mc.*, s.name as strain_name, s.type as strain_type
             FROM market_conditions mc
             JOIN strains s ON mc.strain_id = s.id
             WHERE mc.location_id = ?
             ORDER BY s.name",
            [$location_id]
        );
    }
    
    public static function getPriceModifier($location_id, $strain_id) {
        $db = self::getDB();
        $result = $db->fetchOne(
            "SELECT price_modifier FROM market_conditions 
             WHERE location_id = ? AND strain_id = ?",
            [$location_id, $strain_id]
        );
        
        return $result ? $result['price_modifier'] : 1.0;
    }
    
    public static function getTopDemandLocations($limit = 5) {
        $db = self::getDB();
        return $db->fetchAll(
            "SELECT l.name as location_name, AVG(mc.price_modifier) as avg_price_modifier,
                    AVG(mc.demand_level) as avg_demand
             FROM market_conditions mc
             JOIN growing_locations l ON mc.location_id = l.id
             GROUP BY mc.location_id, l.name
             ORDER BY avg_price_modifier DESC
             LIMIT ?",
            [$limit]
        );
    }
}
?>
