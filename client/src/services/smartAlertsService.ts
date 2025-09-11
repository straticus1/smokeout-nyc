import { ComprehensiveRiskAssessment } from './advancedRiskService';

export interface AlertRule {
  id: string;
  name: string;
  description: string;
  enabled: boolean;
  triggerConditions: AlertCondition[];
  actions: AlertAction[];
  priority: 'low' | 'medium' | 'high' | 'critical';
  cooldownMinutes: number; // Prevent spam alerts
  createdAt: Date;
  updatedAt: Date;
}

export interface AlertCondition {
  type: 'enforcement_activity' | 'new_closure' | 'reopening' | 'risk_change' | 'distance' | 'market_change';
  parameter: string;
  operator: 'equals' | 'greater_than' | 'less_than' | 'contains' | 'within_range';
  value: any;
  description: string;
}

export interface AlertAction {
  type: 'push_notification' | 'email' | 'sms' | 'webhook' | 'in_app';
  settings: Record<string, any>;
  enabled: boolean;
}

export interface SmartAlert {
  id: string;
  ruleId: string;
  ruleName: string;
  title: string;
  message: string;
  priority: AlertRule['priority'];
  data: Record<string, any>;
  location?: {
    latitude: number;
    longitude: number;
    address?: string;
  };
  triggeredAt: Date;
  readAt?: Date;
  dismissedAt?: Date;
  actions: AlertActionResult[];
}

export interface AlertActionResult {
  actionType: AlertAction['type'];
  success: boolean;
  timestamp: Date;
  error?: string;
}

export interface AlertSubscription {
  userId: string;
  ruleIds: string[];
  preferences: {
    pushNotifications: boolean;
    emailNotifications: boolean;
    smsNotifications: boolean;
    emailAddress?: string;
    phoneNumber?: string;
    quietHours: {
      enabled: boolean;
      startTime: string; // HH:MM format
      endTime: string;
    };
  };
}

/**
 * Smart Alerts Service for SmokeoutNYC
 * Provides intelligent notification system with customizable rules and multi-channel delivery
 */
export class SmartAlertsService {
  private static alertRules: AlertRule[] = [];
  private static activeAlerts: SmartAlert[] = [];
  private static subscriptions: Map<string, AlertSubscription> = new Map();
  
  /**
   * Initialize default alert rules for new users
   */
  public static initializeDefaultRules(): AlertRule[] {
    const defaultRules: AlertRule[] = [
      {
        id: 'nearby-enforcement',
        name: 'Nearby Enforcement Activity',
        description: 'Alert when enforcement activity is detected within 1 mile',
        enabled: true,
        triggerConditions: [
          {
            type: 'enforcement_activity',
            parameter: 'distance',
            operator: 'less_than',
            value: 1.0,
            description: 'Within 1 mile of user location'
          }
        ],
        actions: [
          {
            type: 'push_notification',
            settings: { urgent: true },
            enabled: true
          },
          {
            type: 'in_app',
            settings: { persistent: true },
            enabled: true
          }
        ],
        priority: 'high',
        cooldownMinutes: 60,
        createdAt: new Date(),
        updatedAt: new Date()
      },
      {
        id: 'new-closures',
        name: 'New Shop Closures',
        description: 'Alert when any smoke shop in NYC is closed',
        enabled: true,
        triggerConditions: [
          {
            type: 'new_closure',
            parameter: 'status',
            operator: 'equals',
            value: 'CLOSED_OPERATION_SMOKEOUT',
            description: 'Shop status changed to closed'
          }
        ],
        actions: [
          {
            type: 'push_notification',
            settings: { category: 'closure_alert' },
            enabled: true
          },
          {
            type: 'email',
            settings: { template: 'closure_notification' },
            enabled: false
          }
        ],
        priority: 'medium',
        cooldownMinutes: 30,
        createdAt: new Date(),
        updatedAt: new Date()
      },
      {
        id: 'shop-reopening',
        name: 'Shop Reopenings',
        description: 'Alert when a previously closed shop reopens',
        enabled: true,
        triggerConditions: [
          {
            type: 'reopening',
            parameter: 'status',
            operator: 'equals',
            value: 'REOPENED',
            description: 'Shop status changed to reopened'
          }
        ],
        actions: [
          {
            type: 'push_notification',
            settings: { category: 'reopening_alert', priority: 'normal' },
            enabled: true
          },
          {
            type: 'in_app',
            settings: { showMap: true },
            enabled: true
          }
        ],
        priority: 'medium',
        cooldownMinutes: 15,
        createdAt: new Date(),
        updatedAt: new Date()
      },
      {
        id: 'risk-level-change',
        name: 'Risk Level Changes',
        description: 'Alert when risk assessment changes significantly for saved locations',
        enabled: false, // Opt-in for advanced users
        triggerConditions: [
          {
            type: 'risk_change',
            parameter: 'risk_score_delta',
            operator: 'greater_than',
            value: 15,
            description: 'Risk score increased by more than 15 points'
          }
        ],
        actions: [
          {
            type: 'push_notification',
            settings: { category: 'risk_alert' },
            enabled: true
          },
          {
            type: 'email',
            settings: { 
              template: 'risk_change_detailed',
              includeRecommendations: true 
            },
            enabled: true
          }
        ],
        priority: 'low',
        cooldownMinutes: 720, // 12 hours
        createdAt: new Date(),
        updatedAt: new Date()
      },
      {
        id: 'high-value-market-opportunity',
        name: 'Market Opportunities',
        description: 'Alert when favorable market conditions are detected',
        enabled: false, // Premium feature
        triggerConditions: [
          {
            type: 'market_change',
            parameter: 'opportunity_score',
            operator: 'greater_than',
            value: 80,
            description: 'High-value market opportunity detected'
          }
        ],
        actions: [
          {
            type: 'push_notification',
            settings: { 
              category: 'market_opportunity',
              priority: 'high'
            },
            enabled: true
          },
          {
            type: 'email',
            settings: { 
              template: 'market_opportunity',
              includeAnalytics: true
            },
            enabled: true
          }
        ],
        priority: 'high',
        cooldownMinutes: 180, // 3 hours
        createdAt: new Date(),
        updatedAt: new Date()
      }
    ];
    
    this.alertRules = defaultRules;
    return defaultRules;
  }
  
