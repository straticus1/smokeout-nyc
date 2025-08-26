import { MapLocation } from '../components/Map';

export interface GeocodingResult {
  location: MapLocation;
  formattedAddress: string;
  placeId?: string;
  components?: {
    streetNumber?: string;
    streetName?: string;
    city?: string;
    state?: string;
    zipCode?: string;
    country?: string;
  };
}

export interface ReverseGeocodingResult {
  formattedAddress: string;
  components?: {
    streetNumber?: string;
    streetName?: string;
    city?: string;
    state?: string;
    zipCode?: string;
    country?: string;
  };
}

class GeocodingService {
  private googleMapsApiKey?: string;

  constructor() {
    this.googleMapsApiKey = process.env.REACT_APP_GOOGLE_MAPS_API_KEY;
  }

  /**
   * Geocode an address using Google Maps Geocoding API
   */
  async geocodeWithGoogle(address: string): Promise<GeocodingResult[]> {
    if (!this.googleMapsApiKey) {
      throw new Error('Google Maps API key not configured');
    }

    const url = `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(address)}&key=${this.googleMapsApiKey}`;
    
    try {
      const response = await fetch(url);
      const data = await response.json();

      if (data.status !== 'OK') {
        throw new Error(`Google Geocoding API error: ${data.status}`);
      }

      return data.results.map((result: any) => ({
        location: {
          latitude: result.geometry.location.lat,
          longitude: result.geometry.location.lng
        },
        formattedAddress: result.formatted_address,
        placeId: result.place_id,
        components: this.parseGoogleComponents(result.address_components)
      }));
    } catch (error) {
      console.error('Google geocoding failed:', error);
      throw error;
    }
  }

  /**
   * Geocode an address using OpenStreetMap Nominatim (free)
   */
  async geocodeWithNominatim(address: string): Promise<GeocodingResult[]> {
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=5&addressdetails=1`;
    
    try {
      const response = await fetch(url, {
        headers: {
          'User-Agent': 'SmokeoutNYC/1.0'
        }
      });
      const data = await response.json();

      return data.map((result: any) => ({
        location: {
          latitude: parseFloat(result.lat),
          longitude: parseFloat(result.lon)
        },
        formattedAddress: result.display_name,
        components: this.parseNominatimComponents(result.address)
      }));
    } catch (error) {
      console.error('Nominatim geocoding failed:', error);
      throw error;
    }
  }

  /**
   * Reverse geocode coordinates using Google Maps
   */
  async reverseGeocodeWithGoogle(location: MapLocation): Promise<ReverseGeocodingResult[]> {
    if (!this.googleMapsApiKey) {
      throw new Error('Google Maps API key not configured');
    }

    const url = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${location.latitude},${location.longitude}&key=${this.googleMapsApiKey}`;
    
    try {
      const response = await fetch(url);
      const data = await response.json();

      if (data.status !== 'OK') {
        throw new Error(`Google Reverse Geocoding API error: ${data.status}`);
      }

      return data.results.map((result: any) => ({
        formattedAddress: result.formatted_address,
        components: this.parseGoogleComponents(result.address_components)
      }));
    } catch (error) {
      console.error('Google reverse geocoding failed:', error);
      throw error;
    }
  }

  /**
   * Reverse geocode coordinates using OpenStreetMap Nominatim
   */
  async reverseGeocodeWithNominatim(location: MapLocation): Promise<ReverseGeocodingResult[]> {
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${location.latitude}&lon=${location.longitude}&addressdetails=1`;
    
    try {
      const response = await fetch(url, {
        headers: {
          'User-Agent': 'SmokeoutNYC/1.0'
        }
      });
      const data = await response.json();

      return [{
        formattedAddress: data.display_name,
        components: this.parseNominatimComponents(data.address)
      }];
    } catch (error) {
      console.error('Nominatim reverse geocoding failed:', error);
      throw error;
    }
  }

  /**
   * Geocode with fallback strategy: Google first, then Nominatim
   */
  async geocode(address: string): Promise<GeocodingResult[]> {
    try {
      // Try Google first if API key is available
      if (this.googleMapsApiKey) {
        return await this.geocodeWithGoogle(address);
      }
    } catch (error) {
      console.warn('Google geocoding failed, falling back to Nominatim:', error);
    }

    // Fallback to Nominatim
    return await this.geocodeWithNominatim(address);
  }

  /**
   * Reverse geocode with fallback strategy
   */
  async reverseGeocode(location: MapLocation): Promise<ReverseGeocodingResult[]> {
    try {
      // Try Google first if API key is available
      if (this.googleMapsApiKey) {
        return await this.reverseGeocodeWithGoogle(location);
      }
    } catch (error) {
      console.warn('Google reverse geocoding failed, falling back to Nominatim:', error);
    }

    // Fallback to Nominatim
    return await this.reverseGeocodeWithNominatim(location);
  }

  /**
   * Parse Google Maps address components
   */
  private parseGoogleComponents(components: any[]) {
    const parsed: any = {};
    
    components.forEach(component => {
      const types = component.types;
      
      if (types.includes('street_number')) {
        parsed.streetNumber = component.long_name;
      } else if (types.includes('route')) {
        parsed.streetName = component.long_name;
      } else if (types.includes('locality')) {
        parsed.city = component.long_name;
      } else if (types.includes('administrative_area_level_1')) {
        parsed.state = component.short_name;
      } else if (types.includes('postal_code')) {
        parsed.zipCode = component.long_name;
      } else if (types.includes('country')) {
        parsed.country = component.short_name;
      }
    });

    return parsed;
  }

  /**
   * Parse Nominatim address components
   */
  private parseNominatimComponents(address: any) {
    return {
      streetNumber: address.house_number,
      streetName: address.road,
      city: address.city || address.town || address.village,
      state: address.state,
      zipCode: address.postcode,
      country: address.country_code?.toUpperCase()
    };
  }
}

export const geocodingService = new GeocodingService();
