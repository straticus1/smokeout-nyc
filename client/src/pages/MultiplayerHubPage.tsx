import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import MultiplayerGameHub from '../components/MultiplayerGameHub';
import { UserGroupIcon, BuildingOffice2Icon, ShoppingCartIcon, TrophyIcon } from '@heroicons/react/24/outline';

const MultiplayerHubPage: React.FC = () => {
  const { isAuthenticated } = useAuth();
  const [isHubOpen, setIsHubOpen] = useState(false);

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
          <UserGroupIcon className="w-16 h-16 text-indigo-600 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900 mb-4">
            Multiplayer Game Hub
          </h2>
          <p className="text-gray-600 mb-6">
            Please sign in to access multiplayer gaming features including guilds, cooperative operations, trading, and competitions.
          </p>
          <a
            href="/login"
            className="bg-indigo-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-indigo-700 transition-colors"
          >
            Sign In
          </a>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Hero Section */}
      <div className="bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <div className="flex items-center justify-center mb-4">
              <UserGroupIcon className="w-12 h-12 mr-3" />
              <h1 className="text-4xl md:text-5xl font-bold">
                Multiplayer Game Hub
              </h1>
            </div>
            <p className="text-xl md:text-2xl mb-8 opacity-90 max-w-3xl mx-auto">
              Connect, collaborate, and compete with other players in the ultimate cannabis gaming experience.
            </p>
            <button
              onClick={() => setIsHubOpen(true)}
              className="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition-colors flex items-center mx-auto"
            >
              <UserGroupIcon className="w-6 h-6 mr-2" />
              Enter Game Hub
            </button>
          </div>
        </div>
      </div>

      {/* Features Section */}
      <div className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              Multiplayer Gaming Features
            </h2>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              Join a thriving community of cannabis gaming enthusiasts with advanced multiplayer features designed for collaboration and competition.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
            {/* Guild System */}
            <div className="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <UserGroupIcon className="w-8 h-8 text-indigo-600 mr-3" />
                <h3 className="text-2xl font-semibold text-gray-900">Guild System</h3>
              </div>
              <p className="text-gray-600 mb-6">
                Create or join gaming guilds with role-based permissions. Choose from casual, competitive, or professional guild types and build your cannabis empire together.
              </p>
              <div className="grid grid-cols-3 gap-4 text-center">
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-indigo-600">3</div>
                  <div className="text-sm text-gray-600">Guild Types</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-indigo-600">50</div>
                  <div className="text-sm text-gray-600">Max Members</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-indigo-600">∞</div>
                  <div className="text-sm text-gray-600">Activities</div>
                </div>
              </div>
            </div>

            {/* Cooperative Operations */}
            <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <BuildingOffice2Icon className="w-8 h-8 text-green-600 mr-3" />
                <h3 className="text-2xl font-semibold text-gray-900">Cooperative Operations</h3>
              </div>
              <p className="text-gray-600 mb-6">
                Pool investments for larger cannabis cultivation projects. Share risks, rewards, and decision-making in collaborative growing operations.
              </p>
              <div className="grid grid-cols-3 gap-4 text-center">
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-green-600">💰</div>
                  <div className="text-sm text-gray-600">Investment Pooling</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-green-600">🤝</div>
                  <div className="text-sm text-gray-600">Shared Rewards</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-green-600">🌱</div>
                  <div className="text-sm text-gray-600">Collaborative Growing</div>
                </div>
              </div>
            </div>

            {/* Player Trading */}
            <div className="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <ShoppingCartIcon className="w-8 h-8 text-orange-600 mr-3" />
                <h3 className="text-2xl font-semibold text-gray-900">Player-to-Player Trading</h3>
              </div>
              <p className="text-gray-600 mb-6">
                Secure marketplace for trading game assets. List items with expiration dates, secure escrow functionality, and reputation tracking.
              </p>
              <div className="grid grid-cols-3 gap-4 text-center">
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-orange-600">🛡️</div>
                  <div className="text-sm text-gray-600">Secure Trading</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-orange-600">⭐</div>
                  <div className="text-sm text-gray-600">Reputation System</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-orange-600">📈</div>
                  <div className="text-sm text-gray-600">Trade History</div>
                </div>
              </div>
            </div>

            {/* Competitive Gaming */}
            <div className="bg-gradient-to-br from-red-50 to-pink-50 rounded-xl p-8 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <TrophyIcon className="w-8 h-8 text-red-600 mr-3" />
                <h3 className="text-2xl font-semibold text-gray-900">Competitive Gaming</h3>
              </div>
              <p className="text-gray-600 mb-6">
                Register for tournaments and competitions. Entry fees, prize pools, leaderboard tracking, and various competition formats to test your skills.
              </p>
              <div className="grid grid-cols-3 gap-4 text-center">
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-red-600">🏆</div>
                  <div className="text-sm text-gray-600">Tournaments</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-red-600">💎</div>
                  <div className="text-sm text-gray-600">Prize Pools</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-3">
                  <div className="text-2xl font-bold text-red-600">📊</div>
                  <div className="text-sm text-gray-600">Leaderboards</div>
                </div>
              </div>
            </div>
          </div>

          {/* Social Features */}
          <div className="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-8">
            <div className="text-center mb-8">
              <h3 className="text-2xl font-semibold text-gray-900 mb-4">Real-time Social Features</h3>
              <p className="text-gray-600 max-w-2xl mx-auto">
                Enhanced player interaction capabilities including friend systems, social networking, player status tracking, and guild communication tools.
              </p>
            </div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
              <div className="bg-white bg-opacity-50 rounded-lg p-4">
                <div className="text-3xl mb-2">👥</div>
                <div className="font-semibold text-gray-900">Friend Systems</div>
                <div className="text-sm text-gray-600">Connect & Network</div>
              </div>
              <div className="bg-white bg-opacity-50 rounded-lg p-4">
                <div className="text-3xl mb-2">📱</div>
                <div className="font-semibold text-gray-900">Activity Feeds</div>
                <div className="text-sm text-gray-600">Stay Updated</div>
              </div>
              <div className="bg-white bg-opacity-50 rounded-lg p-4">
                <div className="text-3xl mb-2">🟢</div>
                <div className="font-semibold text-gray-900">Player Status</div>
                <div className="text-sm text-gray-600">Real-time Tracking</div>
              </div>
              <div className="bg-white bg-opacity-50 rounded-lg p-4">
                <div className="text-3xl mb-2">💬</div>
                <div className="font-semibold text-gray-900">Guild Chat</div>
                <div className="text-sm text-gray-600">Communication</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Call to Action */}
      <div className="bg-gray-50 py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl font-bold text-gray-900 mb-4">
            Ready to Join the Community?
          </h2>
          <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            Enter the Multiplayer Game Hub and start connecting with other players, joining guilds, and participating in collaborative cannabis gaming.
          </p>
          <button
            onClick={() => setIsHubOpen(true)}
            className="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-8 py-4 rounded-lg font-semibold text-lg hover:from-indigo-700 hover:to-purple-700 transition-all transform hover:scale-105 flex items-center mx-auto"
          >
            <UserGroupIcon className="w-6 h-6 mr-2" />
            Launch Game Hub
          </button>
        </div>
      </div>

      {/* Multiplayer Game Hub Component */}
      <MultiplayerGameHub 
        isOpen={isHubOpen} 
        onClose={() => setIsHubOpen(false)} 
      />
    </div>
  );
};

export default MultiplayerHubPage;
