import axios, { AxiosResponse } from 'axios';
import { getSiteConfig } from '../config/site';

// Get API base URL from config
const getApiBaseUrl = () => {
  const config = getSiteConfig();
  return config.api.baseUrl || '/api';
};

// API response wrapper for consistent error handling
interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
}

// API error class for better error handling
class ApiError extends Error {
  public status?: number;
  public response?: any;

  constructor(message: string, status?: number, response?: any) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.response = response;
  }
}

// Generic API request function with error handling
async function apiRequest<T>(
  endpoint: string,
  options: {
    method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
    data?: any;
    params?: any;
    timeout?: number;
  } = {}
): Promise<T> {
  const { method = 'GET', data, params, timeout = 10000 } = options;
  const baseURL = getApiBaseUrl();

  try {
    const response: AxiosResponse<ApiResponse<T>> = await axios({
      method,
      url: `${baseURL}${endpoint}`,
      data,
      params,
      timeout,
      headers: {
        'Content-Type': 'application/json',
        // Add auth headers if available
        ...(localStorage.getItem('authToken') && {
          Authorization: `Bearer ${localStorage.getItem('authToken')}`
        })
      }
    });

    // Handle API response format
    if (response.data.success === false) {
      throw new ApiError(
        response.data.error || response.data.message || 'API request failed',
        response.status,
        response.data
      );
    }

    return response.data.data || response.data;
  } catch (error: any) {
    if (error instanceof ApiError) {
      throw error;
    }

    // Handle network errors
    if (error.code === 'ECONNABORTED') {
      throw new ApiError('Request timeout - please try again', 408);
    }

    if (error.response) {
      // Server responded with error status
      throw new ApiError(
        error.response.data?.error || error.response.data?.message || `HTTP ${error.response.status}`,
        error.response.status,
        error.response.data
      );
    }

    if (error.request) {
      // Request made but no response received
      throw new ApiError('Network error - please check your connection');
    }

    // Other errors
    throw new ApiError(error.message || 'An unexpected error occurred');
  }
}

// AI Risk Assistant API
export const aiRiskAssistantApi = {
  // Explain risk factors
  explainRisk: async (data: { location?: string; businessType?: string; factors?: string[] }): Promise<any> => {
    return apiRequest('/ai-risk-assistant.php', {
      method: 'POST',
      data: { action: 'explain_risk', ...data }
    });
  },

  // Get personalized recommendations
  getRecommendations: async (data: { userId?: string; riskProfile?: any }): Promise<any> => {
    return apiRequest('/ai-risk-assistant.php', {
      method: 'POST',
      data: { action: 'get_recommendations', ...data }
    });
  },

  // Chat with AI assistant
  chat: async (data: { message: string; conversationId?: string; context?: any }): Promise<any> => {
    return apiRequest('/ai-risk-assistant.php', {
      method: 'POST',
      data: { action: 'chat', ...data }
    });
  },

  // Get insights
  getInsights: async (data: { userId?: string; timeframe?: string }): Promise<any> => {
    return apiRequest('/ai-risk-assistant.php', {
      method: 'POST',
      data: { action: 'get_insights', ...data }
    });
  }
};

// Multiplayer Game Hub API
export const multiplayerGameApi = {
  // Guild operations
  guilds: {
    getMyGuild: (): Promise<any> => 
      apiRequest('/multiplayer-game.php?action=get_my_guild'),
    
    getAvailable: (): Promise<any> => 
      apiRequest('/multiplayer-game.php?action=get_available_guilds'),
    
    create: (data: any): Promise<any> => 
      apiRequest('/multiplayer-game.php', { method: 'POST', data: { action: 'create_guild', ...data } }),
    
    join: (guildId: string): Promise<any> => 
      apiRequest('/multiplayer-game.php', { method: 'POST', data: { action: 'join_guild', guild_id: guildId } })
  },

  // Cooperative operations
  coopOperations: {
    getMy: (): Promise<any> => 
      apiRequest('/multiplayer-game.php?action=get_my_operations'),
    
    getAvailable: (): Promise<any> => 
      apiRequest('/multiplayer-game.php?action=get_available_operations'),
    
    join: (operationId: string, data: any): Promise<any> => 
      apiRequest('/multiplayer-game.php', { method: 'POST', data: { action: 'join_operation', operation_id: operationId, ...data } })
  },

  // Trading
  trading: {
    getMarketplace: (): Promise<any> => 
      apiRequest('/multiplayer-game.php?action=get_marketplace'),
    
    getMyTrades: (): Promise<any> => 
      apiRequest('/multiplayer-game.php?action=get_my_trades'),
    
    purchase: (tradeId: string): Promise<any> => 
      apiRequest('/multiplayer-game.php', { method: 'POST', data: { action: 'purchase_item', trade_id: tradeId } })
  },

  // Competitions
  competitions: {
    getActive: (): Promise<any> => 
      apiRequest('/multiplayer-game.php?action=get_active_competitions'),
    
    getMy: (): Promise<any> => 
      apiRequest('/multiplayer-game.php?action=get_my_competitions'),
    
    register: (competitionId: string): Promise<any> => 
      apiRequest('/multiplayer-game.php', { method: 'POST', data: { action: 'register_competition', competition_id: competitionId } })
  }
};

// Smart Notifications API
export const smartNotificationsApi = {
  // Queue management
  queue: {
    getHistory: (): Promise<any> => 
      apiRequest('/smart-notifications.php?action=get_notification_history'),
    
    markRead: (notificationIds: string[]): Promise<any> => 
      apiRequest('/smart-notifications.php', { method: 'POST', data: { action: 'mark_read', notification_ids: notificationIds } }),
    
    dismiss: (notificationIds: string[]): Promise<any> => 
      apiRequest('/smart-notifications.php', { method: 'POST', data: { action: 'dismiss', notification_ids: notificationIds } }),
    
    snooze: (notificationId: string, duration: number): Promise<any> => 
      apiRequest('/smart-notifications.php', { method: 'POST', data: { action: 'snooze', notification_id: notificationId, snooze_duration: duration } })
  },

  // Preferences
  preferences: {
    get: (): Promise<any> => 
      apiRequest('/smart-notifications.php?action=get_preferences'),
    
    update: (preferences: any): Promise<any> => 
      apiRequest('/smart-notifications.php', { method: 'POST', data: { action: 'update_preferences', ...preferences } })
  },

  // Analytics
  analytics: {
    get: (timeframe?: string): Promise<any> => 
      apiRequest(`/smart-notifications.php?action=get_analytics${timeframe ? `&timeframe=${timeframe}` : ''}`),
    
    getInsights: (): Promise<any> => 
      apiRequest('/smart-notifications.php?action=get_ai_insights')
  }
};

// Utility function to handle API errors consistently
export const handleApiError = (error: any): string => {
  if (error instanceof ApiError) {
    return error.message;
  }
  
  if (error.response?.data?.error) {
    return error.response.data.error;
  }
  
  if (error.message) {
    return error.message;
  }
  
  return 'An unexpected error occurred. Please try again.';
};

// Export the ApiError class for use in components
export { ApiError };
