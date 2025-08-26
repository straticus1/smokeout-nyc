import React, { useRef, useEffect, useState } from 'react';
import Map, { Marker, Popup, NavigationControl, GeolocateControl } from 'react-map-gl/maplibre';
import 'maplibre-gl/dist/maplibre-gl.css';

export interface MapLocation {
  latitude: number;
  longitude: number;
}

export interface Store {
  id: string;
  name: string;
  address: string;
  latitude: number;
  longitude: number;
  status: 'OPEN' | 'CLOSED_OPERATION_SMOKEOUT' | 'CLOSED_OTHER' | 'CLOSED_UNKNOWN' | 'REOPENED';
  phone?: string;
  website?: string;
}

interface MapLibreMapProps {
  stores?: Store[];
  center?: MapLocation;
  zoom?: number;
  height?: string;
  onLocationSelect?: (location: MapLocation) => void;
  selectedStore?: Store | null;
  onStoreSelect?: (store: Store | null) => void;
  interactive?: boolean;
}

const MapLibreMap: React.FC<MapLibreMapProps> = ({
  stores = [],
  center = { latitude: 40.7589, longitude: -73.9851 }, // NYC center
  zoom = 11,
  height = '400px',
  onLocationSelect,
  selectedStore,
  onStoreSelect,
  interactive = true
}) => {
  const mapRef = useRef<any>();
  const [viewState, setViewState] = useState({
    longitude: center.longitude,
    latitude: center.latitude,
    zoom: zoom
  });
  const [selectedLocation, setSelectedLocation] = useState<MapLocation | null>(null);

  const getMarkerColor = (status: Store['status']) => {
    switch (status) {
      case 'OPEN':
        return '#10B981'; // Green
      case 'CLOSED_OPERATION_SMOKEOUT':
        return '#EF4444'; // Red
      case 'CLOSED_OTHER':
        return '#F59E0B'; // Orange
      case 'REOPENED':
        return '#3B82F6'; // Blue
      default:
        return '#6B7280'; // Gray
    }
  };

  const handleMapClick = (event: any) => {
    if (onLocationSelect && interactive) {
      const { lng, lat } = event.lngLat;
      const location = { latitude: lat, longitude: lng };
      setSelectedLocation(location);
      onLocationSelect(location);
    }
  };

  const handleMarkerClick = (store: Store) => {
    if (onStoreSelect) {
      onStoreSelect(store);
    }
    // Center map on selected store
    setViewState({
      ...viewState,
      longitude: store.longitude,
      latitude: store.latitude,
      zoom: 15
    });
  };

  return (
    <div style={{ height, width: '100%' }}>
      <Map
        ref={mapRef}
        {...viewState}
        onMove={evt => setViewState(evt.viewState)}
        onClick={handleMapClick}
        mapStyle="https://demotiles.maplibre.org/style.json"
        style={{ width: '100%', height: '100%' }}
        interactive={interactive}
      >
        {/* Navigation Controls */}
        <NavigationControl position="top-right" />
        <GeolocateControl position="top-right" />

        {/* Store Markers */}
        {stores.map((store) => (
          <Marker
            key={store.id}
            longitude={store.longitude}
            latitude={store.latitude}
            anchor="bottom"
            onClick={(e) => {
              e.originalEvent.stopPropagation();
              handleMarkerClick(store);
            }}
          >
            <div
              className="w-6 h-6 rounded-full border-2 border-white shadow-lg cursor-pointer transform hover:scale-110 transition-transform"
              style={{ backgroundColor: getMarkerColor(store.status) }}
            />
          </Marker>
        ))}

        {/* Selected Location Marker (for adding new stores) */}
        {selectedLocation && (
          <Marker
            longitude={selectedLocation.longitude}
            latitude={selectedLocation.latitude}
            anchor="bottom"
          >
            <div className="w-6 h-6 bg-blue-600 rounded-full border-2 border-white shadow-lg animate-pulse" />
          </Marker>
        )}

        {/* Store Popup */}
        {selectedStore && (
          <Popup
            longitude={selectedStore.longitude}
            latitude={selectedStore.latitude}
            anchor="top"
            onClose={() => onStoreSelect?.(null)}
            closeButton={true}
            closeOnClick={false}
          >
            <div className="p-2 max-w-xs">
              <h3 className="font-semibold text-gray-900 mb-1">{selectedStore.name}</h3>
              <p className="text-sm text-gray-600 mb-2">{selectedStore.address}</p>
              <div className="flex items-center mb-2">
                <div
                  className="w-3 h-3 rounded-full mr-2"
                  style={{ backgroundColor: getMarkerColor(selectedStore.status) }}
                />
                <span className="text-sm font-medium">
                  {selectedStore.status.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, l => l.toUpperCase())}
                </span>
              </div>
              {selectedStore.phone && (
                <p className="text-sm text-gray-600">
                  <i className="fas fa-phone mr-1"></i>
                  {selectedStore.phone}
                </p>
              )}
              {selectedStore.website && (
                <a
                  href={selectedStore.website}
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
        )}
      </Map>
    </div>
  );
};

export default MapLibreMap;
