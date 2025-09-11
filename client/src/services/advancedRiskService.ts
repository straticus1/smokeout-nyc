import { getPoliceDistanceRisk, DistanceResult } from './policeStationService';

interface RiskFactor {
  factor: string;
  weight: number; // 0-1, how much this factor affects overall risk
  score: number; // 0-100, risk score for this factor
  description: string;
}

interface ComprehensiveRiskAssessment {
  overallRisk: {
    level: 'very_low' | 'low' | 'moderate' | 'high' | 'very_high';
    score: number; // 0-100
    confidence: number; // 0-100
  };
  factors: RiskFactor[];
  recommendations: string[];
  lastUpdated: Date;
  validUntil: Date;
}

interface LocationContext {
  latitude: number;
  longitude: number;
  address?: string;
  borough?: string;
  neighborhood?: string;
  zipCode?: string;
}

interface HistoricalData {
  closureRate: number; // Percentage of shops that have closed in this area
  reopenRate: number; // Percentage that reopened after closure
  averageClosureDuration: number; // Days closed on average
  lastEnforcementDate?: Date;
  enforcementFrequency: number; // Enforcements per year in area
}

interface MarketFactors {
  competition: number; // Number of similar businesses nearby
  footTraffic: number; // Estimated daily foot traffic
  economicIndex: number; // Economic health of area (0-100)
  touristActivity: number; // Tourist presence (0-100)
  residentialDensity: number; // Population density
}

interface RegulatoryFactors {
  zoningCompliance: number; // 0-100, how well location fits zoning laws
  licenseType: 'licensed' | 'unlicensed' | 'pending' | 'unknown';
  violationHistory: number; // Previous violations count
  inspectionFrequency: number; // How often location is inspected
  municipalSupport: number; // 0-100, local government support for cannabis
}

/**
 * Advanced Risk Assessment Service
 * Combines multiple data sources for comprehensive risk analysis
 */
export class AdvancedRiskAssessmentService {
  
  /**
   * Perform comprehensive risk assessment for a location
   */
  public static async assessLocation(context: LocationContext): Promise<ComprehensiveRiskAssessment> {
    const factors: RiskFactor[] = [];
    
    // 1. Police Proximity Risk (25% weight)
    const policeRisk = getPoliceDistanceRisk(context.latitude, context.longitude);
    factors.push({
      factor: 'police_proximity',
      weight: 0.25,
      score: policeRisk.riskScore,
      description: `Distance to nearest police station: ${policeRisk.message}`
    });
    
    // 2. Historical Enforcement Risk (20% weight)
    const enforcementRisk = await this.calculateEnforcementRisk(context);
    factors.push({
      factor: 'enforcement_history',
      weight: 0.20,
      score: enforcementRisk.score,
      description: enforcementRisk.description
    });
    
    // 3. Market Competition Risk (15% weight)
    const marketRisk = await this.calculateMarketRisk(context);
    factors.push({
      factor: 'market_competition',
      weight: 0.15,
      score: marketRisk.score,
      description: marketRisk.description
    });
    
    // 4. Regulatory Compliance Risk (20% weight)
    const regulatoryRisk = await this.calculateRegulatoryRisk(context);
    factors.push({
      factor: 'regulatory_compliance',
      weight: 0.20,
      score: regulatoryRisk.score,
      description: regulatoryRisk.description
    });
    
    // 5. Economic Stability Risk (10% weight)
    const economicRisk = await this.calculateEconomicRisk(context);
    factors.push({
      factor: 'economic_stability',
      weight: 0.10,
      score: economicRisk.score,
      description: economicRisk.description
    });
    
    // 6. Neighborhood Demographics Risk (10% weight)
    const demographicRisk = await this.calculateDemographicRisk(context);
    factors.push({
      factor: 'demographics',
      weight: 0.10,
      score: demographicRisk.score,
      description: demographicRisk.description
    });
    
    // Calculate weighted overall score
    const overallScore = factors.reduce((total, factor) => {
      return total + (factor.score * factor.weight);
    }, 0);
    
    // Determine risk level
    let riskLevel: ComprehensiveRiskAssessment['overallRisk']['level'];
    if (overallScore >= 80) riskLevel = 'very_high';
    else if (overallScore >= 65) riskLevel = 'high';
    else if (overallScore >= 45) riskLevel = 'moderate';
    else if (overallScore >= 25) riskLevel = 'low';
    else riskLevel = 'very_low';
    
    // Generate recommendations
    const recommendations = this.generateRecommendations(factors, riskLevel);
    
    // Calculate confidence (based on data completeness and recency)
    const confidence = this.calculateConfidence(factors, context);
    
    return {
      overallRisk: {
        level: riskLevel,
        score: Math.round(overallScore),
        confidence: Math.round(confidence)
      },
      factors,
      recommendations,
      lastUpdated: new Date(),
      validUntil: new Date(Date.now() + 24 * 60 * 60 * 1000) // Valid for 24 hours
    };
  }
  
