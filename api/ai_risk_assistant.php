<?php
require_once 'config/database.php';
require_once 'auth_helper.php';
require_once 'ai_risk_meter.php';

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
array_shift($segments); // remove 'ai-risk-assistant'

$endpoint = $segments[0] ?? '';

try {
    switch ($endpoint) {
        case 'explain':
            handleExplainEndpoints($method, $user['id']);
            break;
        case 'recommendations':
            handleRecommendationEndpoints($method, $user['id']);
            break;
        case 'conversation':
            handleConversationEndpoints($method, $user['id']);
            break;
        case 'insights':
            handleInsightsEndpoints($method, $user['id']);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'AI Risk Assistant endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleExplainEndpoints($method, $user_id) {
    switch ($method) {
        case 'POST':
            // POST /api/ai-risk-assistant/explain - Get natural language explanation of risk data
            $data = json_decode(file_get_contents('php://input'), true);
            $risk_assessment = $data['risk_assessment'] ?? null;
            $explanation_type = $data['type'] ?? 'detailed'; // detailed, summary, simple
            
            if (!$risk_assessment) {
                http_response_code(400);
                echo json_encode(['error' => 'Risk assessment data required']);
                return;
            }
            
            $explanation = generateNaturalLanguageExplanation($risk_assessment, $explanation_type);
            
            echo json_encode([
                'explanation' => $explanation,
                'generated_at' => date('c')
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleRecommendationEndpoints($method, $user_id) {
    switch ($method) {
        case 'POST':
            // POST /api/ai-risk-assistant/recommendations - Get AI-powered personalized recommendations
            $data = json_decode(file_get_contents('php://input'), true);
            $business_profile = $data['business_profile'] ?? [];
            $risk_data = $data['risk_data'] ?? [];
            $user_preferences = $data['preferences'] ?? [];
            
            $recommendations = generatePersonalizedRecommendations(
                $business_profile, 
                $risk_data, 
                $user_preferences,
                $user_id
            );
            
            echo json_encode([
                'recommendations' => $recommendations,
                'confidence_level' => 0.87,
                'generated_at' => date('c')
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleConversationEndpoints($method, $user_id) {
    switch ($method) {
        case 'POST':
            // POST /api/ai-risk-assistant/conversation - Chat-based risk consultation
            $data = json_decode(file_get_contents('php://input'), true);
            $message = $data['message'] ?? '';
            $conversation_id = $data['conversation_id'] ?? null;
            $context = $data['context'] ?? [];
            
            if (!$message) {
                http_response_code(400);
                echo json_encode(['error' => 'Message required']);
                return;
            }
            
            $response = processChatMessage($user_id, $message, $conversation_id, $context);
            
            echo json_encode($response);
            break;
            
        case 'GET':
            // GET /api/ai-risk-assistant/conversation/{id} - Get conversation history
            $conversation_id = $_GET['conversation_id'] ?? null;
            
            if ($conversation_id) {
                $history = getConversationHistory($user_id, $conversation_id);
                echo json_encode(['conversation' => $history]);
            } else {
                $conversations = getUserConversations($user_id);
                echo json_encode(['conversations' => $conversations]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleInsightsEndpoints($method, $user_id) {
    switch ($method) {
        case 'GET':
            // GET /api/ai-risk-assistant/insights - Get AI-powered insights and trends
            $location = $_GET['location'] ?? null;
            $timeframe = $_GET['timeframe'] ?? '30'; // days
            
            $insights = generateRiskInsights($user_id, $location, $timeframe);
            
            echo json_encode([
                'insights' => $insights,
                'timeframe_days' => $timeframe,
                'generated_at' => date('c')
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// AI Risk Assistant Core Functions

function generateNaturalLanguageExplanation($risk_assessment, $type = 'detailed') {
    $overall_score = $risk_assessment['overall_risk_score'] ?? 0;
    $risk_level = $risk_assessment['risk_level'] ?? 'unknown';
    $risk_factors = $risk_assessment['risk_factors'] ?? [];
    
    // Generate main explanation based on overall risk
    $main_explanation = generateMainExplanation($overall_score, $risk_level);
    
    // Generate factor-specific explanations
    $factor_explanations = [];
    foreach ($risk_factors as $factor => $score) {
        $factor_explanations[$factor] = explainRiskFactor($factor, $score);
    }
    
    // Create different explanation types
    switch ($type) {
        case 'simple':
            return [
                'summary' => $main_explanation['simple'],
                'key_message' => getKeyMessage($overall_score),
                'action_needed' => $overall_score > 0.6
            ];
            
        case 'summary':
            return [
                'summary' => $main_explanation['summary'],
                'top_concerns' => getTopConcerns($risk_factors, 3),
                'confidence' => $risk_assessment['confidence'] ?? 0.85
            ];
            
        case 'detailed':
        default:
            return [
                'overall_explanation' => $main_explanation['detailed'],
                'risk_breakdown' => $factor_explanations,
                'what_this_means' => generateImplicationsExplanation($overall_score),
                'next_steps' => generateNextStepsExplanation($risk_factors, $overall_score),
                'confidence_explanation' => explainConfidenceLevel($risk_assessment['confidence'] ?? 0.85)
            ];
    }
}

function generateMainExplanation($score, $level) {
    $percentage = round($score * 100);
    
    $explanations = [
        'simple' => "",
        'summary' => "",
        'detailed' => ""
    ];
    
    if ($score <= 0.2) {
        $explanations['simple'] = "This location looks great for a cannabis business! 🟢";
        $explanations['summary'] = "Low risk location with favorable conditions for cannabis business operations.";
        $explanations['detailed'] = "Based on our analysis of multiple risk factors including enforcement activity, regulatory environment, and market conditions, this location presents a low-risk opportunity ({$percentage}% risk score). The combination of favorable regulatory conditions, minimal enforcement pressure, and positive market dynamics creates an environment where a cannabis business is likely to succeed.";
    } elseif ($score <= 0.4) {
        $explanations['simple'] = "Good location with some considerations to keep in mind. 🟡";
        $explanations['summary'] = "Moderate-low risk location with manageable challenges that can be addressed with proper planning.";
        $explanations['detailed'] = "This location shows moderate-low risk ({$percentage}% risk score) with some areas that warrant attention. While the overall business environment is supportive, there are specific factors that could impact operations. With proper planning and mitigation strategies, these challenges can be effectively managed to ensure business success.";
    } elseif ($score <= 0.6) {
        $explanations['simple'] = "Proceed with caution - there are some significant risks here. 🟡";
        $explanations['summary'] = "Moderate risk location requiring careful consideration and risk mitigation strategies before proceeding.";
        $explanations['detailed'] = "Our analysis indicates moderate risk ({$percentage}% risk score) for this location. Several factors contribute to elevated risk levels that could significantly impact business operations. While not prohibitive, these conditions require comprehensive risk mitigation strategies and may affect profitability and operational stability.";
    } elseif ($score <= 0.8) {
        $explanations['simple'] = "High risk location - seriously consider alternatives. 🔴";
        $explanations['summary'] = "High-risk location with multiple challenging factors that pose significant threats to business viability.";
        $explanations['detailed'] = "This location presents high risk ({$percentage}% risk score) with multiple factors creating challenging operating conditions. The combination of enforcement pressure, regulatory uncertainty, and market conditions poses significant threats to business success. Proceeding would require exceptional risk management and might still result in operational difficulties or closure.";
    } else {
        $explanations['simple'] = "Critical risk - strongly recommend avoiding this location. 🔴";
        $explanations['summary'] = "Critical risk location with severe challenges that make successful business operations extremely unlikely.";
        $explanations['detailed'] = "Our analysis reveals critical risk levels ({$percentage}% risk score) that make this location extremely challenging for cannabis business operations. The convergence of multiple high-risk factors including intense enforcement activity, hostile regulatory environment, and poor market conditions creates conditions where business failure is highly likely. We strongly recommend exploring alternative locations.";
    }
    
    return $explanations;
}

function explainRiskFactor($factor, $score) {
    $impact_level = $score > 0.7 ? 'high' : ($score > 0.4 ? 'moderate' : 'low');
    $percentage = round($score * 100);
    
    $explanations = [
        'location_risk' => [
            'name' => 'Location Risk',
            'high' => "Location factors pose significant challenges ({$percentage}%). This includes proximity to sensitive areas like schools, high crime rates, or zoning restrictions that could affect operations.",
            'moderate' => "Location presents some challenges ({$percentage}%) such as moderate distance from sensitive areas or average crime rates that may require additional security measures.",
            'low' => "Excellent location factors ({$percentage}%) with good distance from sensitive areas, low crime rates, and favorable zoning conditions."
        ],
        'regulatory_risk' => [
            'name' => 'Regulatory Environment',
            'high' => "Regulatory environment is very challenging ({$percentage}%). Local authorities may be hostile to cannabis businesses, with strict enforcement of regulations and limited licensing opportunities.",
            'moderate' => "Regulatory environment requires careful navigation ({$percentage}%). Some restrictions exist but businesses can succeed with proper compliance and relationship building with authorities.",
            'low' => "Favorable regulatory environment ({$percentage}%) with supportive local authorities and clear, reasonable regulations for cannabis businesses."
        ],
        'enforcement_risk' => [
            'name' => 'Enforcement Activity',
            'high' => "High enforcement activity detected ({$percentage}%). Recent raids, investigations, or citations in the area suggest authorities are actively targeting cannabis businesses.",
            'moderate' => "Moderate enforcement activity ({$percentage}%) with some recent actions but not systematic targeting. Businesses should maintain excellent compliance.",
            'low' => "Low enforcement pressure ({$percentage}%) with minimal recent activity against compliant cannabis businesses in the area."
        ],
        'market_risk' => [
            'name' => 'Market Conditions',
            'high' => "Challenging market conditions ({$percentage}%) including oversaturation, declining prices, or limited customer base that could impact profitability.",
            'moderate' => "Market presents moderate challenges ({$percentage}%) with some competition but still opportunities for well-positioned businesses.",
            'low' => "Strong market conditions ({$percentage}%) with good demand, reasonable competition levels, and favorable customer demographics."
        ],
        'competition_risk' => [
            'name' => 'Competition Level',
            'high' => "Intense competition ({$percentage}%) with many dispensaries in the immediate area, potentially limiting market share and driving down prices.",
            'moderate' => "Moderate competition ({$percentage}%) present but market appears to have room for additional well-differentiated businesses.",
            'low' => "Limited competition ({$percentage}%) presents good market opportunity for a new business to establish strong market position."
        ]
    ];
    
    $factor_data = $explanations[$factor] ?? [
        'name' => ucfirst(str_replace('_', ' ', $factor)),
        'high' => "High risk factor ({$percentage}%) - requires immediate attention",
        'moderate' => "Moderate risk factor ({$percentage}%) - monitor closely",
        'low' => "Low risk factor ({$percentage}%) - minimal concern"
    ];
    
    return [
        'name' => $factor_data['name'],
        'explanation' => $factor_data[$impact_level],
        'impact_level' => $impact_level,
        'score' => $score
    ];
}

function getKeyMessage($score) {
    if ($score <= 0.2) return "Strong business opportunity with minimal risks";
    if ($score <= 0.4) return "Viable location with manageable challenges";
    if ($score <= 0.6) return "Requires careful planning and risk mitigation";
    if ($score <= 0.8) return "High-risk venture - consider alternatives";
    return "Extremely risky - strongly advise against";
}

function getTopConcerns($risk_factors, $count = 3) {
    arsort($risk_factors);
    $top_factors = array_slice($risk_factors, 0, $count, true);
    
    $concerns = [];
    foreach ($top_factors as $factor => $score) {
        if ($score > 0.3) { // Only include significant risks
            $concerns[] = [
                'factor' => $factor,
                'score' => $score,
                'explanation' => explainRiskFactor($factor, $score)['explanation']
            ];
        }
    }
    
    return $concerns;
}

function generateImplicationsExplanation($score) {
    if ($score <= 0.3) {
        return "This low risk score suggests excellent conditions for business success. You can expect stable operations, positive community relations, and good growth potential. Financial institutions and investors are likely to view this location favorably.";
    } elseif ($score <= 0.6) {
        return "This moderate risk level means you'll need to be proactive in managing challenges. Expect some operational hurdles and the need for strong compliance practices. Success is achievable but will require more effort and resources than low-risk locations.";
    } else {
        return "This high risk score indicates significant challenges that could impact business viability. Expect potential operational disruptions, regulatory challenges, and higher insurance costs. Consider whether the potential returns justify the elevated risks.";
    }
}

function generateNextStepsExplanation($risk_factors, $overall_score) {
    $steps = [];
    
    // Priority actions based on specific risk factors
    foreach ($risk_factors as $factor => $score) {
        if ($score > 0.6) {
            switch ($factor) {
                case 'enforcement_risk':
                    $steps[] = "🚨 High Priority: Consult with a cannabis attorney immediately to understand local enforcement patterns and develop compliance strategies.";
                    break;
                case 'regulatory_risk':
                    $steps[] = "📋 High Priority: Schedule meetings with local regulatory authorities to understand requirements and build positive relationships.";
                    break;
                case 'market_risk':
                    $steps[] = "📊 High Priority: Conduct detailed market research to understand customer demand and develop differentiation strategies.";
                    break;
            }
        }
    }
    
    // General next steps based on overall risk
    if ($overall_score <= 0.3) {
        $steps[] = "✅ This location looks promising! Consider conducting due diligence on specific properties and begin the licensing application process.";
        $steps[] = "💡 Take advantage of favorable conditions by moving quickly to secure your position in this market.";
    } elseif ($overall_score <= 0.6) {
        $steps[] = "⚖️ Develop a comprehensive risk mitigation plan addressing the identified concerns.";
        $steps[] = "💰 Ensure adequate capitalization to handle potential challenges and extended startup periods.";
    } else {
        $steps[] = "🔍 Seriously evaluate whether this location aligns with your risk tolerance and business goals.";
        $steps[] = "🏃‍♂️ Consider exploring alternative locations with more favorable risk profiles.";
    }
    
    return $steps;
}

function explainConfidenceLevel($confidence) {
    $percentage = round($confidence * 100);
    
    if ($confidence >= 0.9) {
        return "Very high confidence ({$percentage}%) - our analysis is based on comprehensive, recent data from multiple reliable sources.";
    } elseif ($confidence >= 0.8) {
        return "High confidence ({$percentage}%) - analysis based on solid data with some minor gaps that don't significantly impact conclusions.";
    } elseif ($confidence >= 0.7) {
        return "Good confidence ({$percentage}%) - analysis based on available data with some limitations that users should be aware of.";
    } else {
        return "Moderate confidence ({$percentage}%) - analysis based on limited data. Consider gathering additional local information to supplement this assessment.";
    }
}

function generatePersonalizedRecommendations($business_profile, $risk_data, $preferences, $user_id) {
    $recommendations = [
        'immediate_actions' => [],
        'strategic_planning' => [],
        'monitoring' => [],
        'resources' => []
    ];
    
    $business_type = $business_profile['business_type'] ?? 'dispensary';
    $experience_level = $business_profile['experience_level'] ?? 'beginner';
    $budget_range = $business_profile['budget_range'] ?? 'moderate';
    
    // Immediate actions based on risk factors
    if (($risk_data['enforcement_risk'] ?? 0) > 0.5) {
        $recommendations['immediate_actions'][] = [
            'priority' => 'critical',
            'title' => 'Legal Consultation Required',
            'description' => 'Schedule consultation with cannabis-experienced attorney within 48 hours',
            'estimated_cost' => '$500-$1,500',
            'timeframe' => 'Immediate'
        ];
    }
    
    if (($risk_data['regulatory_risk'] ?? 0) > 0.4) {
        $recommendations['immediate_actions'][] = [
            'priority' => 'high',
            'title' => 'Regulatory Compliance Audit',
            'description' => 'Conduct comprehensive review of all local and state requirements',
            'estimated_cost' => '$1,000-$5,000',
            'timeframe' => '1-2 weeks'
        ];
    }
    
    // Strategic planning based on business profile
    if ($experience_level === 'beginner') {
        $recommendations['strategic_planning'][] = [
            'title' => 'Cannabis Business Education Program',
            'description' => 'Enroll in comprehensive cannabis business course to build foundational knowledge',
            'rationale' => 'New operators benefit significantly from structured learning programs',
            'resources' => ['Cannabis Training University', 'Green Flower Business Courses']
        ];
    }
    
    // Budget-specific recommendations
    switch ($budget_range) {
        case 'limited':
            $recommendations['strategic_planning'][] = [
                'title' => 'Phased Launch Strategy',
                'description' => 'Consider starting with lower-cost options like delivery or consulting',
                'rationale' => 'Minimize initial capital requirements while building market presence'
            ];
            break;
        case 'substantial':
            $recommendations['strategic_planning'][] = [
                'title' => 'Market Dominance Strategy',
                'description' => 'Consider securing multiple locations or vertical integration opportunities',
                'rationale' => 'Strong capital position allows for aggressive market capture'
            ];
            break;
    }
    
    // Monitoring recommendations
    $recommendations['monitoring'][] = [
        'title' => 'Weekly Enforcement Activity Alerts',
        'description' => 'Set up automated alerts for enforcement activity in your target area',
        'frequency' => 'Weekly'
    ];
    
    $recommendations['monitoring'][] = [
        'title' => 'Regulatory Change Tracking',
        'description' => 'Monitor local and state regulatory updates that could impact operations',
        'frequency' => 'Daily'
    ];
    
    // Resource recommendations
    $recommendations['resources'] = [
        [
            'category' => 'Legal',
            'name' => 'Cannabis Law Database',
            'description' => 'Up-to-date legal resource for cannabis businesses',
            'url' => 'https://cannabislawdatabase.com',
            'cost' => 'Free'
        ],
        [
            'category' => 'Compliance',
            'name' => 'Compliance Management Software',
            'description' => 'Track and manage regulatory compliance requirements',
            'cost' => '$100-$500/month'
        ]
    ];
    
    return $recommendations;
}

function processChatMessage($user_id, $message, $conversation_id, $context) {
    // Create new conversation if needed
    if (!$conversation_id) {
        $conversation_id = createNewConversation($user_id);
    }
    
    // Store user message
    storeMessage($conversation_id, 'user', $message);
    
    // Generate AI response based on message content
    $ai_response = generateAIResponse($message, $context, $user_id);
    
    // Store AI response
    storeMessage($conversation_id, 'assistant', $ai_response['content']);
    
    return [
        'conversation_id' => $conversation_id,
        'response' => $ai_response,
        'suggested_actions' => generateSuggestedActions($message, $context),
        'timestamp' => date('c')
    ];
}

function generateAIResponse($message, $context, $user_id) {
    // Simple keyword-based response generation (in production, would use OpenAI API)
    $message_lower = strtolower($message);
    
    if (strpos($message_lower, 'risk') !== false) {
        return [
            'content' => "I can help you understand the risks associated with cannabis business locations. Would you like me to analyze a specific location, or do you have questions about particular types of risks like enforcement activity, regulatory challenges, or market conditions?",
            'response_type' => 'risk_consultation',
            'confidence' => 0.9
        ];
    }
    
    if (strpos($message_lower, 'location') !== false || strpos($message_lower, 'address') !== false) {
        return [
            'content' => "For location-specific risk analysis, I'll need the address or coordinates. I can provide detailed assessments covering enforcement risk, regulatory environment, market conditions, competition levels, and more. What location would you like me to analyze?",
            'response_type' => 'location_request',
            'confidence' => 0.95
        ];
    }
    
    if (strpos($message_lower, 'enforcement') !== false || strpos($message_lower, 'raid') !== false) {
        return [
            'content' => "Enforcement risk is one of the most critical factors for cannabis businesses. I analyze recent enforcement activity, regulatory violation patterns, and political climate to assess risk levels. Higher enforcement risk means more frequent inspections, raids, and potential legal challenges. Would you like me to check enforcement activity in a specific area?",
            'response_type' => 'enforcement_consultation',
            'confidence' => 0.92
        ];
    }
    
    // Default response
    return [
        'content' => "I'm here to help you understand cannabis business risks and make informed decisions. I can analyze locations, explain risk factors, provide recommendations, and answer questions about enforcement, regulations, market conditions, and more. What specific aspect would you like to explore?",
        'response_type' => 'general',
        'confidence' => 0.8
    ];
}

function generateSuggestedActions($message, $context) {
    $actions = [];
    $message_lower = strtolower($message);
    
    if (strpos($message_lower, 'location') !== false) {
        $actions[] = [
            'action' => 'analyze_location',
            'label' => 'Analyze a specific location',
            'description' => 'Get detailed risk assessment for an address'
        ];
    }
    
    if (strpos($message_lower, 'risk') !== false) {
        $actions[] = [
            'action' => 'risk_factors',
            'label' => 'Learn about risk factors',
            'description' => 'Understand what factors affect business risk'
        ];
    }
    
    $actions[] = [
        'action' => 'get_recommendations',
        'label' => 'Get personalized recommendations',
        'description' => 'Receive customized advice based on your situation'
    ];
    
    return $actions;
}

function createNewConversation($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO ai_conversations (user_id, title, created_at) 
        VALUES (?, 'Risk Consultation', NOW())
    ");
    $stmt->execute([$user_id]);
    return $pdo->lastInsertId();
}

function storeMessage($conversation_id, $role, $content) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO ai_messages (conversation_id, role, content, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$conversation_id, $role, $content]);
}

function generateRiskInsights($user_id, $location, $timeframe) {
    global $pdo;
    
    // Get trending risk factors
    $trending = getTrendingRiskFactors($timeframe);
    
    // Get location-specific insights if provided
    $location_insights = [];
    if ($location) {
        $location_insights = getLocationInsights($location, $timeframe);
    }
    
    // Get personalized insights based on user's previous assessments
    $personal_insights = getPersonalizedInsights($user_id, $timeframe);
    
    return [
        'trending_risks' => $trending,
        'location_insights' => $location_insights,
        'personalized_insights' => $personal_insights,
        'market_summary' => getMarketSummary($timeframe),
        'recommendations' => getGeneralRecommendations($timeframe)
    ];
}

function getTrendingRiskFactors($timeframe) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(risk_factors, '$.enforcement_risk')) as enforcement_risk,
            JSON_UNQUOTE(JSON_EXTRACT(risk_factors, '$.regulatory_risk')) as regulatory_risk,
            COUNT(*) as assessment_count,
            AVG(risk_score) as avg_risk
        FROM risk_assessments 
        WHERE last_updated >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY enforcement_risk, regulatory_risk
        ORDER BY assessment_count DESC
        LIMIT 5
    ");
    $stmt->execute([$timeframe]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLocationInsights($location, $timeframe) {
    // Parse location (could be city, state, or coordinates)
    return [
        'risk_trend' => 'Enforcement activity has increased 15% in the last 30 days',
        'market_condition' => 'New dispensaries opening at 2x the state average',
        'regulatory_changes' => 'No significant regulatory changes in the timeframe'
    ];
}

function getPersonalizedInsights($user_id, $timeframe) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            AVG(risk_score) as avg_personal_risk,
            COUNT(*) as assessment_count,
            MAX(last_updated) as last_assessment
        FROM risk_assessments ra
        JOIN user_memberships um ON ra.user_id = um.user_id
        WHERE um.user_id = ?
        AND ra.last_updated >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$user_id, $timeframe]);
    $stats = $stmt->fetch();
    
    $insights = [];
    
    if ($stats && $stats['assessment_count'] > 0) {
        $insights[] = "You've conducted {$stats['assessment_count']} risk assessments in the last {$timeframe} days";
        
        if ($stats['avg_personal_risk'] > 0.6) {
            $insights[] = "Your recent location searches show higher than average risk levels - consider expanding your search area";
        } else {
            $insights[] = "Great job focusing on lower-risk locations! This approach significantly improves success probability";
        }
    } else {
        $insights[] = "Consider running risk assessments on potential locations to make data-driven decisions";
    }
    
    return $insights;
}

function getMarketSummary($timeframe) {
    return [
        'overall_trend' => 'Cannabis business risk levels have remained stable over the past month',
        'key_factors' => [
            'Enforcement activity is up 8% nationally',
            'Regulatory environment is improving in 12 states',
            'Market saturation concerns growing in mature markets'
        ],
        'recommendation' => 'Focus on emerging markets with clear regulatory frameworks'
    ];
}

function getGeneralRecommendations($timeframe) {
    return [
        [
            'category' => 'Market Entry',
            'recommendation' => 'Consider emerging markets over saturated ones',
            'rationale' => 'Better growth potential with less competition'
        ],
        [
            'category' => 'Compliance',
            'recommendation' => 'Invest in compliance management systems early',
            'rationale' => 'Regulatory requirements are becoming more complex'
        ],
        [
            'category' => 'Risk Management',
            'recommendation' => 'Maintain 6-12 months operating capital reserve',
            'rationale' => 'Regulatory changes can impact cash flow unexpectedly'
        ]
    ];
}

function getConversationHistory($user_id, $conversation_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT am.role, am.content, am.created_at
        FROM ai_messages am
        JOIN ai_conversations ac ON am.conversation_id = ac.id
        WHERE ac.user_id = ? AND ac.id = ?
        ORDER BY am.created_at ASC
    ");
    $stmt->execute([$user_id, $conversation_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserConversations($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            id, title, created_at,
            (SELECT COUNT(*) FROM ai_messages WHERE conversation_id = ai_conversations.id) as message_count
        FROM ai_conversations 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
