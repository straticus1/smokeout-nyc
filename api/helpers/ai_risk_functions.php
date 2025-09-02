<?php
/**
 * AI Risk Assessment Helper Functions
 * SmokeoutNYC v2.0
 * 
 * Contains functions for calculating various risk assessments
 */

require_once __DIR__ . '/../auth_helper.php';

/**
 * Calculate comprehensive dispensary risk assessment
 * 
 * @param float $lat Latitude
 * @param float $lng Longitude
 * @param string $city City
 * @param string $state State
 * @param string $address Full address
 * @return array Risk assessment data
 */
function calculateDispensaryRisk($lat, $lng, $city, $state, $address) {
    try {
        $risk_factors = [];
        $total_score = 0;
        
        // 1. Proximity to sensitive locations (25% weight)
        $proximity_risk = calculateProximityRisk($lat, $lng);
        $risk_factors['proximity'] = $proximity_risk;
        $total_score += $proximity_risk['score'] * 0.25;
        
        // 2. Local regulation environment (20% weight)
        $regulatory_risk = calculateRegulatoryRisk($city, $state);
        $risk_factors['regulatory'] = $regulatory_risk;
        $total_score += $regulatory_risk['score'] * 0.20;
        
        // 3. Market saturation (15% weight)
        $market_risk = calculateMarketSaturationRisk($lat, $lng);
        $risk_factors['market_saturation'] = $market_risk;
        $total_score += $market_risk['score'] * 0.15;
        
        // 4. Crime rates (15% weight)
        $crime_risk = calculateCrimeRisk($city, $state);
        $risk_factors['crime'] = $crime_risk;
        $total_score += $crime_risk['score'] * 0.15;
        
        // 5. Demographics (10% weight)
        $demo_risk = calculateDemographicRisk($city, $state);
        $risk_factors['demographics'] = $demo_risk;
        $total_score += $demo_risk['score'] * 0.10;
        
        // 6. Zoning compliance (10% weight)
        $zoning_risk = calculateZoningRisk($lat, $lng, $address);
        $risk_factors['zoning'] = $zoning_risk;
        $total_score += $zoning_risk['score'] * 0.10;
        
        // 7. Economic factors (5% weight)
        $economic_risk = calculateEconomicRisk($city, $state);
        $risk_factors['economic'] = $economic_risk;
        $total_score += $economic_risk['score'] * 0.05;
        
        // Determine risk level
        $risk_level = 'low';
        if ($total_score >= 80) {
            $risk_level = 'very_high';
        } elseif ($total_score >= 60) {
            $risk_level = 'high';
        } elseif ($total_score >= 40) {
            $risk_level = 'medium';
        } elseif ($total_score >= 20) {
            $risk_level = 'low';
        } else {
            $risk_level = 'very_low';
        }
        
        return [
            'overall_score' => round($total_score, 2),
            'risk_level' => $risk_level,
            'risk_factors' => $risk_factors,
            'recommendations' => generateRecommendations($risk_level, $risk_factors),
            'confidence' => calculateConfidence($risk_factors),
            'location' => [
                'latitude' => $lat,
                'longitude' => $lng,
                'city' => $city,
                'state' => $state,
                'address' => $address
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error calculating dispensary risk: " . $e->getMessage());
        return [
            'overall_score' => 50,
            'risk_level' => 'medium',
            'error' => 'Unable to calculate full risk assessment',
            'confidence' => 30
        ];
    }
}

/**
 * Calculate proximity risk to sensitive locations
 */
function calculateProximityRisk($lat, $lng) {
    // Mock implementation - in production, use real GIS data
    $schools_nearby = rand(0, 5);
    $churches_nearby = rand(0, 3);
    $parks_nearby = rand(0, 4);
    $residential_density = rand(20, 80); // Percentage
    
    $proximity_score = 0;
    
    // Schools within 1000 feet (major risk factor)
    $proximity_score += $schools_nearby * 15;
    
    // Churches/religious centers
    $proximity_score += $churches_nearby * 8;
    
    // Parks and recreational areas
    $proximity_score += $parks_nearby * 5;
    
    // High residential density
    if ($residential_density > 60) {
        $proximity_score += 20;
    } elseif ($residential_density > 40) {
        $proximity_score += 10;
    }
    
    return [
        'score' => min(100, $proximity_score),
        'details' => [
            'schools_within_1000ft' => $schools_nearby,
            'churches_nearby' => $churches_nearby,
            'parks_nearby' => $parks_nearby,
            'residential_density' => $residential_density
        ],
        'impact' => $proximity_score > 40 ? 'high' : ($proximity_score > 20 ? 'medium' : 'low')
    ];
}

/**
 * Calculate regulatory environment risk
 */
function calculateRegulatoryRisk($city, $state) {
    // State-level regulations (mock data)
    $state_regulations = [
        'NY' => ['score' => 45, 'status' => 'restrictive'],
        'CA' => ['score' => 25, 'status' => 'permissive'],
        'TX' => ['score' => 85, 'status' => 'very_restrictive'],
        'CO' => ['score' => 20, 'status' => 'very_permissive'],
        'FL' => ['score' => 60, 'status' => 'moderate']
    ];
    
    $state_risk = $state_regulations[$state] ?? ['score' => 50, 'status' => 'unknown'];
    
    // Local municipality factors
    $local_score = rand(10, 40);
    
    $total_score = ($state_risk['score'] * 0.7) + ($local_score * 0.3);
    
    return [
        'score' => round($total_score),
        'details' => [
            'state_regulations' => $state_risk,
            'local_ordinances' => $local_score,
            'recent_changes' => rand(0, 1) ? 'tightening' : 'stable'
        ],
        'impact' => $total_score > 60 ? 'high' : ($total_score > 40 ? 'medium' : 'low')
    ];
}

/**
 * Calculate market saturation risk
 */
function calculateMarketSaturationRisk($lat, $lng) {
    // Mock calculation - count nearby dispensaries
    $dispensaries_1mi = rand(2, 12);
    $dispensaries_3mi = rand(5, 25);
    $population_density = rand(1000, 8000); // Per sq mile
    
    $saturation_score = 0;
    
    // High density of nearby dispensaries
    if ($dispensaries_1mi > 8) {
        $saturation_score += 40;
    } elseif ($dispensaries_1mi > 5) {
        $saturation_score += 25;
    } elseif ($dispensaries_1mi > 2) {
        $saturation_score += 10;
    }
    
    // Market oversaturation in 3-mile radius
    $competition_ratio = $dispensaries_3mi / ($population_density / 1000);
    if ($competition_ratio > 5) {
        $saturation_score += 30;
    } elseif ($competition_ratio > 3) {
        $saturation_score += 20;
    }
    
    return [
        'score' => min(100, $saturation_score),
        'details' => [
            'dispensaries_1_mile' => $dispensaries_1mi,
            'dispensaries_3_mile' => $dispensaries_3mi,
            'population_density' => $population_density,
            'competition_ratio' => round($competition_ratio, 2)
        ],
        'impact' => $saturation_score > 50 ? 'high' : ($saturation_score > 25 ? 'medium' : 'low')
    ];
}

/**
 * Calculate crime risk
 */
function calculateCrimeRisk($city, $state) {
    // Mock crime data
    $violent_crime_rate = rand(200, 800); // Per 100,000
    $property_crime_rate = rand(1500, 4500);
    $drug_crime_rate = rand(100, 600);
    
    $crime_score = 0;
    
    // Violent crime
    if ($violent_crime_rate > 600) {
        $crime_score += 25;
    } elseif ($violent_crime_rate > 400) {
        $crime_score += 15;
    }
    
    // Property crime
    if ($property_crime_rate > 3500) {
        $crime_score += 20;
    } elseif ($property_crime_rate > 2500) {
        $crime_score += 10;
    }
    
    // Drug-related crime
    if ($drug_crime_rate > 400) {
        $crime_score += 15;
    } elseif ($drug_crime_rate > 250) {
        $crime_score += 8;
    }
    
    return [
        'score' => min(100, $crime_score),
        'details' => [
            'violent_crime_rate' => $violent_crime_rate,
            'property_crime_rate' => $property_crime_rate,
            'drug_crime_rate' => $drug_crime_rate
        ],
        'impact' => $crime_score > 40 ? 'high' : ($crime_score > 20 ? 'medium' : 'low')
    ];
}

/**
 * Calculate demographic risk factors
 */
function calculateDemographicRisk($city, $state) {
    // Mock demographic data
    $median_age = rand(25, 55);
    $median_income = rand(35000, 120000);
    $education_level = rand(40, 90); // % with college education
    $support_percentage = rand(45, 75); // % supporting legalization
    
    $demo_score = 0;
    
    // Age demographics
    if ($median_age > 50) {
        $demo_score += 15; // Older populations may be less supportive
    } elseif ($median_age < 30) {
        $demo_score -= 5; // Younger populations more supportive
    }
    
    // Income level
    if ($median_income < 45000) {
        $demo_score += 10; // Lower income may indicate less political influence
    } elseif ($median_income > 80000) {
        $demo_score += 5; // Higher income may bring more opposition
    }
    
    // Education level (higher education generally more supportive)
    if ($education_level < 50) {
        $demo_score += 10;
    }
    
    // Support level
    if ($support_percentage < 55) {
        $demo_score += 20;
    }
    
    return [
        'score' => max(0, min(100, $demo_score)),
        'details' => [
            'median_age' => $median_age,
            'median_income' => $median_income,
            'education_level' => $education_level,
            'support_percentage' => $support_percentage
        ],
        'impact' => $demo_score > 25 ? 'medium' : 'low'
    ];
}

/**
 * Calculate zoning compliance risk
 */
function calculateZoningRisk($lat, $lng, $address) {
    // Mock zoning analysis
    $zoning_type = ['commercial', 'mixed_use', 'industrial', 'residential'][rand(0, 3)];
    $compliant = rand(0, 1);
    $buffer_violations = rand(0, 3);
    
    $zoning_score = 0;
    
    if (!$compliant) {
        $zoning_score += 50;
    }
    
    if ($zoning_type === 'residential') {
        $zoning_score += 30;
    }
    
    $zoning_score += $buffer_violations * 10;
    
    return [
        'score' => min(100, $zoning_score),
        'details' => [
            'zoning_type' => $zoning_type,
            'compliant' => $compliant,
            'buffer_violations' => $buffer_violations
        ],
        'impact' => $zoning_score > 40 ? 'high' : ($zoning_score > 20 ? 'medium' : 'low')
    ];
}

/**
 * Calculate economic risk factors
 */
function calculateEconomicRisk($city, $state) {
    // Mock economic indicators
    $unemployment_rate = rand(3, 12);
    $business_growth = rand(-5, 15); // Percentage change
    $tax_burden = rand(20, 45); // Percentage
    
    $economic_score = 0;
    
    if ($unemployment_rate > 8) {
        $economic_score += 15;
    }
    
    if ($business_growth < 2) {
        $economic_score += 10;
    }
    
    if ($tax_burden > 35) {
        $economic_score += 10;
    }
    
    return [
        'score' => min(100, $economic_score),
        'details' => [
            'unemployment_rate' => $unemployment_rate,
            'business_growth' => $business_growth,
            'tax_burden' => $tax_burden
        ],
        'impact' => $economic_score > 25 ? 'medium' : 'low'
    ];
}

/**
 * Generate recommendations based on risk assessment
 */
function generateRecommendations($risk_level, $risk_factors) {
    $recommendations = [];
    
    if ($risk_level === 'very_high' || $risk_level === 'high') {
        $recommendations[] = [
            'priority' => 'critical',
            'title' => 'Reconsider Location',
            'description' => 'This location presents significant risks. Consider alternative locations.'
        ];
    }
    
    if ($risk_factors['proximity']['score'] > 50) {
        $recommendations[] = [
            'priority' => 'high',
            'title' => 'Address Proximity Issues',
            'description' => 'Ensure compliance with buffer zone requirements from schools and sensitive areas.'
        ];
    }
    
    if ($risk_factors['regulatory']['score'] > 60) {
        $recommendations[] = [
            'priority' => 'high',
            'title' => 'Legal Compliance Review',
            'description' => 'Engage legal counsel familiar with local cannabis regulations.'
        ];
    }
    
    if ($risk_factors['market_saturation']['score'] > 40) {
        $recommendations[] = [
            'priority' => 'medium',
            'title' => 'Market Differentiation Strategy',
            'description' => 'Develop unique value proposition to compete in saturated market.'
        ];
    }
    
    if ($risk_factors['crime']['score'] > 30) {
        $recommendations[] = [
            'priority' => 'medium',
            'title' => 'Enhanced Security Measures',
            'description' => 'Implement comprehensive security system and consider additional insurance.'
        ];
    }
    
    return $recommendations;
}

/**
 * Calculate confidence score for the assessment
 */
function calculateConfidence($risk_factors) {
    // Base confidence
    $confidence = 75;
    
    // Reduce confidence if we have limited data
    foreach ($risk_factors as $factor) {
        if (!isset($factor['details']) || empty($factor['details'])) {
            $confidence -= 10;
        }
    }
    
    return max(30, min(100, $confidence));
}

/**
 * Calculate business closure risk assessment
 */
function calculateClosureRisk($business_data, $timeframe_months = 12) {
    try {
        $risk_factors = [];
        $total_score = 0;
        
        // 1. Financial distress (25% weight)
        $financial_risk = calculateFinancialDistressRisk($business_data);
        $risk_factors['financial_distress'] = $financial_risk;
        $total_score += $financial_risk['score'] * 0.25;
        
        // 2. Regulatory violations (20% weight)
        $regulatory_risk = calculateRegulatoryViolationRisk($business_data);
        $risk_factors['regulatory_violations'] = $regulatory_risk;
        $total_score += $regulatory_risk['score'] * 0.20;
        
        // 3. Enforcement pressure (15% weight)
        $enforcement_risk = calculateEnforcementPressureRisk($business_data);
        $risk_factors['enforcement_pressure'] = $enforcement_risk;
        $total_score += $enforcement_risk['score'] * 0.15;
        
        // 4. Market decline (12% weight)
        $market_decline_risk = calculateMarketDeclineRisk($business_data);
        $risk_factors['market_decline'] = $market_decline_risk;
        $total_score += $market_decline_risk['score'] * 0.12;
        
        // Add other risk factors...
        
        // Calculate probability based on timeframe
        $base_probability = $total_score / 100;
        $timeframe_adjustment = 1 + ($timeframe_months - 12) * 0.05; // 5% increase per additional month
        $closure_probability = min(0.95, $base_probability * $timeframe_adjustment);
        
        return [
            'closure_probability' => round($closure_probability * 100, 2),
            'risk_score' => round($total_score, 2),
            'timeframe_months' => $timeframe_months,
            'risk_factors' => $risk_factors,
            'recommendations' => generateClosureRecommendations($total_score, $risk_factors),
            'confidence' => calculateConfidence($risk_factors)
        ];
        
    } catch (Exception $e) {
        error_log("Error calculating closure risk: " . $e->getMessage());
        return [
            'closure_probability' => 50,
            'risk_score' => 50,
            'error' => 'Unable to calculate full closure risk assessment'
        ];
    }
}

/**
 * Calculate financial distress risk
 */
function calculateFinancialDistressRisk($business_data) {
    // Mock financial analysis
    $revenue_trend = rand(-30, 20); // Percentage change
    $cash_flow = rand(-50000, 100000); // Monthly cash flow
    $debt_ratio = rand(20, 80); // Percentage
    
    $financial_score = 0;
    
    if ($revenue_trend < -10) {
        $financial_score += 40;
    } elseif ($revenue_trend < 0) {
        $financial_score += 20;
    }
    
    if ($cash_flow < 0) {
        $financial_score += 30;
    } elseif ($cash_flow < 10000) {
        $financial_score += 15;
    }
    
    if ($debt_ratio > 60) {
        $financial_score += 20;
    } elseif ($debt_ratio > 40) {
        $financial_score += 10;
    }
    
    return [
        'score' => min(100, $financial_score),
        'details' => [
            'revenue_trend' => $revenue_trend,
            'monthly_cash_flow' => $cash_flow,
            'debt_ratio' => $debt_ratio
        ],
        'impact' => $financial_score > 60 ? 'high' : ($financial_score > 30 ? 'medium' : 'low')
    ];
}

/**
 * Calculate regulatory violation risk
 */
function calculateRegulatoryViolationRisk($business_data) {
    // Mock violation data
    $violations_count = rand(0, 8);
    $severity_avg = rand(1, 5);
    $recent_violations = rand(0, 3);
    
    $violation_score = 0;
    
    $violation_score += min(50, $violations_count * 8);
    $violation_score += $severity_avg * 8;
    $violation_score += $recent_violations * 15;
    
    return [
        'score' => min(100, $violation_score),
        'details' => [
            'total_violations' => $violations_count,
            'average_severity' => $severity_avg,
            'recent_violations' => $recent_violations
        ],
        'impact' => $violation_score > 50 ? 'high' : ($violation_score > 25 ? 'medium' : 'low')
    ];
}

/**
 * Calculate enforcement pressure risk
 */
function calculateEnforcementPressureRisk($business_data) {
    // Mock enforcement data
    $raids_in_area = rand(0, 5);
    $investigations_ongoing = rand(0, 2);
    $political_pressure = rand(1, 5);
    
    $enforcement_score = 0;
    
    $enforcement_score += $raids_in_area * 15;
    $enforcement_score += $investigations_ongoing * 25;
    $enforcement_score += $political_pressure * 8;
    
    return [
        'score' => min(100, $enforcement_score),
        'details' => [
            'raids_in_area' => $raids_in_area,
            'ongoing_investigations' => $investigations_ongoing,
            'political_pressure' => $political_pressure
        ],
        'impact' => $enforcement_score > 50 ? 'high' : ($enforcement_score > 25 ? 'medium' : 'low')
    ];
}

/**
 * Calculate market decline risk
 */
function calculateMarketDeclineRisk($business_data) {
    // Mock market data
    $price_decline = rand(-20, 10); // Percentage
    $oversupply = rand(0, 1);
    $new_competitors = rand(0, 5);
    
    $market_score = 0;
    
    if ($price_decline < -10) {
        $market_score += 30;
    } elseif ($price_decline < 0) {
        $market_score += 15;
    }
    
    if ($oversupply) {
        $market_score += 25;
    }
    
    $market_score += min(25, $new_competitors * 5);
    
    return [
        'score' => min(100, $market_score),
        'details' => [
            'price_decline_percent' => $price_decline,
            'market_oversupply' => $oversupply,
            'new_competitors' => $new_competitors
        ],
        'impact' => $market_score > 40 ? 'high' : ($market_score > 20 ? 'medium' : 'low')
    ];
}

/**
 * Generate closure risk recommendations
 */
function generateClosureRecommendations($risk_score, $risk_factors) {
    $recommendations = [];
    
    if ($risk_score > 70) {
        $recommendations[] = [
            'priority' => 'critical',
            'title' => 'Immediate Action Required',
            'description' => 'Business faces high closure risk. Consider exit strategy or major restructuring.'
        ];
    }
    
    if ($risk_factors['financial_distress']['score'] > 50) {
        $recommendations[] = [
            'priority' => 'high',
            'title' => 'Financial Restructuring',
            'description' => 'Seek financial counseling, renegotiate debt terms, or secure additional funding.'
        ];
    }
    
    return $recommendations;
}

/**
 * Calculate enforcement risk for area
 */
function calculateEnforcementRisk($lat, $lng, $radius, $city, $state) {
    // Mock enforcement analysis
    $recent_raids = rand(0, 8);
    $enforcement_activity = rand(1, 10);
    $political_climate = ['hostile', 'neutral', 'supportive'][rand(0, 2)];
    
    $enforcement_score = 0;
    
    $enforcement_score += $recent_raids * 10;
    $enforcement_score += $enforcement_activity * 5;
    
    if ($political_climate === 'hostile') {
        $enforcement_score += 30;
    } elseif ($political_climate === 'neutral') {
        $enforcement_score += 10;
    }
    
    return [
        'enforcement_score' => min(100, $enforcement_score),
        'risk_level' => $enforcement_score > 60 ? 'high' : ($enforcement_score > 30 ? 'medium' : 'low'),
        'details' => [
            'recent_raids' => $recent_raids,
            'activity_level' => $enforcement_activity,
            'political_climate' => $political_climate,
            'radius_miles' => $radius
        ]
    ];
}

/**
 * Get nationwide overview data
 */
function getNationwideOverview() {
    return [
        'total_states_legal' => 38,
        'total_dispensaries' => 12500,
        'average_risk_score' => 42.3,
        'high_risk_states' => ['TX', 'ID', 'KS'],
        'low_risk_states' => ['CA', 'CO', 'WA'],
        'market_size_billion' => 28.4,
        'growth_rate_percent' => 15.2
    ];
}

/**
 * Get nationwide heatmap data
 */
function getNationwideHeatmap() {
    // Mock heatmap data for each state
    $states = ['CA', 'NY', 'TX', 'FL', 'CO', 'WA', 'OR', 'NV', 'MA', 'IL'];
    $heatmap_data = [];
    
    foreach ($states as $state) {
        $heatmap_data[$state] = [
            'risk_score' => rand(20, 80),
            'dispensary_count' => rand(50, 1200),
            'legal_status' => ['full_legal', 'medical_only', 'decriminalized', 'illegal'][rand(0, 3)]
        ];
    }
    
    return $heatmap_data;
}

/**
 * Get nationwide trends data
 */
function getNationwideTrends() {
    return [
        'monthly_risk_scores' => [
            '2024-01' => 45.2,
            '2024-02' => 44.8,
            '2024-03' => 43.5,
            '2024-04' => 42.1,
            '2024-05' => 41.7,
            '2024-06' => 42.3
        ],
        'enforcement_activity' => [
            '2024-01' => 156,
            '2024-02' => 142,
            '2024-03' => 138,
            '2024-04' => 125,
            '2024-05' => 119,
            '2024-06' => 134
        ],
        'new_licenses_issued' => [
            '2024-01' => 234,
            '2024-02' => 267,
            '2024-03' => 289,
            '2024-04' => 301,
            '2024-05' => 278,
            '2024-06' => 312
        ]
    ];
}

?>