  /**
   * Calculate enforcement risk based on historical data
   */
  private static async calculateEnforcementRisk(context: LocationContext): Promise<{ score: number; description: string }> {
    // In production, this would query a database of enforcement actions
    // For now, use synthetic data based on NYC borough patterns
    
    const borough = this.getBoroughFromCoordinates(context.latitude, context.longitude);
    let baseScore = 50; // Default moderate risk
    let description = '';
    
    switch (borough) {
      case 'Manhattan':
        baseScore = 70; // Higher enforcement in Manhattan
        description = 'Manhattan has historically higher enforcement activity';
        break;
      case 'Brooklyn':
        baseScore = 45;
        description = 'Brooklyn shows moderate enforcement patterns';
        break;
      case 'Queens':
        baseScore = 40;
        description = 'Queens has lower reported enforcement activity';
        break;
      case 'Bronx':
        baseScore = 35;
        description = 'Bronx shows relatively lower enforcement rates';
        break;
      case 'Staten Island':
        baseScore = 30;
        description = 'Staten Island has minimal enforcement activity';
        break;
      default:
        description = 'Limited historical enforcement data available';
    }
    
    // Adjust based on distance from tourist areas
    const distanceFromTouristAreas = this.calculateTouristAreaDistance(context);
    if (distanceFromTouristAreas < 0.5) {
      baseScore += 15; // Higher risk near tourist areas
      description += '; proximity to high-traffic tourist areas increases risk';
    }
    
    return {
      score: Math.min(100, Math.max(0, baseScore)),
      description
    };
  }
  
  /**
   * Calculate market competition risk
   */
  private static async calculateMarketRisk(context: LocationContext): Promise<{ score: number; description: string }> {
    // Simulate market density analysis
    const competitorCount = Math.floor(Math.random() * 15) + 1; // 1-15 competitors nearby
    const marketSaturation = (competitorCount / 15) * 100;
    
    let riskScore = marketSaturation;
    let description = `Estimated ${competitorCount} similar businesses within 1 mile radius`;
    
    if (competitorCount > 10) {
      description += '; highly saturated market';
    } else if (competitorCount > 5) {
      description += '; moderate competition';
    } else {
      description += '; relatively low competition';
    }
    
    return { score: riskScore, description };
  }
  
  /**
   * Calculate regulatory compliance risk
   */
  private static async calculateRegulatoryRisk(context: LocationContext): Promise<{ score: number; description: string }> {
    // Simulate regulatory analysis
    const zoningScore = Math.floor(Math.random() * 40) + 60; // 60-100 (mostly compliant)
    const licenseScore = Math.floor(Math.random() * 30) + 70; // 70-100
    
    const avgScore = (zoningScore + licenseScore) / 2;
    const riskScore = 100 - avgScore; // Invert score (lower compliance = higher risk)
    
    return {
      score: riskScore,
      description: `Regulatory compliance estimated at ${Math.round(avgScore)}% based on zoning and licensing factors`
    };
  }
  
  /**
   * Calculate economic stability risk
   */
  private static async calculateEconomicRisk(context: LocationContext): Promise<{ score: number; description: string }> {
    const borough = this.getBoroughFromCoordinates(context.latitude, context.longitude);
    
    // Economic risk scores by borough (based on general economic indicators)
    const economicRisk: { [key: string]: number } = {
      'Manhattan': 20, // Low risk - strong economy
      'Brooklyn': 35,
      'Queens': 40,
      'Bronx': 55,
      'Staten Island': 45
    };
    
    const score = economicRisk[borough] || 50;
    
    return {
      score,
      description: `Economic stability in ${borough} indicates ${score < 30 ? 'low' : score < 50 ? 'moderate' : 'elevated'} business risk`
    };
  }
  
  /**
   * Calculate demographic risk
   */
  private static async calculateDemographicRisk(context: LocationContext): Promise<{ score: number; description: string }> {
    // Simulate demographic analysis
    const populationDensity = Math.floor(Math.random() * 50000) + 10000; // People per sq mile
    const avgAge = Math.floor(Math.random() * 20) + 25; // 25-45 years old
    const incomeLevel = Math.floor(Math.random() * 50000) + 30000; // $30k-$80k median income
    
    // Lower risk with higher density, younger demographics, moderate income
    let riskScore = 50;
    
    if (populationDensity > 30000) riskScore -= 10; // High density good for business
    if (avgAge < 35) riskScore -= 10; // Younger demographic more accepting
    if (incomeLevel > 50000) riskScore -= 10; // Higher income = more discretionary spending
    
    riskScore = Math.max(10, Math.min(90, riskScore));
    
    return {
      score: riskScore,
      description: `Demographics show ${populationDensity.toLocaleString()} people/sq mi, median age ${avgAge}, median income $${incomeLevel.toLocaleString()}`
    };
  }
  
