import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import AIRiskAssistant from '../components/AIRiskAssistant';
import { SparklesIcon } from '@heroicons/react/24/outline';

const AIRiskAssistantPage: React.FC = () => {
  const { isAuthenticated } = useAuth();
  const [isAssistantOpen, setIsAssistantOpen] = useState(false);

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
          <SparklesIcon className="w-16 h-16 text-purple-600 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900 mb-4">
            AI Risk Assistant
          </h2>
          <p className="text-gray-600 mb-6">
            Please sign in to access the AI Risk Assistant and get personalized risk assessments for your cannabis business.
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
      <div className="bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <div className="flex items-center justify-center mb-4">
              <SparklesIcon className="w-12 h-12 mr-3" />
              <h1 className="text-4xl md:text-5xl font-bold">
                AI Risk Assistant
              </h1>
            </div>
            <p className="text-xl md:text-2xl mb-8 opacity-90 max-w-3xl mx-auto">
              Get intelligent risk assessments and personalized recommendations for your cannabis business with natural language explanations.
            </p>
            <button
              onClick={() => setIsAssistantOpen(true)}
              className="bg-white text-purple-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition-colors flex items-center mx-auto"
            >
              <SparklesIcon className="w-6 h-6 mr-2" />
              Start AI Consultation
            </button>
          </div>
        </div>
      </div>

      {/* Features Section */}
      <div className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              How the AI Risk Assistant Helps You
            </h2>
            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
              Our advanced AI analyzes multiple risk factors and provides clear, actionable insights for your cannabis business operations.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div className="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-purple-600 mb-4">🧠</div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Natural Language Explanations</h3>
              <p className="text-gray-600">
                Complex risk factors explained in simple, understandable terms. No technical jargon, just clear insights you can act on.
              </p>
            </div>

            <div className="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-blue-600 mb-4">🎯</div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Personalized Recommendations</h3>
              <p className="text-gray-600">
                AI-generated suggestions based on your specific business profile, risk tolerance, and current market conditions.
              </p>
            </div>

            <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-green-600 mb-4">💬</div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Interactive Chat Interface</h3>
              <p className="text-gray-600">
                Ask questions in natural language and get instant, context-aware responses with actionable next steps.
              </p>
            </div>

            <div className="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-yellow-600 mb-4">📊</div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Smart Risk Visualization</h3>
              <p className="text-gray-600">
                Color-coded risk levels with intuitive icons and detailed explanations help you understand your exposure at a glance.
              </p>
            </div>

            <div className="bg-gradient-to-br from-red-50 to-pink-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-red-600 mb-4">⚡</div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Real-time Intelligence</h3>
              <p className="text-gray-600">
                Live monitoring of news alerts, enforcement activity, and regulatory changes affecting your business risk profile.
              </p>
            </div>

            <div className="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-6 hover:shadow-lg transition-shadow">
              <div className="text-4xl text-indigo-600 mb-4">🔮</div>
              <h3 className="text-xl font-semibold text-gray-900 mb-3">Predictive Analysis</h3>
              <p className="text-gray-600">
                Advanced modeling helps predict future risks and opportunities, giving you time to prepare and adapt your strategy.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Call to Action */}
      <div className="bg-gray-50 py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl font-bold text-gray-900 mb-4">
            Ready to Get Started?
          </h2>
          <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            Start your AI-powered risk assessment consultation now and make more informed decisions for your cannabis business.
          </p>
          <button
            onClick={() => setIsAssistantOpen(true)}
            className="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-8 py-4 rounded-lg font-semibold text-lg hover:from-purple-700 hover:to-indigo-700 transition-all transform hover:scale-105 flex items-center mx-auto"
          >
            <SparklesIcon className="w-6 h-6 mr-2" />
            Launch AI Risk Assistant
          </button>
        </div>
      </div>

      {/* AI Risk Assistant Component */}
      <AIRiskAssistant 
        isOpen={isAssistantOpen} 
        onClose={() => setIsAssistantOpen(false)} 
      />
    </div>
  );
};

export default AIRiskAssistantPage;