  /**
   * Check all active alert rules against new data
   */
  public static async processAlerts(eventData: {
    type: string;
    data: any;
    location?: { latitude: number; longitude: number; };
    timestamp: Date;
  }): Promise<SmartAlert[]> {
    
    const triggeredAlerts: SmartAlert[] = [];
    
    for (const rule of this.alertRules.filter(r => r.enabled)) {
      if (await this.shouldTriggerRule(rule, eventData)) {
        const alert = await this.createAlert(rule, eventData);
        if (alert) {
          triggeredAlerts.push(alert);
          await this.deliverAlert(alert);
        }
      }
    }
    
    return triggeredAlerts;
  }
  
  /**
   * Create a subscription for a user
   */
  public static createSubscription(subscription: AlertSubscription): void {
    this.subscriptions.set(subscription.userId, subscription);
  }
  
  /**
   * Update user's alert preferences
   */
  public static updateSubscription(userId: string, updates: Partial<AlertSubscription>): boolean {
    const existing = this.subscriptions.get(userId);
    if (!existing) return false;
    
    const updated = { ...existing, ...updates };
    this.subscriptions.set(userId, updated);
    return true;
  }
  
  /**
   * Get user's active alerts
   */
  public static getUserAlerts(userId: string, limit: number = 50): SmartAlert[] {
    return this.activeAlerts
      .filter(alert => this.subscriptions.get(userId)?.ruleIds.includes(alert.ruleId))
      .sort((a, b) => b.triggeredAt.getTime() - a.triggeredAt.getTime())
      .slice(0, limit);
  }
  
  /**
   * Mark alert as read
   */
  public static markAlertRead(alertId: string, userId: string): boolean {
    const alert = this.activeAlerts.find(a => a.id === alertId);
    if (!alert) return false;
    
    // Verify user has access to this alert
    const subscription = this.subscriptions.get(userId);
    if (!subscription?.ruleIds.includes(alert.ruleId)) return false;
    
    alert.readAt = new Date();
    return true;
  }
  
  /**
   * Dismiss an alert
   */
  public static dismissAlert(alertId: string, userId: string): boolean {
    const alert = this.activeAlerts.find(a => a.id === alertId);
    if (!alert) return false;
    
    // Verify user has access to this alert
    const subscription = this.subscriptions.get(userId);
    if (!subscription?.ruleIds.includes(alert.ruleId)) return false;
    
    alert.dismissedAt = new Date();
    return true;
  }
  
