import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { getMapConfig, getSiteConfig, type MapProvider } from '../../config/site';

interface MapProviderContextType {
  currentProvider: MapProvider;
  setProvider: (provider: MapProvider) => void;
  isProviderAvailable: (provider: MapProvider) => boolean;
  fallbackToNextProvider: () => void;
  isLoaded: boolean;
  error: string | null;
}

const MapContext = createContext<MapProviderContextType | undefined>(undefined);

interface MapProviderProps {
  children: React.ReactNode;
  preferredProvider?: MapProvider;
}

export const MapProviderComponent: React.FC<MapProviderProps> = ({ 
  children, 
  preferredProvider
}) => {
  const mapConfig = getMapConfig();
  const siteConfig = getSiteConfig();
  
  // Use config-based primary provider if no preference specified
  const initialProvider = preferredProvider || mapConfig.primaryProvider;
  
  const [currentProvider, setCurrentProvider] = useState<MapProvider>(initialProvider);
  const [isLoaded, setIsLoaded] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fallbackIndex, setFallbackIndex] = useState(0);

  const isProviderAvailable = (provider: MapProvider): boolean => {
    switch (provider) {
      case 'maplibre':
        return siteConfig.features.enableLeaflet; // MapLibre uses same availability as Leaflet fallback
      case 'google':
        return siteConfig.features.enableGoogleMaps && !!siteConfig.services.googleMaps.apiKey;
      case 'leaflet':
        return siteConfig.features.enableLeaflet;
      default:
        return false;
    }
  };

  const fallbackToNextProvider = () => {
    if (!mapConfig.enableFallback) {
      setError('Fallback disabled in configuration');
      return;
    }

    const availableProviders = mapConfig.fallbackProviders.filter(isProviderAvailable);
    
    if (fallbackIndex < availableProviders.length) {
      const nextProvider = availableProviders[fallbackIndex];
      setCurrentProvider(nextProvider);
      setFallbackIndex(prev => prev + 1);
    } else {
      setError('No more fallback providers available');
    }
  };

  useEffect(() => {
    const loadMapProvider = async () => {
      try {
        setIsLoaded(false);
        setError(null);

        // Check if current provider is available
        if (!isProviderAvailable(currentProvider)) {
          throw new Error(`${currentProvider} provider not available or not configured`);
        }

        // Try to load the current provider
        switch (currentProvider) {
          case 'maplibre':
            // MapLibre loads automatically with react-map-gl
            setIsLoaded(true);
            break;
          
          case 'google':
            // Google Maps will be loaded by @googlemaps/react-wrapper
            setIsLoaded(true);
            break;
          
          case 'leaflet':
            // Leaflet loads automatically with react-leaflet
            setIsLoaded(true);
            break;
          
          default:
            throw new Error(`Unknown map provider: ${currentProvider}`);
        }
      } catch (err) {
        console.warn(`Failed to load ${currentProvider} map provider:`, err);
        setError(err instanceof Error ? err.message : 'Unknown error');
        
        // Auto-fallback if enabled
        if (mapConfig.enableFallback) {
          fallbackToNextProvider();
        }
      }
    };

    loadMapProvider();
  }, [currentProvider, mapConfig, siteConfig]);

  const setProvider = (provider: MapProvider) => {
    setCurrentProvider(provider);
    setFallbackIndex(0); // Reset fallback index when manually changing provider
  };

  return (
    <MapContext.Provider value={{ 
      currentProvider, 
      setProvider, 
      isProviderAvailable, 
      fallbackToNextProvider,
      isLoaded, 
      error 
    }}>
      {children}
    </MapContext.Provider>
  );
};

export const useMapProvider = () => {
  const context = useContext(MapContext);
  if (context === undefined) {
    throw new Error('useMapProvider must be used within a MapProviderComponent');
  }
  return context;
};
