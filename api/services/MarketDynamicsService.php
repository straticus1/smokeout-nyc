<?php

require_once __DIR__ . '/../../config/database.php';

class MarketDynamicsService {
    private static $db;
    
    public static function init() {
        if (!self::$db) {
            self::$db = DB::getInstance();
        }
    }
    
    /**
     * Update market prices based on supply and demand
     */
    public static function updateMarketPrices() {
        self::init();
        
        $locations = self::$db->fetchAll("SELECT * FROM growing_locations WHERE is_active = 1");
        $strains = self::$db->fetchAll("SELECT * FROM genetics WHERE is_active = 1");
        
        foreach ($locations as $location) {
            foreach ($strains as $strain) {
                self::updateLocationStrainPrice($location['id'], $strain['id']);
            }
        }
        
        // Update global market trends
        self::updateGlobalTrends();
    }
    
    /**
     * Update price for specific location and strain combination
     */
    private static function updateLocationStrainPrice($location_id, $strain_id) {
        self::init();
        
        // Get current market data
        $current = self::$db->fetchOne(
            "SELECT * FROM market_conditions WHERE location_id = ? AND strain_id = ?",
            [$location_id, $strain_id]
        );
        
        // Calculate supply from recent harvests and active plants
        $supply_data = self::calculateSupply($location_id, $strain_id);
        
        // Calculate demand from player activity and market trends
        $demand_data = self::calculateDemand($location_id, $strain_id);
        
        // Calculate new price modifier
        $price_modifier = self::calculatePriceModifier($supply_data, $demand_data, $current);
        
        // Apply market volatility
        $volatility_factor = self::getVolatilityFactor($location_id, $strain_id);
        $price_modifier *= (1 + (mt_rand(-100, 100) / 1000) * $volatility_factor);
        
        // Clamp price modifier within reasonable bounds
        $price_modifier = max(0.3, min(3.0, $price_modifier));
        
        // Update or insert market condition
        if ($current) {
            self::$db->query(
                "UPDATE market_conditions SET 
                 supply_level = ?, demand_level = ?, price_modifier = ?,
                 volatility = ?, last_updated = NOW() WHERE id = ?",
                [$supply_data['level'], $demand_data['level'], $price_modifier, $volatility_factor, $current['id']]
            );
        } else {
            self::$db->query(
                "INSERT INTO market_conditions 
                 (location_id, strain_id, supply_level, demand_level, price_modifier, volatility, created_at, last_updated)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [$location_id, $strain_id, $supply_data['level'], $demand_data['level'], $price_modifier, $volatility_factor]
            );
        }
        
        // Record price history
        self::recordPriceHistory($location_id, $strain_id, $price_modifier, $supply_data, $demand_data);
    }
    
    /**
     * Calculate supply metrics for location/strain
     */
    private static function calculateSupply($location_id, $strain_id) {
        self::init();
        
        // Count harvested plants in last 30 days
        $recent_harvest = self::$db->fetchOne(
            "SELECT COUNT(*) as harvest_count, COALESCE(SUM(final_weight), 0) as total_weight
             FROM plants 
             WHERE location_id = ? AND strain_id = ? AND stage = 'harvested' 
             AND harvested_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$location_id, $strain_id]
        );
        
