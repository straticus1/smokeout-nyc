import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import SmartNotificationsCenter from '../components/SmartNotificationsCenter';
import { BellIcon, SparklesIcon, ChartBarIcon, Cog6ToothIcon } from '@heroicons/react/24/outline';

const NotificationsPage: React.FC = () => {
  const { isAuthenticated } = useAuth();
  const [isNotificationCenterOpen, setIsNotificationCenterOpen] = useState(false);

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
          <BellIcon className="w-16 h-16 text-blue-600 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900 mb-4">
            Smart Notifications
          </h2>
          <p className="text-gray-600 mb-6">
            Please sign in to access AI-powered smart notifications with personalized delivery optimization and comprehensive analytics.
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
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <div className="flex items-center justify-center mb-4">
              <BellIcon className="w-12 h-12 mr-3" />
              <h1 className="text-4xl md:text-5xl font-bold">
                Smart Notifications Center
              </h1>
            </div>
            <p className="text-xl md:text-2xl mb-8 opacity-90 max-w-3xl mx-auto">
              AI-powered notification management with intelligent delivery optimization, behavioral analytics, and personalized content.
            </p>
            <button
              onClick={() => setIsNotificationCenterOpen(true)}
              className="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition-colors flex items-center mx-auto"
            >
              <BellIcon className="w-6 h-6 mr-2" />
              Open Notification Center
            </button>
          </div>
        </div>
      </div>

      {/* Features Section */}
      <div className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              AI-Powered Notification Features
            </h2>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              Advanced machine learning algorithms optimize your notification experience, delivering the right message through the right channel at the perfect time.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {/* Smart Delivery Optimization */}
            <div className="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <SparklesIcon className="w-8 h-8 text-purple-600 mr-3" />
                <h3 className="text-xl font-semibold text-gray-900">Smart Delivery Optimization</h3>
              </div>
              <p className="text-gray-600 mb-4">
                Machine learning algorithms analyze your behavior patterns to determine optimal delivery timing and automatically select the best channels.
              </p>
              <div className="space-y-2">
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-purple-600 rounded-full mr-2"></div>
                  <span>AI-powered timing optimization</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-purple-600 rounded-full mr-2"></div>
                  <span>Automatic channel selection</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-purple-600 rounded-full mr-2"></div>
                  <span>Engagement history analysis</span>
                </div>
              </div>
            </div>

            {/* Multi-Channel Delivery */}
            <div className="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <BellIcon className="w-8 h-8 text-blue-600 mr-3" />
                <h3 className="text-xl font-semibold text-gray-900">Multi-Channel Delivery</h3>
              </div>
              <p className="text-gray-600 mb-4">
                Support for 6 different delivery channels with intelligent routing based on message priority, user preferences, and AI optimization.
              </p>
              <div className="grid grid-cols-2 gap-2">
                <div className="bg-white bg-opacity-50 rounded-lg p-2 text-center">
                  <div className="text-lg font-semibold text-blue-600">📱</div>
                  <div className="text-xs text-gray-600">In-App</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-2 text-center">
                  <div className="text-lg font-semibold text-blue-600">✉️</div>
                  <div className="text-xs text-gray-600">Email</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-2 text-center">
                  <div className="text-lg font-semibold text-blue-600">🔔</div>
                  <div className="text-xs text-gray-600">Push</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-2 text-center">
                  <div className="text-lg font-semibold text-blue-600">💬</div>
                  <div className="text-xs text-gray-600">SMS</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-2 text-center">
                  <div className="text-lg font-semibold text-blue-600">🎮</div>
                  <div className="text-xs text-gray-600">Discord</div>
                </div>
                <div className="bg-white bg-opacity-50 rounded-lg p-2 text-center">
                  <div className="text-lg font-semibold text-blue-600">🏢</div>
                  <div className="text-xs text-gray-600">Slack</div>
                </div>
              </div>
            </div>

            {/* Advanced Analytics */}
            <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <ChartBarIcon className="w-8 h-8 text-green-600 mr-3" />
                <h3 className="text-xl font-semibold text-gray-900">Advanced Analytics</h3>
              </div>
              <p className="text-gray-600 mb-4">
                Comprehensive notification performance tracking with engagement analysis, optimization suggestions, and AI impact measurement.
              </p>
              <div className="space-y-2">
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-green-600 rounded-full mr-2"></div>
                  <span>Engagement rate tracking</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-green-600 rounded-full mr-2"></div>
                  <span>Channel effectiveness metrics</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-green-600 rounded-full mr-2"></div>
                  <span>AI optimization impact</span>
                </div>
              </div>
            </div>

            {/* Behavioral Analysis */}
            <div className="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <div className="w-8 h-8 text-orange-600 mr-3 flex items-center justify-center">🧠</div>
                <h3 className="text-xl font-semibold text-gray-900">Behavioral Pattern Analysis</h3>
              </div>
              <p className="text-gray-600 mb-4">
                Deep insights into your notification preferences with peak engagement identification and predictive analysis for future optimization.
              </p>
              <div className="space-y-2">
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-orange-600 rounded-full mr-2"></div>
                  <span>Peak engagement hours</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-orange-600 rounded-full mr-2"></div>
                  <span>Channel effectiveness by time</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-orange-600 rounded-full mr-2"></div>
                  <span>Predictive optimization</span>
                </div>
              </div>
            </div>

            {/* Content Personalization */}
            <div className="bg-gradient-to-br from-red-50 to-pink-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <div className="w-8 h-8 text-red-600 mr-3 flex items-center justify-center">🎨</div>
                <h3 className="text-xl font-semibold text-gray-900">Intelligent Content Personalization</h3>
              </div>
              <p className="text-gray-600 mb-4">
                Context-aware notification content with risk-level appropriate messaging and user preference-based content adaptation.
              </p>
              <div className="space-y-2">
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-red-600 rounded-full mr-2"></div>
                  <span>Risk-appropriate messaging</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-red-600 rounded-full mr-2"></div>
                  <span>Preference-based adaptation</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-red-600 rounded-full mr-2"></div>
                  <span>Personalized recommendations</span>
                </div>
              </div>
            </div>

            {/* Granular Controls */}
            <div className="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="flex items-center mb-4">
                <Cog6ToothIcon className="w-8 h-8 text-indigo-600 mr-3" />
                <h3 className="text-xl font-semibold text-gray-900">Granular Controls</h3>
              </div>
              <p className="text-gray-600 mb-4">
                Complete control over your notification experience with quiet hours, category preferences, and frequency controls.
              </p>
              <div className="space-y-2">
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-indigo-600 rounded-full mr-2"></div>
                  <span>Configurable quiet hours</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-indigo-600 rounded-full mr-2"></div>
                  <span>Category-based preferences</span>
                </div>
                <div className="flex items-center text-sm text-gray-600">
                  <div className="w-2 h-2 bg-indigo-600 rounded-full mr-2"></div>
                  <span>Frequency controls</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* AI Insights Section */}
      <div className="py-16 bg-gradient-to-br from-indigo-50 to-purple-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <div className="flex items-center justify-center mb-4">
              <SparklesIcon className="w-10 h-10 text-purple-600 mr-3" />
              <h2 className="text-3xl font-bold text-gray-900">AI Insights & Recommendations</h2>
            </div>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              Machine learning-powered optimization with behavior pattern recognition, delivery timing suggestions, and predictive engagement modeling.
            </p>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div className="bg-white bg-opacity-70 rounded-xl p-6 text-center">
              <div className="text-3xl mb-4">🔍</div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Pattern Recognition</h3>
              <p className="text-gray-600">Identify behavior patterns with confidence scoring and impact analysis</p>
            </div>
            
            <div className="bg-white bg-opacity-70 rounded-xl p-6 text-center">
              <div className="text-3xl mb-4">💡</div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Optimization Suggestions</h3>
              <p className="text-gray-600">AI-generated recommendations with potential impact predictions</p>
            </div>
            
            <div className="bg-white bg-opacity-70 rounded-xl p-6 text-center">
              <div className="text-3xl mb-4">🔮</div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">Predictive Modeling</h3>
              <p className="text-gray-600">Next optimal send times and engagement rate predictions</p>
            </div>
          </div>
        </div>
      </div>

      {/* Call to Action */}
      <div className="bg-gray-50 py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl font-bold text-gray-900 mb-4">
            Ready to Optimize Your Notifications?
          </h2>
          <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            Access your Smart Notifications Center and start experiencing AI-powered notification management with personalized delivery optimization.
          </p>
          <button
            onClick={() => setIsNotificationCenterOpen(true)}
            className="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-lg font-semibold text-lg hover:from-blue-700 hover:to-purple-700 transition-all transform hover:scale-105 flex items-center mx-auto"
          >
            <BellIcon className="w-6 h-6 mr-2" />
            Launch Notification Center
          </button>
        </div>
      </div>

      {/* Smart Notifications Center Component */}
      <SmartNotificationsCenter 
        isOpen={isNotificationCenterOpen} 
        onClose={() => setIsNotificationCenterOpen(false)} 
      />
    </div>
  );
};

export default NotificationsPage;
