<?php

require_once __DIR__ . '/../../config/database.php';

class WeatherEffectsService {
    private static $db;
    
    public static function init() {
        if (!self::$db) {
            self::$db = DB::getInstance();
        }
    }
    
    /**
     * Get current active weather effects
     */
    public static function getActiveWeatherEffects() {
        self::init();
        
        return self::$db->fetchAll(
            "SELECT * FROM weather_effects 
             WHERE is_active = 1 AND end_time > NOW() 
             ORDER BY start_time DESC"
        );
    }
    
    /**
     * Create a new weather effect
     */
    public static function createWeatherEffect($type, $severity, $duration_hours, $description = null) {
        self::init();
        
        $effects = self::getWeatherTypeEffects($type, $severity);
        
        $stmt = self::$db->query(
            "INSERT INTO weather_effects 
             (type, severity, start_time, end_time, temperature_modifier, humidity_modifier, 
              light_modifier, growth_modifier, yield_modifier, disease_risk_modifier, description, is_active) 
             VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $type,
                $severity,
                $duration_hours,
                $effects['temperature_modifier'],
                $effects['humidity_modifier'],
                $effects['light_modifier'],
                $effects['growth_modifier'],
                $effects['yield_modifier'],
                $effects['disease_risk_modifier'],
                $description ?: "A {$severity} {$type} weather event"
            ]
        );
        
