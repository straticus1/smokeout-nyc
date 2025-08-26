import React, { useState } from 'react';
import { UniversalMap, MapProviderComponent, MapLocation } from '../components/Map';
import { geocodingService } from '../services/geocoding';

interface StoreFormData {
  name: string;
  address: string;
  latitude: number;
  longitude: number;
  phone: string;
  email: string;
  website: string;
  description: string;
  status: 'OPEN' | 'CLOSED_OTHER' | 'CLOSED_UNKNOWN';
  hours: {
    [key: string]: string;
  };
}

const AddStore: React.FC = () => {
  const [formData, setFormData] = useState<StoreFormData>({
    name: '',
    address: '',
    latitude: 0,
    longitude: 0,
    phone: '',
    email: '',
    website: '',
    description: '',
    status: 'OPEN',
    hours: {
      monday: '',
      tuesday: '',
      wednesday: '',
      thursday: '',
      friday: '',
      saturday: '',
      sunday: ''
    }
  });

  const [selectedLocation, setSelectedLocation] = useState<MapLocation | null>(null);
  const [isGeocoding, setIsGeocoding] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleHoursChange = (day: string, value: string) => {
    setFormData(prev => ({
      ...prev,
      hours: {
        ...prev.hours,
        [day]: value
      }
    }));
  };

  const handleAddressChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const address = e.target.value;
    setFormData(prev => ({ ...prev, address }));

    // Auto-geocode when address is complete enough
    if (address.length > 10 && address.includes(',')) {
      await geocodeAddress(address);
    }
  };

  const geocodeAddress = async (address?: string) => {
    const addressToGeocode = address || formData.address;
    if (!addressToGeocode.trim()) return;

    setIsGeocoding(true);
    try {
      const results = await geocodingService.geocode(addressToGeocode);
      if (results.length > 0) {
        const result = results[0];
        setSelectedLocation(result.location);
        setFormData(prev => ({
          ...prev,
          latitude: result.location.latitude,
          longitude: result.location.longitude,
          address: result.formattedAddress
        }));
      }
    } catch (error) {
      console.error('Geocoding failed:', error);
    } finally {
      setIsGeocoding(false);
    }
  };

  const handleLocationSelect = (location: MapLocation) => {
    setSelectedLocation(location);
    setFormData(prev => ({
      ...prev,
      latitude: location.latitude,
      longitude: location.longitude
    }));

    // Reverse geocode to get address
    geocodingService.reverseGeocode(location)
      .then(results => {
        if (results.length > 0) {
          setFormData(prev => ({
            ...prev,
            address: results[0].formattedAddress
          }));
        }
      })
      .catch(error => console.error('Reverse geocoding failed:', error));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    // Validation
    if (!formData.name.trim()) {
      setError('Store name is required');
      return;
    }
    if (!formData.address.trim()) {
      setError('Address is required');
      return;
    }
    if (!formData.latitude || !formData.longitude) {
      setError('Please set the location on the map');
      return;
    }

    setIsSubmitting(true);
    try {
      // Submit to API
      const response = await fetch('/api/stores', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify(formData)
      });

      if (response.ok) {
        setSuccess('Store submitted successfully! It will be reviewed by administrators before appearing on the site.');
        // Reset form
        setFormData({
          name: '',
          address: '',
          latitude: 0,
          longitude: 0,
          phone: '',
          email: '',
          website: '',
          description: '',
          status: 'OPEN',
          hours: {
            monday: '',
            tuesday: '',
            wednesday: '',
            thursday: '',
            friday: '',
            saturday: '',
            sunday: ''
          }
        });
        setSelectedLocation(null);
      } else {
        const errorData = await response.json();
        setError(errorData.error || 'Failed to submit store');
      }
    } catch (error) {
      setError('Network error. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const clearForm = () => {
    if (confirm('Are you sure you want to clear all form data?')) {
      setFormData({
        name: '',
        address: '',
        latitude: 0,
        longitude: 0,
        phone: '',
        email: '',
        website: '',
        description: '',
        status: 'OPEN',
        hours: {
          monday: '',
          tuesday: '',
          wednesday: '',
          thursday: '',
          friday: '',
          saturday: '',
          sunday: ''
        }
      });
      setSelectedLocation(null);
      setError('');
      setSuccess('');
    }
  };

  const formatPhoneNumber = (value: string) => {
    const cleaned = value.replace(/\D/g, '');
    if (cleaned.length >= 6) {
      return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
    } else if (cleaned.length >= 3) {
      return cleaned.replace(/(\d{3})(\d{0,3})/, '($1) $2');
    }
    return cleaned;
  };

  const handlePhoneChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const formatted = formatPhoneNumber(e.target.value);
    setFormData(prev => ({ ...prev, phone: formatted }));
  };

  const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

  return (
    <MapProviderComponent preferredProvider="maplibre">
      <div className="min-h-screen bg-gray-50">
        {/* Header */}
        <div className="bg-gradient-to-r from-green-600 to-blue-600 text-white py-16">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 className="text-4xl md:text-5xl font-bold mb-4">
              <i className="fas fa-plus-circle mr-3"></i>
              Add a Smoke Shop
            </h1>
            <p className="text-xl md:text-2xl opacity-90 max-w-3xl mx-auto">
              Help keep our community informed by adding smoke shops to our database
            </p>
          </div>
        </div>

        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="bg-white rounded-lg shadow-md p-8">
            <div className="mb-8">
              <h2 className="text-2xl font-bold text-gray-900 mb-2">Submit New Smoke Shop</h2>
              <p className="text-gray-600">
                Please provide accurate information about the smoke shop. All submissions will be reviewed by our administrators before being published.
              </p>
            </div>

            {error && (
              <div className="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <div className="flex">
                  <i className="fas fa-exclamation-circle text-red-400 mr-3 mt-0.5"></i>
                  <div className="text-sm text-red-700">{error}</div>
                </div>
              </div>
            )}

            {success && (
              <div className="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                <div className="flex">
                  <i className="fas fa-check-circle text-green-400 mr-3 mt-0.5"></i>
                  <div className="text-sm text-green-700">{success}</div>
                </div>
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Basic Information */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                    Store Name <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    value={formData.name}
                    onChange={handleInputChange}
                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Enter store name"
                  />
                </div>

                <div>
                  <label htmlFor="status" className="block text-sm font-medium text-gray-700">
                    Current Status
                  </label>
                  <select
                    id="status"
                    name="status"
                    value={formData.status}
                    onChange={handleInputChange}
                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  >
                    <option value="OPEN">Open</option>
                    <option value="CLOSED_OTHER">Closed</option>
                    <option value="CLOSED_UNKNOWN">Status Unknown</option>
                  </select>
                </div>
              </div>

              {/* Address and Location */}
              <div>
                <label htmlFor="address" className="block text-sm font-medium text-gray-700">
                  Full Address <span className="text-red-500">*</span>
                </label>
                <div className="mt-1 flex">
                  <input
                    type="text"
                    id="address"
                    name="address"
                    required
                    value={formData.address}
                    onChange={handleAddressChange}
                    className="flex-1 px-3 py-2 border border-gray-300 rounded-l-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="123 Main St, New York, NY 10001"
                  />
                  <button
                    type="button"
                    onClick={() => geocodeAddress()}
                    disabled={isGeocoding}
                    className="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                  >
                    {isGeocoding ? (
                      <i className="fas fa-spinner fa-spin"></i>
                    ) : (
                      <i className="fas fa-search"></i>
                    )}
                  </button>
                </div>
                <p className="mt-1 text-sm text-gray-500">
                  Enter the complete address. We'll automatically find the coordinates.
                </p>
              </div>

              {/* Map for Location Selection */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  <i className="fas fa-map-marker-alt mr-1"></i>
                  Location on Map <span className="text-red-500">*</span>
                </label>
                <div className="border border-gray-300 rounded-md overflow-hidden">
                  <UniversalMap
                    center={selectedLocation || { latitude: 40.7589, longitude: -73.9851 }}
                    zoom={selectedLocation ? 15 : 11}
                    height="300px"
                    onLocationSelect={handleLocationSelect}
                    interactive={true}
                  />
                </div>
                <p className="mt-2 text-sm text-gray-500">
                  Click on the map to set the exact location, or it will be set automatically from the address.
                </p>
              </div>

              {/* Contact Information */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label htmlFor="phone" className="block text-sm font-medium text-gray-700">
                    Phone Number
                  </label>
                  <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value={formData.phone}
                    onChange={handlePhoneChange}
                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="(212) 555-0123"
                  />
                </div>

                <div>
                  <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                    Email Address
                  </label>
                  <input
                    type="email"
                    id="email"
                    name="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="store@example.com"
                  />
                </div>
              </div>

              <div>
                <label htmlFor="website" className="block text-sm font-medium text-gray-700">
                  Website URL
                </label>
                <input
                  type="url"
                  id="website"
                  name="website"
                  value={formData.website}
                  onChange={handleInputChange}
                  className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="https://www.example.com"
                />
              </div>

              {/* Description */}
              <div>
                <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                  Description
                </label>
                <textarea
                  id="description"
                  name="description"
                  rows={4}
                  value={formData.description}
                  onChange={handleInputChange}
                  className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Describe the store, products available, special features, etc."
                />
                <p className="mt-1 text-sm text-gray-500">
                  Optional: Provide additional information about the store.
                </p>
              </div>

              {/* Store Hours */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-3">
                  <i className="fas fa-clock mr-1"></i>
                  Store Hours (Optional)
                </label>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {days.map(day => (
                    <div key={day}>
                      <label htmlFor={`hours_${day}`} className="block text-xs font-medium text-gray-600 mb-1">
                        {day.charAt(0).toUpperCase() + day.slice(1)}
                      </label>
                      <input
                        type="text"
                        id={`hours_${day}`}
                        value={formData.hours[day]}
                        onChange={(e) => handleHoursChange(day, e.target.value)}
                        className="block w-full px-2 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                        placeholder="9:00 AM - 9:00 PM"
                      />
                    </div>
                  ))}
                </div>
                <p className="mt-2 text-sm text-gray-500">
                  Enter hours in any format (e.g., "9:00 AM - 9:00 PM", "Closed", "24 hours")
                </p>
              </div>

              {/* Submission Guidelines */}
              <div className="bg-blue-50 border border-blue-200 rounded-md p-4">
                <h4 className="font-semibold text-blue-900 mb-2">
                  <i className="fas fa-info-circle mr-2"></i>
                  Submission Guidelines
                </h4>
                <ul className="text-sm text-blue-800 space-y-1">
                  <li>• All submissions are reviewed by administrators before publication</li>
                  <li>• Please provide accurate and up-to-date information</li>
                  <li>• Duplicate submissions will be removed</li>
                  <li>• You may be contacted for verification</li>
                  <li>• False information may result in account suspension</li>
                </ul>
              </div>

              {/* Submit Button */}
              <div className="flex justify-end space-x-4">
                <button
                  type="button"
                  onClick={clearForm}
                  className="px-6 py-3 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                  <i className="fas fa-times mr-2"></i>Clear Form
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="px-6 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                >
                  {isSubmitting ? (
                    <>
                      <i className="fas fa-spinner fa-spin mr-2"></i>Submitting...
                    </>
                  ) : (
                    <>
                      <i className="fas fa-paper-plane mr-2"></i>Submit for Review
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </MapProviderComponent>
  );
};

export default AddStore;
