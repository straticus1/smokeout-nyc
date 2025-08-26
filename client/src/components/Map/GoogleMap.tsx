import React, { useCallback, useState } from 'react';
import { Wrapper, Status } from '@googlemaps/react-wrapper';
import { Store, MapLocation } from './MapLibreMap';

interface GoogleMapProps {
  stores?: Store[];
  center?: MapLocation;
  zoom?: number;
  height?: string;
  onLocationSelect?: (location: MapLocation) => void;
  selectedStore?: Store | null;
  onStoreSelect?: (store: Store | null) => void;
  interactive?: boolean;
}

const MapComponent: React.FC<GoogleMapProps & { map: google.maps.Map }> = ({
  stores = [],
  onLocationSelect,
  selectedStore,
  onStoreSelect,
  interactive = true,
  map
}) => {
  const [markers, setMarkers] = useState<google.maps.Marker[]>([]);
  const [infoWindow, setInfoWindow] = useState<google.maps.InfoWindow | null>(null);

  const getMarkerColor = (status: Store['status']) => {
    switch (status) {
      case 'OPEN':
        return 'green';
      case 'CLOSED_OPERATION_SMOKEOUT':
        return 'red';
      case 'CLOSED_OTHER':
        return 'orange';
      case 'REOPENED':
        return 'blue';
      default:
        return 'gray';
    }
  };

  React.useEffect(() => {
    if (!map) return;

    // Clear existing markers
    markers.forEach(marker => marker.setMap(null));
    setMarkers([]);

    // Add store markers
    const newMarkers = stores.map(store => {
      const marker = new google.maps.Marker({
        position: { lat: store.latitude, lng: store.longitude },
        map,
        title: store.name,
        icon: {
          path: google.maps.SymbolPath.CIRCLE,
          fillColor: getMarkerColor(store.status),
          fillOpacity: 1,
          strokeColor: '#ffffff',
          strokeWeight: 2,
          scale: 8
        }
      });

      marker.addListener('click', () => {
        if (onStoreSelect) {
          onStoreSelect(store);
        }
        
        // Show info window
        if (infoWindow) {
          infoWindow.close();
        }
        
        const newInfoWindow = new google.maps.InfoWindow({
          content: `
            <div style="padding: 8px; max-width: 200px;">
              <h3 style="margin: 0 0 8px 0; font-weight: 600;">${store.name}</h3>
              <p style="margin: 0 0 8px 0; color: #666; font-size: 14px;">${store.address}</p>
              <div style="display: flex; align-items: center; margin-bottom: 8px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background-color: ${getMarkerColor(store.status)}; margin-right: 8px;"></div>
                <span style="font-size: 14px; font-weight: 500;">${store.status.replace(/_/g, ' ')}</span>
              </div>
              ${store.phone ? `<p style="margin: 0; font-size: 14px;"><i class="fas fa-phone"></i> ${store.phone}</p>` : ''}
              ${store.website ? `<a href="${store.website}" target="_blank" style="font-size: 14px; color: #3b82f6;">Visit Website</a>` : ''}
            </div>
          `
        });
        
        newInfoWindow.open(map, marker);
        setInfoWindow(newInfoWindow);
      });

      return marker;
    });

    setMarkers(newMarkers);

    // Add click listener for location selection
    if (interactive && onLocationSelect) {
      const clickListener = map.addListener('click', (event: google.maps.MapMouseEvent) => {
        if (event.latLng) {
          const location = {
            latitude: event.latLng.lat(),
            longitude: event.latLng.lng()
          };
          onLocationSelect(location);
        }
      });

      return () => {
        google.maps.event.removeListener(clickListener);
      };
    }
  }, [map, stores, onLocationSelect, onStoreSelect, interactive, markers, infoWindow]);

  return null;
};

const GoogleMapContainer: React.FC<GoogleMapProps> = ({
  center = { latitude: 40.7589, longitude: -73.9851 },
  zoom = 11,
  height = '400px',
  ...props
}) => {
  const [map, setMap] = useState<google.maps.Map | null>(null);
  const mapRef = useCallback((node: HTMLDivElement | null) => {
    if (node !== null && !map) {
      const newMap = new google.maps.Map(node, {
        center: { lat: center.latitude, lng: center.longitude },
        zoom,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
      });
      setMap(newMap);
    }
  }, [center, zoom, map]);

  return (
    <div style={{ height, width: '100%' }}>
      <div ref={mapRef} style={{ height: '100%', width: '100%' }} />
      {map && <MapComponent map={map} {...props} />}
    </div>
  );
};

const render = (status: Status) => {
  switch (status) {
    case Status.LOADING:
      return (
        <div className="flex items-center justify-center h-full">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
      );
    case Status.FAILURE:
      return (
        <div className="flex items-center justify-center h-full text-red-600">
          <p>Failed to load Google Maps</p>
        </div>
      );
    default:
      return null;
  }
};

const GoogleMap: React.FC<GoogleMapProps> = (props) => {
  const apiKey = process.env.REACT_APP_GOOGLE_MAPS_API_KEY;
  
  if (!apiKey) {
    return (
      <div className="flex items-center justify-center h-full text-gray-600">
        <p>Google Maps API key not configured</p>
      </div>
    );
  }

  return (
    <Wrapper apiKey={apiKey} render={render}>
      <GoogleMapContainer {...props} />
    </Wrapper>
  );
};

export default GoogleMap;