        return self::$db->lastInsertId();
    }
    
    /**
     * Apply weather effects to plant growth calculation
     */
    public static function applyWeatherEffectsToPlant($plantId, $baseGrowthRate) {
        self::init();
        
        $activeEffects = self::getActiveWeatherEffects();
        if (empty($activeEffects)) {
            return $baseGrowthRate;
        }
        
        $totalGrowthModifier = 1.0;
        $totalYieldModifier = 1.0;
        $totalDiseaseRisk = 0.0;
        
        foreach ($activeEffects as $effect) {
            // Apply growth modifier
            $totalGrowthModifier *= (1 + $effect['growth_modifier']);
            
            // Apply yield modifier
            $totalYieldModifier *= (1 + $effect['yield_modifier']);
            
            // Accumulate disease risk
            $totalDiseaseRisk += $effect['disease_risk_modifier'];
        }
        
        // Cap disease risk at 100%
        $totalDiseaseRisk = min($totalDiseaseRisk, 1.0);
        
        // Log weather impact on plant
        self::logWeatherImpact($plantId, $totalGrowthModifier, $totalYieldModifier, $totalDiseaseRisk);
        
        // Handle potential disease from weather
        if ($totalDiseaseRisk > 0) {
            self::checkForWeatherDisease($plantId, $totalDiseaseRisk);
        }
        
        return $baseGrowthRate * $totalGrowthModifier;
    }
    
    /**
     * Get weather type specific effects
     */
    private static function getWeatherTypeEffects($type, $severity) {
        $baseEffects = [
            'temperature_modifier' => 0,
            'humidity_modifier' => 0,
            'light_modifier' => 0,
            'growth_modifier' => 0,
            'yield_modifier' => 0,
            'disease_risk_modifier' => 0
        ];
        
        $multiplier = self::getSeverityMultiplier($severity);
        
        switch ($type) {
            case 'heat_wave':
                return array_merge($baseEffects, [
                    'temperature_modifier' => 15 * $multiplier,
                    'humidity_modifier' => -20 * $multiplier,
                    'growth_modifier' => -0.3 * $multiplier,
                    'yield_modifier' => -0.15 * $multiplier,
                    'disease_risk_modifier' => 0.1 * $multiplier
                ]);
                
            case 'cold_snap':
                return array_merge($baseEffects, [
                    'temperature_modifier' => -20 * $multiplier,
                    'growth_modifier' => -0.5 * $multiplier,
                    'yield_modifier' => -0.2 * $multiplier,
                    'disease_risk_modifier' => 0.05 * $multiplier
                ]);
                
            case 'rain_storm':
                return array_merge($baseEffects, [
                    'humidity_modifier' => 30 * $multiplier,
                    'light_modifier' => -25 * $multiplier,
                    'growth_modifier' => 0.1 * $multiplier,
                    'disease_risk_modifier' => 0.15 * $multiplier
                ]);
                
            case 'drought':
                return array_merge($baseEffects, [
                    'humidity_modifier' => -40 * $multiplier,
                    'growth_modifier' => -0.4 * $multiplier,
                    'yield_modifier' => -0.25 * $multiplier,
                    'disease_risk_modifier' => 0.2 * $multiplier
                ]);
                
            case 'sunny':
                return array_merge($baseEffects, [
                    'light_modifier' => 20 * $multiplier,
                    'temperature_modifier' => 5 * $multiplier,
                    'growth_modifier' => 0.15 * $multiplier,
                    'yield_modifier' => 0.1 * $multiplier
                ]);
                
            case 'overcast':
                return array_merge($baseEffects, [
                    'light_modifier' => -15 * $multiplier,
                    'temperature_modifier' => -3 * $multiplier,
                    'growth_modifier' => -0.1 * $multiplier
                ]);
                
            case 'windy':
                return array_merge($baseEffects, [
                    'humidity_modifier' => -10 * $multiplier,
                    'growth_modifier' => -0.05 * $multiplier,
                    'disease_risk_modifier' => -0.05 * $multiplier // Wind can reduce some diseases
                ]);
                
            default:
                return $baseEffects;
        }
    }
    
    /**
     * Get severity multiplier
     */
    private static function getSeverityMultiplier($severity) {
        switch ($severity) {
            case 'mild':
                return 0.5;
            case 'moderate':
                return 1.0;
            case 'severe':
                return 1.5;
            case 'extreme':
                return 2.0;
            default:
                return 1.0;
        }
    }
    
    /**
     * Log weather impact on specific plant
     */
    private static function logWeatherImpact($plantId, $growthModifier, $yieldModifier, $diseaseRisk) {
        self::$db->query(
            "INSERT INTO weather_plant_impacts 
             (plant_id, growth_modifier_applied, yield_modifier_applied, disease_risk_applied, recorded_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$plantId, $growthModifier, $yieldModifier, $diseaseRisk]
        );
    }
    
    /**
     * Check if weather causes disease and apply it
     */
    private static function checkForWeatherDisease($plantId, $diseaseRisk) {
        $random = mt_rand() / mt_getrandmax();
        
        if ($random < $diseaseRisk) {
            // Weather caused disease - determine type based on current weather
            $activeEffects = self::getActiveWeatherEffects();
            $diseaseType = self::determineDiseaseType($activeEffects);
            
            self::$db->query(
                "INSERT INTO plant_diseases (plant_id, disease_type, severity, caused_by_weather, detected_at) 
                 VALUES (?, ?, 'mild', 1, NOW())",
                [$plantId, $diseaseType]
            );
            
            // Reduce plant health
            self::$db->query(
                "UPDATE plants SET health = GREATEST(health - 15, 0) WHERE id = ?",
                [$plantId]
            );
        }
    }
    
    /**
     * Determine disease type based on weather conditions
     */
    private static function determineDiseaseType($weatherEffects) {
        foreach ($weatherEffects as $effect) {
            switch ($effect['type']) {
                case 'rain_storm':
                case 'high_humidity':
                    return mt_rand(0, 1) ? 'mold' : 'root_rot';
                case 'heat_wave':
                    return 'heat_stress';
                case 'drought':
                    return 'nutrient_deficiency';
                case 'cold_snap':
                    return 'cold_stress';
                default:
                    return 'general_stress';
            }
        }
        return 'environmental_stress';
    }
    
    /**
     * Generate random weather events for gameplay
     */
    public static function generateRandomWeatherEvent() {
        $weatherTypes = ['heat_wave', 'cold_snap', 'rain_storm', 'drought', 'sunny', 'overcast', 'windy'];
        $severities = ['mild', 'moderate', 'severe'];
        
        $type = $weatherTypes[array_rand($weatherTypes)];
        $severity = $severities[array_rand($severities)];
        $duration = mt_rand(2, 24); // 2-24 hours
        
        return self::createWeatherEffect($type, $severity, $duration);
    }
    
    /**
     * Get weather forecast for next 24 hours
     */
    public static function getWeatherForecast() {
        self::init();
        
        return self::$db->fetchAll(
            "SELECT type, severity, start_time, end_time, description
             FROM weather_effects 
             WHERE start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
             ORDER BY start_time ASC"
        );
    }
    
    /**
     * End weather effect early
     */
    public static function endWeatherEffect($effectId) {
        self::init();
        
        return self::$db->query(
            "UPDATE weather_effects SET end_time = NOW(), is_active = 0 WHERE id = ?",
            [$effectId]
        );
    }
    
    /**
     * Get weather statistics for analytics
     */
    public static function getWeatherStats($days = 7) {
        self::init();
        
        return self::$db->fetchAll(
            "SELECT 
                type,
                severity,
                AVG(TIMESTAMPDIFF(HOUR, start_time, end_time)) as avg_duration_hours,
                COUNT(*) as occurrence_count,
                AVG(growth_modifier) as avg_growth_impact,
                AVG(yield_modifier) as avg_yield_impact,
                AVG(disease_risk_modifier) as avg_disease_risk
             FROM weather_effects 
             WHERE start_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY type, severity
             ORDER BY occurrence_count DESC",
            [$days]
        );
    }
    
    /**
     * Calculate seasonal weather patterns (NYC specific)
     */
    public static function generateSeasonalWeather() {
        $month = date('n');
        $seasonalTypes = [];
        
        // NYC seasonal weather patterns
        if ($month >= 12 || $month <= 2) { // Winter
            $seasonalTypes = ['cold_snap', 'overcast', 'windy'];
        } elseif ($month >= 3 && $month <= 5) { // Spring
            $seasonalTypes = ['rain_storm', 'sunny', 'overcast'];
        } elseif ($month >= 6 && $month <= 8) { // Summer
            $seasonalTypes = ['heat_wave', 'sunny', 'rain_storm'];
        } else { // Fall
            $seasonalTypes = ['overcast', 'windy', 'cold_snap'];
        }
        
        $type = $seasonalTypes[array_rand($seasonalTypes)];
        $severity = (mt_rand(1, 100) <= 70) ? 'mild' : 'moderate'; // 70% chance mild, 30% moderate
        $duration = mt_rand(4, 16);
        
        return self::createWeatherEffect($type, $severity, $duration);
    }
}

// Auto-weather system for continuous gameplay
class AutoWeatherSystem {
    public static function startWeatherSimulation() {
        // Create initial weather if none exists
        $activeEffects = WeatherEffectsService::getActiveWeatherEffects();
        
        if (empty($activeEffects)) {
            WeatherEffectsService::generateSeasonalWeather();
        }
    }
    
    public static function processWeatherCycle() {
        // Check if we need new weather events
        $activeEffects = WeatherEffectsService::getActiveWeatherEffects();
        
        // 20% chance to generate new weather every hour
        if (mt_rand(1, 100) <= 20) {
            WeatherEffectsService::generateSeasonalWeather();
        }
        
        // 5% chance for random extreme weather
        if (mt_rand(1, 100) <= 5) {
            $extremeTypes = ['heat_wave', 'cold_snap', 'drought'];
            $type = $extremeTypes[array_rand($extremeTypes)];
            WeatherEffectsService::createWeatherEffect($type, 'severe', mt_rand(1, 6));
        }
    }
}