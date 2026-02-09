import React, {createContext, useContext, useState, useEffect, useCallback, useRef, useMemo, ReactNode} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios, {AxiosInstance} from 'axios';
import Config from '../config';

interface User {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
  roles: string[];
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  loading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  register: (data: RegisterData) => Promise<void>;
  api: AxiosInstance;
}

interface RegisterData {
  email: string;
  password: string;
  firstName?: string;
  lastName?: string;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

const API_URL = Config.API_URL || 'http://10.0.2.2:8081/api';

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({children}) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  // Use a ref to always have the latest token available for the interceptor.
  // This eliminates race conditions where React state hasn't propagated yet.
  const tokenRef = useRef<string | null>(null);

  // Single stable axios instance — never recreated
  const api = useMemo<AxiosInstance>(() => {
    const instance = axios.create({
      baseURL: API_URL,
      headers: {
        'Content-Type': 'application/json',
      },
      timeout: 30000,
    });

    // Interceptor reads token from ref, so it always uses the latest value
    instance.interceptors.request.use(
      config => {
        const currentToken = tokenRef.current;
        if (currentToken) {
          config.headers.Authorization = `Bearer ${currentToken}`;
        }
        return config;
      },
      error => Promise.reject(error),
    );

    return instance;
  }, []);

  // Helper: update token in both ref (immediate) and state (triggers re-render)
  const updateAuth = useCallback((newToken: string | null, userData: User | null) => {
    tokenRef.current = newToken;
    setToken(newToken);
    setUser(userData);
  }, []);

  // Load and verify stored auth data on mount
  useEffect(() => {
    const loadStoredAuth = async () => {
      try {
        const storedToken = await AsyncStorage.getItem('authToken');

        if (storedToken) {
          // Set token in ref immediately so the api instance can use it
          tokenRef.current = storedToken;

          try {
            // Verify the stored token is still valid
            const response = await api.get('/me');
            const userData = response.data;
            setToken(storedToken);
            setUser(userData);
            await AsyncStorage.setItem('authUser', JSON.stringify(userData));
          } catch (verifyError) {
            // Token expired or invalid — clear
            console.warn('Stored token invalid, clearing auth');
            tokenRef.current = null;
            await AsyncStorage.removeItem('authToken');
            await AsyncStorage.removeItem('authUser');
          }
        }
      } catch (error) {
        console.error('Error loading stored auth:', error);
      } finally {
        setLoading(false);
      }
    };

    loadStoredAuth();
  }, [api, updateAuth]);

  const login = useCallback(async (emailAddress: string, password: string) => {
    try {
      const response = await axios.post(`${API_URL}/login`, {
        email: emailAddress,
        password,
      });

      const newToken = response.data.token;

      if (!newToken) {
        throw new Error('No token received from server');
      }

      // Set token in ref FIRST so subsequent api calls are authenticated
      tokenRef.current = newToken;

      // Fetch user profile using the now-authenticated api instance
      const userResponse = await api.get('/me');
      const userData = userResponse.data;

      await AsyncStorage.setItem('authToken', newToken);
      await AsyncStorage.setItem('authUser', JSON.stringify(userData));

      // Update state to trigger re-render (isAuthenticated becomes true)
      setToken(newToken);
      setUser(userData);
    } catch (error: any) {
      // If login failed, clear the ref
      tokenRef.current = null;
      const message = error.response?.data?.detail || error.response?.data?.message || 'Login failed';
      throw new Error(message);
    }
  }, [api]);

  const logout = useCallback(async () => {
    tokenRef.current = null;
    await AsyncStorage.removeItem('authToken');
    await AsyncStorage.removeItem('authUser');
    // Clear offline caches
    try {
      const keys = await AsyncStorage.getAllKeys();
      const cacheKeys = keys.filter(k => k.startsWith('cache_') || k.startsWith('offline_cache_') || k === 'global_selected_vehicle_id');
      if (cacheKeys.length > 0) {
        await AsyncStorage.multiRemove(cacheKeys);
      }
    } catch (e) {
      // Non-critical
    }
    setToken(null);
    setUser(null);
  }, []);

  const register = useCallback(async (data: RegisterData) => {
    try {
      const response = await axios.post(`${API_URL}/register`, data);
      const {token: newToken, user: userData} = response.data;

      if (newToken) {
        tokenRef.current = newToken;
        await AsyncStorage.setItem('authToken', newToken);
        await AsyncStorage.setItem('authUser', JSON.stringify(userData));
        setToken(newToken);
        setUser(userData);
      }
    } catch (error: any) {
      const message = error.response?.data?.error || 'Registration failed';
      throw new Error(message);
    }
  }, []);

  const value: AuthContextType = {
    user,
    token,
    loading,
    isAuthenticated: !!token && !!user,
    login,
    logout,
    register,
    api,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export default AuthContext;
