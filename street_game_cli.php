#!/usr/bin/env php
<?php
/**
 * SmokeoutNYC Street Game CLI/REPL
 * Interactive command-line interface for the street-level gaming system
 */

// Colors and styling
class Colors {
    const RESET = "\033[0m";
    const GREEN = "\033[32m";
    const RED = "\033[31m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const PURPLE = "\033[35m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const BOLD = "\033[1m";
    const DIM = "\033[2m";
}

class StreetGameCLI {
    private $api_base = 'http://localhost:8080/api/street_game.php';
    private $auth_token = 'user_1';
    private $running = true;
    
    public function __construct() {
        $this->clearScreen();
        $this->showWelcome();
        $this->checkConnection();
    }
    
    private function clearScreen() {
        system('clear');
    }
    
    private function showWelcome() {
        echo Colors::GREEN . Colors::BOLD;
        echo "╔══════════════════════════════════════════════════════════════════════╗\n";
        echo "║                    🌿 SMOKEOUT NYC STREET GAME 🌿                    ║\n";
        echo "║                     Command Line Interface                           ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════╝\n";
        echo Colors::RESET . Colors::CYAN;
        echo "Welcome to the streets of NYC. Build your cannabis empire, but watch out\n";
        echo "for rival dealers, corrupt cops, and street-level challenges.\n\n";
        echo Colors::RESET;
    }
    
    private function checkConnection() {
        echo Colors::YELLOW . "🔗 Checking API connection...\n" . Colors::RESET;
        
        $stats = $this->apiCall('get_player_stats');
        if ($stats) {
            echo Colors::GREEN . "✅ Connected successfully!\n" . Colors::RESET;
            $this->showPlayerStats($stats);
        } else {
            echo Colors::RED . "❌ API server not running. Start with: php -S localhost:8080 -t .\n" . Colors::RESET;
            exit(1);
        }
        echo "\n";
    }
    
