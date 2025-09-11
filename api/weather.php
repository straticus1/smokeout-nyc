<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'auth_helper.php';
require_once 'services/WeatherEffectsService.php';

// Initialize weather system
AutoWeatherSystem::startWeatherSimulation();

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri_parts = explode('/', trim($uri, '/'));

try {
    switch ($method) {
        case 'GET':
            handleGet($uri_parts);
            break;
        case 'POST':
            handlePost($uri_parts);
            break;
        case 'PUT':
            handlePut($uri_parts);
            break;
        case 'DELETE':
            handleDelete($uri_parts);
            break;
        default:
            sendJsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Weather API error: " . $e->getMessage());
    sendJsonResponse(['error' => 'Internal server error'], 500);
}

function handleGet($uri_parts) {
    $action = $uri_parts[2] ?? 'current';
    
    switch ($action) {
        case 'current':
            getCurrentWeather();
            break;
        case 'forecast':
            getWeatherForecast();
            break;
        case 'stats':
            getWeatherStats();
            break;
        case 'plant-impact':
            getPlantWeatherImpact();
            break;
        default:
            sendJsonResponse(['error' => 'Unknown action'], 400);
    }
}

function handlePost($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    $action = $uri_parts[2] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create-event':
            createWeatherEvent($input);
            break;
        case 'simulate-cycle':
            simulateWeatherCycle();
            break;
        default:
            sendJsonResponse(['error' => 'Unknown action'], 400);
    }
}

function handlePut($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    $action = $uri_parts[2] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'end-event':
            endWeatherEvent($input);
            break;
        default:
            sendJsonResponse(['error' => 'Unknown action'], 400);
    }
}

function handleDelete($uri_parts) {
    $user_id = authenticate();
    if (!$user_id) {
        sendJsonResponse(['error' => 'Authentication required'], 401);
        return;
    }
    
    sendJsonResponse(['error' => 'Delete operations not supported'], 405);
}

function getCurrentWeather() {
    try {
        $activeEffects = WeatherEffectsService::getActiveWeatherEffects();
        
        $response = [
            'current_time' => date('c'),
            'active_effects' => $activeEffects,
            'effects_count' => count($activeEffects)
        ];
        
        // Add summary of current conditions
        if (!empty($activeEffects)) {
            $totalGrowthModifier = 1.0;
            $totalYieldModifier = 1.0;
            $totalDiseaseRisk = 0.0;
            $dominantWeather = [];
            
            foreach ($activeEffects as $effect) {
                $totalGrowthModifier *= (1 + $effect['growth_modifier']);
                $totalYieldModifier *= (1 + $effect['yield_modifier']);
                $totalDiseaseRisk += $effect['disease_risk_modifier'];
                
                $dominantWeather[] = $effect['type'] . ' (' . $effect['severity'] . ')';
            }
            
            $response['summary'] = [
                'dominant_weather' => implode(', ', $dominantWeather),
                'overall_growth_impact' => ($totalGrowthModifier - 1.0) * 100, // As percentage
                'overall_yield_impact' => ($totalYieldModifier - 1.0) * 100,
                'disease_risk' => min($totalDiseaseRisk * 100, 100) // As percentage
            ];
        } else {
            $response['summary'] = [
                'dominant_weather' => 'Clear conditions',
                'overall_growth_impact' => 0,
                'overall_yield_impact' => 0,
                'disease_risk' => 0
            ];
        }
        
        sendJsonResponse($response);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get current weather: ' . $e->getMessage()], 500);
    }
}

function getWeatherForecast() {
    try {
        $forecast = WeatherEffectsService::getWeatherForecast();
        
        sendJsonResponse([
            'forecast_period' => '24 hours',
            'generated_at' => date('c'),
            'upcoming_events' => $forecast
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get forecast: ' . $e->getMessage()], 500);
    }
}

function getWeatherStats() {
    try {
        $days = $_GET['days'] ?? 7;
        $stats = WeatherEffectsService::getWeatherStats((int)$days);
        
        sendJsonResponse([
            'period_days' => (int)$days,
            'statistics' => $stats,
            'generated_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get weather statistics: ' . $e->getMessage()], 500);
    }
}

function getPlantWeatherImpact() {
    try {
        $plant_id = $_GET['plant_id'] ?? null;
        
        if (!$plant_id) {
            sendJsonResponse(['error' => 'Plant ID required'], 400);
            return;
        }
        
        // Get plant instance
        $plant = Plant::getById($plant_id);
        if (!$plant) {
            sendJsonResponse(['error' => 'Plant not found'], 404);
            return;
        }
        
        $plant_obj = new Plant();
        foreach ($plant as $key => $value) {
            $plant_obj->$key = $value;
        }
        
        $impact = $plant_obj->getWeatherImpactSummary();
        
        sendJsonResponse([
            'plant_id' => $plant_id,
            'weather_impact' => $impact,
            'current_weather' => WeatherEffectsService::getActiveWeatherEffects()
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to get plant weather impact: ' . $e->getMessage()], 500);
    }
}

function createWeatherEvent($input) {
    try {
        $type = $input['type'] ?? null;
        $severity = $input['severity'] ?? 'moderate';
        $duration = $input['duration_hours'] ?? 4;
        $description = $input['description'] ?? null;
        
        if (!$type) {
            sendJsonResponse(['error' => 'Weather type required'], 400);
            return;
        }
        
        $validTypes = ['heat_wave', 'cold_snap', 'rain_storm', 'drought', 'sunny', 'overcast', 'windy'];
        $validSeverities = ['mild', 'moderate', 'severe', 'extreme'];
        
        if (!in_array($type, $validTypes)) {
            sendJsonResponse(['error' => 'Invalid weather type'], 400);
            return;
        }
        
        if (!in_array($severity, $validSeverities)) {
            sendJsonResponse(['error' => 'Invalid severity level'], 400);
            return;
        }
        
        $eventId = WeatherEffectsService::createWeatherEvent($type, $severity, $duration, $description);
        
        sendJsonResponse([
            'success' => true,
            'event_id' => $eventId,
            'type' => $type,
            'severity' => $severity,
            'duration_hours' => $duration
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to create weather event: ' . $e->getMessage()], 500);
    }
}

function simulateWeatherCycle() {
    try {
        AutoWeatherSystem::processWeatherCycle();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Weather cycle processed',
            'current_weather' => WeatherEffectsService::getActiveWeatherEffects()
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to simulate weather cycle: ' . $e->getMessage()], 500);
    }
}

function endWeatherEvent($input) {
    try {
        $event_id = $input['event_id'] ?? null;
        
        if (!$event_id) {
            sendJsonResponse(['error' => 'Event ID required'], 400);
            return;
        }
        
        $result = WeatherEffectsService::endWeatherEvent($event_id);
        
        sendJsonResponse([
            'success' => true,
            'event_id' => $event_id,
            'ended_at' => date('c')
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'Failed to end weather event: ' . $e->getMessage()], 500);
    }
}
?>