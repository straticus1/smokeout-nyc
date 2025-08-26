export type MapProvider = 'maplibre' | 'google' | 'leaflet';

export interface MapConfig {
  // Primary map provider to use
  primaryProvider: MapProvider;
  
  // Fallback providers in order of preference
  fallbackProviders: MapProvider[];
  
  // Enable automatic fallback when primary provider fails
  enableFallback: boolean;
  
  // Default map center (NYC coordinates)
  defaultCenter: {
    latitude: number;
    longitude: number;
  };
  
  // Default zoom level
  defaultZoom: number;
  
  // Map style preferences
  styles: {
    // MapLibre style URL (can be custom or default)
    maplibreStyle: string;
    
    // Google Maps style options
    googleMapsStyle: {
      disableDefaultUI: boolean;
      mapTypeControl: boolean;
      streetViewControl: boolean;
      fullscreenControl: boolean;
    };
    
    // Leaflet tile layer options
    leafletTileLayer: {
      url: string;
      attribution: string;
    };
  };
  
  // Geocoding preferences
  geocoding: {
    // Preferred geocoding service
    primaryService: 'google' | 'nominatim';
    
    // Enable fallback geocoding
    enableFallback: boolean;
    
    // Rate limiting for free services (ms between requests)
    rateLimitDelay: number;
  };
}

export interface SiteConfig {
  // Application metadata
  app: {
    name: string;
    version: string;
    description: string;
    url: string;
  };
  
  // API configuration
  api: {
    baseUrl: string;
    timeout: number;
  };
  
  // Map configuration
  maps: MapConfig;
  
  // Feature flags
  features: {
    enableGoogleMaps: boolean;
    enableMapbox: boolean;
    enableLeaflet: boolean;
    enableGeocoding: boolean;
    enableReverseGeocoding: boolean;
    enableLocationSearch: boolean;
  };
  
  // UI preferences
  ui: {
    theme: 'light' | 'dark' | 'auto';
    showMapLegend: boolean;
    showOnlineUserCount: boolean;
    enableAnimations: boolean;
  };
  
  // External service configurations
  services: {
    googleMaps: {
      enabled: boolean;
      apiKey?: string;
    };
    mapbox: {
      enabled: boolean;
      accessToken?: string;
    };
  };
}

// Default site configuration
export const defaultSiteConfig: SiteConfig = {
  app: {
    name: 'SmokeoutNYC',
    version: '2.0.0',
    description: 'Track NYC Smoke Shop Closures & Operation Smokeout',
    url: process.env.REACT_APP_SITE_URL || 'http://localhost:3000'
  },
  
  api: {
    baseUrl: process.env.REACT_APP_API_BASE_URL || 'http://localhost:3001/api',
    timeout: 10000
  },
  
  maps: {
    primaryProvider: 'maplibre',
    fallbackProviders: ['leaflet', 'google'],
    enableFallback: true,
    
    defaultCenter: {
      latitude: 40.7589,
      longitude: -73.9851
    },
    
    defaultZoom: 11,
    
    styles: {
      maplibreStyle: 'https://demotiles.maplibre.org/style.json',
      
      googleMapsStyle: {
        disableDefaultUI: false,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false
      },
      
      leafletTileLayer: {
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '© OpenStreetMap contributors'
      }
    },
    
    geocoding: {
      primaryService: process.env.REACT_APP_GOOGLE_MAPS_API_KEY ? 'google' : 'nominatim',
      enableFallback: true,
      rateLimitDelay: 1000 // 1 second between requests for free services
    }
  },
  
  features: {
    enableGoogleMaps: !!process.env.REACT_APP_GOOGLE_MAPS_API_KEY,
    enableMapbox: !!process.env.REACT_APP_MAPBOX_ACCESS_TOKEN,
    enableLeaflet: true, // Always available as fallback
    enableGeocoding: true,
    enableReverseGeocoding: true,
    enableLocationSearch: true
  },
  
  ui: {
    theme: 'light',
    showMapLegend: true,
    showOnlineUserCount: true,
    enableAnimations: true
  },
  
  services: {
    googleMaps: {
      enabled: !!process.env.REACT_APP_GOOGLE_MAPS_API_KEY,
      apiKey: process.env.REACT_APP_GOOGLE_MAPS_API_KEY
    },
    mapbox: {
      enabled: !!process.env.REACT_APP_MAPBOX_ACCESS_TOKEN,
      accessToken: process.env.REACT_APP_MAPBOX_ACCESS_TOKEN
    }
  }
};

// Allow runtime configuration override
let siteConfig: SiteConfig = { ...defaultSiteConfig };

// Function to update site configuration
export const updateSiteConfig = (updates: Partial<SiteConfig>): void => {
  siteConfig = {
    ...siteConfig,
    ...updates,
    maps: {
      ...siteConfig.maps,
      ...(updates.maps || {})
    },
    features: {
      ...siteConfig.features,
      ...(updates.features || {})
    },
    ui: {
      ...siteConfig.ui,
      ...(updates.ui || {})
    },
    services: {
      ...siteConfig.services,
      ...(updates.services || {})
    }
  };
};

// Function to get current site configuration
export const getSiteConfig = (): SiteConfig => siteConfig;

// Helper functions for common config access
export const getMapConfig = (): MapConfig => siteConfig.maps;
export const getApiConfig = () => siteConfig.api;
export const getFeatures = () => siteConfig.features;
export const getUIConfig = () => siteConfig.ui;
