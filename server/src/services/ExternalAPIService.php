<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use Exception;

/**
 * External API Integration Service
 * 
 * Handles integration with various external APIs for enhanced functionality
 * including payment processing, AI services, geolocation, social features,
 * and third-party data sources.
 */
class ExternalAPIService
{
    private $httpClient;
    private $config;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'SmokeOutNYC/1.0',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);

        $this->config = [
            'stripe_secret' => env('STRIPE_SECRET_KEY'),
            'openai_api_key' => env('OPENAI_API_KEY'),
            'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
            'twilio_sid' => env('TWILIO_ACCOUNT_SID'),
            'twilio_token' => env('TWILIO_AUTH_TOKEN'),
            'sendgrid_api_key' => env('SENDGRID_API_KEY'),
            'firebase_server_key' => env('FIREBASE_SERVER_KEY'),
            'weather_api_key' => env('OPENWEATHERMAP_API_KEY'),
            'news_api_key' => env('NEWS_API_KEY')
        ];
    }

    /**
     * PAYMENT PROCESSING - Stripe Integration
     */
    public function processPayment($amount, $currency, $paymentMethodId, $customerId = null)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['stripe_secret'],
                'Stripe-Version' => '2023-10-16'
            ])->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $amount * 100, // Convert to cents
                'currency' => $currency,
                'payment_method' => $paymentMethodId,
                'customer' => $customerId,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'metadata' => [
                    'platform' => 'smokeout_nyc',
                    'timestamp' => now()->toISOString()
                ]
            ]);

            if ($response->successful()) {
                Log::info('Payment processed successfully', [
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_intent_id' => $response->json()['id']
                ]);

                return [
                    'success' => true,
                    'payment_intent_id' => $response->json()['id'],
                    'status' => $response->json()['status'],
                    'client_secret' => $response->json()['client_secret']
                ];
            }

            throw new Exception('Payment processing failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Payment processing error', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'currency' => $currency
            ]);

            return [
                'success' => false,
                'error' => 'Payment processing failed. Please try again.'
            ];
        }
    }

    /**
     * AI SERVICES - OpenAI Integration
     */
    public function generateAIRecommendations($businessData, $riskFactors = [], $context = 'compliance')
    {
        try {
            $prompt = $this->buildAIPrompt($businessData, $riskFactors, $context);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['openai_api_key'],
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert compliance advisor for NYC smoking regulations. Provide actionable, specific recommendations based on business data and risk factors.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $aiResponse = $response->json();
                $recommendations = $aiResponse['choices'][0]['message']['content'];

                // Parse and structure the recommendations
                $structuredRecommendations = $this->parseAIRecommendations($recommendations);

                Log::info('AI recommendations generated', [
                    'business_id' => $businessData['id'] ?? 'unknown',
                    'context' => $context,
                    'token_usage' => $aiResponse['usage']['total_tokens']
                ]);

                return [
                    'success' => true,
                    'recommendations' => $structuredRecommendations,
                    'raw_response' => $recommendations,
                    'confidence_score' => $this->calculateConfidenceScore($riskFactors)
                ];
            }

            throw new Exception('AI service request failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('AI recommendations error', [
                'error' => $e->getMessage(),
                'business_data' => $businessData,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Unable to generate AI recommendations at this time.'
            ];
        }
    }

    /**
     * GEOLOCATION SERVICES - Google Maps Integration
     */
    public function validateBusinessAddress($address, $city = 'New York', $state = 'NY')
    {
        try {
            $fullAddress = "{$address}, {$city}, {$state}";
            
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $fullAddress,
                'key' => $this->config['google_maps_api_key'],
                'components' => 'country:US|administrative_area:NY'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'OK' && !empty($data['results'])) {
                    $result = $data['results'][0];
                    
                    return [
                        'success' => true,
                        'formatted_address' => $result['formatted_address'],
                        'latitude' => $result['geometry']['location']['lat'],
                        'longitude' => $result['geometry']['location']['lng'],
                        'place_id' => $result['place_id'],
                        'address_components' => $result['address_components']
                    ];
                }
                
                return [
                    'success' => false,
                    'error' => 'Address not found or invalid'
                ];
            }

            throw new Exception('Geocoding request failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Address validation error', [
                'error' => $e->getMessage(),
                'address' => $address
            ]);

            return [
                'success' => false,
                'error' => 'Unable to validate address at this time.'
            ];
        }
    }

    /**
     * SMS NOTIFICATIONS - Twilio Integration
     */
    public function sendSMSNotification($phoneNumber, $message, $type = 'alert')
    {
        try {
            $response = Http::withBasicAuth(
                $this->config['twilio_sid'],
                $this->config['twilio_token']
            )->asForm()->post(
                "https://api.twilio.com/2010-04-01/Accounts/{$this->config['twilio_sid']}/Messages.json",
                [
                    'To' => $phoneNumber,
                    'From' => env('TWILIO_PHONE_NUMBER'),
                    'Body' => $message,
                    'StatusCallback' => url('/api/webhooks/twilio/status')
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('SMS sent successfully', [
                    'to' => $phoneNumber,
                    'message_sid' => $data['sid'],
                    'type' => $type
                ]);

                return [
                    'success' => true,
                    'message_sid' => $data['sid'],
                    'status' => $data['status']
                ];
            }

            throw new Exception('SMS sending failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('SMS notification error', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber,
                'type' => $type
            ]);

            return [
                'success' => false,
                'error' => 'Unable to send SMS notification.'
            ];
        }
    }

    /**
     * EMAIL NOTIFICATIONS - SendGrid Integration
     */
    public function sendEmailNotification($to, $subject, $htmlContent, $templateId = null)
    {
        try {
            $payload = [
                'personalizations' => [
                    [
                        'to' => [['email' => $to]],
                        'subject' => $subject
                    ]
                ],
                'from' => [
                    'email' => env('MAIL_FROM_ADDRESS', 'noreply@smokeoutnyc.com'),
                    'name' => 'SmokeOut NYC'
                ],
                'content' => [
                    [
                        'type' => 'text/html',
                        'value' => $htmlContent
                    ]
                ]
            ];

            if ($templateId) {
                $payload['template_id'] = $templateId;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['sendgrid_api_key'],
                'Content-Type' => 'application/json'
            ])->post('https://api.sendgrid.com/v3/mail/send', $payload);

            if ($response->successful() || $response->status() === 202) {
                Log::info('Email sent successfully', [
                    'to' => $to,
                    'subject' => $subject,
                    'template_id' => $templateId
                ]);

                return [
                    'success' => true,
                    'message' => 'Email sent successfully'
                ];
            }

            throw new Exception('Email sending failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Email notification error', [
                'error' => $e->getMessage(),
                'to' => $to,
                'subject' => $subject
            ]);

            return [
                'success' => false,
                'error' => 'Unable to send email notification.'
            ];
        }
    }

    /**
     * PUSH NOTIFICATIONS - Firebase Integration
     */
    public function sendPushNotification($tokens, $title, $body, $data = [])
    {
        try {
            if (!is_array($tokens)) {
                $tokens = [$tokens];
            }

            $payload = [
                'registration_ids' => $tokens,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'icon' => 'ic_notification',
                    'sound' => 'default'
                ],
                'data' => array_merge($data, [
                    'timestamp' => now()->toISOString(),
                    'platform' => 'smokeout_nyc'
                ])
            ];

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->config['firebase_server_key'],
                'Content-Type' => 'application/json'
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('Push notification sent', [
                    'success_count' => $result['success'],
                    'failure_count' => $result['failure'],
                    'title' => $title
                ]);

                return [
                    'success' => true,
                    'success_count' => $result['success'],
                    'failure_count' => $result['failure'],
                    'results' => $result['results']
                ];
            }

            throw new Exception('Push notification failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Push notification error', [
                'error' => $e->getMessage(),
                'title' => $title,
                'tokens_count' => count($tokens)
            ]);

            return [
                'success' => false,
                'error' => 'Unable to send push notification.'
            ];
        }
    }

    /**
     * WEATHER DATA - OpenWeatherMap Integration
     */
    public function getWeatherData($lat, $lon)
    {
        try {
            $cacheKey = "weather_data_{$lat}_{$lon}";
            
            return Cache::remember($cacheKey, 600, function () use ($lat, $lon) { // Cache for 10 minutes
                $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $this->config['weather_api_key'],
                    'units' => 'imperial'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    return [
                        'success' => true,
                        'temperature' => $data['main']['temp'],
                        'feels_like' => $data['main']['feels_like'],
                        'humidity' => $data['main']['humidity'],
                        'wind_speed' => $data['wind']['speed'],
                        'description' => $data['weather'][0]['description'],
                        'icon' => $data['weather'][0]['icon']
                    ];
                }

                throw new Exception('Weather API request failed: ' . $response->body());
            });
        } catch (Exception $e) {
            Log::error('Weather data error', [
                'error' => $e->getMessage(),
                'lat' => $lat,
                'lon' => $lon
            ]);

            return [
                'success' => false,
                'error' => 'Unable to fetch weather data.'
            ];
        }
    }

    /**
     * NEWS AND UPDATES - News API Integration
     */
    public function getRelevantNews($query = 'NYC smoking regulations', $limit = 10)
    {
        try {
            $cacheKey = "news_" . md5($query);
            
            return Cache::remember($cacheKey, 1800, function () use ($query, $limit) { // Cache for 30 minutes
                $response = Http::get('https://newsapi.org/v2/everything', [
                    'q' => $query,
                    'language' => 'en',
                    'sortBy' => 'publishedAt',
                    'pageSize' => $limit,
                    'apiKey' => $this->config['news_api_key']
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    $articles = array_map(function ($article) {
                        return [
                            'title' => $article['title'],
                            'description' => $article['description'],
                            'url' => $article['url'],
                            'published_at' => $article['publishedAt'],
                            'source' => $article['source']['name']
                        ];
                    }, $data['articles']);

                    return [
                        'success' => true,
                        'articles' => $articles,
                        'total_results' => $data['totalResults']
                    ];
                }

                throw new Exception('News API request failed: ' . $response->body());
            });
        } catch (Exception $e) {
            Log::error('News data error', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);

            return [
                'success' => false,
                'error' => 'Unable to fetch news data.'
            ];
        }
    }

    /**
     * HELPER METHODS
     */
    private function buildAIPrompt($businessData, $riskFactors, $context)
    {
        $prompt = "Business Information:\n";
        $prompt .= "- Type: " . ($businessData['type'] ?? 'Unknown') . "\n";
        $prompt .= "- Location: " . ($businessData['address'] ?? 'Unknown') . "\n";
        $prompt .= "- Size: " . ($businessData['square_footage'] ?? 'Unknown') . " sq ft\n";
        
        if (!empty($riskFactors)) {
            $prompt .= "\nRisk Factors:\n";
            foreach ($riskFactors as $factor) {
                $prompt .= "- {$factor}\n";
            }
        }

        $prompt .= "\nContext: {$context}\n";
        $prompt .= "\nPlease provide specific, actionable recommendations for this business to maintain compliance with NYC smoking regulations. Include prioritized action items and potential cost-saving measures.";

        return $prompt;
    }

    private function parseAIRecommendations($recommendations)
    {
        // Parse the AI response into structured recommendations
        // This would include parsing bullet points, priorities, etc.
        $lines = explode("\n", $recommendations);
        $structured = [
            'priority_actions' => [],
            'cost_saving_measures' => [],
            'compliance_tips' => [],
            'next_steps' => []
        ];

        $currentSection = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (stripos($line, 'priority') !== false || stripos($line, 'urgent') !== false) {
                $currentSection = 'priority_actions';
            } elseif (stripos($line, 'cost') !== false || stripos($line, 'saving') !== false) {
                $currentSection = 'cost_saving_measures';
            } elseif (stripos($line, 'compliance') !== false || stripos($line, 'tip') !== false) {
                $currentSection = 'compliance_tips';
            } elseif (stripos($line, 'next') !== false || stripos($line, 'step') !== false) {
                $currentSection = 'next_steps';
            }

            if ($currentSection && (strpos($line, '-') === 0 || strpos($line, '•') === 0)) {
                $structured[$currentSection][] = ltrim($line, '- •');
            }
        }

        return $structured;
    }

    private function calculateConfidenceScore($riskFactors)
    {
        // Simple confidence scoring based on risk factors
        $baseScore = 85;
        $riskPenalty = count($riskFactors) * 5;
        
        return max(50, min(95, $baseScore - $riskPenalty));
    }

    /**
     * Health check for all external services
     */
    public function healthCheck()
    {
        $services = [
            'stripe' => $this->checkStripeHealth(),
            'openai' => $this->checkOpenAIHealth(),
            'google_maps' => $this->checkGoogleMapsHealth(),
            'twilio' => $this->checkTwilioHealth(),
            'sendgrid' => $this->checkSendGridHealth(),
            'weather' => $this->checkWeatherAPIHealth(),
            'news' => $this->checkNewsAPIHealth()
        ];

        return [
            'timestamp' => now()->toISOString(),
            'services' => $services,
            'overall_health' => $this->calculateOverallHealth($services)
        ];
    }

    private function checkStripeHealth()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['stripe_secret']
            ])->get('https://api.stripe.com/v1/account');

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats ? $response->transferStats->getTransferTime() : null
            ];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkOpenAIHealth()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['openai_api_key']
            ])->get('https://api.openai.com/v1/models');

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats ? $response->transferStats->getTransferTime() : null
            ];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkGoogleMapsHealth()
    {
        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => '1600 Amphitheatre Parkway, Mountain View, CA',
                'key' => $this->config['google_maps_api_key']
            ]);

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats ? $response->transferStats->getTransferTime() : null
            ];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkTwilioHealth()
    {
        try {
            $response = Http::withBasicAuth(
                $this->config['twilio_sid'],
                $this->config['twilio_token']
            )->get("https://api.twilio.com/2010-04-01/Accounts/{$this->config['twilio_sid']}.json");

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats ? $response->transferStats->getTransferTime() : null
            ];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkSendGridHealth()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['sendgrid_api_key']
            ])->get('https://api.sendgrid.com/v3/user/profile');

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats ? $response->transferStats->getTransferTime() : null
            ];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkWeatherAPIHealth()
    {
        try {
            $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'lat' => 40.7128,
                'lon' => -74.0060,
                'appid' => $this->config['weather_api_key']
            ]);

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats ? $response->transferStats->getTransferTime() : null
            ];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function checkNewsAPIHealth()
    {
        try {
            $response = Http::get('https://newsapi.org/v2/top-headlines', [
                'country' => 'us',
                'pageSize' => 1,
                'apiKey' => $this->config['news_api_key']
            ]);

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time' => $response->transferStats ? $response->transferStats->getTransferTime() : null
            ];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    private function calculateOverallHealth($services)
    {
        $healthy = array_filter($services, function ($service) {
            return $service['status'] === 'healthy';
        });

        $healthPercentage = (count($healthy) / count($services)) * 100;

        if ($healthPercentage >= 90) return 'excellent';
        if ($healthPercentage >= 75) return 'good';
        if ($healthPercentage >= 50) return 'fair';
        return 'poor';
    }
}
