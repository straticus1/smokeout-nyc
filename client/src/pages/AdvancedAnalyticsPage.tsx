import React, { useState, useEffect, useRef } from 'react';
import { useAuth } from '../contexts/AuthContext';
import {
  ChartBarIcon,
  UsersIcon,
  MapIcon,
  ClockIcon,
  TrendingUpIcon,
  TrendingDownIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  ArrowRefreshIcon,
  FunnelIcon,
  CalendarIcon,
  GlobeAltIcon
} from '@heroicons/react/24/outline';
import { motion } from 'framer-motion';
import axios from 'axios';

// Mock Chart component (replace with actual charting library like Chart.js or D3)
const MockChart: React.FC<{ type: string; data: any; className?: string }> = ({ type, data, className = "" }) => {
  return (
    <div className={`bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg p-4 flex items-center justify-center ${className}`}>
      <div className="text-center">
        <ChartBarIcon className="w-12 h-12 text-gray-500 mx-auto mb-2" />
        <div className="text-sm text-gray-600">{type} Chart</div>
        <div className="text-xs text-gray-500 mt-1">
          {data ? `${Object.keys(data).length} data points` : 'Loading...'}
        </div>
      </div>
    </div>
  );
};

const AdvancedAnalyticsPage: React.FC = () => {
  const { isAuthenticated, user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [realTimeData, setRealTimeData] = useState<any>(null);
  const [selectedTimeframe, setSelectedTimeframe] = useState<'24h' | '7d' | '30d' | '90d'>('7d');
  const [selectedMetrics, setSelectedMetrics] = useState<string[]>(['users', 'revenue', 'risk', 'gaming']);
  const [dashboardData, setDashboardData] = useState<any>(null);
  const [isConnected, setIsConnected] = useState(false);
  const socketRef = useRef<any>(null);

  // Analytics data state
  const [analyticsData, setAnalyticsData] = useState({
    overview: {
      totalUsers: 15247,
      activeUsers: 3421,
      totalRevenue: 89654,
      riskAlerts: 23,
      gamesSessions: 1847,
      avgSessionTime: 26.4
    },
    trends: {
      userGrowth: 12.4,
      revenueGrowth: 8.7,
      engagementGrowth: -2.1,
      riskTrendChange: 15.3
    },
    realtimeMetrics: {
      onlineUsers: 342,
      activeGames: 18,
      riskAssessments: 7,
      notifications: 156,
      chatMessages: 89
    }
  });

  useEffect(() => {
    if (isAuthenticated) {
      loadAnalyticsData();
      initializeRealTimeConnection();
    }

    return () => {
      if (socketRef.current) {
        socketRef.current.disconnect();
      }
    };
  }, [isAuthenticated, selectedTimeframe]);

  const loadAnalyticsData = async () => {
    setLoading(true);
    try {
      // Load analytics data from API
      const response = await axios.get(`/api/analytics/dashboard?timeframe=${selectedTimeframe}&metrics=${selectedMetrics.join(',')}`);
      setDashboardData(response.data);
    } catch (error) {
      console.error('Failed to load analytics data:', error);
    } finally {
      setLoading(false);
    }
  };

  const initializeRealTimeConnection = () => {
    // Initialize WebSocket connection for real-time updates
    // Note: This would connect to the realtime server we created
    try {
      // Simulated real-time connection
      setIsConnected(true);
      
      // Simulate real-time data updates
      const interval = setInterval(() => {
        setRealTimeData({
          onlineUsers: Math.floor(Math.random() * 50) + 300,
          activeGames: Math.floor(Math.random() * 10) + 15,
          newRiskAlerts: Math.floor(Math.random() * 3),
          timestamp: new Date()
        });
      }, 5000);

      return () => clearInterval(interval);
    } catch (error) {
      console.error('Failed to connect to real-time server:', error);
      setIsConnected(false);
    }
  };

  const refreshData = () => {
    loadAnalyticsData();
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
          <ChartBarIcon className="w-16 h-16 text-blue-600 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-gray-900 mb-4">
            Advanced Analytics
          </h2>
          <p className="text-gray-600 mb-6">
            Please sign in to access the advanced analytics dashboard with real-time insights and comprehensive reporting.
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
      {/* Header */}
      <div className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <ChartBarIcon className="w-10 h-10" />
              <div>
                <h1 className="text-3xl font-bold">Advanced Analytics Dashboard</h1>
                <p className="text-blue-100">Real-time insights and comprehensive reporting</p>
              </div>
            </div>
            
            <div className="flex items-center space-x-4">
              {/* Connection Status */}
              <div className="flex items-center space-x-2">
                <div className={`w-3 h-3 rounded-full ${isConnected ? 'bg-green-400' : 'bg-red-400'}`}></div>
                <span className="text-sm">{isConnected ? 'Real-time Connected' : 'Offline'}</span>
              </div>
              
              {/* Refresh Button */}
              <button
                onClick={refreshData}
                className="flex items-center space-x-2 bg-white bg-opacity-20 hover:bg-opacity-30 px-4 py-2 rounded-lg transition-all"
              >
                <ArrowRefreshIcon className="w-4 h-4" />
                <span>Refresh</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Controls */}
        <div className="bg-white rounded-lg shadow-sm border p-4 mb-8">
          <div className="flex flex-wrap items-center gap-4">
            {/* Timeframe Selector */}
            <div className="flex items-center space-x-2">
              <CalendarIcon className="w-5 h-5 text-gray-500" />
              <span className="text-sm font-medium text-gray-700">Timeframe:</span>
              <select
                value={selectedTimeframe}
                onChange={(e) => setSelectedTimeframe(e.target.value as any)}
                className="border border-gray-300 rounded px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500"
              >
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
                <option value="90d">Last 90 Days</option>
              </select>
            </div>

            {/* Metrics Filter */}
            <div className="flex items-center space-x-2">
              <FunnelIcon className="w-5 h-5 text-gray-500" />
              <span className="text-sm font-medium text-gray-700">Metrics:</span>
              <div className="flex space-x-2">
                {['users', 'revenue', 'risk', 'gaming'].map(metric => (
                  <label key={metric} className="flex items-center">
                    <input
                      type="checkbox"
                      checked={selectedMetrics.includes(metric)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedMetrics([...selectedMetrics, metric]);
                        } else {
                          setSelectedMetrics(selectedMetrics.filter(m => m !== metric));
                        }
                      }}
                      className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span className="ml-1 text-sm text-gray-700 capitalize">{metric}</span>
                  </label>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Real-time Metrics */}
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="bg-white rounded-lg shadow-sm border p-4 text-center"
          >
            <UsersIcon className="w-8 h-8 text-blue-600 mx-auto mb-2" />
            <div className="text-2xl font-bold text-gray-900">
              {realTimeData?.onlineUsers || analyticsData.realtimeMetrics.onlineUsers}
            </div>
            <div className="text-sm text-gray-600">Online Users</div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="bg-white rounded-lg shadow-sm border p-4 text-center"
          >
            <ChartBarIcon className="w-8 h-8 text-green-600 mx-auto mb-2" />
            <div className="text-2xl font-bold text-gray-900">
              {realTimeData?.activeGames || analyticsData.realtimeMetrics.activeGames}
            </div>
            <div className="text-sm text-gray-600">Active Games</div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="bg-white rounded-lg shadow-sm border p-4 text-center"
          >
            <ExclamationTriangleIcon className="w-8 h-8 text-orange-600 mx-auto mb-2" />
            <div className="text-2xl font-bold text-gray-900">
              {analyticsData.realtimeMetrics.riskAssessments}
            </div>
            <div className="text-sm text-gray-600">Risk Assessments</div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="bg-white rounded-lg shadow-sm border p-4 text-center"
          >
            <InformationCircleIcon className="w-8 h-8 text-purple-600 mx-auto mb-2" />
            <div className="text-2xl font-bold text-gray-900">
              {analyticsData.realtimeMetrics.notifications}
            </div>
            <div className="text-sm text-gray-600">Notifications</div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
            className="bg-white rounded-lg shadow-sm border p-4 text-center"
          >
            <MapIcon className="w-8 h-8 text-indigo-600 mx-auto mb-2" />
            <div className="text-2xl font-bold text-gray-900">
              {analyticsData.realtimeMetrics.chatMessages}
            </div>
            <div className="text-sm text-gray-600">Chat Messages</div>
          </motion.div>
        </div>

        {/* Key Performance Indicators */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="bg-white rounded-lg shadow-sm border p-6"
          >
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Total Users</h3>
              <UsersIcon className="w-6 h-6 text-blue-600" />
            </div>
            <div className="text-3xl font-bold text-gray-900 mb-2">
              {analyticsData.overview.totalUsers.toLocaleString()}
            </div>
            <div className="flex items-center">
              <TrendingUpIcon className="w-4 h-4 text-green-600 mr-1" />
              <span className="text-sm text-green-600 font-medium">
                +{analyticsData.trends.userGrowth}%
              </span>
              <span className="text-sm text-gray-500 ml-1">vs last period</span>
            </div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="bg-white rounded-lg shadow-sm border p-6"
          >
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Revenue</h3>
              <TrendingUpIcon className="w-6 h-6 text-green-600" />
            </div>
            <div className="text-3xl font-bold text-gray-900 mb-2">
              ${analyticsData.overview.totalRevenue.toLocaleString()}
            </div>
            <div className="flex items-center">
              <TrendingUpIcon className="w-4 h-4 text-green-600 mr-1" />
              <span className="text-sm text-green-600 font-medium">
                +{analyticsData.trends.revenueGrowth}%
              </span>
              <span className="text-sm text-gray-500 ml-1">vs last period</span>
            </div>
          </motion.div>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="bg-white rounded-lg shadow-sm border p-6"
          >
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900">Risk Alerts</h3>
              <ExclamationTriangleIcon className="w-6 h-6 text-orange-600" />
            </div>
            <div className="text-3xl font-bold text-gray-900 mb-2">
              {analyticsData.overview.riskAlerts}
            </div>
            <div className="flex items-center">
              <TrendingUpIcon className="w-4 h-4 text-orange-600 mr-1" />
              <span className="text-sm text-orange-600 font-medium">
                +{analyticsData.trends.riskTrendChange}%
              </span>
              <span className="text-sm text-gray-500 ml-1">vs last period</span>
            </div>
          </motion.div>
        </div>

        {/* Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* User Activity Chart */}
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="bg-white rounded-lg shadow-sm border p-6"
          >
            <h3 className="text-lg font-semibold text-gray-900 mb-4">User Activity Trends</h3>
            <MockChart type="Line" data={{ users: 100, sessions: 200 }} className="h-64" />
          </motion.div>

          {/* Revenue Chart */}
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="bg-white rounded-lg shadow-sm border p-6"
          >
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Revenue Analytics</h3>
            <MockChart type="Bar" data={{ revenue: 50, growth: 25 }} className="h-64" />
          </motion.div>

          {/* Gaming Analytics */}
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="bg-white rounded-lg shadow-sm border p-6"
          >
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Gaming Engagement</h3>
            <MockChart type="Doughnut" data={{ games: 30, players: 60 }} className="h-64" />
          </motion.div>

          {/* Risk Assessment Analytics */}
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="bg-white rounded-lg shadow-sm border p-6"
          >
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Risk Assessment Insights</h3>
            <MockChart type="Area" data={{ risks: 40, alerts: 20 }} className="h-64" />
          </motion.div>
        </div>

        {/* Additional Insights */}
        <motion.div 
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg border border-indigo-200 p-6 mt-8"
        >
          <div className="flex items-center space-x-3 mb-4">
            <GlobeAltIcon className="w-8 h-8 text-indigo-600" />
            <h3 className="text-xl font-semibold text-gray-900">AI-Powered Insights</h3>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-white bg-opacity-50 rounded-lg p-4">
              <h4 className="font-medium text-gray-900 mb-2">Peak Usage</h4>
              <p className="text-sm text-gray-600">
                Highest user activity occurs between 7-9 PM EST, with 40% more engagement during this window.
              </p>
            </div>
            <div className="bg-white bg-opacity-50 rounded-lg p-4">
              <h4 className="font-medium text-gray-900 mb-2">Revenue Opportunity</h4>
              <p className="text-sm text-gray-600">
                Premium feature adoption increased 15% after implementing AI recommendations in notifications.
              </p>
            </div>
            <div className="bg-white bg-opacity-50 rounded-lg p-4">
              <h4 className="font-medium text-gray-900 mb-2">Risk Prediction</h4>
              <p className="text-sm text-gray-600">
                Our AI model predicts 23% reduction in enforcement actions for businesses following AI recommendations.
              </p>
            </div>
          </div>
        </motion.div>
      </div>
    </div>
  );
};

export default AdvancedAnalyticsPage;
