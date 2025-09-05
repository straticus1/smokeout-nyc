import React, { useState, useEffect } from 'react';
import {
  BellIcon,
  Cog6ToothIcon,
  ChartBarIcon,
  ClockIcon,
  XMarkIcon,
  CheckIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  SparklesIcon,
  EyeIcon,
  EyeSlashIcon
} from '@heroicons/react/24/outline';
import { motion, AnimatePresence } from 'framer-motion';
import toast from 'react-hot-toast';
import axios from 'axios';

interface SmartNotificationsCenterProps {
  isOpen: boolean;
  onClose: () => void;
}

interface Notification {
  id: string;
  title: string;
  content: string;
  type: 'info' | 'warning' | 'success' | 'error' | 'ai_risk' | 'game' | 'social';
  priority: 'low' | 'normal' | 'high' | 'critical';
  status: 'pending' | 'sent' | 'read' | 'dismissed' | 'snoozed';
  channels: string[];
  scheduled_for: string;
  ai_optimized?: boolean;
  created_at: string;
  metadata?: any;
}

interface NotificationPreferences {
  channels: {
    in_app: boolean;
    email: boolean;
    push: boolean;
    sms: boolean;
    discord: boolean;
    slack: boolean;
  };
  frequency: 'immediate' | 'batched_hourly' | 'batched_daily' | 'weekly_digest';
  quiet_hours_enabled: boolean;
  quiet_hours_start: string;
  quiet_hours_end: string;
  ai_optimization_enabled: boolean;
  ai_delivery_timing: boolean;
  ai_channel_selection: boolean;
  ai_content_personalization: boolean;
  categories: {
    ai_risk_alerts: boolean;
    game_updates: boolean;
    social_notifications: boolean;
    system_alerts: boolean;
    marketing: boolean;
    recommendations: boolean;
  };
}

interface NotificationAnalytics {
  total_sent: number;
  total_read: number;
  total_dismissed: number;
  read_rate: number;
  engagement_rate: number;
  preferred_channels: Array<{ channel: string; count: number; percentage: number }>;
  peak_engagement_hours: Array<{ hour: number; engagement_rate: number }>;
  ai_optimization_impact: {
    improvement_percentage: number;
    better_timing_success: number;
    better_channel_success: number;
  };
}

interface AIInsights {
  behavior_patterns: Array<{
    pattern_type: string;
    description: string;
    confidence: number;
    impact: 'positive' | 'negative' | 'neutral';
  }>;
  optimization_suggestions: Array<{
    category: string;
    suggestion: string;
    potential_impact: string;
    confidence: number;
  }>;
  predictive_analysis: {
    next_optimal_send_time: string;
    predicted_engagement_rate: number;
    recommended_channels: string[];
  };
}