        // Count active plants approaching harvest
        $pending_harvest = self::$db->fetchOne(
            "SELECT COUNT(*) as pending_count
             FROM plants 
             WHERE location_id = ? AND strain_id = ? AND stage IN ('flowering', 'harvest_ready')
             AND harvest_ready_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)",
            [$location_id, $strain_id]
        );
        
        // Calculate supply level (0-100)
        $base_supply = ($recent_harvest['harvest_count'] ?? 0) * 10;
        $pending_supply = ($pending_harvest['pending_count'] ?? 0) * 5;
        $total_weight_factor = min(50, ($recent_harvest['total_weight'] ?? 0) / 10);
        
        $supply_level = min(100, $base_supply + $pending_supply + $total_weight_factor);
        
        return [
            'level' => $supply_level,
            'recent_harvest_count' => $recent_harvest['harvest_count'] ?? 0,
            'pending_harvest_count' => $pending_harvest['pending_count'] ?? 0,
            'total_weight' => $recent_harvest['total_weight'] ?? 0
        ];
    }
    
    /**
     * Calculate demand metrics for location/strain
     */
    private static function calculateDemand($location_id, $strain_id) {
        self::init();
        
        // Player activity in this location
        $player_activity = self::$db->fetchOne(
            "SELECT COUNT(DISTINCT player_id) as active_players,
                    COUNT(*) as total_actions
             FROM plants 
             WHERE location_id = ? 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$location_id]
        );
        
        // Recent sales of this strain
        $recent_sales = self::$db->fetchOne(
            "SELECT COUNT(*) as sales_count, AVG(final_price) as avg_price
             FROM sales 
             WHERE strain_id = ? AND location_id = ?
             AND sold_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)",
            [$strain_id, $location_id]
        );
        
        // Strain popularity (rarity and stats influence)
        $strain = self::$db->fetchOne(
            "SELECT rarity, thc_max, cbd_max FROM genetics WHERE id = ?",
            [$strain_id]
        );
        
        // Calculate demand level (0-100)
        $activity_demand = min(30, ($player_activity['active_players'] ?? 0) * 3);
        $sales_demand = min(25, ($recent_sales['sales_count'] ?? 0) * 2);
        
        // Rarity affects base demand
        $rarity_demand = match($strain['rarity'] ?? 'common') {
            'legendary' => 40,
            'epic' => 30,
            'rare' => 20,
            'uncommon' => 15,
            'common' => 10,
            default => 10
        };
        
        // High THC/CBD increases demand
        $potency_demand = min(15, (($strain['thc_max'] ?? 0) + ($strain['cbd_max'] ?? 0)) / 2);
        
        $demand_level = min(100, $activity_demand + $sales_demand + $rarity_demand + $potency_demand);
        
        return [
            'level' => $demand_level,
            'active_players' => $player_activity['active_players'] ?? 0,
            'recent_sales' => $recent_sales['sales_count'] ?? 0,
            'avg_sale_price' => $recent_sales['avg_price'] ?? 0,
            'rarity_factor' => $rarity_demand
        ];
    }
    
    /**
     * Calculate price modifier based on supply and demand
     */
    private static function calculatePriceModifier($supply_data, $demand_data, $current_market) {
        $supply_level = $supply_data['level'];
        $demand_level = $demand_data['level'];
        
        // Basic supply/demand ratio
        if ($supply_level == 0) {
            $ratio = $demand_level > 0 ? 2.0 : 1.0;
        } else {
            $ratio = $demand_level / $supply_level;
        }
        
        // Base price modifier from ratio
        $base_modifier = 0.5 + ($ratio * 0.5);
        
        // Apply current market momentum (gradual changes)
        if ($current_market) {
            $current_modifier = $current_market['price_modifier'];
            $momentum_factor = 0.3;
            $base_modifier = ($base_modifier * (1 - $momentum_factor)) + ($current_modifier * $momentum_factor);
        }
        
        // Market events can influence prices
        $event_modifier = self::getMarketEventModifier();
        
        return $base_modifier * $event_modifier;
    }
    
    /**
     * Get volatility factor based on market conditions
     */
    private static function getVolatilityFactor($location_id, $strain_id) {
        self::init();
        
        // Base volatility
        $base_volatility = 0.1;
        
        // New strains are more volatile
        $strain = self::$db->fetchOne(
            "SELECT created_at, is_bred FROM genetics WHERE id = ?",
            [$strain_id]
        );
        
        if ($strain) {
            $days_old = (time() - strtotime($strain['created_at'])) / (24 * 60 * 60);
            if ($days_old < 7) {
                $base_volatility += 0.15; // New strain volatility
            }
            
            if ($strain['is_bred']) {
                $base_volatility += 0.05; // Player-bred strains more volatile
            }
        }
        
        // Market events increase volatility
        $event_volatility = self::getEventVolatility();
        
        return min(0.5, $base_volatility + $event_volatility);
    }
    
    /**
     * Record price history for analytics
     */
    private static function recordPriceHistory($location_id, $strain_id, $price_modifier, $supply_data, $demand_data) {
        self::$db->query(
            "INSERT INTO price_history 
             (location_id, strain_id, price_modifier, supply_level, demand_level, recorded_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$location_id, $strain_id, $price_modifier, $supply_data['level'], $demand_data['level']]
        );
    }
    
    /**
     * Update global market trends
     */
    private static function updateGlobalTrends() {
        self::init();
        
        // Calculate overall market health
        $market_health = self::calculateMarketHealth();
        
        // Update seasonal trends
        self::updateSeasonalTrends();
        
        // Generate market events
        self::processMarketEvents();
        
        // Update global market stats
        self::$db->query(
            "INSERT INTO market_trends (trend_date, market_health, trend_data, created_at)
             VALUES (CURDATE(), ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
             market_health = VALUES(market_health),
             trend_data = VALUES(trend_data),
             updated_at = NOW()",
            [$market_health, json_encode(['updated' => time()])]
        );
    }
    
    /**
     * Calculate overall market health score (0-100)
     */
    private static function calculateMarketHealth() {
        self::init();
        
        // Active player count
        $active_players = self::$db->fetchOne(
            "SELECT COUNT(DISTINCT user_id) as count FROM game_players 
             WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )['count'] ?? 0;
        
        // Recent transaction volume
        $recent_sales = self::$db->fetchOne(
            "SELECT COUNT(*) as count, AVG(final_price) as avg_price FROM sales 
             WHERE sold_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Active plants/growing activity
        $active_plants = self::$db->fetchOne(
            "SELECT COUNT(*) as count FROM plants 
             WHERE stage != 'harvested' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)"
        )['count'] ?? 0;
        
        // Calculate health score
        $player_score = min(30, $active_players);
        $sales_score = min(25, ($recent_sales['count'] ?? 0));
        $activity_score = min(25, $active_plants / 2);
        $price_stability = 20; // Base score for price stability
        
        return min(100, $player_score + $sales_score + $activity_score + $price_stability);
    }
    
    /**
     * Update seasonal market trends
     */
    private static function updateSeasonalTrends() {
        $month = date('n');
        $seasonal_modifiers = [];
        
        // Different seasons affect different strain types
        if ($month >= 3 && $month <= 5) { // Spring
            $seasonal_modifiers['sativa'] = 1.1;
            $seasonal_modifiers['hybrid'] = 1.05;
        } elseif ($month >= 6 && $month <= 8) { // Summer
            $seasonal_modifiers['sativa'] = 1.15;
            $seasonal_modifiers['outdoor'] = 1.2;
        } elseif ($month >= 9 && $month <= 11) { // Fall
            $seasonal_modifiers['indica'] = 1.1;
            $seasonal_modifiers['hybrid'] = 1.05;
        } else { // Winter
            $seasonal_modifiers['indica'] = 1.15;
            $seasonal_modifiers['indoor'] = 1.1;
        }
        
        // Apply seasonal modifiers to relevant strains
        foreach ($seasonal_modifiers as $type => $modifier) {
            self::$db->query(
                "UPDATE market_conditions mc
                 JOIN genetics g ON mc.strain_id = g.id
                 SET mc.price_modifier = mc.price_modifier * ?
                 WHERE g.growth_pattern = ? OR g.effects LIKE ?",
                [$modifier, $type, "%{$type}%"]
            );
        }
    }
    
    /**
     * Process random market events
     */
    private static function processMarketEvents() {
        // 5% chance of market event each update cycle
        if (mt_rand(1, 100) <= 5) {
            self::generateMarketEvent();
        }
    }
    
    /**
     * Generate random market event
     */
    private static function generateMarketEvent() {
        $events = [
            [
                'type' => 'supply_shortage',
                'description' => 'Supply shortage causes price spike',
                'price_effect' => 1.3,
                'duration_hours' => 6
            ],
            [
                'type' => 'high_demand',
                'description' => 'Celebrity endorsement increases demand',
                'price_effect' => 1.2,
                'duration_hours' => 12
            ],
            [
                'type' => 'market_crash',
                'description' => 'Market oversaturation causes prices to drop',
                'price_effect' => 0.7,
                'duration_hours' => 8
            ],
            [
                'type' => 'quality_premium',
                'description' => 'High-quality strains command premium prices',
                'price_effect' => 1.4,
                'duration_hours' => 4
            ],
            [
                'type' => 'regulatory_news',
                'description' => 'Regulatory changes affect market sentiment',
                'price_effect' => mt_rand(80, 120) / 100,
                'duration_hours' => 24
            ]
        ];
        
        $event = $events[array_rand($events)];
        
        self::$db->query(
            "INSERT INTO market_events 
             (event_type, description, price_effect, start_time, end_time, is_active)
             VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR), 1)",
            [$event['type'], $event['description'], $event['price_effect'], $event['duration_hours']]
        );
    }
    
    /**
     * Get current market event modifiers
     */
    private static function getMarketEventModifier() {
        self::init();
        
        $active_events = self::$db->fetchAll(
            "SELECT price_effect FROM market_events 
             WHERE is_active = 1 AND end_time > NOW()"
        );
        
        $total_modifier = 1.0;
        foreach ($active_events as $event) {
            $total_modifier *= $event['price_effect'];
        }
        
        return $total_modifier;
    }
    
    /**
     * Get event-based volatility
     */
    private static function getEventVolatility() {
        self::init();
        
        $event_count = self::$db->fetchOne(
            "SELECT COUNT(*) as count FROM market_events 
             WHERE is_active = 1 AND end_time > NOW()"
        )['count'] ?? 0;
        
        return $event_count * 0.05; // Each active event adds 5% volatility
    }
    
    /**
     * Get current market prices for a location
     */
    public static function getMarketPrices($location_id = null) {
        self::init();
        
        $query = "
            SELECT mc.*, g.name as strain_name, g.rarity, g.thc_max, g.cbd_max,
                   l.name as location_name, l.market_modifier
            FROM market_conditions mc
            JOIN genetics g ON mc.strain_id = g.id
            JOIN growing_locations l ON mc.location_id = l.id
        ";
        
        $params = [];
        if ($location_id) {
            $query .= " WHERE mc.location_id = ?";
            $params[] = $location_id;
        }
        
        $query .= " ORDER BY mc.price_modifier DESC, g.rarity DESC";
        
        return self::$db->fetchAll($query, $params);
    }
    
    /**
     * Get price history for analytics
     */
    public static function getPriceHistory($strain_id, $location_id = null, $days = 7) {
        self::init();
        
        $query = "
            SELECT ph.*, g.name as strain_name
            FROM price_history ph
            JOIN genetics g ON ph.strain_id = g.id
            WHERE ph.strain_id = ? AND ph.recorded_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        $params = [$strain_id, $days];
        
        if ($location_id) {
            $query .= " AND ph.location_id = ?";
            $params[] = $location_id;
        }
        
        $query .= " ORDER BY ph.recorded_at ASC";
        
        return self::$db->fetchAll($query, $params);
    }
    
    /**
     * Get market trends and forecasts
     */
    public static function getMarketTrends() {
        self::init();
        
        return [
            'current_health' => self::calculateMarketHealth(),
            'active_events' => self::$db->fetchAll(
                "SELECT * FROM market_events WHERE is_active = 1 AND end_time > NOW()"
            ),
            'top_performers' => self::$db->fetchAll(
                "SELECT g.name, mc.price_modifier, mc.demand_level
                 FROM market_conditions mc
                 JOIN genetics g ON mc.strain_id = g.id
                 ORDER BY mc.price_modifier DESC
                 LIMIT 5"
            ),
            'trending_up' => self::getTrendingStrains('up'),
            'trending_down' => self::getTrendingStrains('down')
        ];
    }
    
    /**
     * Get trending strains (up or down)
     */
    private static function getTrendingStrains($direction = 'up') {
        self::init();
        
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        
        return self::$db->fetchAll(
            "SELECT g.name, 
                    AVG(ph1.price_modifier) as recent_avg,
                    AVG(ph2.price_modifier) as previous_avg,
                    (AVG(ph1.price_modifier) - AVG(ph2.price_modifier)) as trend_change
             FROM genetics g
             JOIN price_history ph1 ON g.id = ph1.strain_id AND ph1.recorded_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
             JOIN price_history ph2 ON g.id = ph2.strain_id AND ph2.recorded_at BETWEEN DATE_SUB(NOW(), INTERVAL 3 DAY) AND DATE_SUB(NOW(), INTERVAL 1 DAY)
             GROUP BY g.id, g.name
             HAVING ABS(trend_change) > 0.1
             ORDER BY trend_change {$order}
             LIMIT 5"
        );
    }
}

// Auto-market system for continuous updates
class AutoMarketSystem {
    public static function processMarketCycle() {
        // Update prices every hour
        MarketDynamicsService::updateMarketPrices();
        
        // Clean up old data
        self::cleanupOldData();
    }
    
    private static function cleanupOldData() {
        $db = DB::getInstance();
        
        // Keep only 30 days of price history
        $db->query(
            "DELETE FROM price_history WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Clean up expired market events
        $db->query(
            "UPDATE market_events SET is_active = 0 WHERE end_time <= NOW()"
        );
    }
}