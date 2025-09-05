<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Enhanced Gaming System with Advanced Features
 * 
 * Includes multiplayer features, advanced strain genetics, weather effects,
 * market dynamics, social features, and real-time interactions
 */
class EnhancedGamingSystem extends Model
{
    protected $table = 'gaming_sessions';
    
    protected $fillable = [
        'user_id', 'session_data', 'multiplayer_room_id', 'genetics_data',
        'weather_effects', 'market_conditions', 'social_interactions'
    ];

    protected $casts = [
        'session_data' => 'array',
        'genetics_data' => 'array',
        'weather_effects' => 'array',
        'market_conditions' => 'array',
        'social_interactions' => 'array'
    ];

    // Advanced Strain Genetics System
    public static function generateAdvancedGenetics($parentStrains = [], $crossbreedingLevel = 1)
    {
        $baseGenetics = [
            'thc_range' => [15, 25],
            'cbd_range' => [0.1, 2.0],
            'terpene_profile' => [],
            'flowering_time' => [8, 12], // weeks
            'yield_multiplier' => 1.0,
            'disease_resistance' => 0.5,
            'environmental_adaptation' => 0.5,
            'potency_stability' => 0.7,
            'bag_appeal' => 0.6
        ];

        // Advanced genetic traits
        $advancedTraits = [
            'stress_tolerance' => rand(30, 90) / 100,
            'nutrient_efficiency' => rand(40, 95) / 100,
            'harvest_window' => rand(7, 21), // days flexibility
            'trichome_density' => rand(50, 100) / 100,
            'aroma_intensity' => rand(30, 100) / 100,
            'color_expression' => self::generateColorGenetics(),
            'cannabinoid_diversity' => self::generateCannabinoidProfile(),
            'growth_pattern' => self::getRandomGrowthPattern(),
            'climate_preference' => self::getRandomClimatePreference()
        ];

        // Crossbreeding effects
        if (!empty($parentStrains)) {
            $advancedTraits = self::applyCrossbreedingEffects($parentStrains, $advancedTraits, $crossbreedingLevel);
        }

        return array_merge($baseGenetics, $advancedTraits);
    }

    private static function generateColorGenetics()
    {
        $colors = ['green', 'purple', 'orange', 'red', 'yellow', 'pink'];
        $expressions = ['recessive', 'dominant', 'co-dominant'];
        
        return [
            'primary_color' => $colors[array_rand($colors)],
            'secondary_color' => $colors[array_rand($colors)],
            'expression_type' => $expressions[array_rand($expressions)],
            'intensity' => rand(20, 100) / 100
        ];
    }

    private static function generateCannabinoidProfile()
    {
        return [
            'thc_dominant' => rand(0, 1),
            'cbd_ratio' => rand(1, 20),
            'minor_cannabinoids' => [
                'cbg' => rand(0, 300) / 100,
                'cbc' => rand(0, 150) / 100,
                'cbn' => rand(0, 100) / 100,
                'thcv' => rand(0, 200) / 100
            ],
            'entourage_effect' => rand(50, 100) / 100
        ];
    }

    private static function getRandomGrowthPattern()
    {
        $patterns = ['indica', 'sativa', 'hybrid', 'autoflower', 'ruderalis'];
        return $patterns[array_rand($patterns)];
    }

    private static function getRandomClimatePreference()
    {
        return [
            'temperature_range' => [rand(65, 75), rand(78, 85)], // F
            'humidity_preference' => rand(40, 60), // %
            'light_intensity' => rand(600, 1200), // PPFD
            'co2_responsiveness' => rand(50, 100) / 100
        ];
    }