const SmartNotificationsCenter: React.FC<SmartNotificationsCenterProps> = ({ isOpen, onClose }) => {
  const [activeTab, setActiveTab] = useState<'notifications' | 'preferences' | 'analytics'>('notifications');
  const [loading, setLoading] = useState(false);
  
  // Notifications state
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [filter, setFilter] = useState<'all' | 'unread' | 'read' | 'snoozed'>('all');
  
  // Preferences state
  const [preferences, setPreferences] = useState<NotificationPreferences>({
    channels: {
      in_app: true,
      email: true,
      push: false,
      sms: false,
      discord: false,
      slack: false
    },
    frequency: 'immediate',
    quiet_hours_enabled: false,
    quiet_hours_start: '22:00',
    quiet_hours_end: '07:00',
    ai_optimization_enabled: true,
    ai_delivery_timing: true,
    ai_channel_selection: true,
    ai_content_personalization: true,
    categories: {
      ai_risk_alerts: true,
      game_updates: true,
      social_notifications: true,
      system_alerts: true,
      marketing: false,
      recommendations: true
    }
  });
  
  // Analytics state
  const [analytics, setAnalytics] = useState<NotificationAnalytics | null>(null);
  const [aiInsights, setAiInsights] = useState<AIInsights | null>(null);
  const [analyticsTimeframe, setAnalyticsTimeframe] = useState<'7d' | '30d' | '90d'>('30d');

  useEffect(() => {
    if (isOpen) {
      loadInitialData();
    }
  }, [isOpen, activeTab]);

  const loadInitialData = async () => {
    setLoading(true);
    try {
      switch (activeTab) {
        case 'notifications':
          await loadNotifications();
          break;
        case 'preferences':
          await loadPreferences();
          break;
        case 'analytics':
          await Promise.all([loadAnalytics(), loadAIInsights()]);
          break;
      }
    } catch (error) {
      console.error('Failed to load data:', error);
      toast.error('Failed to load data');
    } finally {
      setLoading(false);
    }
  };

  const loadNotifications = async () => {
    const response = await axios.get('/api/smart-notifications/queue/history');
    setNotifications(response.data.notifications);
  };

  const loadPreferences = async () => {
    const response = await axios.get('/api/smart-notifications/preferences');
    setPreferences(response.data.preferences);
  };

  const loadAnalytics = async () => {
    const response = await axios.get(`/api/smart-notifications/analytics?timeframe=${analyticsTimeframe}`);
    setAnalytics(response.data.analytics);
  };

  const loadAIInsights = async () => {
    const response = await axios.get('/api/smart-notifications/ai-insights');
    setAiInsights(response.data.insights);
  };

  const markAsRead = async (notificationIds: string[]) => {
    try {
      await axios.post('/api/smart-notifications/queue/mark-read', { notification_ids: notificationIds });
      toast.success('Marked as read');
      loadNotifications();
    } catch (error) {
      toast.error('Failed to mark as read');
    }
  };

  const dismissNotification = async (notificationIds: string[]) => {
    try {
      await axios.post('/api/smart-notifications/queue/dismiss', { notification_ids: notificationIds });
      toast.success('Notification dismissed');
      loadNotifications();
    } catch (error) {
      toast.error('Failed to dismiss notification');
    }
  };

  const snoozeNotification = async (notificationId: string, duration: number) => {
    try {
      await axios.post('/api/smart-notifications/queue/snooze', {
        notification_id: notificationId,
        snooze_duration: duration
      });
      toast.success(`Snoozed for ${duration} minutes`);
      loadNotifications();
    } catch (error) {
      toast.error('Failed to snooze notification');
    }
  };

  const updatePreferences = async () => {
    try {
      await axios.post('/api/smart-notifications/preferences/update', preferences);
      toast.success('Preferences updated successfully!');
    } catch (error) {
      toast.error('Failed to update preferences');
    }
  };

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case 'warning':
        return <ExclamationTriangleIcon className="w-5 h-5 text-yellow-600" />;
      case 'error':
        return <XMarkIcon className="w-5 h-5 text-red-600" />;
      case 'success':
        return <CheckIcon className="w-5 h-5 text-green-600" />;
      case 'ai_risk':
        return <SparklesIcon className="w-5 h-5 text-purple-600" />;
      default:
        return <InformationCircleIcon className="w-5 h-5 text-blue-600" />;
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'critical':
        return 'bg-red-100 text-red-800 border-red-200';
      case 'high':
        return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'normal':
        return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'low':
        return 'bg-gray-100 text-gray-800 border-gray-200';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const filteredNotifications = notifications.filter(notification => {
    switch (filter) {
      case 'unread':
        return notification.status === 'pending' || notification.status === 'sent';
      case 'read':
        return notification.status === 'read';
      case 'snoozed':
        return notification.status === 'snoozed';
      default:
        return true;
    }
  });

  const tabs = [
    { key: 'notifications', label: 'Notifications', icon: BellIcon, count: notifications.filter(n => n.status !== 'read').length },
    { key: 'preferences', label: 'Preferences', icon: Cog6ToothIcon },
    { key: 'analytics', label: 'Analytics', icon: ChartBarIcon }
  ];

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black bg-opacity-50" onClick={onClose} />
      
      {/* Panel */}
      <motion.div
        initial={{ x: '100%' }}
        animate={{ x: 0 }}
        exit={{ x: '100%' }}
        transition={{ type: 'spring', damping: 20, stiffness: 300 }}
        className="absolute right-0 top-0 h-full w-full max-w-4xl bg-white shadow-2xl"
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
          <div className="flex items-center space-x-3">
            <BellIcon className="w-8 h-8" />
            <div>
              <h2 className="text-xl font-bold">Smart Notifications Center</h2>
              <p className="text-sm opacity-90">AI-powered notification management</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-white hover:bg-opacity-20 rounded-full transition-colors"
          >
            ✕
          </button>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-gray-200 bg-gray-50">
          {tabs.map(tab => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key as any)}
              className={`flex-1 flex items-center justify-center space-x-2 py-4 px-4 text-sm font-medium transition-all ${
                activeTab === tab.key
                  ? 'bg-white border-b-2 border-blue-600 text-blue-600'
                  : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
              }`}
            >
              <tab.icon className="w-5 h-5" />
              <span>{tab.label}</span>
              {tab.count !== undefined && tab.count > 0 && (
                <span className="ml-1 px-2 py-1 text-xs bg-blue-100 text-blue-600 rounded-full">
                  {tab.count}
                </span>
              )}
            </button>
          ))}
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto">
          {loading ? (
            <div className="flex items-center justify-center h-64">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
          ) : (
            <div>
              {/* Notifications Tab */}
              {activeTab === 'notifications' && (
                <div>
                  {/* Filter Bar */}
                  <div className="p-4 bg-gray-50 border-b border-gray-200">
                    <div className="flex items-center space-x-4">
                      <span className="text-sm font-medium text-gray-700">Filter:</span>
                      {['all', 'unread', 'read', 'snoozed'].map(filterOption => (
                        <button
                          key={filterOption}
                          onClick={() => setFilter(filterOption as any)}
                          className={`px-3 py-1 text-sm rounded-full transition-colors ${
                            filter === filterOption
                              ? 'bg-blue-600 text-white'
                              : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                          }`}
                        >
                          {filterOption === 'all' ? 'All' : filterOption.charAt(0).toUpperCase() + filterOption.slice(1)}
                          {filterOption !== 'all' && (
                            <span className="ml-1">
                              ({notifications.filter(n => {
                                if (filterOption === 'unread') return n.status === 'pending' || n.status === 'sent';
                                return n.status === filterOption;
                              }).length})
                            </span>
                          )}
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Notifications List */}
                  <div className="p-4 space-y-4">
                    {filteredNotifications.length > 0 ? (
                      filteredNotifications.map(notification => (
                        <motion.div
                          key={notification.id}
                          initial={{ opacity: 0, y: 20 }}
                          animate={{ opacity: 1, y: 0 }}
                          className={`border rounded-lg p-4 ${
                            notification.status === 'read' ? 'bg-gray-50' : 'bg-white'
                          } hover:shadow-md transition-shadow`}
                        >
                          <div className="flex items-start space-x-4">
                            <div className="flex-shrink-0 mt-1">
                              {getNotificationIcon(notification.type)}
                            </div>
                            <div className="flex-1 min-w-0">
                              <div className="flex items-start justify-between">
                                <div className="flex-1">
                                  <div className="flex items-center space-x-2 mb-1">
                                    <h4 className="text-sm font-medium text-gray-900">
                                      {notification.title}
                                    </h4>
                                    <span className={`px-2 py-1 text-xs rounded-full border ${getPriorityColor(notification.priority)}`}>
                                      {notification.priority}
                                    </span>
                                    {notification.ai_optimized && (
                                      <span className="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full border border-purple-200">
                                        <SparklesIcon className="w-3 h-3 inline mr-1" />
                                        AI Optimized
                                      </span>
                                    )}
                                  </div>
                                  <p className="text-sm text-gray-600 mb-2">
                                    {notification.content}
                                  </p>
                                  <div className="flex items-center space-x-4 text-xs text-gray-500">
                                    <span className="flex items-center">
                                      <ClockIcon className="w-3 h-3 mr-1" />
                                      {new Date(notification.created_at).toLocaleString()}
                                    </span>
                                    <span>
                                      Channels: {notification.channels.join(', ')}
                                    </span>
                                  </div>
                                </div>
                                <div className="flex items-center space-x-2 ml-4">
                                  {notification.status !== 'read' && (
                                    <button
                                      onClick={() => markAsRead([notification.id])}
                                      className="p-1 text-blue-600 hover:text-blue-800 transition-colors"
                                      title="Mark as read"
                                    >
                                      <EyeIcon className="w-4 h-4" />
                                    </button>
                                  )}
                                  <button
                                    onClick={() => snoozeNotification(notification.id, 60)}
                                    className="p-1 text-orange-600 hover:text-orange-800 transition-colors"
                                    title="Snooze for 1 hour"
                                  >
                                    <ClockIcon className="w-4 h-4" />
                                  </button>
                                  <button
                                    onClick={() => dismissNotification([notification.id])}
                                    className="p-1 text-red-600 hover:text-red-800 transition-colors"
                                    title="Dismiss"
                                  >
                                    <XMarkIcon className="w-4 h-4" />
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>
                        </motion.div>
                      ))
                    ) : (
                      <div className="text-center py-12">
                        <BellIcon className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                          No notifications found
                        </h3>
                        <p className="text-gray-600">
                          {filter === 'all' 
                            ? "You're all caught up! No notifications to show."
                            : `No ${filter} notifications at the moment.`}
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Preferences Tab */}
              {activeTab === 'preferences' && (
                <div className="p-6 space-y-8">
                  {/* Delivery Channels */}
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Delivery Channels</h3>
                    <div className="grid grid-cols-2 gap-4">
                      {Object.entries(preferences.channels).map(([channel, enabled]) => (
                        <label key={channel} className="flex items-center space-x-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                          <input
                            type="checkbox"
                            checked={enabled}
                            onChange={(e) => setPreferences({
                              ...preferences,
                              channels: { ...preferences.channels, [channel]: e.target.checked }
                            })}
                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          />
                          <span className="text-sm font-medium text-gray-700 capitalize">
                            {channel.replace('_', ' ')}
                          </span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Frequency Settings */}
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Notification Frequency</h3>
                    <select
                      value={preferences.frequency}
                      onChange={(e) => setPreferences({ ...preferences, frequency: e.target.value as any })}
                      className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                      <option value="immediate">Immediate</option>
                      <option value="batched_hourly">Batched Hourly</option>
                      <option value="batched_daily">Batched Daily</option>
                      <option value="weekly_digest">Weekly Digest</option>
                    </select>
                  </div>

                  {/* Quiet Hours */}
                  <div>
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="text-lg font-semibold text-gray-900">Quiet Hours</h3>
                      <label className="flex items-center space-x-2">
                        <input
                          type="checkbox"
                          checked={preferences.quiet_hours_enabled}
                          onChange={(e) => setPreferences({ ...preferences, quiet_hours_enabled: e.target.checked })}
                          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span className="text-sm text-gray-700">Enable quiet hours</span>
                      </label>
                    </div>
                    {preferences.quiet_hours_enabled && (
                      <div className="grid grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                          <input
                            type="time"
                            value={preferences.quiet_hours_start}
                            onChange={(e) => setPreferences({ ...preferences, quiet_hours_start: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                          <input
                            type="time"
                            value={preferences.quiet_hours_end}
                            onChange={(e) => setPreferences({ ...preferences, quiet_hours_end: e.target.value })}
                            className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          />
                        </div>
                      </div>
                    )}
                  </div>

                  {/* AI Optimization */}
                  <div className="bg-purple-50 border border-purple-200 rounded-lg p-6">
                    <div className="flex items-center space-x-2 mb-4">
                      <SparklesIcon className="w-6 h-6 text-purple-600" />
                      <h3 className="text-lg font-semibold text-gray-900">AI Optimization</h3>
                    </div>
                    <div className="space-y-3">
                      {[
                        { key: 'ai_optimization_enabled', label: 'Enable AI optimization', description: 'Let AI improve notification delivery' },
                        { key: 'ai_delivery_timing', label: 'AI delivery timing', description: 'Optimize when notifications are sent' },
                        { key: 'ai_channel_selection', label: 'AI channel selection', description: 'Automatically choose the best delivery channel' },
                        { key: 'ai_content_personalization', label: 'Content personalization', description: 'Personalize notification content' }
                      ].map(option => (
                        <label key={option.key} className="flex items-start space-x-3 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={preferences[option.key as keyof NotificationPreferences] as boolean}
                            onChange={(e) => setPreferences({ ...preferences, [option.key]: e.target.checked })}
                            className="mt-1 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                          />
                          <div>
                            <span className="text-sm font-medium text-gray-700">{option.label}</span>
                            <p className="text-xs text-gray-500">{option.description}</p>
                          </div>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Notification Categories */}
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Notification Categories</h3>
                    <div className="space-y-3">
                      {Object.entries(preferences.categories).map(([category, enabled]) => (
                        <label key={category} className="flex items-center space-x-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                          <input
                            type="checkbox"
                            checked={enabled}
                            onChange={(e) => setPreferences({
                              ...preferences,
                              categories: { ...preferences.categories, [category]: e.target.checked }
                            })}
                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          />
                          <span className="text-sm font-medium text-gray-700 capitalize">
                            {category.replace('_', ' ')}
                          </span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Save Button */}
                  <div className="pt-4 border-t border-gray-200">
                    <button
                      onClick={updatePreferences}
                      className="w-full px-4 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      Save Preferences
                    </button>
                  </div>
                </div>
              )}

              {/* Analytics Tab */}
              {activeTab === 'analytics' && (
                <div className="p-6 space-y-8">
                  {/* Timeframe Selector */}
                  <div className="flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-900">Analytics Overview</h3>
                    <select
                      value={analyticsTimeframe}
                      onChange={(e) => {
                        setAnalyticsTimeframe(e.target.value as any);
                        loadAnalytics();
                      }}
                      className="px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                      <option value="7d">Last 7 days</option>
                      <option value="30d">Last 30 days</option>
                      <option value="90d">Last 90 days</option>
                    </select>
                  </div>

                  {analytics && (
                    <div>
                      {/* Key Metrics */}
                      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                          <div className="text-2xl font-bold text-blue-600">{analytics.total_sent}</div>
                          <div className="text-sm text-blue-700">Total Sent</div>
                        </div>
                        <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                          <div className="text-2xl font-bold text-green-600">{analytics.total_read}</div>
                          <div className="text-sm text-green-700">Total Read</div>
                        </div>
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                          <div className="text-2xl font-bold text-yellow-600">{(analytics.read_rate * 100).toFixed(1)}%</div>
                          <div className="text-sm text-yellow-700">Read Rate</div>
                        </div>
                        <div className="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                          <div className="text-2xl font-bold text-purple-600">{(analytics.engagement_rate * 100).toFixed(1)}%</div>
                          <div className="text-sm text-purple-700">Engagement Rate</div>
                        </div>
                      </div>

                      {/* Preferred Channels */}
                      <div className="mb-8">
                        <h4 className="text-lg font-semibold text-gray-900 mb-4">Preferred Channels</h4>
                        <div className="space-y-2">
                          {analytics.preferred_channels.map((channel, index) => (
                            <div key={index} className="flex items-center space-x-3">
                              <div className="w-24 text-sm text-gray-600 capitalize">
                                {channel.channel.replace('_', ' ')}
                              </div>
                              <div className="flex-1 bg-gray-200 rounded-full h-4 relative">
                                <div
                                  className="bg-blue-600 h-4 rounded-full transition-all"
                                  style={{ width: `${channel.percentage}%` }}
                                ></div>
                              </div>
                              <div className="text-sm font-medium text-gray-900 w-12 text-right">
                                {channel.percentage.toFixed(1)}%
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>

                      {/* AI Optimization Impact */}
                      {analytics.ai_optimization_impact && (
                        <div className="bg-purple-50 border border-purple-200 rounded-lg p-6">
                          <div className="flex items-center space-x-2 mb-4">
                            <SparklesIcon className="w-6 h-6 text-purple-600" />
                            <h4 className="text-lg font-semibold text-gray-900">AI Optimization Impact</h4>
                          </div>
                          <div className="grid grid-cols-3 gap-4">
                            <div className="text-center">
                              <div className="text-2xl font-bold text-purple-600">
                                +{analytics.ai_optimization_impact.improvement_percentage}%
                              </div>
                              <div className="text-sm text-purple-700">Overall Improvement</div>
                            </div>
                            <div className="text-center">
                              <div className="text-2xl font-bold text-purple-600">
                                {analytics.ai_optimization_impact.better_timing_success}%
                              </div>
                              <div className="text-sm text-purple-700">Better Timing</div>
                            </div>
                            <div className="text-center">
                              <div className="text-2xl font-bold text-purple-600">
                                {analytics.ai_optimization_impact.better_channel_success}%
                              </div>
                              <div className="text-sm text-purple-700">Better Channels</div>
                            </div>
                          </div>
                        </div>
                      )}
                    </div>
                  )}

                  {/* AI Insights */}
                  {aiInsights && (
                    <div className="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-lg p-6">
                      <div className="flex items-center space-x-2 mb-6">
                        <SparklesIcon className="w-6 h-6 text-indigo-600" />
                        <h4 className="text-lg font-semibold text-gray-900">AI Insights & Recommendations</h4>
                      </div>

                      {/* Behavior Patterns */}
                      <div className="mb-6">
                        <h5 className="font-medium text-gray-900 mb-3">Behavior Patterns</h5>
                        <div className="space-y-2">
                          {aiInsights.behavior_patterns.map((pattern, index) => (
                            <div key={index} className="flex items-start space-x-3 p-3 bg-white bg-opacity-50 rounded-lg">
                              <div className="flex-1">
                                <div className="flex items-center space-x-2 mb-1">
                                  <span className="text-sm font-medium text-gray-900 capitalize">
                                    {pattern.pattern_type.replace('_', ' ')}
                                  </span>
                                  <span className={`px-2 py-1 text-xs rounded-full ${
                                    pattern.impact === 'positive' ? 'bg-green-100 text-green-800' :
                                    pattern.impact === 'negative' ? 'bg-red-100 text-red-800' :
                                    'bg-gray-100 text-gray-800'
                                  }`}>
                                    {pattern.impact}
                                  </span>
                                </div>
                                <p className="text-sm text-gray-600">{pattern.description}</p>
                              </div>
                              <div className="text-xs text-gray-500">
                                {(pattern.confidence * 100).toFixed(0)}% confidence
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>

                      {/* Optimization Suggestions */}
                      <div className="mb-6">
                        <h5 className="font-medium text-gray-900 mb-3">Optimization Suggestions</h5>
                        <div className="space-y-2">
                          {aiInsights.optimization_suggestions.map((suggestion, index) => (
                            <div key={index} className="p-3 bg-white bg-opacity-50 rounded-lg">
                              <div className="flex items-center space-x-2 mb-1">
                                <span className="text-sm font-medium text-gray-900 capitalize">
                                  {suggestion.category.replace('_', ' ')}
                                </span>
                                <span className="text-xs text-gray-500">
                                  {(suggestion.confidence * 100).toFixed(0)}% confidence
                                </span>
                              </div>
                              <p className="text-sm text-gray-600 mb-1">{suggestion.suggestion}</p>
                              <p className="text-xs text-gray-500">{suggestion.potential_impact}</p>
                            </div>
                          ))}
                        </div>
                      </div>

                      {/* Predictive Analysis */}
                      <div>
                        <h5 className="font-medium text-gray-900 mb-3">Predictive Analysis</h5>
                        <div className="grid grid-cols-3 gap-4">
                          <div className="p-3 bg-white bg-opacity-50 rounded-lg text-center">
                            <div className="text-sm text-gray-600 mb-1">Next Optimal Send Time</div>
                            <div className="font-medium text-gray-900">
                              {new Date(aiInsights.predictive_analysis.next_optimal_send_time).toLocaleTimeString()}
                            </div>
                          </div>
                          <div className="p-3 bg-white bg-opacity-50 rounded-lg text-center">
                            <div className="text-sm text-gray-600 mb-1">Predicted Engagement</div>
                            <div className="font-medium text-gray-900">
                              {(aiInsights.predictive_analysis.predicted_engagement_rate * 100).toFixed(1)}%
                            </div>
                          </div>
                          <div className="p-3 bg-white bg-opacity-50 rounded-lg text-center">
                            <div className="text-sm text-gray-600 mb-1">Recommended Channels</div>
                            <div className="font-medium text-gray-900 text-xs">
                              {aiInsights.predictive_analysis.recommended_channels.join(', ')}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      </motion.div>
    </div>
  );
};

export default SmartNotificationsCenter;
