import React from 'react';
import { ExclamationTriangleIcon, ShieldCheckIcon, MapPinIcon } from '@heroicons/react/24/outline';
import { getPoliceDistanceRisk, DistanceResult } from '../services/policeStationService';

interface PoliceDistanceIndicatorProps {
  latitude: number;
  longitude: number;
  showDetails?: boolean;
  className?: string;
}

const PoliceDistanceIndicator: React.FC<PoliceDistanceIndicatorProps> = ({
  latitude,
  longitude,
  showDetails = false,
  className = ''
}) => {
  const riskData = getPoliceDistanceRisk(latitude, longitude);

  if (!riskData.nearestStation) {
    return null;
  }

  const getRiskColor = (riskLevel: string) => {
    switch (riskLevel) {
      case 'high': return 'text-red-600 bg-red-50 border-red-200';
      case 'medium': return 'text-yellow-600 bg-yellow-50 border-yellow-200';
      case 'low': return 'text-green-600 bg-green-50 border-green-200';
      default: return 'text-gray-600 bg-gray-50 border-gray-200';
    }
  };

  const getRiskIcon = (riskLevel: string) => {
    switch (riskLevel) {
      case 'high': return <ExclamationTriangleIcon className="w-4 h-4" />;
      case 'medium': return <MapPinIcon className="w-4 h-4" />;
      case 'low': return <ShieldCheckIcon className="w-4 h-4" />;
      default: return <MapPinIcon className="w-4 h-4" />;
    }
  };

  const getRiskBadgeText = (riskLevel: string, distance: number) => {
    switch (riskLevel) {
      case 'high': return `⚠️ ${distance.toFixed(2)}mi`;
      case 'medium': return `📍 ${distance.toFixed(2)}mi`;
      case 'low': return `✅ ${distance.toFixed(2)}mi`;
      default: return `${distance.toFixed(2)}mi`;
    }
  };

  if (!showDetails) {
    // Compact badge version
    return (
      <div className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border ${getRiskColor(riskData.riskLevel)} ${className}`}>
        {getRiskIcon(riskData.riskLevel)}
        <span className="ml-1">
          {getRiskBadgeText(riskData.riskLevel, riskData.nearestStation.distance)}
        </span>
      </div>
    );
  }

  // Detailed card version
  return (
    <div className={`border rounded-lg p-4 ${getRiskColor(riskData.riskLevel)} ${className}`}>
      <div className="flex items-start space-x-3">
        <div className="flex-shrink-0 mt-1">
          {getRiskIcon(riskData.riskLevel)}
        </div>
        
        <div className="flex-1 min-w-0">
          <h3 className="text-sm font-semibold">
            Police Station Proximity
          </h3>
          
          <p className="text-sm mt-1">
            {riskData.message}
          </p>
          
          <div className="mt-3 space-y-2 text-xs">
            <div className="flex justify-between">
              <span className="font-medium">Risk Level:</span>
              <span className="capitalize font-semibold">{riskData.riskLevel}</span>
            </div>
            
            <div className="flex justify-between">
              <span className="font-medium">Risk Score:</span>
              <span className="font-semibold">{riskData.riskScore}/100</span>
            </div>
            
            <div className="flex justify-between">
              <span className="font-medium">Walking Time:</span>
              <span>~{riskData.nearestStation.walkingTime} min</span>
            </div>
            
            <div className="mt-2 pt-2 border-t border-current border-opacity-20">
              <div className="font-medium text-xs">Nearest Station:</div>
              <div className="text-xs">{riskData.nearestStation.station.name}</div>
              <div className="text-xs opacity-75">{riskData.nearestStation.station.address}</div>
              {riskData.nearestStation.station.phone && (
                <div className="text-xs opacity-75">{riskData.nearestStation.station.phone}</div>
              )}
            </div>
          </div>
        </div>
      </div>
      
      {/* Risk explanation */}
      <div className="mt-3 pt-3 border-t border-current border-opacity-20 text-xs opacity-75">
        <strong>Risk Assessment:</strong> Based on proximity to NYPD stations. 
        Closer proximity may indicate higher enforcement activity in the area.
      </div>
    </div>
  );
};

interface PoliceStationsMapOverlayProps {
  onStationClick?: (station: any) => void;
}

export const PoliceStationsMapOverlay: React.FC<PoliceStationsMapOverlayProps> = ({
  onStationClick
}) => {
  // This would be used to overlay police station markers on the map
  // Implementation would depend on the specific map component being used
  return null;
};

export default PoliceDistanceIndicator;