    private static function applyCrossbreedingEffects($parentStrains, $traits, $level)
    {
        // Advanced genetic inheritance algorithms
        foreach ($parentStrains as $parent) {
            $inheritanceRate = 0.4 + ($level * 0.1); // Higher level = more inheritance
            
            foreach ($traits as $trait => $value) {
                if (isset($parent['genetics'][$trait])) {
                    $parentValue = $parent['genetics'][$trait];
                    
                    if (is_numeric($value) && is_numeric($parentValue)) {
                        $traits[$trait] = ($value * (1 - $inheritanceRate)) + ($parentValue * $inheritanceRate);
                    }
                }
            }
        }

        // Add hybrid vigor effects
        if (count($parentStrains) >= 2) {
            $traits['hybrid_vigor'] = rand(5, 25) / 100; // 5-25% boost
            $traits['yield_multiplier'] *= (1 + $traits['hybrid_vigor']);
        }

        return $traits;
    }

    // Weather Effects System
    public static function applyWeatherEffects($growthStage, $weatherData, $strainGenetics)
    {
        $effects = [
            'growth_rate_modifier' => 1.0,
            'quality_impact' => 0.0,
            'disease_risk' => 0.0,
            'stress_level' => 0.0,
            'watering_needs' => 1.0
        ];

        $temperature = $weatherData['temperature'] ?? 75;
        $humidity = $weatherData['humidity'] ?? 50;
        $pressure = $weatherData['pressure'] ?? 1013;
        $windSpeed = $weatherData['wind_speed'] ?? 5;

        // Temperature effects
        $optimalTemp = ($strainGenetics['climate_preference']['temperature_range'][0] + 
                       $strainGenetics['climate_preference']['temperature_range'][1]) / 2;
        
        $tempDeviation = abs($temperature - $optimalTemp);
        
        if ($tempDeviation > 10) {
            $effects['stress_level'] += $tempDeviation * 0.02;
            $effects['growth_rate_modifier'] *= max(0.5, 1 - ($tempDeviation * 0.015));
        }

        // Humidity effects
        $optimalHumidity = $strainGenetics['climate_preference']['humidity_preference'];
        $humidityDeviation = abs($humidity - $optimalHumidity);
        
        if ($humidity > 70) {
            $effects['disease_risk'] += ($humidity - 70) * 0.02;
        }
        
        if ($humidityDeviation > 15) {
            $effects['stress_level'] += $humidityDeviation * 0.01;
        }

        // Barometric pressure effects (advanced)
        if ($pressure < 1000) {
            $effects['stress_level'] += 0.1;
        } elseif ($pressure > 1025) {
            $effects['growth_rate_modifier'] *= 1.05;
        }

        // Wind effects for outdoor grows
        if ($windSpeed > 15) {
            $effects['stress_level'] += 0.15;
            $effects['watering_needs'] *= 1.3;
        }

        // Growth stage specific effects
        switch ($growthStage) {
            case 'seedling':
                $effects['growth_rate_modifier'] *= (1 - $effects['stress_level'] * 2);
                break;
            case 'vegetative':
                $effects['growth_rate_modifier'] *= (1 - $effects['stress_level'] * 1.5);
                break;
            case 'flowering':
                $effects['quality_impact'] = $effects['stress_level'] * -0.3;
                break;
            case 'harvest':
                if ($humidity > 60) {
                    $effects['quality_impact'] -= 0.2;
                }
                break;
        }

        return $effects;
    }

