<?php
/**
 * Game Helper Functions
 * SmokeoutNYC v2.0
 * 
 * Contains utility functions used by the advanced game system
 */

require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../models/Game.php';

/**
 * Get current impairment level for a player
 * 
 * @param int $user_id User ID
 * @return float Current impairment level (0.0 to 1.0)
 */
function getCurrentImpairment($user_id) {
    try {
        $player = GamePlayer::getByUserId($user_id);
        return floatval($player->current_impairment ?? 0.0);
    } catch (Exception $e) {
        error_log("Error getting current impairment: " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Get active effects for a player based on recent consumption
 * 
 * @param int $user_id User ID
 * @return array Array of active effects
 */
function getActiveEffects($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.type as product_type, p.thc_content
            FROM consumptions c
            JOIN products p ON c.product_id = p.id
            WHERE c.player_id = (SELECT id FROM game_players WHERE user_id = ?)
            AND c.consumed_at > DATE_SUB(NOW(), INTERVAL 4 HOUR)
            ORDER BY c.consumed_at DESC
        ");
        $stmt->execute([$user_id]);
        $consumptions = $stmt->fetchAll();
        
        $effects = [];
        foreach ($consumptions as $consumption) {
            $time_elapsed = time() - strtotime($consumption['consumed_at']);
            $duration_seconds = $consumption['duration_minutes'] * 60;
            
            if ($time_elapsed < $duration_seconds) {
                $effects[] = [
                    'type' => $consumption['product_type'],
                    'method' => $consumption['method'],
                    'remaining_minutes' => max(0, ($duration_seconds - $time_elapsed) / 60),
                    'intensity' => max(0, $consumption['impairment_added'] * (1 - $time_elapsed / $duration_seconds))
                ];
            }
        }
        
        return $effects;
    } catch (Exception $e) {
        error_log("Error getting active effects: " . $e->getMessage());
        return [];
    }
}

/**
 * Get product by ID
 * 
 * @param int $product_id Product ID
 * @return array|null Product data
 */
function getProductById($product_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting product: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate impairment level based on product and consumption method
 * 
 * @param array $product Product data
 * @param string $method Consumption method
 * @return float Impairment level to add
 */
function calculateImpairmentLevel($product, $method) {
    $base_impairment = $product['thc_content'] / 100; // Convert percentage to decimal
    $quality_modifier = $product['quality'] ?? 1.0;
    
    // Method multipliers
    $method_multipliers = [
        'smoke' => 0.8,
        'vape' => 0.9,
        'eat' => 1.2, // Edibles are stronger
        'dab' => 1.5  // Concentrates are much stronger
    ];
    
    $method_multiplier = $method_multipliers[$method] ?? 1.0;
    
    return min(1.0, $base_impairment * $quality_modifier * $method_multiplier);
}

/**
 * Calculate effect duration based on product and method
 * 
 * @param array $product Product data
 * @param string $method Consumption method
 * @return int Duration in minutes
 */
function calculateEffectDuration($product, $method) {
    // Base durations by method
    $base_durations = [
        'smoke' => 120,  // 2 hours
        'vape' => 90,    // 1.5 hours
        'eat' => 240,    // 4 hours (edibles last longer)
        'dab' => 180     // 3 hours
    ];
    
    $base_duration = $base_durations[$method] ?? 120;
    
    // THC content affects duration slightly
    $thc_modifier = 1.0 + ($product['thc_content'] / 1000); // Small increase for high THC
    
    return round($base_duration * $thc_modifier);
}

/**
 * Record consumption in database
 * 
 * @param int $user_id User ID
 * @param array $product Product data
 * @param string $method Consumption method
 * @param float $impairment Impairment added
 * @param int $duration Duration in minutes
 * @return int Consumption ID
 */
function recordConsumption($user_id, $product, $method, $impairment, $duration) {
    global $pdo;
    
    try {
        $player = GamePlayer::getByUserId($user_id);
        
        $stmt = $pdo->prepare("
            INSERT INTO consumptions (player_id, product_id, method, amount, impairment_added, duration_minutes, consumed_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $player->id,
            $product['id'],
            $method,
            1.0, // For now, assume consuming 1 gram
            $impairment,
            $duration
        ]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error recording consumption: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Update product status
 * 
 * @param int $product_id Product ID
 * @param string $status New status
 */
function updateProductStatus($product_id, $status) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $product_id]);
    } catch (Exception $e) {
        error_log("Error updating product status: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Update player's current impairment level
 * 
 * @param int $user_id User ID
 */
function updatePlayerImpairment($user_id) {
    try {
        $player = GamePlayer::getByUserId($user_id);
        $current_impairment = getCurrentImpairment($user_id);
        
        // Calculate decay over time (impairment reduces by 0.1 per hour)
        $last_update = strtotime($player->updated_at ?? 'now');
        $hours_elapsed = max(0, (time() - $last_update) / 3600);
        $decay = $hours_elapsed * 0.1;
        
        $new_impairment = max(0, $current_impairment - $decay);
        
        $player->updateImpairment($new_impairment);
    } catch (Exception $e) {
        error_log("Error updating player impairment: " . $e->getMessage());
    }
}

/**
 * Check if player should trigger a mistake based on impairment
 * 
 * @param int $user_id User ID
 * @param string $action_type Type of action being performed
 * @return bool True if mistake should occur
 */
function shouldTriggerMistake($user_id, $action_type) {
    $impairment = getCurrentImpairment($user_id);
    
    // Base mistake chances by action type
    $base_chances = [
        'process_product' => 0.1,  // 10% base chance
        'sell_bulk' => 0.15,       // 15% base chance
        'plant_care' => 0.05,      // 5% base chance
        'harvesting' => 0.08,      // 8% base chance
        'pricing' => 0.12          // 12% base chance
    ];
    
    $base_chance = $base_chances[$action_type] ?? 0.1;
    
    // Impairment increases mistake chance exponentially
    $mistake_chance = $base_chance * (1 + ($impairment * $impairment * 5));
    
    return (rand(0, 10000) / 10000) < $mistake_chance;
}

/**
 * Trigger a processing mistake
 * 
 * @param int $user_id User ID
 * @param int $plant_id Plant ID
 * @param float $impairment Current impairment level
 */
function triggerProcessingMistake($user_id, $plant_id, $impairment) {
    global $pdo;
    
    try {
        $player = GamePlayer::getByUserId($user_id);
        
        // Record mistake
        $stmt = $pdo->prepare("
            INSERT INTO game_mistakes (player_id, mistake_type, impairment_level, description, created_at)
            VALUES (?, 'process_product', ?, 'Processing failed due to impairment', NOW())
        ");
        
        $stmt->execute([$player->id, $impairment]);
        
        // Update player mistake count
        $stmt = $pdo->prepare("
            UPDATE game_players SET mistakes_count = mistakes_count + 1, updated_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$player->id]);
        
    } catch (Exception $e) {
        error_log("Error recording processing mistake: " . $e->getMessage());
    }
}

/**
 * Trigger a sales mistake
 * 
 * @param int $user_id User ID
 * @param string $sale_type Type of sale (smokeshop, dealer, etc.)
 * @param float $impairment Current impairment level
 */
function triggerSaleMistake($user_id, $sale_type, $impairment) {
    global $pdo;
    
    try {
        $player = GamePlayer::getByUserId($user_id);
        
        // Calculate loss amount based on impairment level
        $loss_amount = rand(50, 200) * (1 + $impairment);
        
        // Record mistake
        $stmt = $pdo->prepare("
            INSERT INTO game_mistakes (player_id, mistake_type, impairment_level, loss_amount, description, created_at)
            VALUES (?, 'sell_bulk', ?, ?, ?, NOW())
        ");
        
        $description = "Sale to $sale_type failed due to impairment - lost potential earnings";
        $stmt->execute([$player->id, $impairment, $loss_amount, $description]);
        
        // Update player mistake count
        $stmt = $pdo->prepare("
            UPDATE game_players SET mistakes_count = mistakes_count + 1, updated_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$player->id]);
        
    } catch (Exception $e) {
        error_log("Error recording sale mistake: " . $e->getMessage());
    }
}

/**
 * Get player's products
 * 
 * @param int $user_id User ID
 * @return array Array of products
 */
function getPlayerProducts($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, pl.name as plant_name, s.name as strain_name
            FROM products p
            JOIN plants pl ON p.plant_id = pl.id
            JOIN strains s ON pl.strain_id = s.id
            WHERE p.player_id = (SELECT id FROM game_players WHERE user_id = ?)
            AND p.status = 'available'
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting player products: " . $e->getMessage());
        return [];
    }
}

/**
 * Create product from harvested plant
 * 
 * @param int $user_id User ID
 * @param int $plant_id Plant ID
 * @param string $product_type Product type
 * @param float $quality_penalty Quality penalty from impairment
 * @return array Product data
 */
function createProduct($user_id, $plant_id, $product_type, $quality_penalty) {
    global $pdo;
    
    try {
        $player = GamePlayer::getByUserId($user_id);
        $plant = Plant::getById($plant_id);
        
        if (!$plant || $plant['stage'] !== 'harvested') {
            throw new Exception("Plant not ready for processing");
        }
        
        // Calculate product properties based on plant and type
        $weight_ratios = [
            'flower' => 1.0,      // Use all plant material
            'edible' => 0.8,      // Some loss in processing
            'concentrate' => 0.2, // Much smaller yield but higher potency
            'pre_roll' => 0.9,    // Slight loss from trimming
            'hash' => 0.3,        // Low yield but high potency
            'rosin' => 0.15       // Lowest yield, highest potency
        ];
        
        $potency_multipliers = [
            'flower' => 1.0,
            'edible' => 0.8,      // Lower bioavailability
            'concentrate' => 3.0, // Much higher THC
            'pre_roll' => 1.0,
            'hash' => 2.5,        // Higher THC
            'rosin' => 4.0        // Highest THC
        ];
        
        $weight = $plant['final_weight'] * ($weight_ratios[$product_type] ?? 1.0);
        $thc_content = $plant['final_thc'] * ($potency_multipliers[$product_type] ?? 1.0);
        $quality = max(0.1, min(1.0, $plant['final_quality'] - $quality_penalty));
        
        // Calculate market value
        $base_prices = [
            'flower' => 12,      // $12/gram
            'edible' => 25,      // $25/gram (premium)
            'concentrate' => 40, // $40/gram
            'pre_roll' => 8,     // $8/gram (lower than flower)
            'hash' => 35,        // $35/gram
            'rosin' => 60        // $60/gram (premium)
        ];
        
        $market_value = $weight * ($base_prices[$product_type] ?? 12) * $quality;
        
        // Insert product
        $stmt = $pdo->prepare("
            INSERT INTO products (player_id, plant_id, type, weight, thc_content, quality, 
                                potency_multiplier, market_value, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW(), NOW())
        ");
        
        $stmt->execute([
            $player->id,
            $plant_id,
            $product_type,
            $weight,
            $thc_content,
            $quality,
            $potency_multipliers[$product_type] ?? 1.0,
            $market_value
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        // Mark plant as processed
        $stmt = $pdo->prepare("UPDATE plants SET stage = 'processed', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$plant_id]);
        
        // Add experience for processing
        $xp_gained = round($weight * 10); // 10 XP per gram processed
        $player->addExperience($xp_gained);
        
        return [
            'id' => $product_id,
            'type' => $product_type,
            'weight' => $weight,
            'thc_content' => $thc_content,
            'quality' => $quality,
            'market_value' => $market_value,
            'xp_gained' => $xp_gained
        ];
        
    } catch (Exception $e) {
        error_log("Error creating product: " . $e->getMessage());
        throw $e;
    }
}

?>
