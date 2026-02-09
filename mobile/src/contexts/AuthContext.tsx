import React, {createContext, useContext, useState, useEffect, useCallback, useRef, useMemo, ReactNode} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import Config from '../config';
import {useServerConfig} from './ServerConfigContext';
import {createLocalApiAdapter} from '../services/LocalApiAdapter';

interface User {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
  roles: string[];
}

export interface ApiClient {
  get(url: string, config?: any): Promise<any>;
  post(url: string, data?: any, config?: any): Promise<any>;
  put(url: string, data?: any, config?: any): Promise<any>;
  delete(url: string, config?: any): Promise<any>;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  loading: boolean;
  isAuthenticated: boolean;
  isStandalone: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  register: (data: RegisterData) => Promise<void>;
  api: ApiClient;
}

interface RegisterData {
  email: string;
  password: string;
  firstName?: string;
  lastName?: string;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

const LOCAL_USER: User = {id: 1, email: 'local@standalone', firstName: 'Local', lastName: 'User', roles: ['ROLE_USER']};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({children}) => {
  const {mode, serverUrl, resetConfig} = useServerConfig();
  const isStandalone = mode === 'standalone';
  const apiUrl = serverUrl || Config.API_URL || 'http://10.0.2.2:8081/api';

  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const tokenRef = useRef<string | null>(null);

  const api = useMemo<ApiClient>(() => {
    if (isStandalone) {
      return createLocalApiAdapter();
    }

    const instance = axios.create({
      baseURL: apiUrl,
      headers: {'Content-Type': 'application/json'},
      timeout: 30000,
    });

    instance.interceptors.request.use(
      config => {
        const currentToken = tokenRef.current;
        if (currentToken && config.headers) {
          config.headers.Authorization = `Bearer ${currentToken}`;
        }
        return config;
      },
      error => Promise.reject(error),
    );

    return instance;
  }, [isStandalone, apiUrl]);

  const updateAuth = useCallback((newToken: string | null, userData: User | null) => {
    tokenRef.current = newToken;
    setToken(newToken);
    setUser(userData);
  }, []);

  // Load and verify stored auth data on mount
  useEffect(() => {
    if (isStandalone) {
      // Standalone mode: auto-authenticate with local user
      tokenRef.current = 'standalone-token';
      setToken('standalone-token');
      setUser(LOCAL_USER);
      setLoading(false);
      return;
    }

    const loadStoredAuth = async () => {
      try {
        const storedToken = await AsyncStorage.getItem('authToken');

        if (storedToken) {
          tokenRef.current = storedToken;

          try {
            const response = await api.get('/me');
            const userData = response.data;
            setToken(storedToken);
            setUser(userData);
            await AsyncStorage.setItem('authUser', JSON.stringify(userData));
          } catch (verifyError) {
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
  }, [isStandalone, api, updateAuth]);

  const login = useCallback(async (emailAddress: string, password: string) => {
    if (isStandalone) {
      tokenRef.current = 'standalone-token';
      setToken('standalone-token');
      setUser(LOCAL_USER);
      return;
    }

    try {
      const response = await axios.post(`${apiUrl}/login`, {
        email: emailAddress,
        password,
      });

      const newToken = response.data.token;

      if (!newToken) {
        throw new Error('No token received from server');
      }

      tokenRef.current = newToken;

      const userResponse = await api.get('/me');
      const userData = userResponse.data;

      await AsyncStorage.setItem('authToken', newToken);
      await AsyncStorage.setItem('authUser', JSON.stringify(userData));

      setToken(newToken);
      setUser(userData);
    } catch (error: any) {
      tokenRef.current = null;
      const message = error.response?.data?.detail || error.response?.data?.message || 'Login failed';
      throw new Error(message);
    }
  }, [isStandalone, api, apiUrl]);

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

    if (isStandalone) {
      // Return to server config screen
      resetConfig();
      return;
    }

    setToken(null);
    setUser(null);
  }, [isStandalone, resetConfig]);

  const register = useCallback(async (data: RegisterData) => {
    if (isStandalone) {
      tokenRef.current = 'standalone-token';
      setToken('standalone-token');
      setUser(LOCAL_USER);
      return;
    }

    try {
      const response = await axios.post(`${apiUrl}/register`, data);
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
  }, [isStandalone, apiUrl]);

  const value: AuthContextType = {
    user,
    token,
    loading,
    isAuthenticated: !!token && !!user,
    isStandalone,
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