    // Dynamic Market System
    public static function calculateDynamicMarketPrices($strainType, $quality, $marketFactors = [])
    {
        $basePrice = self::getBaseStrainPrice($strainType);
        
        $marketModifiers = [
            'supply_demand_ratio' => $marketFactors['supply_demand'] ?? 1.0,
            'seasonal_factor' => self::getSeasonalFactor(),
            'quality_multiplier' => self::getQualityMultiplier($quality),
            'rarity_bonus' => $marketFactors['rarity'] ?? 1.0,
            'market_volatility' => rand(85, 115) / 100, // ±15% random fluctuation
            'regulatory_impact' => $marketFactors['regulatory'] ?? 1.0,
            'competition_factor' => $marketFactors['competition'] ?? 1.0
        ];

        // Advanced market dynamics
        $trendingStrains = Cache::remember('trending_strains', 3600, function () {
            return DB::table('market_transactions')
                ->select('strain_type', DB::raw('COUNT(*) as popularity'))
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('strain_type')
                ->orderBy('popularity', 'desc')
                ->take(5)
                ->pluck('strain_type')
                ->toArray();
        });

        if (in_array($strainType, $trendingStrains)) {
            $marketModifiers['trending_bonus'] = 1.15;
        }

        // Economic factors
        $economicFactors = [
            'inflation_rate' => 1.03, // 3% annual inflation
            'local_economy' => $marketFactors['local_economy'] ?? 1.0,
            'tourism_impact' => $marketFactors['tourism'] ?? 1.0,
            'event_premium' => self::getEventPremium()
        ];

        $finalPrice = $basePrice;
        
        // Apply all modifiers
        foreach (array_merge($marketModifiers, $economicFactors) as $modifier) {
            $finalPrice *= $modifier;
        }

        // Price floor and ceiling
        $minPrice = $basePrice * 0.3;
        $maxPrice = $basePrice * 3.0;
        
        return [
            'current_price' => max($minPrice, min($maxPrice, $finalPrice)),
            'base_price' => $basePrice,
            'price_factors' => array_merge($marketModifiers, $economicFactors),
            'market_trend' => self::getMarketTrend($strainType),
            'price_prediction' => self::predictPriceMovement($strainType, $finalPrice),
            'volatility_index' => self::calculateVolatilityIndex($strainType)
        ];
    }

    private static function getBaseStrainPrice($strainType)
    {
        $basePrices = [
            'Northern Lights' => 12,
            'Sour Diesel' => 18,
            'Girl Scout Cookies' => 25,
            'White Widow' => 15,
            'OG Kush' => 28,
            'Blue Dream' => 22,
            'Wedding Cake' => 32,
            'Gorilla Glue #4' => 26,
            'Purple Haze' => 20,
            'Jack Herer' => 24
        ];

        return $basePrices[$strainType] ?? 20;
    }

    private static function getSeasonalFactor()
    {
        $month = date('n');
        
        // Summer outdoor harvest season = lower prices
        // Winter = higher prices due to indoor costs
        $seasonalMultipliers = [
            1 => 1.15,  // January
            2 => 1.12,  // February
            3 => 1.08,  // March
            4 => 1.05,  // April
            5 => 1.02,  // May
            6 => 0.98,  // June
            7 => 0.95,  // July
            8 => 0.92,  // August
            9 => 0.90,  // September
            10 => 0.95, // October
            11 => 1.05, // November
            12 => 1.10  // December
        ];

        return $seasonalMultipliers[$month];
    }

    private static function getQualityMultiplier($quality)
    {
        // Quality from 0-100
        if ($quality >= 95) return 2.5;
        if ($quality >= 90) return 2.0;
        if ($quality >= 85) return 1.7;
        if ($quality >= 80) return 1.5;
        if ($quality >= 75) return 1.3;
        if ($quality >= 70) return 1.1;
        if ($quality >= 60) return 1.0;
        if ($quality >= 50) return 0.8;
        return 0.6;
    }

    private static function getEventPremium()
    {
        $events = [
            '04-20' => 1.5,  // 4/20
            '07-04' => 1.2,  // July 4th
            '12-31' => 1.3,  // New Year's Eve
        ];

        $today = date('m-d');
        return $events[$today] ?? 1.0;
    }

    private static function getMarketTrend($strainType)
    {
        // Simulate market trends based on recent transactions
        $recentSales = DB::table('market_transactions')
            ->where('strain_type', $strainType)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->pluck('sale_price')
            ->toArray();

        if (count($recentSales) < 5) {
            return ['trend' => 'stable', 'change' => 0];
        }

        $recent = array_slice($recentSales, 0, 5);
        $older = array_slice($recentSales, -5);

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);

        $percentChange = (($recentAvg - $olderAvg) / $olderAvg) * 100;