    private function apiCall($action, $method = 'GET', $data = null) {
        $url = $this->api_base . '?action=' . $action;
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Authorization: Bearer ' . $this->auth_token,
                    'Content-Type: application/json'
                ],
                'ignore_errors' => true
            ]
        ];
        
        if ($data) {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            return null;
        }
        
        return json_decode($result, true);
    }
    
    public function run() {
        // For demonstration, let's run a few commands automatically
        $demo_commands = ['help', 'stats', 'dealers', 'cops', 'territories'];
        
        if (getenv('DEMO_MODE') === '1') {
            echo Colors::YELLOW . "🎮 Running in demo mode - showing key features:\n\n" . Colors::RESET;
            foreach ($demo_commands as $cmd) {
                echo Colors::GREEN . "🏙️  smokeout" . Colors::CYAN . " > " . Colors::RESET . $cmd . "\n";
                $this->processCommand($cmd);
                echo "\n" . str_repeat("─", 70) . "\n";
                sleep(1);
            }
            echo Colors::CYAN . "\n✨ Demo complete! Run without DEMO_MODE=1 for interactive mode.\n" . Colors::RESET;
            return;
        }
        
        // Try to use readline if available, fallback to fgets
        $use_readline = function_exists('readline');
        
        while ($this->running) {
            $this->showPrompt();
            
            if ($use_readline) {
                $input = readline('');
                if ($input === false) break; // EOF or Ctrl+C
                readline_add_history($input);
            } else {
                $input = trim(fgets(STDIN));
            }
            
            if (empty($input)) continue;
            
            $this->processCommand($input);
        }
    }
    
    private function showPrompt() {
        echo Colors::GREEN . "🏙️  smokeout" . Colors::CYAN . " > " . Colors::RESET;
    }
    
    private function processCommand($input) {
        $parts = explode(' ', $input);
        $command = strtolower($parts[0]);
        $args = array_slice($parts, 1);
        
        switch ($command) {
            case 'help':
            case 'h':
                $this->showHelp();
                break;
                
            case 'status':
            case 'stats':
            case 'me':
                $this->showDetailedStats();
                break;
                
            case 'dealers':
            case 'competition':
                $this->showDealers();
                break;
                
            case 'cops':
            case 'police':
                $this->showCorruptCops();
                break;
                
            case 'territories':
            case 'map':
                $this->showTerritories();
                break;
                
            case 'events':
                $this->showStreetEvents();
                break;
                
            case 'level':
            case 'xp':
                $amount = isset($args[0]) ? intval($args[0]) : 500;
                $this->addExperience($amount);
                break;
                
            case 'expand':
                if (isset($args[0])) {
                    $this->expandTerritory(intval($args[0]));
                } else {
                    echo Colors::RED . "Usage: expand <territory_id>\n" . Colors::RESET;
                }
                break;
                
            case 'bribe':
                if (isset($args[0]) && isset($args[1])) {
                    $this->bribeCop(intval($args[0]), intval($args[1]));
                } else {
                    echo Colors::RED . "Usage: bribe <cop_id> <amount>\n" . Colors::RESET;
                }
                break;
                
            case 'resolve':
                if (isset($args[0]) && isset($args[1])) {
                    $this->resolveEvent(intval($args[0]), $args[1]);
                } else {
                    echo Colors::RED . "Usage: resolve <event_id> <choice>\n" . Colors::RESET;
                }
                break;
                
            case 'respond':
                if (isset($args[0]) && isset($args[1])) {
                    $this->respondToDealer(intval($args[0]), $args[1]);
                } else {
                    echo Colors::RED . "Usage: respond <action_id> <response>\n" . Colors::RESET;
                }
                break;
                
            case 'actions':
                $this->showDealerActions();
                break;
                
            case 'refresh':
            case 'r':
                $this->clearScreen();
                $this->showWelcome();
                $this->checkConnection();
                break;
                
            case 'clear':
            case 'cls':
                $this->clearScreen();
                break;
                
            case 'quit':
            case 'exit':
            case 'q':
                $this->running = false;
                echo Colors::GREEN . "Stay safe on the streets! 🌿\n" . Colors::RESET;
                break;
                
            default:
                echo Colors::RED . "Unknown command: $command\n" . Colors::RESET;
                echo Colors::YELLOW . "Type 'help' for available commands\n" . Colors::RESET;
        }
        
        echo "\n";
    }
    
    private function showHelp() {
        echo Colors::BOLD . "📖 Available Commands:\n\n" . Colors::RESET;
        
        $commands = [
            ['Command', 'Description'],
            ['--------', '-----------'],
            ['help, h', 'Show this help message'],
            ['status, stats, me', 'Show detailed player statistics'],
            ['dealers, competition', 'View active street dealers'],
            ['cops, police', 'Show corrupt cops available for bribes'],
            ['territories, map', 'Display territory control information'],
            ['events', 'View active street events'],
            ['actions', 'Show dealer actions against you'],
            ['level [amount]', 'Gain experience (default 500)'],
            ['expand <id>', 'Expand territory control with $1000 investment'],
            ['bribe <cop_id> <amount>', 'Bribe a corrupt cop'],
            ['respond <action_id> <choice>', 'Respond to dealer action'],
            ['resolve <event_id> <choice>', 'Resolve a street event'],
            ['refresh, r', 'Refresh screen and reload data'],
            ['clear, cls', 'Clear screen'],
            ['quit, exit, q', 'Exit the game']
        ];
        
        foreach ($commands as $i => $cmd) {
            if ($i <= 1) {
                echo Colors::CYAN . Colors::BOLD;
            } else {
                echo Colors::WHITE;
            }
            printf("%-25s %s\n", $cmd[0], $cmd[1]);
        }
        echo Colors::RESET;
    }
    
    private function showPlayerStats($stats) {
        echo Colors::BOLD . "👤 Player Profile:\n" . Colors::RESET;
        echo Colors::GREEN . "Level: " . Colors::BOLD . $stats['current_level'] . Colors::RESET;
        echo Colors::DIM . " (" . $stats['respect_level'] . ")\n" . Colors::RESET;
        echo Colors::CYAN . "Experience: " . number_format($stats['total_experience']) . "\n" . Colors::RESET;
        echo Colors::PURPLE . "Reputation: " . $stats['reputation_score'] . "\n" . Colors::RESET;
        echo Colors::YELLOW . "Territories Controlled: " . $stats['territories_controlled'] . "\n" . Colors::RESET;
    }
    
    private function showDetailedStats() {
        $stats = $this->apiCall('get_player_stats');
        if (!$stats) {
            echo Colors::RED . "❌ Failed to load player stats\n" . Colors::RESET;
            return;
        }
        
        echo Colors::BOLD . "📊 Detailed Player Statistics:\n\n" . Colors::RESET;
        
        echo Colors::GREEN . "🎮 Character Info:\n" . Colors::RESET;
        echo "  Level: " . Colors::BOLD . $stats['current_level'] . Colors::RESET . " (" . $stats['respect_level'] . ")\n";
        echo "  Experience: " . number_format($stats['experience_points']) . " / " . number_format($stats['total_experience']) . " total\n";
        echo "  Reputation: " . $stats['reputation_score'] . " points\n";
        echo "  Street Cred: " . $stats['street_cred'] . "\n\n";
        
        echo Colors::YELLOW . "🏢 Business Empire:\n" . Colors::RESET;
        echo "  Territories Controlled: " . $stats['territories_controlled'] . "\n";
        echo "  Daily Revenue: $" . number_format($stats['daily_territory_revenue']) . "\n\n";
        
        echo Colors::BLUE . "🛡️ Security Network:\n" . Colors::RESET;
        echo "  Bodyguard Level: " . $stats['bodyguard_level'] . "\n";
        echo "  Security Budget: $" . number_format($stats['security_budget']) . "\n";
        echo "  Corrupt Cops: " . $stats['corrupt_cop_network'] . "\n";
        echo "  Street Informants: " . $stats['street_informants'] . "\n";
    }
    
    private function showDealers() {
        $dealers = $this->apiCall('get_active_dealers');
        if (!$dealers) {
            echo Colors::RED . "❌ Failed to load dealers\n" . Colors::RESET;
            return;
        }
        
        echo Colors::BOLD . "🔫 Active Street Dealers:\n\n" . Colors::RESET;
        
        if (empty($dealers)) {
            echo Colors::YELLOW . "🌱 No active dealers in your area yet.\n";
            echo "Reach level 10+ to encounter street competition!\n" . Colors::RESET;
            return;
        }
        
        foreach ($dealers as $dealer) {
            $color = $this->getAggressionColor($dealer['aggression_level']);
            echo $color . "👤 " . $dealer['name'] . ' "' . $dealer['nickname'] . '"' . Colors::RESET . "\n";
            echo "   📍 " . $dealer['territory_name'] . ", " . $dealer['borough'] . "\n";
            echo "   🎯 Aggression: " . ucfirst($dealer['aggression_level']) . " (Violence: " . $dealer['violence_tendency'] . "%)\n";
            echo "   👥 Customer Base: " . $dealer['customer_base'] . "\n";
            if ($dealer['recent_actions'] > 0) {
                echo Colors::RED . "   ⚠️  Recent Actions: " . $dealer['recent_actions'] . Colors::RESET . "\n";
            }
            echo "\n";
        }
    }
    
    private function showCorruptCops() {
        $cops = $this->apiCall('get_corrupt_cops');
        if (!$cops) {
            echo Colors::RED . "❌ Failed to load cops\n" . Colors::RESET;
            return;
        }
        
        echo Colors::BOLD . "👮‍♂️ Corrupt Police Network:\n\n" . Colors::RESET;
        
        foreach ($cops as $cop) {
            $color = $this->getCorruptionColor($cop['corruption_level']);
            echo $color . "🚔 " . $cop['name'] . Colors::RESET . "\n";
            echo "   📋 " . $cop['rank_title'] . " - Precinct " . $cop['precinct'] . "\n";
            echo "   💰 Corruption: " . ucfirst(str_replace('_', ' ', $cop['corruption_level'])) . "\n";
            
            if ($cop['bribe_threshold']) {
                echo "   💵 Min Bribe: $" . number_format($cop['bribe_threshold']) . "\n";
            }
            
            if ($cop['protection_active']) {
                echo Colors::GREEN . "   🛡️  PROTECTED" . Colors::RESET . "\n";
            } else if ($cop['relationship_type'] === 'friendly') {
                echo Colors::YELLOW . "   🤝 Friendly" . Colors::RESET . "\n";
            }
            
            echo "   🔧 Specialties: " . implode(', ', json_decode($cop['specialties'] ?? '[]', true)) . "\n";
            echo "   📊 Reliability: " . $cop['reliability_score'] . "%\n\n";
        }
        
        echo Colors::DIM . "Use 'bribe <cop_id> <amount>' to pay for protection\n" . Colors::RESET;
    }
    
    private function showTerritories() {
        $territories = $this->apiCall('get_territories');
        if (!$territories) {
            echo Colors::RED . "❌ Failed to load territories\n" . Colors::RESET;
            return;
        }
        
        echo Colors::BOLD . "🗺️ NYC Territory Control:\n\n" . Colors::RESET;
        
        foreach ($territories as $territory) {
            echo Colors::CYAN . "📍 " . $territory['name'] . " (" . $territory['borough'] . ")" . Colors::RESET . "\n";
            echo "   🏛️  Police Presence: " . ucfirst($territory['police_presence']) . "\n";
            echo "   📈 Customer Demand: " . $territory['customer_demand'] . "%\n";
            
            if ($territory['player_control'] > 0) {
                echo Colors::GREEN . "   🏆 Your Control: " . $territory['player_control'] . "%" . Colors::RESET . "\n";
                if ($territory['daily_revenue'] > 0) {
                    echo Colors::YELLOW . "   💰 Daily Revenue: $" . number_format($territory['daily_revenue']) . Colors::RESET . "\n";
                }
            } else {
                echo Colors::DIM . "   🚫 No control\n" . Colors::RESET;
            }
            
            if ($territory['active_dealers'] > 0) {
                echo Colors::RED . "   ⚠️  Active Dealers: " . $territory['active_dealers'] . Colors::RESET . "\n";
            }
            
            echo "\n";
        }
        
        echo Colors::DIM . "Use 'expand <territory_id>' to invest $1000 in territory control\n" . Colors::RESET;
    }
    
    private function showStreetEvents() {
        $events = $this->apiCall('get_street_events');
        if (!$events) {
            echo Colors::RED . "❌ Failed to load events\n" . Colors::RESET;
            return;
        }
        
        echo Colors::BOLD . "⚡ Active Street Events:\n\n" . Colors::RESET;
        
        if (empty($events)) {
            echo Colors::YELLOW . "🌿 No active street events.\n";
            echo "Keep playing to encounter random challenges!\n" . Colors::RESET;
            return;
        }
        
        foreach ($events as $event) {
            $severityColor = $this->getSeverityColor($event['severity']);
            echo $severityColor . "🚨 " . strtoupper(str_replace('_', ' ', $event['event_type'])) . Colors::RESET . "\n";
            echo "   📍 " . ($event['territory_name'] ?? 'Unknown Location') . "\n";
            echo "   📋 " . $event['description'] . "\n";
            
            if (!$event['resolved'] && $event['choices']) {
                $choices = json_decode($event['choices'], true);
                echo Colors::YELLOW . "   🎯 Choices:\n" . Colors::RESET;
                foreach ($choices as $key => $value) {
                    echo "      " . $key . ": " . $value . "\n";
                }
                echo Colors::DIM . "   Use 'resolve " . $event['id'] . " <choice>' to respond\n" . Colors::RESET;
            } else {
                echo Colors::GREEN . "   ✅ Resolved\n" . Colors::RESET;
            }
            echo "\n";
        }
    }
    
    private function showDealerActions() {
        $actions = $this->apiCall('get_dealer_actions');
        if (!$actions) {
            echo Colors::RED . "❌ Failed to load dealer actions\n" . Colors::RESET;
            return;
        }
        
        echo Colors::BOLD . "🎯 Dealer Actions Against You:\n\n" . Colors::RESET;
        
        if (empty($actions)) {
            echo Colors::YELLOW . "🌱 No dealer actions yet. Stay vigilant!\n" . Colors::RESET;
            return;
        }
        
        foreach ($actions as $action) {
            echo Colors::RED . "⚔️  " . $action['dealer_name'] . ' "' . $action['nickname'] . '"' . Colors::RESET . "\n";
            echo "   📍 " . $action['territory_name'] . "\n";
            echo "   🎯 Action: " . ucfirst(str_replace('_', ' ', $action['action_type'])) . "\n";
            echo "   📋 " . $action['outcome_description'] . "\n";
            
            if ($action['player_response'] === 'ignore') {
                echo Colors::YELLOW . "   ⏳ Awaiting response...\n";
                echo "   Options: negotiate, retaliate, call_police, bribe_cops, flee\n";
                echo Colors::DIM . "   Use 'respond " . $action['id'] . " <choice>' to respond\n" . Colors::RESET;
            } else {
                echo "   ✅ Response: " . ucfirst(str_replace('_', ' ', $action['player_response'])) . "\n";
                if ($action['money_involved'] != 0) {
                    $color = $action['money_involved'] > 0 ? Colors::GREEN : Colors::RED;
                    echo "   💰 " . $color . ($action['money_involved'] > 0 ? '+' : '') . "$" . $action['money_involved'] . Colors::RESET . "\n";
                }
                if ($action['reputation_change'] != 0) {
                    $color = $action['reputation_change'] > 0 ? Colors::GREEN : Colors::RED;
                    echo "   📊 " . $color . ($action['reputation_change'] > 0 ? '+' : '') . $action['reputation_change'] . " rep" . Colors::RESET . "\n";
                }
            }
            echo "\n";
        }
    }
    
    private function addExperience($amount) {
        $result = $this->apiCall('add_experience', 'POST', [
            'experience' => $amount,
            'reason' => 'CLI interaction'
        ]);
        
        if ($result && $result['success']) {
            echo Colors::GREEN . "⚡ Gained " . $result['experience_gained'] . " XP!" . Colors::RESET . "\n";
            if ($result['level_up']) {
                echo Colors::YELLOW . Colors::BOLD . "🎉 LEVEL UP! You're now level " . $result['new_level'] . "!" . Colors::RESET . "\n";
            }
            echo "Total Experience: " . number_format($result['total_experience']) . "\n";
        } else {
            echo Colors::RED . "❌ Failed to gain experience\n" . Colors::RESET;
        }
    }
    
    private function expandTerritory($territoryId) {
        $result = $this->apiCall('expand_territory', 'POST', [
            'territory_id' => $territoryId,
            'investment' => 1000
        ]);
        
        if ($result && $result['success']) {
            echo Colors::GREEN . "🏙️ Successfully invested in " . $result['territory'] . "!" . Colors::RESET . "\n";
            echo "Influence gained: " . $result['influence_gained'] . " points\n";
            echo "Investment: $" . number_format($result['investment']) . "\n";
        } else {
            echo Colors::RED . "❌ Failed to expand territory. Check your funds and level!\n" . Colors::RESET;
        }
    }
    
    private function bribeCop($copId, $amount) {
        $result = $this->apiCall('bribe_cop', 'POST', [
            'cop_id' => $copId,
            'amount' => $amount,
            'service_type' => 'protection'
        ]);
        
        if ($result && $result['success']) {
            echo Colors::GREEN . "👮‍♂️ " . $result['message'] . Colors::RESET . "\n";
            echo "Amount paid: $" . number_format($result['amount_paid']) . "\n";
        } else {
            echo Colors::RED . "❌ Failed to bribe cop. Check your funds!\n" . Colors::RESET;
        }
    }
    
    private function resolveEvent($eventId, $choice) {
        $result = $this->apiCall('resolve_event', 'POST', [
            'event_id' => $eventId,
            'choice' => $choice
        ]);
        
        if ($result && $result['success']) {
            echo Colors::GREEN . "⚡ Event resolved!" . Colors::RESET . "\n";
            $outcome = $result['outcome'];
            
            if ($outcome['money_change'] != 0) {
                $color = $outcome['money_change'] > 0 ? Colors::GREEN : Colors::RED;
                echo $color . "💰 Money: " . ($outcome['money_change'] > 0 ? '+' : '') . "$" . $outcome['money_change'] . Colors::RESET . "\n";
            }
            if ($outcome['reputation_change'] != 0) {
                $color = $outcome['reputation_change'] > 0 ? Colors::GREEN : Colors::RED;
                echo $color . "📊 Reputation: " . ($outcome['reputation_change'] > 0 ? '+' : '') . $outcome['reputation_change'] . Colors::RESET . "\n";
            }
            if ($outcome['experience'] > 0) {
                echo Colors::CYAN . "⚡ Experience: +" . $outcome['experience'] . Colors::RESET . "\n";
            }
        } else {
            echo Colors::RED . "❌ Failed to resolve event\n" . Colors::RESET;
        }
    }
    
    private function respondToDealer($actionId, $response) {
        $result = $this->apiCall('respond_to_dealer', 'POST', [
            'action_id' => $actionId,
            'response' => $response
        ]);
        
        if ($result && $result['success']) {
            echo Colors::GREEN . "⚔️ Response successful!" . Colors::RESET . "\n";
            $outcome = $result['outcome'];
            echo "Outcome: " . $outcome['description'] . "\n";
            
            if ($outcome['money_change'] != 0) {
                $color = $outcome['money_change'] > 0 ? Colors::GREEN : Colors::RED;
                echo $color . "💰 " . ($outcome['money_change'] > 0 ? '+' : '') . "$" . $outcome['money_change'] . Colors::RESET . "\n";
            }
            if ($outcome['reputation_change'] != 0) {
                $color = $outcome['reputation_change'] > 0 ? Colors::GREEN : Colors::RED;
                echo $color . "📊 " . ($outcome['reputation_change'] > 0 ? '+' : '') . $outcome['reputation_change'] . " reputation" . Colors::RESET . "\n";
            }
        } else {
            echo Colors::RED . "❌ Failed to respond to dealer\n" . Colors::RESET;
        }
    }
    
    private function getAggressionColor($level) {
        switch ($level) {
            case 'passive': return Colors::GREEN;
            case 'moderate': return Colors::YELLOW;
            case 'aggressive': return Colors::RED;
            case 'violent': return Colors::RED . Colors::BOLD;
            default: return Colors::WHITE;
        }
    }
    
    private function getCorruptionColor($level) {
        switch ($level) {
            case 'clean': return Colors::WHITE;
            case 'minor': return Colors::YELLOW;
            case 'moderate': return Colors::YELLOW . Colors::BOLD;
            case 'dirty': return Colors::RED;
            case 'totally_corrupt': return Colors::RED . Colors::BOLD;
            default: return Colors::WHITE;
        }
    }
    
    private function getSeverityColor($severity) {
        switch ($severity) {
            case 'minor': return Colors::CYAN;
            case 'moderate': return Colors::YELLOW;
            case 'serious': return Colors::RED;
            case 'critical': return Colors::RED . Colors::BOLD;
            default: return Colors::WHITE;
        }
    }
}

// Run the CLI
$cli = new StreetGameCLI();
$cli->run();
?>