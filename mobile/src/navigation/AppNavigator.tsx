import React, { useEffect, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createStackNavigator } from '@react-navigation/stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useSelector } from 'react-redux';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { View, StyleSheet, Dimensions } from 'react-native';
import LinearGradient from 'react-native-linear-gradient';

// Auth Screens
import LoginScreen from '../screens/auth/LoginScreen';
import RegisterScreen from '../screens/auth/RegisterScreen';
import ForgotPasswordScreen from '../screens/auth/ForgotPasswordScreen';
import BiometricSetupScreen from '../screens/auth/BiometricSetupScreen';

// Main App Screens
import HomeScreen from '../screens/main/HomeScreen';
import RiskAssessmentScreen from '../screens/main/RiskAssessmentScreen';
import GameScreen from '../screens/main/GameScreen';
import AnalyticsScreen from '../screens/main/AnalyticsScreen';
import ProfileScreen from '../screens/main/ProfileScreen';
import NotificationsScreen from '../screens/main/NotificationsScreen';
import SettingsScreen from '../screens/main/SettingsScreen';

// Gaming Screens
import GrowingSimulationScreen from '../screens/gaming/GrowingSimulationScreen';
import MarketplaceScreen from '../screens/gaming/MarketplaceScreen';
import MultiplayerScreen from '../screens/gaming/MultiplayerScreen';
import LeaderboardScreen from '../screens/gaming/LeaderboardScreen';

// Business Screens
import BusinessDashboardScreen from '../screens/business/BusinessDashboardScreen';
import ComplianceTrackingScreen from '../screens/business/ComplianceTrackingScreen';
import DocumentScannerScreen from '../screens/business/DocumentScannerScreen';

// Advanced Features
import AIRecommendationsScreen from '../screens/advanced/AIRecommendationsScreen';
import PredictiveAnalyticsScreen from '../screens/advanced/PredictiveAnalyticsScreen';
import ARVisualizationScreen from '../screens/advanced/ARVisualizationScreen';
import BlockchainWalletScreen from '../screens/advanced/BlockchainWalletScreen';

// Onboarding
import OnboardingScreen from '../screens/onboarding/OnboardingScreen';
import WelcomeScreen from '../screens/onboarding/WelcomeScreen';

import { RootState } from '../store/store';
import { colors, spacing } from '../theme/theme';

const Stack = createStackNavigator();
const Tab = createBottomTabNavigator();
const { width } = Dimensions.get('window');

interface TabBarIconProps {
  focused: boolean;
  color: string;
  size: number;
}

const TabBarIcon = ({ focused, iconName }: { focused: boolean; iconName: string }) => (
  <View style={styles.tabIconContainer}>
    {focused && (
      <LinearGradient
        colors={[colors.primary, colors.secondary]}
        style={styles.tabIconBackground}
      />
    )}
    <Icon
      name={iconName}
      size={24}
      color={focused ? colors.white : colors.gray}
      style={styles.tabIcon}
    />
  </View>
);

const MainTabs = () => {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused }) => {
          let iconName = '';

          switch (route.name) {
            case 'Home':
              iconName = 'home';
              break;
            case 'RiskAssessment':
              iconName = 'shield-alert';
              break;
            case 'Game':
              iconName = 'gamepad-variant';
              break;
            case 'Analytics':
              iconName = 'chart-line';
              break;
            case 'Profile':
              iconName = 'account';
              break;
          }

          return <TabBarIcon focused={focused} iconName={iconName} />;
        },
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.gray,
        tabBarStyle: styles.tabBar,
        tabBarLabelStyle: styles.tabBarLabel,
        headerShown: false,
        tabBarHideOnKeyboard: true,
      })}
    >
      <Tab.Screen 
        name="Home" 
        component={HomeScreen}
        options={{ tabBarLabel: 'Home' }}
      />
      <Tab.Screen 
        name="RiskAssessment" 
        component={RiskAssessmentScreen}
        options={{ tabBarLabel: 'Risk' }}
      />
      <Tab.Screen 
        name="Game" 
        component={GameScreen}
        options={{ tabBarLabel: 'Game' }}
      />
      <Tab.Screen 
        name="Analytics" 
        component={AnalyticsScreen}
        options={{ tabBarLabel: 'Analytics' }}
      />
      <Tab.Screen 
        name="Profile" 
        component={ProfileScreen}
        options={{ tabBarLabel: 'Profile' }}
      />
    </Tab.Navigator>
  );
};