  /**
   * Get alert statistics for analytics
   */
  public static getAlertStats(userId: string): {
    totalAlerts: number;
    unreadAlerts: number;
    alertsByPriority: Record<string, number>;
    alertsByType: Record<string, number>;
    deliverySuccessRate: number;
  } {
    const userAlerts = this.getUserAlerts(userId, 1000);
    
    const stats = {
      totalAlerts: userAlerts.length,
      unreadAlerts: userAlerts.filter(a => !a.readAt && !a.dismissedAt).length,
      alertsByPriority: {} as Record<string, number>,
      alertsByType: {} as Record<string, number>,
      deliverySuccessRate: 0
    };
    
    // Count by priority
    userAlerts.forEach(alert => {
      stats.alertsByPriority[alert.priority] = (stats.alertsByPriority[alert.priority] || 0) + 1;
    });
    
    // Count by type (based on rule name)
    userAlerts.forEach(alert => {
      const type = alert.ruleName.toLowerCase().replace(/\s+/g, '_');
      stats.alertsByType[type] = (stats.alertsByType[type] || 0) + 1;
    });
    
    // Calculate delivery success rate
    const totalActions = userAlerts.reduce((sum, alert) => sum + alert.actions.length, 0);
    const successfulActions = userAlerts.reduce((sum, alert) => 
      sum + alert.actions.filter(action => action.success).length, 0
    );
    stats.deliverySuccessRate = totalActions > 0 ? (successfulActions / totalActions) * 100 : 100;
    
    return stats;
  }
  
  /**
   * Check if a rule should trigger based on event data
   */
  private static async shouldTriggerRule(rule: AlertRule, eventData: any): Promise<boolean> {
    // Check cooldown
    const lastTriggered = this.getLastTriggeredTime(rule.id);
    const cooldownMs = rule.cooldownMinutes * 60 * 1000;
    if (lastTriggered && (Date.now() - lastTriggered.getTime()) < cooldownMs) {
      return false;
    }
    
    // Check all conditions (AND logic)
    for (const condition of rule.triggerConditions) {
      if (!await this.evaluateCondition(condition, eventData)) {
        return false;
      }
    }
    
    return true;
  }
  
  /**
   * Evaluate a single alert condition
   */
  private static async evaluateCondition(condition: AlertCondition, eventData: any): Promise<boolean> {
    const value = this.extractValue(eventData, condition.parameter);
    
    switch (condition.operator) {
      case 'equals':
        return value === condition.value;
      case 'greater_than':
        return Number(value) > Number(condition.value);
      case 'less_than':
        return Number(value) < Number(condition.value);
      case 'contains':
        return String(value).toLowerCase().includes(String(condition.value).toLowerCase());
      case 'within_range':
        const [min, max] = condition.value;
        return Number(value) >= min && Number(value) <= max;
      default:
        return false;
    }
  }
  
  /**
   * Extract value from event data using parameter path
   */
  private static extractValue(data: any, parameter: string): any {
    const path = parameter.split('.');
    let value = data;
    
    for (const key of path) {
      if (value && typeof value === 'object') {
        value = value[key];
      } else {
        return undefined;
      }
    }
    
    return value;
  }
  
  /**
   * Create alert from rule and event data
   */
  private static async createAlert(rule: AlertRule, eventData: any): Promise<SmartAlert | null> {
    try {
      const alert: SmartAlert = {
        id: this.generateAlertId(),
        ruleId: rule.id,
        ruleName: rule.name,
        title: this.generateAlertTitle(rule, eventData),
        message: this.generateAlertMessage(rule, eventData),
        priority: rule.priority,
        data: eventData.data,
        location: eventData.location,
        triggeredAt: new Date(),
        actions: []
      };
      
      this.activeAlerts.push(alert);
      return alert;
    } catch (error) {
      console.error('Failed to create alert:', error);
      return null;
    }
  }
  
  /**
   * Deliver alert through configured channels
   */
  private static async deliverAlert(alert: SmartAlert): Promise<void> {
    const rule = this.alertRules.find(r => r.id === alert.ruleId);
    if (!rule) return;
    
    for (const action of rule.actions.filter(a => a.enabled)) {
      try {
        const result = await this.executeAlertAction(alert, action);
        alert.actions.push(result);
      } catch (error) {
        console.error(`Failed to execute action ${action.type}:`, error);
        alert.actions.push({
          actionType: action.type,
          success: false,
          timestamp: new Date(),
          error: error instanceof Error ? error.message : 'Unknown error'
        });
      }
    }
  }
  
