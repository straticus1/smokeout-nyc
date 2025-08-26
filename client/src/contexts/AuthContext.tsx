import React, { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';

interface User {
  id: number;
  username: string;
  email: string;
  credits: number;
  zip_code?: string;
  city?: string;
  state?: string;
  email_verified: boolean;
  phone_verified: boolean;
  created_at: string;
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<boolean>;
  register: (userData: RegisterData) => Promise<boolean>;
  logout: () => void;
  updateCredits: (credits: number) => void;
  updateLocation: (location: LocationData) => Promise<boolean>;
  isAuthenticated: boolean;
}

interface RegisterData {
  username: string;
  email: string;
  password: string;
  phone?: string;
}

interface LocationData {
  zip_code: string;
  city?: string;
  state?: string;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Set up axios defaults
  useEffect(() => {
    const token = localStorage.getItem('session_token');
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
  }, []);

  // Check if user is authenticated on app load
  useEffect(() => {
    const checkAuth = async () => {
      const token = localStorage.getItem('session_token');
      if (token) {
        try {
          const response = await axios.get('/api/auth/verify-session');
          if (response.data.success) {
            setUser(response.data.user);
          } else {
            localStorage.removeItem('session_token');
            delete axios.defaults.headers.common['Authorization'];
          }
        } catch (error) {
          console.error('Auth check failed:', error);
          localStorage.removeItem('session_token');
          delete axios.defaults.headers.common['Authorization'];
        }
      }
      setLoading(false);
    };

    checkAuth();
  }, []);

  const login = async (email: string, password: string): Promise<boolean> => {
    try {
      const response = await axios.post('/api/auth/login', {
        email,
        password,
      });

      if (response.data.success) {
        const { user: userData, session_token } = response.data;
        setUser(userData);
        localStorage.setItem('session_token', session_token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${session_token}`;
        toast.success('Login successful!');
        return true;
      } else {
        toast.error(response.data.error || 'Login failed');
        return false;
      }
    } catch (error: any) {
      const message = error.response?.data?.error || 'Login failed';
      toast.error(message);
      return false;
    }
  };

  const register = async (userData: RegisterData): Promise<boolean> => {
    try {
      const response = await axios.post('/api/auth/register', userData);

      if (response.data.success) {
        toast.success('Registration successful! Please check your email for verification.');
        return true;
      } else {
        toast.error(response.data.error || 'Registration failed');
        return false;
      }
    } catch (error: any) {
      const message = error.response?.data?.error || 'Registration failed';
      toast.error(message);
      return false;
    }
  };

  const logout = async () => {
    try {
      await axios.post('/api/auth/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      setUser(null);
      localStorage.removeItem('session_token');
      delete axios.defaults.headers.common['Authorization'];
      toast.success('Logged out successfully');
    }
  };

  const updateCredits = (credits: number) => {
    if (user) {
      setUser({ ...user, credits });
    }
  };

  const updateLocation = async (location: LocationData): Promise<boolean> => {
    try {
      const response = await axios.put('/api/auth/location', location);
      
      if (response.data.success) {
        if (user) {
          setUser({
            ...user,
            zip_code: location.zip_code,
            city: location.city,
            state: location.state,
          });
        }
        toast.success('Location updated successfully');
        return true;
      } else {
        toast.error(response.data.error || 'Failed to update location');
        return false;
      }
    } catch (error: any) {
      const message = error.response?.data?.error || 'Failed to update location';
      toast.error(message);
      return false;
    }
  };

  const value: AuthContextType = {
    user,
    loading,
    login,
    register,
    logout,
    updateCredits,
    updateLocation,
    isAuthenticated: !!user,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