const AuthStack = () => (
  <Stack.Navigator
    screenOptions={{
      headerShown: false,
      gestureEnabled: true,
      cardStyleInterpolator: ({ current, layouts }) => {
        return {
          cardStyle: {
            transform: [
              {
                translateX: current.progress.interpolate({
                  inputRange: [0, 1],
                  outputRange: [layouts.screen.width, 0],
                }),
              },
            ],
          },
        };
      },
    }}
  >
    <Stack.Screen name="Welcome" component={WelcomeScreen} />
    <Stack.Screen name="Login" component={LoginScreen} />
    <Stack.Screen name="Register" component={RegisterScreen} />
    <Stack.Screen name="ForgotPassword" component={ForgotPasswordScreen} />
    <Stack.Screen name="BiometricSetup" component={BiometricSetupScreen} />
    <Stack.Screen name="Onboarding" component={OnboardingScreen} />
  </Stack.Navigator>
);

const MainStack = () => (
  <Stack.Navigator
    screenOptions={{
      headerShown: false,
      gestureEnabled: true,
      cardStyleInterpolator: ({ current, layouts }) => {
        return {
          cardStyle: {
            transform: [
              {
                translateX: current.progress.interpolate({
                  inputRange: [0, 1],
                  outputRange: [layouts.screen.width, 0],
                }),
              },
            ],
          },
        };
      },
    }}
  >
    <Stack.Screen name="MainTabs" component={MainTabs} />
    
    {/* Notifications */}
    <Stack.Screen name="Notifications" component={NotificationsScreen} />
    <Stack.Screen name="Settings" component={SettingsScreen} />
    
    {/* Gaming Screens */}
    <Stack.Screen name="GrowingSimulation" component={GrowingSimulationScreen} />
    <Stack.Screen name="Marketplace" component={MarketplaceScreen} />
    <Stack.Screen name="Multiplayer" component={MultiplayerScreen} />
    <Stack.Screen name="Leaderboard" component={LeaderboardScreen} />
    
    {/* Business Screens */}
    <Stack.Screen name="BusinessDashboard" component={BusinessDashboardScreen} />
    <Stack.Screen name="ComplianceTracking" component={ComplianceTrackingScreen} />
    <Stack.Screen name="DocumentScanner" component={DocumentScannerScreen} />
    
    {/* Advanced Features */}
    <Stack.Screen name="AIRecommendations" component={AIRecommendationsScreen} />
    <Stack.Screen name="PredictiveAnalytics" component={PredictiveAnalyticsScreen} />
    <Stack.Screen name="ARVisualization" component={ARVisualizationScreen} />
    <Stack.Screen name="BlockchainWallet" component={BlockchainWalletScreen} />
  </Stack.Navigator>
);

const AppNavigator: React.FC = () => {
  const { isAuthenticated, hasCompletedOnboarding } = useSelector((state: RootState) => state.auth);
  const [isInitializing, setIsInitializing] = useState(true);

  useEffect(() => {
    // Simulate app initialization (check tokens, load user data, etc.)
    const initializeApp = async () => {
      try {
        // Add any initialization logic here
        await new Promise(resolve => setTimeout(resolve, 1000));
      } catch (error) {
        console.error('App initialization failed:', error);
      } finally {
        setIsInitializing(false);
      }
    };

    initializeApp();
  }, []);

  if (isInitializing) {
    return <WelcomeScreen />;
  }

  return (
    <NavigationContainer>
      {isAuthenticated && hasCompletedOnboarding ? <MainStack /> : <AuthStack />}
    </NavigationContainer>
  );
};

const styles = StyleSheet.create({
  tabBar: {
    backgroundColor: colors.white,
    borderTopWidth: 0,
    elevation: 20,
    shadowColor: colors.black,
    shadowOffset: {
      width: 0,
      height: -4,
    },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    height: 80,
    paddingTop: spacing.sm,
    paddingBottom: spacing.md,
    paddingHorizontal: spacing.sm,
  },
  tabBarLabel: {
    fontSize: 12,
    fontWeight: '600',
    marginTop: 4,
  },
  tabIconContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    width: 40,
    height: 40,
    borderRadius: 20,
  },
  tabIconBackground: {
    position: 'absolute',
    width: 40,
    height: 40,
    borderRadius: 20,
  },
  tabIcon: {
    zIndex: 1,
  },
});

export default AppNavigator;