  /**
   * Execute a specific alert action
   */
  private static async executeAlertAction(alert: SmartAlert, action: AlertAction): Promise<AlertActionResult> {
    switch (action.type) {
      case 'push_notification':
        return await this.sendPushNotification(alert, action.settings);
      case 'email':
        return await this.sendEmail(alert, action.settings);
      case 'sms':
        return await this.sendSMS(alert, action.settings);
      case 'in_app':
        return await this.createInAppNotification(alert, action.settings);
      case 'webhook':
        return await this.sendWebhook(alert, action.settings);
      default:
        throw new Error(`Unknown action type: ${action.type}`);
    }
  }
  
  /**
   * Send push notification (simulated)
   */
  private static async sendPushNotification(alert: SmartAlert, settings: any): Promise<AlertActionResult> {
    // In production, integrate with service like Firebase Cloud Messaging
    console.log('Sending push notification:', alert.title);
    
    return {
      actionType: 'push_notification',
      success: true,
      timestamp: new Date()
    };
  }
  
  /**
   * Send email notification (simulated)
   */
  private static async sendEmail(alert: SmartAlert, settings: any): Promise<AlertActionResult> {
    // In production, integrate with email service like SendGrid
    console.log('Sending email notification:', alert.title);
    
    return {
      actionType: 'email',
      success: true,
      timestamp: new Date()
    };
  }
  
  /**
   * Send SMS notification (simulated)
   */
  private static async sendSMS(alert: SmartAlert, settings: any): Promise<AlertActionResult> {
    // In production, integrate with SMS service like Twilio
    console.log('Sending SMS notification:', alert.title);
    
    return {
      actionType: 'sms',
      success: true,
      timestamp: new Date()
    };
  }
  
  /**
   * Create in-app notification
   */
  private static async createInAppNotification(alert: SmartAlert, settings: any): Promise<AlertActionResult> {
    // Store in local state or database for in-app display
    return {
      actionType: 'in_app',
      success: true,
      timestamp: new Date()
    };
  }
  
  /**
   * Send webhook (simulated)
   */
  private static async sendWebhook(alert: SmartAlert, settings: any): Promise<AlertActionResult> {
    // In production, send HTTP POST to configured webhook URL
    console.log('Sending webhook:', alert.title);
    
    return {
      actionType: 'webhook',
      success: true,
      timestamp: new Date()
    };
  }
  
  /**
   * Generate alert title based on rule and data
   */
  private static generateAlertTitle(rule: AlertRule, eventData: any): string {
    switch (rule.id) {
      case 'nearby-enforcement':
        return '⚠️ Enforcement Activity Detected Nearby';
      case 'new-closures':
        return `🚫 Shop Closure: ${eventData.data?.name || 'Unknown Location'}`;
      case 'shop-reopening':
        return `✅ Shop Reopened: ${eventData.data?.name || 'Unknown Location'}`;
      case 'risk-level-change':
        return '📊 Risk Level Changed';
      case 'high-value-market-opportunity':
        return '💰 Market Opportunity Detected';
      default:
        return rule.name;
    }
  }
  
  /**
   * Generate alert message based on rule and data
   */
  private static generateAlertMessage(rule: AlertRule, eventData: any): string {
    switch (rule.id) {
      case 'nearby-enforcement':
        return `Enforcement activity reported within ${eventData.data?.distance || 'unknown'} distance of your saved locations.`;
      case 'new-closures':
        return `${eventData.data?.name || 'A smoke shop'} has been closed. Check the map for details and alternative locations.`;
      case 'shop-reopening':
        return `${eventData.data?.name || 'A smoke shop'} has reopened! Visit now or check their updated information.`;
      case 'risk-level-change':
        return `Risk assessment has changed for your saved location. New risk level: ${eventData.data?.newRiskLevel || 'Unknown'}`;
      case 'high-value-market-opportunity':
        return 'Favorable market conditions detected in your area. Consider expanding or entering the market.';
      default:
        return `Alert triggered: ${rule.description}`;
    }
  }
  
  /**
   * Generate unique alert ID
   */
  private static generateAlertId(): string {
    return `alert_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }
  
  /**
   * Get last triggered time for a rule
   */
  private static getLastTriggeredTime(ruleId: string): Date | null {
    const lastAlert = this.activeAlerts
      .filter(alert => alert.ruleId === ruleId)
      .sort((a, b) => b.triggeredAt.getTime() - a.triggeredAt.getTime())[0];
    
    return lastAlert ? lastAlert.triggeredAt : null;
  }
}

export { SmartAlertsService };