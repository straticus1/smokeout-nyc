import React from 'react';
import { useMapProvider } from './MapProvider';
import MapLibreMap from './MapLibreMap';
import GoogleMap from './GoogleMap';
import LeafletMap from './LeafletMap';
import { Store, MapLocation } from './MapLibreMap';

interface UniversalMapProps {
  stores?: Store[];
  center?: MapLocation;
  zoom?: number;
  height?: string;
  onLocationSelect?: (location: MapLocation) => void;
  selectedStore?: Store | null;
  onStoreSelect?: (store: Store | null) => void;
  interactive?: boolean;
  preferredProvider?: 'maplibre' | 'google' | 'leaflet';
}

const UniversalMap: React.FC<UniversalMapProps> = ({
  preferredProvider,
  ...props
}) => {
  const { provider, isLoaded, error } = useMapProvider();

  if (!isLoaded) {
    return (
      <div className="flex items-center justify-center bg-gray-100 rounded-lg" style={{ height: props.height || '400px' }}>
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
          <p className="text-gray-600">Loading map...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center bg-gray-100 rounded-lg" style={{ height: props.height || '400px' }}>
        <div className="text-center text-red-600">
          <i className="fas fa-exclamation-triangle text-2xl mb-2"></i>
          <p>Failed to load map: {error}</p>
        </div>
      </div>
    );
  }

  switch (provider) {
    case 'google':
      return <GoogleMap {...props} />;
    case 'leaflet':
      return <LeafletMap {...props} />;
    case 'maplibre':
    default:
      return <MapLibreMap {...props} />;
  }
};

export default UniversalMap;
