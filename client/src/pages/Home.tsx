import React from 'react';
import { Link } from 'react-router-dom';

const Home: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Hero Section */}
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h1 className="text-4xl md:text-6xl font-bold mb-6">
            Track NYC <span className="text-yellow-300">Smoke Shop</span> Closures
          </h1>
          <p className="text-xl md:text-2xl mb-8 opacity-90">
            Stay informed about Operation Smokeout enforcement, cannabis laws, and smoke shop status updates across New York City.
          </p>
          
          {/* Quick Stats */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div className="text-center">
              <div className="text-2xl font-bold">1,247</div>
              <div className="text-sm opacity-75">Total Shops</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-red-300">423</div>
              <div className="text-sm opacity-75">Operation Smokeout</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-green-300">687</div>
              <div className="text-sm opacity-75">Still Open</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-blue-300">137</div>
              <div className="text-sm opacity-75">Other Closures</div>
            </div>
          </div>

          {/* CTA Buttons */}
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link
              to="/map"
              className="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors flex items-center justify-center"
            >
              <i className="fas fa-map mr-2"></i>
              View Interactive Map
            </Link>
            <Link
              to="/add-store"
              className="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-colors flex items-center justify-center"
            >
              <i className="fas fa-plus mr-2"></i>
              Add a Store
            </Link>
          </div>
        </div>
      </div>

      {/* Features Section */}
      <div className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              Stay Informed About NYC Cannabis & Smoke Shops
            </h2>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              Track Operation Smokeout enforcement, discover legal dispensaries, and stay updated on NYC cannabis laws.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div className="bg-gray-50 rounded-lg p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-red-600 mb-4">
                <i className="fas fa-ban"></i>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Operation Smokeout Tracking</h3>
              <p className="text-gray-600">
                Real-time updates on smoke shops closed due to Operation Smokeout enforcement. Stay informed about which locations have been affected.
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-green-600 mb-4">
                <i className="fas fa-map-marker-alt"></i>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Interactive Map Search</h3>
              <p className="text-gray-600">
                Find smoke shops near you with our interactive map. Filter by status, location, and get real-time updates on store availability.
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-blue-600 mb-4">
                <i className="fas fa-users"></i>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Community Updates</h3>
              <p className="text-gray-600">
                Join our community to share updates, report store status changes, and help keep everyone informed about smoke shop availability.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Home;