        if ($percentChange > 5) {
            return ['trend' => 'rising', 'change' => round($percentChange, 2)];
        } elseif ($percentChange < -5) {
            return ['trend' => 'falling', 'change' => round($percentChange, 2)];
        }

        return ['trend' => 'stable', 'change' => round($percentChange, 2)];
    }

    private static function predictPriceMovement($strainType, $currentPrice)
    {
        // Simple prediction based on historical data and market factors
        $prediction = [
            '1_day' => $currentPrice * (rand(98, 102) / 100),
            '1_week' => $currentPrice * (rand(95, 105) / 100),
            '1_month' => $currentPrice * (rand(90, 110) / 100)
        ];

        return $prediction;
    }

    private static function calculateVolatilityIndex($strainType)
    {
        $prices = DB::table('market_transactions')
            ->where('strain_type', $strainType)
            ->where('created_at', '>=', now()->subDays(30))
            ->pluck('sale_price')
            ->toArray();

        if (count($prices) < 5) return 0.5; // Default moderate volatility

        $mean = array_sum($prices) / count($prices);
        $variance = array_sum(array_map(function($x) use ($mean) { 
            return pow($x - $mean, 2); 
        }, $prices)) / count($prices);
        
        $standardDeviation = sqrt($variance);
        
        // Normalize to 0-1 scale
        return min(1.0, $standardDeviation / $mean);
    }

    // Multiplayer Features
    public static function createMultiplayerRoom($roomName, $creatorId, $settings = [])
    {
        $defaultSettings = [
            'max_players' => 8,
            'room_type' => 'cooperative', // competitive, cooperative, educational
            'game_mode' => 'standard', // speed_grow, market_master, genetics_lab
            'difficulty' => 'normal',
            'real_time_events' => true,
            'chat_enabled' => true,
            'trade_enabled' => true,
            'weather_sync' => true,
            'market_sync' => true
        ];

        $roomSettings = array_merge($defaultSettings, $settings);

        $roomId = DB::table('multiplayer_rooms')->insertGetId([
            'room_name' => $roomName,
            'creator_id' => $creatorId,
            'settings' => json_encode($roomSettings),
            'status' => 'waiting',
            'current_players' => 1,
            'max_players' => $roomSettings['max_players'],
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Add creator as first player
        DB::table('multiplayer_participants')->insert([
            'room_id' => $roomId,
            'user_id' => $creatorId,
            'role' => 'host',
            'status' => 'active',
            'joined_at' => now()
        ]);

        return [
            'room_id' => $roomId,
            'room_code' => self::generateRoomCode($roomId),
            'settings' => $roomSettings
        ];
    }

    public static function joinMultiplayerRoom($roomId, $userId)
    {
        $room = DB::table('multiplayer_rooms')->find($roomId);
        
        if (!$room || $room->status !== 'waiting') {
            throw new Exception('Room not available');
        }

        if ($room->current_players >= $room->max_players) {
            throw new Exception('Room is full');
        }

        DB::table('multiplayer_participants')->insert([
            'room_id' => $roomId,
            'user_id' => $userId,
            'role' => 'player',
            'status' => 'active',
            'joined_at' => now()
        ]);

        DB::table('multiplayer_rooms')
            ->where('id', $roomId)
            ->increment('current_players');

        return [
            'success' => true,
            'room_info' => self::getRoomInfo($roomId)
        ];
    }

    public static function processMultiplayerAction($roomId, $userId, $action, $data = [])
    {
        $validActions = [
            'trade_offer', 'trade_accept', 'trade_decline',
            'share_genetics', 'request_help', 'send_gift',
            'challenge_create', 'challenge_accept',
            'chat_message', 'reaction'
        ];

        if (!in_array($action, $validActions)) {
            throw new Exception('Invalid action');
        }

        $actionData = [
            'room_id' => $roomId,
            'user_id' => $userId,
            'action_type' => $action,
            'action_data' => json_encode($data),
            'timestamp' => now()
        ];

        DB::table('multiplayer_actions')->insert($actionData);

        // Process specific actions
        switch ($action) {
            case 'trade_offer':
                return self::processTradeOffer($roomId, $userId, $data);
            case 'share_genetics':
                return self::processGeneticsShare($roomId, $userId, $data);
            case 'challenge_create':
                return self::processChallenge($roomId, $userId, $data);
            default:
                return ['success' => true];
        }
    }

    private static function processTradeOffer($roomId, $userId, $data)
    {
        $tradeId = DB::table('multiplayer_trades')->insertGetId([
            'room_id' => $roomId,
            'initiator_id' => $userId,
            'target_id' => $data['target_user_id'],
            'offered_items' => json_encode($data['offered_items']),
            'requested_items' => json_encode($data['requested_items']),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now()
        ]);

        return [
            'success' => true,
            'trade_id' => $tradeId,
            'expires_in' => 900 // 15 minutes in seconds
        ];
    }

    private static function processGeneticsShare($roomId, $userId, $data)
    {
        DB::table('shared_genetics')->insert([
            'room_id' => $roomId,
            'sharer_id' => $userId,
            'genetics_data' => json_encode($data['genetics']),
            'strain_name' => $data['strain_name'],
            'access_level' => $data['access_level'] ?? 'view_only',
            'expires_at' => now()->addDays(7),
            'created_at' => now()
        ]);

        return ['success' => true, 'shared_genetics_id' => DB::getPdo()->lastInsertId()];
    }

    private static function processChallenge($roomId, $userId, $data)
    {
        $challengeTypes = [
            'fastest_harvest', 'highest_yield', 'best_quality',
            'most_profit', 'genetic_diversity', 'efficiency_master'
        ];

        if (!in_array($data['type'], $challengeTypes)) {
            throw new Exception('Invalid challenge type');
        }

        $challengeId = DB::table('multiplayer_challenges')->insertGetId([
            'room_id' => $roomId,
            'creator_id' => $userId,
            'challenge_type' => $data['type'],
            'parameters' => json_encode($data['parameters'] ?? []),
            'duration_hours' => $data['duration'] ?? 24,
            'max_participants' => $data['max_participants'] ?? 4,
            'prize_pool' => $data['prize_pool'] ?? 0,
            'status' => 'open',
            'starts_at' => now()->addMinutes(5),
            'ends_at' => now()->addHours($data['duration'] ?? 24),
            'created_at' => now()
        ]);

        return [
            'success' => true,
            'challenge_id' => $challengeId,
            'starts_in' => 300 // 5 minutes
        ];
    }

    // Social Features
    public static function processPlayerInteraction($playerId, $targetId, $interactionType, $data = [])
    {
        $validInteractions = [
            'friend_request', 'mentor_request', 'collaboration_invite',
            'knowledge_share', 'endorsement', 'review'
        ];

        if (!in_array($interactionType, $validInteractions)) {
            throw new Exception('Invalid interaction type');
        }

        $interactionId = DB::table('player_interactions')->insertGetId([
            'initiator_id' => $playerId,
            'target_id' => $targetId,
            'interaction_type' => $interactionType,
            'data' => json_encode($data),
            'status' => 'pending',
            'created_at' => now()
        ]);

        // Process specific interactions
        switch ($interactionType) {
            case 'friend_request':
                return self::processFriendRequest($playerId, $targetId);
            case 'mentor_request':
                return self::processMentorRequest($playerId, $targetId, $data);
            case 'endorsement':
                return self::processEndorsement($playerId, $targetId, $data);
            default:
                return ['success' => true, 'interaction_id' => $interactionId];
        }
    }

    private static function processFriendRequest($playerId, $targetId)
    {
        // Check if already friends or pending request exists
        $existing = DB::table('friendships')
            ->where(function($q) use ($playerId, $targetId) {
                $q->where('user_id', $playerId)->where('friend_id', $targetId);
            })
            ->orWhere(function($q) use ($playerId, $targetId) {
                $q->where('user_id', $targetId)->where('friend_id', $playerId);
            })
            ->first();

        if ($existing) {
            return ['success' => false, 'message' => 'Friendship already exists or pending'];
        }

        DB::table('friendships')->insert([
            'user_id' => $playerId,
            'friend_id' => $targetId,
            'status' => 'pending',
            'requested_at' => now()
        ]);

        return ['success' => true, 'message' => 'Friend request sent'];
    }

    private static function processMentorRequest($playerId, $targetId, $data)
    {
        // Check mentor qualifications
        $mentorStats = DB::table('player_stats')
            ->where('user_id', $targetId)
            ->first();

        if (!$mentorStats || $mentorStats->level < 10) {
            return ['success' => false, 'message' => 'Target player not qualified as mentor'];
        }

        DB::table('mentorship_requests')->insert([
            'student_id' => $playerId,
            'mentor_id' => $targetId,
            'focus_areas' => json_encode($data['focus_areas'] ?? []),
            'message' => $data['message'] ?? '',
            'status' => 'pending',
            'created_at' => now()
        ]);

        return ['success' => true, 'message' => 'Mentorship request sent'];
    }

    private static function processEndorsement($playerId, $targetId, $data)
    {
        $endorsementTypes = ['helpful', 'knowledgeable', 'trustworthy', 'innovative', 'collaborative'];
        
        if (!in_array($data['type'], $endorsementTypes)) {
            return ['success' => false, 'message' => 'Invalid endorsement type'];
        }

        DB::table('endorsements')->insert([
            'endorser_id' => $playerId,
            'endorsed_id' => $targetId,
            'endorsement_type' => $data['type'],
            'comment' => $data['comment'] ?? '',
            'created_at' => now()
        ]);

        // Update target player's reputation
        DB::table('player_reputation')
            ->where('user_id', $targetId)
            ->increment($data['type'] . '_count');

        return ['success' => true, 'message' => 'Endorsement submitted'];
    }

    // Real-time Growth Simulation with Advanced Features
    public static function simulateAdvancedGrowth($plantId, $hoursElapsed, $conditions = [])
    {
        $plant = DB::table('plants')->find($plantId);
        if (!$plant) throw new Exception('Plant not found');

        $genetics = json_decode($plant->genetics, true);
        $currentStage = $plant->growth_stage;
        $currentHealth = $plant->health;
        
        // Get environmental conditions
        $environment = array_merge([
            'temperature' => 75,
            'humidity' => 55,
            'light_hours' => 18,
            'co2_ppm' => 400,
            'nutrients' => 'balanced',
            'ph_level' => 6.5,
            'air_circulation' => 'good'
        ], $conditions);

        // Advanced growth calculations
        $growthFactors = self::calculateAdvancedGrowthFactors($genetics, $environment, $currentStage);
        
        $baseGrowthRate = self::getBaseGrowthRate($currentStage);
        $actualGrowthRate = $baseGrowthRate * $growthFactors['overall_modifier'];
        
        $newGrowthPoints = $plant->growth_points + ($actualGrowthRate * $hoursElapsed);
        $newHealth = max(0, min(100, $currentHealth + $growthFactors['health_change'] * $hoursElapsed));
        
        // Stage progression
        $newStage = self::determineGrowthStage($newGrowthPoints, $currentStage);
        
        // Advanced effects
        $stressLevel = self::calculateStressLevel($genetics, $environment, $newHealth);
        $diseaseRisk = self::calculateDiseaseRisk($environment, $stressLevel, $genetics);
        $potencyDevelopment = self::calculatePotencyDevelopment($newGrowthPoints, $genetics, $stressLevel);
        
        // Update plant
        DB::table('plants')->where('id', $plantId)->update([
            'growth_points' => $newGrowthPoints,
            'health' => $newHealth,
            'growth_stage' => $newStage,
            'stress_level' => $stressLevel,
            'disease_risk' => $diseaseRisk,
            'potency_development' => json_encode($potencyDevelopment),
            'last_update' => now()
        ]);

        return [
            'plant_id' => $plantId,
            'growth_progress' => $newGrowthPoints,
            'health' => $newHealth,
            'stage' => $newStage,
            'stress_level' => $stressLevel,
            'disease_risk' => $diseaseRisk,
            'potency_preview' => $potencyDevelopment,
            'growth_factors' => $growthFactors,
            'recommendations' => self::generateGrowthRecommendations($growthFactors, $environment)
        ];
    }

    private static function calculateAdvancedGrowthFactors($genetics, $environment, $stage)
    {
        $factors = [
            'temperature_factor' => 1.0,
            'humidity_factor' => 1.0,
            'light_factor' => 1.0,
            'nutrient_factor' => 1.0,
            'genetics_factor' => 1.0,
            'stress_factor' => 1.0,
            'health_change' => 0.0
        ];

        // Temperature optimization
        $optimalTemp = ($genetics['climate_preference']['temperature_range'][0] + 
                       $genetics['climate_preference']['temperature_range'][1]) / 2;
        $tempDiff = abs($environment['temperature'] - $optimalTemp);
        $factors['temperature_factor'] = max(0.3, 1 - ($tempDiff * 0.02));

        // Humidity optimization
        $optimalHumidity = $genetics['climate_preference']['humidity_preference'];
        $humidityDiff = abs($environment['humidity'] - $optimalHumidity);
        $factors['humidity_factor'] = max(0.4, 1 - ($humidityDiff * 0.015));

        // Light optimization by stage
        $optimalLight = match($stage) {
            'seedling' => 14,
            'vegetative' => 18,
            'flowering' => 12,
            default => 16
        };
        $lightDiff = abs($environment['light_hours'] - $optimalLight);
        $factors['light_factor'] = max(0.5, 1 - ($lightDiff * 0.05));

        // Nutrient factor
        $factors['nutrient_factor'] = match($environment['nutrients']) {
            'deficient' => 0.6,
            'low' => 0.8,
            'balanced' => 1.0,
            'high' => 0.9, // Overfeeding
            'excess' => 0.7,
            default => 0.8
        };

        // Genetics adaptation
        $factors['genetics_factor'] = $genetics['environmental_adaptation'];

        // Overall modifier
        $factors['overall_modifier'] = (
            $factors['temperature_factor'] * 0.25 +
            $factors['humidity_factor'] * 0.2 +
            $factors['light_factor'] * 0.25 +
            $factors['nutrient_factor'] * 0.2 +
            $factors['genetics_factor'] * 0.1
        );

        // Health change calculation
        $stressSources = [];
        if ($factors['temperature_factor'] < 0.8) $stressSources[] = 'temperature';
        if ($factors['humidity_factor'] < 0.8) $stressSources[] = 'humidity';
        if ($factors['light_factor'] < 0.8) $stressSources[] = 'lighting';
        if ($factors['nutrient_factor'] < 0.8) $stressSources[] = 'nutrients';

        $factors['health_change'] = count($stressSources) * -2; // -2 health per stress source per hour

        return $factors;
    }

    private static function generateGrowthRecommendations($factors, $environment)
    {
        $recommendations = [];

        if ($factors['temperature_factor'] < 0.8) {
            $recommendations[] = [
                'type' => 'temperature',
                'priority' => 'high',
                'message' => 'Adjust temperature closer to strain\'s optimal range',
                'current' => $environment['temperature'] . '°F'
            ];
        }

        if ($factors['humidity_factor'] < 0.8) {
            $recommendations[] = [
                'type' => 'humidity',
                'priority' => 'medium',
                'message' => 'Optimize humidity levels for better growth',
                'current' => $environment['humidity'] . '%'
            ];
        }

        if ($factors['light_factor'] < 0.8) {
            $recommendations[] = [
                'type' => 'lighting',
                'priority' => 'high',
                'message' => 'Adjust light schedule for current growth stage',
                'current' => $environment['light_hours'] . ' hours'
            ];
        }

        if ($factors['nutrient_factor'] < 0.9) {
            $recommendations[] = [
                'type' => 'nutrients',
                'priority' => 'medium',
                'message' => 'Balance nutrient levels for optimal growth',
                'current' => $environment['nutrients']
            ];
        }

        return $recommendations;
    }

    // Helper methods
    private static function generateRoomCode($roomId)
    {
        return strtoupper(substr(md5($roomId . time()), 0, 6));
    }

    private static function getRoomInfo($roomId)
    {
        return DB::table('multiplayer_rooms')
            ->leftJoin('multiplayer_participants', 'multiplayer_rooms.id', '=', 'multiplayer_participants.room_id')
            ->leftJoin('users', 'multiplayer_participants.user_id', '=', 'users.id')
            ->where('multiplayer_rooms.id', $roomId)
            ->select('multiplayer_rooms.*', 'users.username', 'multiplayer_participants.role')
            ->get();
    }

    private static function getBaseGrowthRate($stage)
    {
        return match($stage) {
            'seedling' => 2.0,
            'vegetative' => 4.0,
            'flowering' => 1.5,
            'harvest' => 0.0,
            default => 1.0
        };
    }

    private static function determineGrowthStage($growthPoints, $currentStage)
    {
        if ($growthPoints < 100) return 'seedling';
        if ($growthPoints < 500) return 'vegetative';
        if ($growthPoints < 800) return 'flowering';
        return 'harvest';
    }

    private static function calculateStressLevel($genetics, $environment, $health)
    {
        $stressFactors = [];
        
        // Environmental stress
        if ($environment['temperature'] < 65 || $environment['temperature'] > 85) {
            $stressFactors[] = 'temperature_stress';
        }
        
        if ($environment['humidity'] > 70) {
            $stressFactors[] = 'humidity_stress';
        }
        
        if ($health < 70) {
            $stressFactors[] = 'health_stress';
        }

        $baseStress = count($stressFactors) * 15;
        $geneticResistance = $genetics['stress_tolerance'] * 100;
        
        return max(0, min(100, $baseStress - $geneticResistance));
    }

    private static function calculateDiseaseRisk($environment, $stressLevel, $genetics)
    {
        $riskFactors = 0;
        
        // Environmental risk factors
        if ($environment['humidity'] > 65) $riskFactors += 20;
        if ($environment['temperature'] < 70) $riskFactors += 15;
        if ($environment['air_circulation'] === 'poor') $riskFactors += 25;
        
        // Stress increases disease risk
        $riskFactors += $stressLevel * 0.3;
        
        // Genetic resistance
        $resistance = $genetics['disease_resistance'] * 100;
        
        return max(0, min(100, $riskFactors - $resistance));
    }

    private static function calculatePotencyDevelopment($growthPoints, $genetics, $stressLevel)
    {
        $maxTHC = $genetics['thc_range'][1];
        $maxCBD = $genetics['cbd_range'][1];
        
        // Growth stage affects potency development
        $developmentFactor = min(1.0, $growthPoints / 800);
        
        // Stress can affect potency (both positively and negatively)
        $stressFactor = 1.0;
        if ($stressLevel > 50) {
            $stressFactor = 0.9; // High stress reduces potency
        } elseif ($stressLevel > 20 && $stressLevel <= 40) {
            $stressFactor = 1.1; // Moderate stress can increase potency
        }

        return [
            'current_thc' => round($maxTHC * $developmentFactor * $stressFactor, 2),
            'current_cbd' => round($maxCBD * $developmentFactor * $stressFactor, 2),
            'projected_thc' => round($maxTHC * $stressFactor, 2),
            'projected_cbd' => round($maxCBD * $stressFactor, 2),
            'development_progress' => round($developmentFactor * 100, 1)
        ];
    }
}
