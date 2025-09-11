interface PoliceStation {
  id: string;
  name: string;
  address: string;
  latitude: number;
  longitude: number;
  phone?: string;
  precinct?: string;
}

interface DistanceResult {
  station: PoliceStation;
  distance: number; // in miles
  walkingTime?: number; // in minutes
}

// NYC Police Stations (sample data - in production this would come from API)
const NYC_POLICE_STATIONS: PoliceStation[] = [
  {
    id: '1',
    name: '1st Precinct',
    address: '16 Ericsson Pl, New York, NY 10013',
    latitude: 40.7142,
    longitude: -74.0052,
    phone: '(212) 334-0611',
    precinct: '1st Precinct'
  },
  {
    id: '5',
    name: '5th Precinct',
    address: '19 Elizabeth St, New York, NY 10013',
    latitude: 40.7153,
    longitude: -73.9958,
    phone: '(212) 334-0711',
    precinct: '5th Precinct'
  },
  {
    id: '6',
    name: '6th Precinct',
    address: '233 W 10th St, New York, NY 10014',
    latitude: 40.7344,
    longitude: -74.0034,
    phone: '(212) 741-4811',
    precinct: '6th Precinct'
  },
  {
    id: '7',
    name: '7th Precinct',
    address: '19 1/2 Pitt St, New York, NY 10002',
    latitude: 40.7179,
    longitude: -73.9859,
    phone: '(212) 477-7311',
    precinct: '7th Precinct'
  },
  {
    id: '9',
    name: '9th Precinct', 
    address: '321 E 5th St, New York, NY 10003',
    latitude: 40.7268,
    longitude: -73.9819,
    phone: '(212) 477-7811',
    precinct: '9th Precinct'
  },
  {
    id: '10',
    name: '10th Precinct',
    address: '230 W 20th St, New York, NY 10011',
    latitude: 40.7432,
    longitude: -73.9973,
    phone: '(212) 741-8211',
    precinct: '10th Precinct'
  },
  {
    id: '13',
    name: '13th Precinct',
    address: '230 E 21st St, New York, NY 10010',
    latitude: 40.7379,
    longitude: -73.9845,
    phone: '(212) 477-7411',
    precinct: '13th Precinct'
  },
  {
    id: '14',
    name: '14th Precinct',
    address: '357 W 35th St, New York, NY 10001',
    latitude: 40.7538,
    longitude: -73.9903,
    phone: '(212) 239-9811',
    precinct: '14th Precinct'
  },
  // Brooklyn precincts
  {
    id: '60',
    name: '60th Precinct',
    address: '2951 W 8th St, Brooklyn, NY 11224',
    latitude: 40.5761,
    longitude: -73.9708,
    phone: '(718) 946-3311',
    precinct: '60th Precinct'
  },
  {
    id: '61',
    name: '61st Precinct',
    address: '2575 Coney Island Ave, Brooklyn, NY 11223',
    latitude: 40.5967,
    longitude: -73.9635,
    phone: '(718) 627-6611',
    precinct: '61st Precinct'
  },
  // Queens precincts
  {
    id: '100',
    name: '100th Precinct',
    address: '92-24 Rockaway Beach Blvd, Rockaway Park, NY 11694',
    latitude: 40.5821,
    longitude: -73.8440,
    phone: '(718) 318-4200',
    precinct: '100th Precinct'
  },
  {
    id: '101',
    name: '101st Precinct',
    address: '16-12 Mott Ave, Far Rockaway, NY 11691',
    latitude: 40.6018,
    longitude: -73.7551,
    phone: '(718) 868-3400',
    precinct: '101st Precinct'
  },
  // Bronx precincts
  {
    id: '40',
    name: '40th Precinct',
    address: '257 Alexander Ave, Bronx, NY 10454',
    latitude: 40.8084,
    longitude: -73.9187,
    phone: '(718) 402-2270',
    precinct: '40th Precinct'
  },
  {
    id: '41',
    name: '41st Precinct',
    address: '1035 Longwood Ave, Bronx, NY 10459',
    latitude: 40.8217,
    longitude: -73.8969,
    phone: '(718) 542-0888',
    precinct: '41st Precinct'
  }
];

/**
 * Calculate distance between two coordinates using Haversine formula
 */
function calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
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

/**
 * Find the nearest police station to given coordinates
 */
export function findNearestPoliceStation(latitude: number, longitude: number): DistanceResult | null {
  if (!latitude || !longitude) return null;

  let nearest: DistanceResult | null = null;
  let minDistance = Infinity;

  for (const station of NYC_POLICE_STATIONS) {
    const distance = calculateDistance(latitude, longitude, station.latitude, station.longitude);
    
    if (distance < minDistance) {
      minDistance = distance;
      nearest = {
        station,
        distance,
        walkingTime: Math.round(distance * 20) // Rough estimate: 20 minutes per mile walking
      };
    }
  }

  return nearest;
}

/**
 * Find all police stations within a certain radius
 */
export function findPoliceStationsInRadius(
  latitude: number, 
  longitude: number, 
  radiusMiles: number = 1.0
): DistanceResult[] {
  if (!latitude || !longitude) return [];

  const results: DistanceResult[] = [];

  for (const station of NYC_POLICE_STATIONS) {
    const distance = calculateDistance(latitude, longitude, station.latitude, station.longitude);
    
    if (distance <= radiusMiles) {
      results.push({
        station,
        distance,
        walkingTime: Math.round(distance * 20)
      });
    }
  }

  // Sort by distance (closest first)
  return results.sort((a, b) => a.distance - b.distance);
}

/**
 * Get risk assessment based on distance to police stations
 */
export function getPoliceDistanceRisk(latitude: number, longitude: number): {
  riskLevel: 'low' | 'medium' | 'high';
  riskScore: number; // 0-100
  nearestStation: DistanceResult | null;
  message: string;
} {
  const nearestStation = findNearestPoliceStation(latitude, longitude);
  
  if (!nearestStation) {
    return {
      riskLevel: 'high',
      riskScore: 100,
      nearestStation: null,
      message: 'Unable to determine police proximity'
    };
  }

  const distance = nearestStation.distance;
  let riskLevel: 'low' | 'medium' | 'high';
  let riskScore: number;
  let message: string;

  if (distance <= 0.1) { // Within 0.1 mile (1-2 blocks)
    riskLevel = 'high';
    riskScore = 90;
    message = `Very close to ${nearestStation.station.name} (${distance.toFixed(2)} miles)`;
  } else if (distance <= 0.25) { // Within 1/4 mile
    riskLevel = 'high';
    riskScore = 75;
    message = `Close to ${nearestStation.station.name} (${distance.toFixed(2)} miles)`;
  } else if (distance <= 0.5) { // Within 1/2 mile
    riskLevel = 'medium';
    riskScore = 50;
    message = `Moderate distance from ${nearestStation.station.name} (${distance.toFixed(2)} miles)`;
  } else if (distance <= 1.0) { // Within 1 mile
    riskLevel = 'medium';
    riskScore = 25;
    message = `Reasonable distance from ${nearestStation.station.name} (${distance.toFixed(2)} miles)`;
  } else { // More than 1 mile
    riskLevel = 'low';
    riskScore = 10;
    message = `Far from nearest station: ${nearestStation.station.name} (${distance.toFixed(2)} miles)`;
  }

  return {
    riskLevel,
    riskScore,
    nearestStation,
    message
  };
}

/**
 * Get all police stations (for displaying on map)
 */
export function getAllPoliceStations(): PoliceStation[] {
  return NYC_POLICE_STATIONS;
}

export type { PoliceStation, DistanceResult };