import React, { useState } from 'react';
import { MapContainer, TileLayer, Marker, Popup, useMapEvents } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Store, MapLocation } from './MapLibreMap';

// Fix for default markers in React Leaflet
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
  iconUrl: require('leaflet/dist/images/marker-icon.png'),
  shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
});

interface LeafletMapProps {
  stores?: Store[];
  center?: MapLocation;
  zoom?: number;
  height?: string;
  onLocationSelect?: (location: MapLocation) => void;
  selectedStore?: Store | null;
  onStoreSelect?: (store: Store | null) => void;
  interactive?: boolean;
}

// Custom marker icons for different store statuses
const createCustomIcon = (color: string) => {
  return L.divIcon({
    className: 'custom-marker',
    html: `<div style="
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background-color: ${color};
      border: 2px solid white;
      box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    "></div>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10]
  });
};

const getMarkerIcon = (status: Store['status']) => {
  switch (status) {
    case 'OPEN':
      return createCustomIcon('#10B981'); // Green
    case 'CLOSED_OPERATION_SMOKEOUT':
      return createCustomIcon('#EF4444'); // Red
    case 'CLOSED_OTHER':
      return createCustomIcon('#F59E0B'); // Orange
    case 'REOPENED':
      return createCustomIcon('#3B82F6'); // Blue
    default:
      return createCustomIcon('#6B7280'); // Gray
  }
};

const LocationSelector: React.FC<{ onLocationSelect?: (location: MapLocation) => void }> = ({ 
  onLocationSelect 
}) => {
  useMapEvents({
    click: (e) => {
      if (onLocationSelect) {
        onLocationSelect({
          latitude: e.latlng.lat,
          longitude: e.latlng.lng
        });
      }
    }
  });
  return null;
};

const LeafletMap: React.FC<LeafletMapProps> = ({
  stores = [],
  center = { latitude: 40.7589, longitude: -73.9851 }, // NYC center
  zoom = 11,
  height = '400px',
  onLocationSelect,
  selectedStore,
  onStoreSelect,
  interactive = true
}) => {
  const [selectedLocation, setSelectedLocation] = useState<MapLocation | null>(null);

  const handleLocationSelect = (location: MapLocation) => {
    setSelectedLocation(location);
    if (onLocationSelect) {
      onLocationSelect(location);
    }
  };

  const handleStoreClick = (store: Store) => {
    if (onStoreSelect) {
      onStoreSelect(store);
    }
  };

  return (
    <div style={{ height, width: '100%' }}>
      <MapContainer
        center={[center.latitude, center.longitude]}
        zoom={zoom}
        style={{ height: '100%', width: '100%' }}
        scrollWheelZoom={interactive}
        dragging={interactive}
        touchZoom={interactive}
        doubleClickZoom={interactive}
        boxZoom={interactive}
        keyboard={interactive}
      >
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        
        {/* Location selector for interactive maps */}
        {interactive && onLocationSelect && (
          <LocationSelector onLocationSelect={handleLocationSelect} />
        )}

        {/* Store markers */}
        {stores.map((store) => (
          <Marker
            key={store.id}
            position={[store.latitude, store.longitude]}
            icon={getMarkerIcon(store.status)}
            eventHandlers={{
              click: () => handleStoreClick(store)
            }}
          >
            <Popup>
              <div className="p-2 max-w-xs">
                <h3 className="font-semibold text-gray-900 mb-1">{store.name}</h3>
                <p className="text-sm text-gray-600 mb-2">{store.address}</p>
                <div className="flex items-center mb-2">
                  <div
                    className="w-3 h-3 rounded-full mr-2"
                    style={{ 
                      backgroundColor: store.status === 'OPEN' ? '#10B981' :
                                     store.status === 'CLOSED_OPERATION_SMOKEOUT' ? '#EF4444' :
                                     store.status === 'CLOSED_OTHER' ? '#F59E0B' :
                                     store.status === 'REOPENED' ? '#3B82F6' : '#6B7280'
                    }}
                  />
                  <span className="text-sm font-medium">
                    {store.status.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, l => l.toUpperCase())}
                  </span>
                </div>
                {store.phone && (
                  <p className="text-sm text-gray-600">
                    <i className="fas fa-phone mr-1"></i>
                    {store.phone}
                  </p>
                )}
                {store.website && (
                  <a
                    href={store.website}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm text-blue-600 hover:text-blue-800"
                  >
                    <i className="fas fa-external-link-alt mr-1"></i>
                    Visit Website
                  </a>
                )}
              </div>
            </Popup>
          </Marker>
        ))}

        {/* Selected location marker (for adding new stores) */}
        {selectedLocation && (
          <Marker
            position={[selectedLocation.latitude, selectedLocation.longitude]}
            icon={createCustomIcon('#3B82F6')}
          >
            <Popup>
              <div className="p-2">
                <p className="text-sm font-medium">Selected Location</p>
                <p className="text-xs text-gray-600">
                  {selectedLocation.latitude.toFixed(6)}, {selectedLocation.longitude.toFixed(6)}
                </p>
              </div>
            </Popup>
          </Marker>
        )}
      </MapContainer>
    </div>
  );
};

export default LeafletMap;