  /**
   * Generate actionable recommendations based on risk factors
   */
  private static generateRecommendations(factors: RiskFactor[], riskLevel: string): string[] {
    const recommendations: string[] = [];
    
    // Police proximity recommendations
    const policeRisk = factors.find(f => f.factor === 'police_proximity');
    if (policeRisk && policeRisk.score > 70) {
      recommendations.push('Consider lower-profile signage and operations due to close police proximity');
      recommendations.push('Ensure 100% compliance with all regulations and licensing requirements');
    }
    
    // Market competition recommendations
    const marketRisk = factors.find(f => f.factor === 'market_competition');
    if (marketRisk && marketRisk.score > 60) {
      recommendations.push('Develop unique value propositions to stand out in saturated market');
      recommendations.push('Consider specialized products or services to differentiate');
    }
    
    // Regulatory recommendations
    const regulatoryRisk = factors.find(f => f.factor === 'regulatory_compliance');
    if (regulatoryRisk && regulatoryRisk.score > 50) {
      recommendations.push('Consult with cannabis law attorney for compliance review');
      recommendations.push('Implement robust record-keeping and reporting systems');
    }
    
    // General recommendations based on overall risk
    if (riskLevel === 'very_high' || riskLevel === 'high') {
      recommendations.push('Consider alternative locations with lower risk profiles');
      recommendations.push('Increase legal and compliance budget allocation');
      recommendations.push('Implement enhanced security and monitoring systems');
    } else if (riskLevel === 'very_low' || riskLevel === 'low') {
      recommendations.push('Location shows favorable conditions for cannabis business');
      recommendations.push('Consider expanding operations or services');
    }
    
    return recommendations;
  }
  
  /**
   * Calculate confidence score based on data quality
   */
  private static calculateConfidence(factors: RiskFactor[], context: LocationContext): number {
    let confidence = 85; // Base confidence
    
    // Reduce confidence if missing key context data
    if (!context.borough) confidence -= 5;
    if (!context.neighborhood) confidence -= 5;
    if (!context.zipCode) confidence -= 10;
    
    // Police data is highly accurate
    confidence += 10;
    
    return Math.max(50, Math.min(95, confidence));
  }
  
  /**
   * Determine NYC borough from coordinates
   */
  private static getBoroughFromCoordinates(lat: number, lng: number): string {
    // Simplified borough detection based on coordinate ranges
    if (lat >= 40.7 && lat <= 40.8 && lng >= -74.02 && lng <= -73.93) {
      return 'Manhattan';
    } else if (lat >= 40.57 && lat <= 40.74 && lng >= -74.05 && lng <= -73.83) {
      return 'Brooklyn';
    } else if (lat >= 40.49 && lat <= 40.8 && lng >= -73.96 && lng <= -73.7) {
      return 'Queens';
    } else if (lat >= 40.79 && lat <= 40.92 && lng >= -73.93 && lng <= -73.77) {
      return 'Bronx';
    } else if (lat >= 40.47 && lat <= 40.65 && lng >= -74.26 && lng <= -74.05) {
      return 'Staten Island';
    }
    return 'Unknown';
  }
  
  /**
   * Calculate distance to major tourist areas
   */
  private static calculateTouristAreaDistance(context: LocationContext): number {
    const touristAreas = [
      { name: 'Times Square', lat: 40.7589, lng: -73.9851 },
      { name: 'Central Park', lat: 40.7829, lng: -73.9654 },
      { name: 'Brooklyn Bridge', lat: 40.7061, lng: -73.9969 },
      { name: 'High Line', lat: 40.7480, lng: -74.0048 },
      { name: 'One World Trade', lat: 40.7127, lng: -74.0134 }
    ];
    
    let minDistance = Infinity;
    
    for (const area of touristAreas) {
      const distance = this.calculateHaversineDistance(
        context.latitude, context.longitude,
        area.lat, area.lng
      );
      minDistance = Math.min(minDistance, distance);
    }
    
    return minDistance;
  }
  
  /**
   * Calculate Haversine distance between two points
   */
  private static calculateHaversineDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const R = 3959; // Earth's radius in miles
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
      Math.sin(dLat/2) * Math.sin(dLat/2) +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
      Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
  }
}

export type { ComprehensiveRiskAssessment, RiskFactor, LocationContext };