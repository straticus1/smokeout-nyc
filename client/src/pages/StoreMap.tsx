import React, { useState, useEffect } from 'react';
import { UniversalMap, MapProviderComponent, Store, MapLocation } from '../components/Map';
import { geocodingService } from '../services/geocoding';
import PoliceDistanceIndicator from '../components/PoliceDistanceIndicator';

const StoreMap: React.FC = () => {
  const [stores, setStores] = useState<Store[]>([]);
  const [selectedStore, setSelectedStore] = useState<Store | null>(null);
  const [loading, setLoading] = useState(true);
  const [searchAddress, setSearchAddress] = useState('');
  const [mapCenter, setMapCenter] = useState<MapLocation>({ latitude: 40.7589, longitude: -73.9851 });
  const [mapZoom, setMapZoom] = useState(11);

  // Mock data - replace with actual API call
  useEffect(() => {
    const mockStores: Store[] = [
      {
        id: '1',
        name: 'Green Leaf Smoke Shop',
        address: '123 Broadway, New York, NY 10001',
        latitude: 40.7505,
        longitude: -73.9934,
        status: 'OPEN',
        phone: '(212) 555-0123',
        website: 'https://greenleaf.example.com'
      },
      {
        id: '2',
        name: 'City Smoke & Vape',
        address: '456 5th Avenue, New York, NY 10018',
        latitude: 40.7549,
        longitude: -73.9840,
        status: 'CLOSED_OPERATION_SMOKEOUT',
        phone: '(212) 555-0456'
      },
      {
        id: '3',
        name: 'Brooklyn Smoke House',
        address: '789 Atlantic Avenue, Brooklyn, NY 11238',
        latitude: 40.6782,
        longitude: -73.9442,
        status: 'REOPENED',
        phone: '(718) 555-0789',
        website: 'https://brooklynsmoke.example.com'
      }
    ];

    setTimeout(() => {
      setStores(mockStores);
      setLoading(false);
    }, 1000);
  }, []);

  const handleStoreSelect = (store: Store | null) => {
    setSelectedStore(store);
    if (store) {
      setMapCenter({ latitude: store.latitude, longitude: store.longitude });
      setMapZoom(15);
    }
  };

  const handleAddressSearch = async () => {
    if (!searchAddress.trim()) return;

    try {
      const results = await geocodingService.geocode(searchAddress);
      if (results.length > 0) {
        const result = results[0];
        setMapCenter(result.location);
        setMapZoom(15);
      }
    } catch (error) {
      console.error('Geocoding failed:', error);
      alert('Could not find the address. Please try a different search.');
    }
  };

  const getStatusColor = (status: Store['status']) => {
    switch (status) {
      case 'OPEN':
        return 'text-green-600 bg-green-100';
      case 'CLOSED_OPERATION_SMOKEOUT':
        return 'text-red-600 bg-red-100';
      case 'CLOSED_OTHER':
        return 'text-orange-600 bg-orange-100';
      case 'REOPENED':
        return 'text-blue-600 bg-blue-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getStatusText = (status: Store['status']) => {
    return status.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading stores...</p>
        </div>
      </div>
    );
  }

  return (
    <MapProviderComponent preferredProvider="maplibre">
      <div className="min-h-screen bg-gray-50">
        {/* Header */}
        <div className="bg-white shadow-sm border-b">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h1 className="text-2xl font-bold text-gray-900">NYC Smoke Shop Map</h1>
                <p className="text-gray-600 mt-1">Find smoke shops and track Operation Smokeout closures</p>
              </div>
              
              {/* Search */}
              <div className="mt-4 sm:mt-0 flex">
                <input
                  type="text"
                  placeholder="Search address..."
                  value={searchAddress}
                  onChange={(e) => setSearchAddress(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleAddressSearch()}
                  className="px-4 py-2 border border-gray-300 rounded-l-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <button
                  onClick={handleAddressSearch}
                  className="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500"
                >
                  <i className="fas fa-search"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {/* Store List */}
            <div className="lg:col-span-1">
              <div className="bg-white rounded-lg shadow-sm border">
                <div className="p-4 border-b">
                  <h2 className="text-lg font-semibold text-gray-900">
                    Stores ({stores.length})
                  </h2>
                </div>
                <div className="max-h-96 overflow-y-auto">
                  {stores.map((store) => (
                    <div
                      key={store.id}
                      className={`p-4 border-b cursor-pointer hover:bg-gray-50 transition-colors ${
                        selectedStore?.id === store.id ? 'bg-blue-50 border-blue-200' : ''
                      }`}
                      onClick={() => handleStoreSelect(store)}
                    >
                      <h3 className="font-medium text-gray-900 mb-1">{store.name}</h3>
                      <p className="text-sm text-gray-600 mb-2">{store.address}</p>
                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(store.status)}`}>
                        {getStatusText(store.status)}
                      </span>
                      {store.phone && (
                        <p className="text-sm text-gray-500 mt-1">
                          <i className="fas fa-phone mr-1"></i>
                          {store.phone}
                        </p>
                      )}
                      
                      {/* Police Distance Indicator */}
                      <div className="mt-2">
                        <PoliceDistanceIndicator 
                          latitude={store.latitude} 
                          longitude={store.longitude}
                          showDetails={false}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Legend */}
              <div className="bg-white rounded-lg shadow-sm border mt-4">
                <div className="p-4 border-b">
                  <h3 className="text-lg font-semibold text-gray-900">Legend</h3>
                </div>
                <div className="p-4 space-y-2">
                  <div className="flex items-center">
                    <div className="w-4 h-4 bg-green-500 rounded-full mr-3"></div>
                    <span className="text-sm text-gray-700">Open</span>
                  </div>
                  <div className="flex items-center">
                    <div className="w-4 h-4 bg-red-500 rounded-full mr-3"></div>
                    <span className="text-sm text-gray-700">Operation Smokeout</span>
                  </div>
                  <div className="flex items-center">
                    <div className="w-4 h-4 bg-orange-500 rounded-full mr-3"></div>
                    <span className="text-sm text-gray-700">Closed (Other)</span>
                  </div>
                  <div className="flex items-center">
                    <div className="w-4 h-4 bg-blue-500 rounded-full mr-3"></div>
                    <span className="text-sm text-gray-700">Reopened</span>
                  </div>
                  <div className="flex items-center">
                    <div className="w-4 h-4 bg-gray-500 rounded-full mr-3"></div>
                    <span className="text-sm text-gray-700">Unknown Status</span>
                  </div>
                  
                  {/* Police Distance Legend */}
                  <hr className="my-3" />
                  <div className="text-xs font-semibold text-gray-900 mb-2">Police Station Proximity</div>
                  <div className="space-y-1 text-xs">
                    <div className="flex items-center">
                      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-red-600 bg-red-50 border border-red-200 mr-2">⚠️</span>
                      <span className="text-gray-600">High Risk (Close)</span>
                    </div>
                    <div className="flex items-center">
                      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-yellow-600 bg-yellow-50 border border-yellow-200 mr-2">📍</span>
                      <span className="text-gray-600">Medium Risk</span>
                    </div>
                    <div className="flex items-center">
                      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-green-600 bg-green-50 border border-green-200 mr-2">✅</span>
                      <span className="text-gray-600">Low Risk (Far)</span>
                    </div>
                  </div>
                  <div className="text-xs text-gray-500 mt-2">
                    Distance to nearest NYPD station. Closer proximity may indicate higher enforcement activity.
                  </div>
                </div>
              </div>
            </div>

            {/* Map */}
            <div className="lg:col-span-3">
              <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
                <UniversalMap
                  stores={stores}
                  center={mapCenter}
                  zoom={mapZoom}
                  height="600px"
                  selectedStore={selectedStore}
                  onStoreSelect={handleStoreSelect}
                  interactive={true}
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </MapProviderComponent>
  );
};

export default StoreMap;
