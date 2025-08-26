<?php

class GamePlayer {
    public $id;
    public $user_id;
    public $tokens;
    public $experience_points;
    public $level;
    public $reputation;
    public $unlocked_locations;
    public $created_at;
    public $updated_at;
    
    public static function getByUserId($user_id) {
        // Get player data by user ID
    }
    
    public static function create($user_id) {
        // Create new player with starting values
    }
    
    public function addTokens($amount) {
        // Add tokens to player account
    }
    
    public function spendTokens($amount) {
        // Spend tokens if sufficient balance
    }
    
    public function addExperience($points) {
        // Add experience and check for level up
    }
    
    public function unlockLocation($location_id) {
        // Unlock new selling location
    }
}

class Strain {
    public $id;
    public $name;
    public $description;
    public $rarity; // common, uncommon, rare, legendary
    public $base_yield;
    public $base_quality;
    public $base_price;
    public $growth_time; // in hours
    public $required_level;
    public $seed_cost;
    public $created_at;
    
    public static function getAvailableForLevel($level) {
        // Get strains available for player level
    }
    
    public static function getById($id) {
        // Get strain by ID
    }
}

class Plant {
    public $id;
    public $player_id;
    public $strain_id;
    public $planted_at;
    public $harvest_ready_at;
    public $status; // growing, ready, harvested, dead
    public $quality_modifier; // random factor affecting final quality
    public $yield_modifier; // random factor affecting final yield
    public $location_id;
    
    public static function getByPlayerId($player_id) {
        // Get all plants for a player
    }
    
    public function plant($player_id, $strain_id, $location_id) {
        // Plant a new seed
    }
    
    public function harvest() {
        // Harvest ready plant
    }
    
    public function isReady() {
        // Check if plant is ready for harvest
    }
    
    public function getTimeRemaining() {
        // Get time until harvest ready
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
    public $type; // token_purchase, seed_purchase, sale, achievement_reward
    public $amount;
    public $description;
    public $reference_id; // ID of related entity (strain, sale, etc.)
    public $created_at;
    
    public static function log($player_id, $type, $amount, $description, $reference_id = null) {
        // Log game transaction
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
