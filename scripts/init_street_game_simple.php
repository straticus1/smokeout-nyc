<?php
/**
 * Simple Street Game Database Initialization
 * Creates tables one by one with better error handling
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = DB::getInstance();
    $pdo = $db->getConnection();
    
    echo "Initializing Street Game Database (Simple Mode)...\n";
    
    // Enable WAL mode for SQLite (better performance)
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec("PRAGMA journal_mode=WAL");
        $pdo->exec("PRAGMA synchronous=NORMAL");
        echo "✓ Configured SQLite for better performance\n";
    }
    
    $tables = [
        'users' => "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                balance REAL DEFAULT 1000.00,
                status TEXT DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'player_levels' => "
            CREATE TABLE IF NOT EXISTS player_levels (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                current_level INTEGER DEFAULT 1,
                experience_points INTEGER DEFAULT 0,
                total_experience INTEGER DEFAULT 0,
                reputation_score INTEGER DEFAULT 0,
                street_cred INTEGER DEFAULT 0,
                respect_level TEXT DEFAULT 'nobody',
                unlock_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_level_up TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ",
        'level_requirements' => "
            CREATE TABLE IF NOT EXISTS level_requirements (
                level INTEGER PRIMARY KEY,
                experience_needed INTEGER NOT NULL,
                title TEXT,
                description TEXT,
                unlocks TEXT,
                rewards TEXT,
                street_dealer_spawn_chance REAL DEFAULT 0.00,
                max_dealers_per_territory INTEGER DEFAULT 0,
                cop_corruption_available INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'territories' => "
            CREATE TABLE IF NOT EXISTS territories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                borough TEXT NOT NULL,
                neighborhood TEXT,
                coordinates TEXT,
                size_sq_blocks INTEGER DEFAULT 4,
                population_density TEXT DEFAULT 'medium',
                police_presence TEXT DEFAULT 'moderate',
                gentrification_level TEXT DEFAULT 'none',
                average_income TEXT DEFAULT 'working_class',
                cannabis_tolerance TEXT DEFAULT 'neutral',
                competition_level INTEGER DEFAULT 3,
                customer_demand INTEGER DEFAULT 50,
                heat_level INTEGER DEFAULT 10,
                is_contested INTEGER DEFAULT 0,
                controlled_by INTEGER NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (controlled_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ",
        'street_dealers' => "
            CREATE TABLE IF NOT EXISTS street_dealers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                nickname TEXT,
                territory_id INTEGER NOT NULL,
                aggression_level TEXT DEFAULT 'moderate',
                street_smarts INTEGER DEFAULT 50,
                violence_tendency INTEGER DEFAULT 30,
                customer_base INTEGER DEFAULT 10,
                product_quality TEXT DEFAULT 'low',
                cash_on_hand REAL DEFAULT 500.00,
                inventory_size INTEGER DEFAULT 20,
                respect_level INTEGER DEFAULT 0,
                heat_level INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                spawn_level INTEGER DEFAULT 10,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (territory_id) REFERENCES territories(id)
            )
        ",
        'nyc_cops' => "
            CREATE TABLE IF NOT EXISTS nyc_cops (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                badge_number TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                rank_title TEXT DEFAULT 'Officer',
                precinct INTEGER NOT NULL,
                corruption_level TEXT DEFAULT 'clean',
                bribe_threshold REAL DEFAULT NULL,
                loyalty_price REAL DEFAULT NULL,
                specialties TEXT,
                heat_reduction_ability INTEGER DEFAULT 10,
                territory_coverage TEXT,
                last_bribe TIMESTAMP NULL,
                total_bribes_taken REAL DEFAULT 0.00,
                reliability_score INTEGER DEFAULT 50,
                is_active INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'dealer_actions' => "
            CREATE TABLE IF NOT EXISTS dealer_actions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                dealer_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER,
                action_type TEXT NOT NULL,
                severity TEXT DEFAULT 'minor',
                success INTEGER DEFAULT 0,
                consequences TEXT,
                player_response TEXT DEFAULT 'ignore',
                outcome_description TEXT,
                money_involved REAL DEFAULT 0.00,
                reputation_change INTEGER DEFAULT 0,
                occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (dealer_id) REFERENCES street_dealers(id) ON DELETE CASCADE
            )
        ",
        'player_cop_relations' => "
            CREATE TABLE IF NOT EXISTS player_cop_relations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                cop_id INTEGER NOT NULL,
                relationship_type TEXT DEFAULT 'unknown',
                trust_level INTEGER DEFAULT 0,
                last_interaction TIMESTAMP NULL,
                total_bribes_paid REAL DEFAULT 0.00,
                services_used INTEGER DEFAULT 0,
                times_betrayed INTEGER DEFAULT 0,
                protection_active INTEGER DEFAULT 0,
                protection_expires TIMESTAMP NULL,
                notes TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (cop_id) REFERENCES nyc_cops(id) ON DELETE CASCADE,
                UNIQUE(user_id, cop_id)
            )
        ",
        'territory_control' => "
            CREATE TABLE IF NOT EXISTS territory_control (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                territory_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                control_percentage REAL DEFAULT 0.00,
                influence_points INTEGER DEFAULT 0,
                established_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_defended TIMESTAMP NULL,
                times_challenged INTEGER DEFAULT 0,
                revenue_per_day REAL DEFAULT 0.00,
                protection_level INTEGER DEFAULT 0,
                status TEXT DEFAULT 'expanding',
                FOREIGN KEY (territory_id) REFERENCES territories(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(territory_id, user_id)
            )
        ",
        'street_events' => "
            CREATE TABLE IF NOT EXISTS street_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                severity TEXT DEFAULT 'minor',
                territory_id INTEGER,
                dealer_id INTEGER,
                cop_id INTEGER,
                description TEXT NOT NULL,
                choices TEXT,
                selected_choice TEXT,
                outcome TEXT,
                money_impact REAL DEFAULT 0.00,
                reputation_impact INTEGER DEFAULT 0,
                heat_impact INTEGER DEFAULT 0,
                experience_gained INTEGER DEFAULT 0,
                resolved INTEGER DEFAULT 0,
                occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (territory_id) REFERENCES territories(id) ON DELETE SET NULL,
                FOREIGN KEY (dealer_id) REFERENCES street_dealers(id) ON DELETE SET NULL,
                FOREIGN KEY (cop_id) REFERENCES nyc_cops(id) ON DELETE SET NULL
            )
        ",
        'player_security' => "
            CREATE TABLE IF NOT EXISTS player_security (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                bodyguard_level INTEGER DEFAULT 0,
                security_budget REAL DEFAULT 0.00,
                safe_house_level INTEGER DEFAULT 0,
                early_warning_system INTEGER DEFAULT 0,
                police_scanner INTEGER DEFAULT 0,
                corrupt_cop_network INTEGER DEFAULT 0,
                street_informants INTEGER DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        "
    ];
    
    // Create tables
    foreach ($tables as $table_name => $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Created table: {$table_name}\n";
        } catch (PDOException $e) {
            echo "⚠ Error creating {$table_name}: " . $e->getMessage() . "\n";
        }
    }
    
    // Insert test user
    try {
        $pdo->prepare("INSERT OR IGNORE INTO users (id, username, email, password_hash, balance) VALUES (1, 'testplayer', 'test@smokeout.nyc', ?, 5000.00)")
             ->execute([password_hash('testpass', PASSWORD_DEFAULT)]);
        echo "✓ Created test user\n";
    } catch (PDOException $e) {
        echo "⚠ Test user creation: " . $e->getMessage() . "\n";
    }
    
    // Insert level requirements
    $levels = [
        [1, 0, 'Green Rookie', 'Just starting out in the cannabis game', '{"features": ["basic_growing", "simple_sales"]}', '{"cash": 100, "seeds": 3}', 0.00, 0, 0],
        [5, 2500, 'Small Timer', 'Getting noticed in the neighborhood', '{"features": ["customer_base", "quality_control"]}', '{"cash": 500, "equipment": "basic_lights"}', 0.00, 0, 0],
        [10, 7500, 'Street Player', 'Making moves on the block', '{"features": ["territory_awareness", "dealer_radar"]}', '{"cash": 1000}', 0.05, 1, 0],
        [12, 10000, 'Corner Hustler', 'First dealer encounters possible', '{"features": ["basic_security", "street_intel"]}', '{"cash": 1500}', 0.10, 1, 0],
        [15, 15000, 'Block Captain', 'Dealers actively compete with you', '{"features": ["reputation_system", "intimidation"]}', '{"cash": 2500}', 0.20, 2, 1],
        [20, 25000, 'Neighborhood Boss', 'Territory control becomes important', '{"features": ["territory_control", "crew_management"]}', '{"cash": 5000}', 0.35, 2, 1],
        [25, 40000, 'District Player', 'Multiple territories, serious competition', '{"features": ["multi_territory", "advanced_security"]}', '{"cash": 10000}', 0.50, 3, 1],
        [30, 60000, 'Borough Heavyweight', 'City-wide recognition and threats', '{"features": ["police_network", "major_operations"]}', '{"cash": 20000}', 0.65, 4, 1],
        [35, 90000, 'City Kingpin', 'Top of the food chain', '{"features": ["empire_management", "political_influence"]}', '{"cash": 50000}', 0.80, 5, 1]
    ];
    
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO level_requirements (level, experience_needed, title, description, unlocks, rewards, street_dealer_spawn_chance, max_dealers_per_territory, cop_corruption_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($levels as $level) {
        $stmt->execute($level);
    }
    echo "✓ Inserted level requirements\n";
    
    // Insert territories
    $territories = [
        ['Washington Heights', 'Manhattan', 'Washington Heights', 6, 'high', 'moderate', 'early', 'working_class', 'tolerant', 4, 70, 25],
        ['East New York', 'Brooklyn', 'East New York', 8, 'high', 'heavy', 'early', 'low', 'neutral', 5, 80, 40],
        ['Jamaica', 'Queens', 'Jamaica', 10, 'very_high', 'moderate', 'moderate', 'working_class', 'neutral', 3, 65, 30],
        ['Mott Haven', 'Bronx', 'Mott Haven', 5, 'high', 'heavy', 'moderate', 'low', 'tolerant', 4, 75, 45],
        ['St. George', 'Staten Island', 'St. George', 4, 'medium', 'light', 'advanced', 'middle_class', 'hostile', 2, 40, 15],
        ['Harlem', 'Manhattan', 'Central Harlem', 7, 'very_high', 'moderate', 'advanced', 'middle_class', 'tolerant', 3, 60, 20],
        ['Bed-Stuy', 'Brooklyn', 'Bedford-Stuyvesant', 9, 'high', 'moderate', 'advanced', 'middle_class', 'very_tolerant', 2, 55, 18],
        ['Astoria', 'Queens', 'Astoria', 6, 'high', 'light', 'complete', 'upper_middle', 'neutral', 1, 35, 10],
        ['Soundview', 'Bronx', 'Soundview', 7, 'high', 'heavy', 'none', 'low', 'tolerant', 5, 85, 50]
    ];
    
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO territories (name, borough, neighborhood, size_sq_blocks, population_density, police_presence, gentrification_level, average_income, cannabis_tolerance, competition_level, customer_demand, heat_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($territories as $territory) {
        $stmt->execute($territory);
    }
    echo "✓ Inserted territories\n";
    
    // Insert corrupt cops
    $cops = [
        ['12345', 'Officer Mike Romano', 'Officer', 34, 'minor', 500.00, 2000.00, '["patrol_routes", "minor_violations"]', 15, '["Washington Heights"]'],
        ['23456', 'Detective Sarah Chen', 'Detective', 75, 'moderate', 1500.00, 5000.00, '["evidence_handling", "case_delays"]', 30, '["East New York", "Bed-Stuy"]'],
        ['34567', 'Sergeant Tony Martinez', 'Sergeant', 103, 'dirty', 1000.00, 3500.00, '["raid_warnings", "witness_intimidation"]', 25, '["Jamaica", "Astoria"]'],
        ['45678', 'Lieutenant Frank O\'Brien', 'Lieutenant', 40, 'totally_corrupt', 3000.00, 10000.00, '["case_dismissal", "evidence_tampering", "protection"]', 50, '["Mott Haven", "Soundview"]'],
        ['56789', 'Captain Maria Rodriguez', 'Captain', 26, 'clean', null, null, '[]', 0, '["Harlem"]']
    ];
    
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO nyc_cops (badge_number, name, rank_title, precinct, corruption_level, bribe_threshold, loyalty_price, specialties, heat_reduction_ability, territory_coverage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($cops as $cop) {
        $stmt->execute($cop);
    }
    echo "✓ Inserted NYC cops\n";
    
    echo "\n🎮 Street Game Database initialized successfully!\n";
    echo "Database type: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    echo "Test user created: username='testplayer', password='testpass', id=1\n";
    echo "Use token 'user_1' for API testing\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>