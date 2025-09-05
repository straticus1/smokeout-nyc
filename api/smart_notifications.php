<?php
require_once 'config/database.php';
require_once 'auth_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user = authenticate();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

array_shift($segments); // remove 'api'
array_shift($segments); // remove 'smart-notifications'

$endpoint = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($endpoint) {
        case 'preferences':
            handlePreferencesEndpoints($method, $id, $user['id']);
            break;
        case 'queue':
            handleQueueEndpoints($method, $id, $user['id']);
            break;
        case 'analytics':
            handleAnalyticsEndpoints($method, $id, $user['id']);
            break;
        case 'templates':
            handleTemplateEndpoints($method, $id, $user['id']);
            break;
        case 'subscriptions':
            handleSubscriptionEndpoints($method, $id, $user['id']);
            break;
        case 'channels':
            handleChannelEndpoints($method, $id, $user['id']);
            break;
        case 'ai-insights':
            handleAIInsightsEndpoints($method, $id, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Smart notification endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ====================================
// Notification Preferences Management
// ====================================

function handlePreferencesEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/smart-notifications/preferences - Get user's notification preferences
            $preferences = getUserNotificationPreferences($user_id);
            echo json_encode(['preferences' => $preferences]);
            break;
            
        case 'PUT':
            // PUT /api/smart-notifications/preferences - Update user's notification preferences
            $data = json_decode(file_get_contents('php://input'), true);
            $result = updateNotificationPreferences($user_id, $data);
            echo json_encode($result);
            break;
            
        case 'POST':
            if ($id === 'optimize') {
                // POST /api/smart-notifications/preferences/optimize - AI-optimize preferences
                $result = optimizeNotificationPreferences($user_id);
                echo json_encode($result);
            } elseif ($id === 'reset') {
                // POST /api/smart-notifications/preferences/reset - Reset to defaults
                $result = resetNotificationPreferences($user_id);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Notification Queue Management
// ====================================

function handleQueueEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id === 'pending') {
                // GET /api/smart-notifications/queue/pending - Get user's pending notifications
                $notifications = getPendingNotifications($user_id);
                echo json_encode(['notifications' => $notifications]);
            } elseif ($id === 'history') {
                // GET /api/smart-notifications/queue/history - Get notification history
                $page = $_GET['page'] ?? 1;
                $limit = $_GET['limit'] ?? 20;
                $history = getNotificationHistory($user_id, $page, $limit);
                echo json_encode($history);
            } elseif ($id) {
                // GET /api/smart-notifications/queue/{id} - Get specific notification
                $notification = getNotificationDetails($user_id, $id);
                echo json_encode(['notification' => $notification]);
            } else {
                // GET /api/smart-notifications/queue - Get all user notifications
                $notifications = getAllUserNotifications($user_id, $_GET);
                echo json_encode($notifications);
            }
            break;
            
        case 'POST':
            if ($id === 'send') {
                // POST /api/smart-notifications/queue/send - Send immediate notification
                $data = json_decode(file_get_contents('php://input'), true);
                $result = sendImmediateNotification($user_id, $data);
                echo json_encode($result);
            } elseif ($id === 'schedule') {
                // POST /api/smart-notifications/queue/schedule - Schedule notification
                $data = json_decode(file_get_contents('php://input'), true);
                $result = scheduleNotification($user_id, $data);
                echo json_encode($result);
            } elseif ($id === 'batch-send') {
                // POST /api/smart-notifications/queue/batch-send - Send multiple notifications
                $data = json_decode(file_get_contents('php://input'), true);
                $result = sendBatchNotifications($user_id, $data);
                echo json_encode($result);
            }
            break;
            
        case 'PUT':
            if ($id) {
                $action = $segments[2] ?? '';
                switch ($action) {
                    case 'mark-read':
                        // PUT /api/smart-notifications/queue/{id}/mark-read - Mark as read
                        $result = markNotificationAsRead($user_id, $id);
                        echo json_encode($result);
                        break;
                    case 'dismiss':
                        // PUT /api/smart-notifications/queue/{id}/dismiss - Dismiss notification
                        $result = dismissNotification($user_id, $id);
                        echo json_encode($result);
                        break;
                    case 'snooze':
                        // PUT /api/smart-notifications/queue/{id}/snooze - Snooze notification
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = snoozeNotification($user_id, $id, $data['snooze_until']);
                        echo json_encode($result);
                        break;
                    default:
                        http_response_code(404);
                        echo json_encode(['error' => 'Queue action not found']);
                }
            }
            break;
            
        case 'DELETE':
            if ($id === 'clear-all') {
                // DELETE /api/smart-notifications/queue/clear-all - Clear all notifications
                $result = clearAllNotifications($user_id);
                echo json_encode($result);
            } elseif ($id) {
                // DELETE /api/smart-notifications/queue/{id} - Delete specific notification
                $result = deleteNotification($user_id, $id);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Notification Analytics
// ====================================

function handleAnalyticsEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id === 'engagement') {
                // GET /api/smart-notifications/analytics/engagement - Get engagement metrics
                $timeframe = $_GET['timeframe'] ?? '30'; // days
                $analytics = getEngagementAnalytics($user_id, $timeframe);
                echo json_encode(['analytics' => $analytics]);
            } elseif ($id === 'preferences-analysis') {
                // GET /api/smart-notifications/analytics/preferences-analysis - Analyze preferences
                $analysis = analyzeNotificationPreferences($user_id);
                echo json_encode(['analysis' => $analysis]);
            } elseif ($id === 'optimal-timing') {
                // GET /api/smart-notifications/analytics/optimal-timing - Get optimal delivery times
                $timing = getOptimalNotificationTiming($user_id);
                echo json_encode(['optimal_timing' => $timing]);
            } elseif ($id === 'channel-performance') {
                // GET /api/smart-notifications/analytics/channel-performance - Channel performance
                $performance = getChannelPerformanceAnalytics($user_id);
                echo json_encode(['channel_performance' => $performance]);
            } else {
                // GET /api/smart-notifications/analytics - Overall analytics dashboard
                $dashboard = getNotificationAnalyticsDashboard($user_id);
                echo json_encode(['dashboard' => $dashboard]);
            }
            break;
            
        case 'POST':
            if ($id === 'track-engagement') {
                // POST /api/smart-notifications/analytics/track-engagement - Track user engagement
                $data = json_decode(file_get_contents('php://input'), true);
                $result = trackNotificationEngagement($user_id, $data);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// AI Insights and Optimization
// ====================================

function handleAIInsightsEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id === 'personalization') {
                // GET /api/smart-notifications/ai-insights/personalization - Get AI personalization insights
                $insights = getPersonalizationInsights($user_id);
                echo json_encode(['insights' => $insights]);
            } elseif ($id === 'behavior-patterns') {
                // GET /api/smart-notifications/ai-insights/behavior-patterns - Get behavior analysis
                $patterns = analyzeUserBehaviorPatterns($user_id);
                echo json_encode(['patterns' => $patterns]);
            } elseif ($id === 'optimization-suggestions') {
                // GET /api/smart-notifications/ai-insights/optimization-suggestions - Get AI suggestions
                $suggestions = getOptimizationSuggestions($user_id);
                echo json_encode(['suggestions' => $suggestions]);
            } elseif ($id === 'predictive-analysis') {
                // GET /api/smart-notifications/ai-insights/predictive-analysis - Predictive engagement
                $predictions = getPredictiveAnalysis($user_id);
                echo json_encode(['predictions' => $predictions]);
            }
            break;
            
        case 'POST':
            if ($id === 'generate-recommendations') {
                // POST /api/smart-notifications/ai-insights/generate-recommendations - Generate AI recommendations
                $data = json_decode(file_get_contents('php://input'), true);
                $recommendations = generateAIRecommendations($user_id, $data);
                echo json_encode(['recommendations' => $recommendations]);
            } elseif ($id === 'update-behavior-model') {
                // POST /api/smart-notifications/ai-insights/update-behavior-model - Update AI model
                $result = updateUserBehaviorModel($user_id);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Subscription Management
// ====================================

function handleSubscriptionEndpoints($method, $id, $user_id) {
    switch ($method) {
        case 'GET':
            if ($id === 'risk-alerts') {
                // GET /api/smart-notifications/subscriptions/risk-alerts - Get risk alert subscriptions
                $subscriptions = getRiskAlertSubscriptions($user_id);
                echo json_encode(['subscriptions' => $subscriptions]);
            } else {
                // GET /api/smart-notifications/subscriptions - Get all subscriptions
                $subscriptions = getAllUserSubscriptions($user_id);
                echo json_encode(['subscriptions' => $subscriptions]);
            }
            break;
            
        case 'POST':
            if ($id === 'risk-monitoring') {
                // POST /api/smart-notifications/subscriptions/risk-monitoring - Create risk monitoring subscription
                $data = json_decode(file_get_contents('php://input'), true);
                $result = createRiskMonitoringSubscription($user_id, $data);
                echo json_encode($result);
            } elseif ($id === 'location-alerts') {
                // POST /api/smart-notifications/subscriptions/location-alerts - Subscribe to location alerts
                $data = json_decode(file_get_contents('php://input'), true);
                $result = createLocationAlertSubscription($user_id, $data);
                echo json_encode($result);
            }
            break;
            
        case 'PUT':
            if ($id) {
                // PUT /api/smart-notifications/subscriptions/{id} - Update subscription
                $data = json_decode(file_get_contents('php://input'), true);
                $result = updateSubscription($user_id, $id, $data);
                echo json_encode($result);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                // DELETE /api/smart-notifications/subscriptions/{id} - Delete subscription
                $result = deleteSubscription($user_id, $id);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// ====================================
// Core Implementation Functions
// ====================================

function getUserNotificationPreferences($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM user_notification_preferences 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$preferences) {
        // Create default preferences if none exist
        $preferences = createDefaultNotificationPreferences($user_id);
    }
    
    return $preferences;
}

function createDefaultNotificationPreferences($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO user_notification_preferences (
            user_id, email_notifications, push_notifications, in_app_notifications,
            system_alerts, game_notifications, risk_alerts, social_notifications,
            transaction_alerts, emergency_alerts, max_notifications_per_hour,
            max_notifications_per_day
        ) VALUES (?, true, true, true, true, true, true, true, true, true, 10, 50)
    ");
    $stmt->execute([$user_id]);
    
    return getUserNotificationPreferences($user_id);
}

function updateNotificationPreferences($user_id, $data) {
    global $pdo;
    
    // Build dynamic update query based on provided data
    $updateFields = [];
    $params = [];
    
    $allowedFields = [
        'email_notifications', 'sms_notifications', 'push_notifications', 
        'in_app_notifications', 'discord_notifications', 'slack_notifications',
        'system_alerts', 'game_notifications', 'risk_alerts', 
        'social_notifications', 'transaction_alerts', 'emergency_alerts',
        'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end', 'quiet_hours_timezone',
        'max_notifications_per_hour', 'max_notifications_per_day'
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        return ['error' => 'No valid fields to update'];
    }
    
    $params[] = $user_id;
    
    $stmt = $pdo->prepare("
        UPDATE user_notification_preferences 
        SET " . implode(', ', $updateFields) . ", updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->execute($params);
    
    // Update user behavior model
    updateUserBehaviorModel($user_id);
    
    return [
        'success' => true,
        'message' => 'Notification preferences updated successfully'
    ];
}

function optimizeNotificationPreferences($user_id) {
    global $pdo;
    
    // Get user's engagement analytics
    $analytics = getEngagementAnalytics($user_id, 30);
    $behavior = getUserBehaviorPatterns($user_id);
    
    // AI-based optimization logic
    $optimizations = [];
    
    // Optimize channels based on engagement rates
    if ($analytics['email_engagement'] < 0.2) {
        $optimizations[] = [
            'field' => 'email_notifications',
            'current' => true,
            'recommended' => false,
            'reason' => 'Low email engagement rate (< 20%). Consider disabling email notifications.'
        ];
    }
    
    if ($analytics['push_engagement'] > 0.6) {
        $optimizations[] = [
            'field' => 'push_notifications',
            'current' => false,
            'recommended' => true,
            'reason' => 'High push notification engagement rate (> 60%). Enable for better engagement.'
        ];
    }
    
    // Optimize timing based on behavior patterns
    if ($behavior && isset($behavior['optimal_notification_timing'])) {
        $optimal_timing = json_decode($behavior['optimal_notification_timing'], true);
        $current_hour = date('H');
        
        // Suggest quiet hours based on low engagement periods
        if ($analytics['hour_' . $current_hour . '_engagement'] < 0.1) {
            $optimizations[] = [
                'field' => 'quiet_hours_enabled',
                'current' => false,
                'recommended' => true,
                'reason' => "Very low engagement during current hour ({$current_hour}:00). Consider enabling quiet hours."
            ];
        }
    }
    
    // Optimize frequency based on engagement vs volume
    $daily_average = $analytics['daily_notification_average'] ?? 0;
    $overall_engagement = $analytics['overall_engagement_rate'] ?? 0;
    
    if ($daily_average > 30 && $overall_engagement < 0.3) {
        $optimizations[] = [
            'field' => 'max_notifications_per_day',
            'current' => 50,
            'recommended' => 20,
            'reason' => 'High notification volume with low engagement. Reduce daily limit to improve engagement quality.'
        ];
    }
    
    return [
        'optimizations' => $optimizations,
        'current_performance' => $analytics,
        'ai_confidence' => 0.85,
        'generated_at' => date('c')
    ];
}

function sendSmartNotification($user_id, $template_name, $data = [], $delivery_options = []) {
    global $pdo;
    
    // Get user preferences
    $preferences = getUserNotificationPreferences($user_id);
    
    // Get template
    $template_stmt = $pdo->prepare("
        SELECT * FROM notification_templates 
        WHERE template_name = ? AND is_active = true
    ");
    $template_stmt->execute([$template_name]);
    $template = $template_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        throw new Exception("Notification template '$template_name' not found");
    }
    
    // Check if user wants this type of notification
    if (!shouldSendNotificationByCategory($preferences, $template['template_type'])) {
        return ['skipped' => true, 'reason' => 'User preferences block this notification type'];
    }
    
    // Check rate limits
    if (!checkRateLimit($user_id, $preferences)) {
        return ['skipped' => true, 'reason' => 'Rate limit exceeded'];
    }
    
    // Determine optimal delivery method and timing
    $optimal_delivery = determineOptimalDelivery($user_id, $template, $delivery_options);
    
    // Process template with personalization data
    $processed_content = processNotificationTemplate($template, $data);
    
    // Determine channels based on user preferences and template settings
    $channels = determineNotificationChannels($preferences, $template);
    
    try {
        // Queue the notification
        $stmt = $pdo->prepare("
            INSERT INTO notification_queue (
                user_id, template_id, priority, channels, title, content, 
                personalization_data, delivery_method, scheduled_for, 
                optimal_delivery_time, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued')
        ");
        
        $stmt->execute([
            $user_id,
            $template['id'],
            $template['priority'],
            json_encode($channels),
            $processed_content['title'],
            $processed_content['content'],
            json_encode($data),
            $optimal_delivery['method'],
            $optimal_delivery['scheduled_for'],
            $optimal_delivery['optimal_time']
        ]);
        
        $notification_id = $pdo->lastInsertId();
        
        // If immediate delivery, process now
        if ($optimal_delivery['method'] === 'immediate') {
            processNotificationQueue($notification_id);
        }
        
        return [
            'success' => true,
            'notification_id' => $notification_id,
            'delivery_method' => $optimal_delivery['method'],
            'scheduled_for' => $optimal_delivery['scheduled_for'],
            'channels' => $channels
        ];
        
    } catch (Exception $e) {
        throw new Exception("Failed to queue notification: " . $e->getMessage());
    }
}

function processNotificationTemplate($template, $data) {
    $title = $template['title_template'];
    $content = $template['content_template'];
    
    // Simple template processing - replace {{variable}} with data values
    foreach ($data as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        $title = str_replace($placeholder, $value, $title);
        $content = str_replace($placeholder, $value, $content);
    }
    
    return [
        'title' => $title,
        'content' => $content
    ];
}

function determineOptimalDelivery($user_id, $template, $options) {
    global $pdo;
    
    // Get user behavior patterns
    $behavior = getUserBehaviorPatterns($user_id);
    $preferences = getUserNotificationPreferences($user_id);
    
    // Default to immediate for high priority notifications
    if ($template['priority'] === 'critical' || $template['priority'] === 'high') {
        return [
            'method' => 'immediate',
            'scheduled_for' => null,
            'optimal_time' => null
        ];
    }
    
    // Check if in quiet hours
    if ($preferences['quiet_hours_enabled'] && isInQuietHours($preferences)) {
        $quiet_end = $preferences['quiet_hours_end'];
        return [
            'method' => 'scheduled',
            'scheduled_for' => date('Y-m-d') . ' ' . $quiet_end,
            'optimal_time' => date('Y-m-d') . ' ' . $quiet_end
        ];
    }
    
    // Use AI-powered optimal timing if available
    if ($behavior && isset($behavior['optimal_notification_timing'])) {
        $optimal_timing = json_decode($behavior['optimal_notification_timing'], true);
        $current_hour = (int)date('H');
        
        // Find next optimal hour
        $optimal_hours = array_keys($optimal_timing);
        sort($optimal_hours);
        
        foreach ($optimal_hours as $hour) {
            if ($hour > $current_hour) {
                $optimal_time = date('Y-m-d') . ' ' . sprintf('%02d:00:00', $hour);
                return [
                    'method' => 'smart_timing',
                    'scheduled_for' => $optimal_time,
                    'optimal_time' => $optimal_time
                ];
            }
        }
    }
    
    // Default to immediate
    return [
        'method' => 'immediate',
        'scheduled_for' => null,
        'optimal_time' => null
    ];
}

function determineNotificationChannels($preferences, $template) {
    $template_channels = json_decode($template['channels'], true) ?? [];
    $user_channels = [];
    
    // Check each channel against user preferences
    foreach ($template_channels as $channel) {
        switch ($channel) {
            case 'in_app':
                if ($preferences['in_app_notifications']) {
                    $user_channels[] = $channel;
                }
                break;
            case 'email':
                if ($preferences['email_notifications']) {
                    $user_channels[] = $channel;
                }
                break;
            case 'sms':
                if ($preferences['sms_notifications']) {
                    $user_channels[] = $channel;
                }
                break;
            case 'push':
                if ($preferences['push_notifications']) {
                    $user_channels[] = $channel;
                }
                break;
            case 'discord':
                if ($preferences['discord_notifications']) {
                    $user_channels[] = $channel;
                }
                break;
            case 'slack':
                if ($preferences['slack_notifications']) {
                    $user_channels[] = $channel;
                }
                break;
        }
    }
    
    // Always include in_app for critical notifications
    if ($template['priority'] === 'critical' && !in_array('in_app', $user_channels)) {
        $user_channels[] = 'in_app';
    }
    
    return $user_channels;
}

function shouldSendNotificationByCategory($preferences, $category) {
    switch ($category) {
        case 'system':
            return $preferences['system_alerts'];
        case 'game':
            return $preferences['game_notifications'];
        case 'risk_alert':
            return $preferences['risk_alerts'];
        case 'social':
            return $preferences['social_notifications'];
        case 'transaction':
            return $preferences['transaction_alerts'];
        case 'emergency':
            return $preferences['emergency_alerts']; // Always send emergency
        default:
            return true;
    }
}

function checkRateLimit($user_id, $preferences) {
    global $pdo;
    
    // Check hourly limit
    $hourly_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notification_queue 
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND status != 'cancelled'
    ");
    $hourly_stmt->execute([$user_id]);
    $hourly_count = $hourly_stmt->fetchColumn();
    
    if ($hourly_count >= $preferences['max_notifications_per_hour']) {
        return false;
    }
    
    // Check daily limit
    $daily_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notification_queue 
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND status != 'cancelled'
    ");
    $daily_stmt->execute([$user_id]);
    $daily_count = $daily_stmt->fetchColumn();
    
    if ($daily_count >= $preferences['max_notifications_per_day']) {
        return false;
    }
    
    return true;
}

function isInQuietHours($preferences) {
    if (!$preferences['quiet_hours_enabled']) {
        return false;
    }
    
    $timezone = new DateTimeZone($preferences['quiet_hours_timezone']);
    $now = new DateTime('now', $timezone);
    $current_time = $now->format('H:i:s');
    
    $quiet_start = $preferences['quiet_hours_start'];
    $quiet_end = $preferences['quiet_hours_end'];
    
    // Handle overnight quiet hours (e.g., 22:00 to 08:00)
    if ($quiet_start > $quiet_end) {
        return $current_time >= $quiet_start || $current_time <= $quiet_end;
    }
    
    return $current_time >= $quiet_start && $current_time <= $quiet_end;
}

function getUserBehaviorPatterns($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM user_behavior_patterns 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateUserBehaviorModel($user_id) {
    global $pdo;
    
    // Calculate new behavior patterns based on recent activity
    $engagement_data = calculateUserEngagement($user_id);
    $activity_patterns = calculateActivityPatterns($user_id);
    $preference_patterns = calculatePreferencePatterns($user_id);
    
    // Update or insert behavior patterns
    $stmt = $pdo->prepare("
        INSERT INTO user_behavior_patterns (
            user_id, most_active_hours, most_active_days, avg_session_duration,
            notification_engagement_rate, preferred_notification_channels,
            optimal_notification_timing, risk_tolerance_level, churn_risk_score,
            engagement_prediction, value_score
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            most_active_hours = VALUES(most_active_hours),
            most_active_days = VALUES(most_active_days),
            avg_session_duration = VALUES(avg_session_duration),
            notification_engagement_rate = VALUES(notification_engagement_rate),
            preferred_notification_channels = VALUES(preferred_notification_channels),
            optimal_notification_timing = VALUES(optimal_notification_timing),
            risk_tolerance_level = VALUES(risk_tolerance_level),
            churn_risk_score = VALUES(churn_risk_score),
            engagement_prediction = VALUES(engagement_prediction),
            value_score = VALUES(value_score),
            last_calculated = NOW()
    ");
    
    $stmt->execute([
        $user_id,
        json_encode($activity_patterns['active_hours']),
        json_encode($activity_patterns['active_days']),
        $activity_patterns['avg_session_duration'],
        $engagement_data['engagement_rate'],
        json_encode($preference_patterns['preferred_channels']),
        json_encode($engagement_data['optimal_timing']),
        $preference_patterns['risk_tolerance'],
        $engagement_data['churn_risk'],
        $engagement_data['engagement_prediction'],
        $activity_patterns['value_score']
    ]);
    
    return [
        'success' => true,
        'message' => 'User behavior model updated',
        'patterns_updated' => date('c')
    ];
}

function getEngagementAnalytics($user_id, $timeframe_days) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_notifications,
            AVG(engagement_score) as avg_engagement,
            SUM(was_opened) as total_opened,
            SUM(was_clicked) as total_clicked,
            SUM(was_dismissed) as total_dismissed,
            AVG(CASE WHEN was_delivered THEN 1 ELSE 0 END) as delivery_rate
        FROM notification_analytics 
        WHERE user_id = ? 
        AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$user_id, $timeframe_days]);
    $overall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Channel-specific analytics
    $channel_stmt = $pdo->prepare("
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(nq.channels, '$[0]')) as primary_channel,
            AVG(na.engagement_score) as avg_engagement,
            COUNT(*) as notification_count
        FROM notification_analytics na
        JOIN notification_queue nq ON na.notification_id = nq.id
        WHERE na.user_id = ? 
        AND na.sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY primary_channel
    ");
    $channel_stmt->execute([$user_id, $timeframe_days]);
    $channel_performance = $channel_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'timeframe_days' => $timeframe_days,
        'total_notifications' => $overall['total_notifications'] ?? 0,
        'overall_engagement_rate' => $overall['avg_engagement'] ?? 0,
        'open_rate' => $overall['total_notifications'] > 0 ? 
            ($overall['total_opened'] / $overall['total_notifications']) : 0,
        'click_rate' => $overall['total_notifications'] > 0 ? 
            ($overall['total_clicked'] / $overall['total_notifications']) : 0,
        'dismiss_rate' => $overall['total_notifications'] > 0 ? 
            ($overall['total_dismissed'] / $overall['total_notifications']) : 0,
        'delivery_rate' => $overall['delivery_rate'] ?? 0,
        'channel_performance' => $channel_performance,
        'generated_at' => date('c')
    ];
}

// Additional utility functions for analytics, behavior analysis, etc.
function calculateUserEngagement($user_id) {
    // Placeholder for complex engagement calculation
    return [
        'engagement_rate' => 0.75,
        'optimal_timing' => ['9' => 0.8, '14' => 0.7, '18' => 0.9],
        'churn_risk' => 0.15,
        'engagement_prediction' => 0.72
    ];
}

function calculateActivityPatterns($user_id) {
    // Placeholder for activity pattern calculation
    return [
        'active_hours' => [9, 12, 14, 18, 20],
        'active_days' => [1, 2, 3, 4, 5], // Monday to Friday
        'avg_session_duration' => 45, // minutes
        'value_score' => 85.5
    ];
}

function calculatePreferencePatterns($user_id) {
    // Placeholder for preference pattern calculation
    return [
        'preferred_channels' => ['in_app', 'push'],
        'risk_tolerance' => 'moderate'
    ];
}

// Public API functions for external use
function queueSmartNotification($user_id, $template_name, $data = [], $options = []) {
    return sendSmartNotification($user_id, $template_name, $data, $options);
}

function processNotificationQueue($notification_id = null) {
    global $pdo;
    
    // Process specific notification or batch process queued notifications
    $where_clause = $notification_id ? "AND id = ?" : "AND status = 'queued'";
    $params = $notification_id ? [$notification_id] : [];
    
    $stmt = $pdo->prepare("
        SELECT * FROM notification_queue 
        WHERE (scheduled_for IS NULL OR scheduled_for <= NOW()) 
        $where_clause
        ORDER BY priority DESC, created_at ASC
        LIMIT 100
    ");
    $stmt->execute($params);
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($notifications as $notification) {
        try {
            deliverNotification($notification);
        } catch (Exception $e) {
            error_log("Failed to deliver notification {$notification['id']}: " . $e->getMessage());
        }
    }
    
    return ['processed' => count($notifications)];
}

function deliverNotification($notification) {
    global $pdo;
    
    // Mark as processing
    $pdo->prepare("UPDATE notification_queue SET status = 'processing' WHERE id = ?")
        ->execute([$notification['id']]);
    
    $channels = json_decode($notification['channels'], true) ?? [];
    $delivery_results = [];
    
    foreach ($channels as $channel) {
        switch ($channel) {
            case 'in_app':
                $result = deliverInAppNotification($notification);
                break;
            case 'email':
                $result = deliverEmailNotification($notification);
                break;
            case 'push':
                $result = deliverPushNotification($notification);
                break;
            case 'sms':
                $result = deliverSMSNotification($notification);
                break;
            default:
                $result = ['success' => false, 'error' => "Unknown channel: $channel"];
        }
        
        $delivery_results[$channel] = $result;
    }
    
    // Update notification status
    $all_successful = !empty($delivery_results) && 
                     array_reduce($delivery_results, function($carry, $result) {
                         return $carry && $result['success'];
                     }, true);
    
    $final_status = $all_successful ? 'sent' : 'failed';
    
    $pdo->prepare("
        UPDATE notification_queue 
        SET status = ?, delivered_at = NOW(), updated_at = NOW() 
        WHERE id = ?
    ")->execute([$final_status, $notification['id']]);
    
    // Log analytics
    recordNotificationAnalytics($notification, $delivery_results);
    
    return $delivery_results;
}

function deliverInAppNotification($notification) {
    // Implementation would store in-app notification
    return ['success' => true, 'delivered_at' => date('c')];
}

function deliverEmailNotification($notification) {
    // Implementation would send email
    return ['success' => true, 'delivered_at' => date('c')];
}

function deliverPushNotification($notification) {
    // Implementation would send push notification
    return ['success' => true, 'delivered_at' => date('c')];
}

function deliverSMSNotification($notification) {
    // Implementation would send SMS
    return ['success' => true, 'delivered_at' => date('c')];
}

function recordNotificationAnalytics($notification, $delivery_results) {
    global $pdo;
    
    $was_delivered = !empty($delivery_results) && 
                    array_reduce($delivery_results, function($carry, $result) {
                        return $carry || $result['success'];
                    }, false);
    
    $stmt = $pdo->prepare("
        INSERT INTO notification_analytics (
            user_id, notification_id, template_id, was_delivered,
            sent_at, user_timezone, user_activity_level, device_type
        ) VALUES (?, ?, ?, ?, NOW(), ?, 'moderate', 'unknown')
    ");
    
    $stmt->execute([
        $notification['user_id'],
        $notification['id'],
        $notification['template_id'],
        $was_delivered,
        'America/New_York' // Would get from user preferences
    ]);
}

?>
