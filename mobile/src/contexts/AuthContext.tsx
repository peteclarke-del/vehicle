import React, {createContext, useContext, useState, useEffect, useCallback, ReactNode} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios, {AxiosInstance} from 'axios';
import Config from 'react-native-config';

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

// Create axios instance
const createApiInstance = (token: string | null): AxiosInstance => {
  const instance = axios.create({
    baseURL: API_URL,
    headers: {
      'Content-Type': 'application/json',
    },
    timeout: 30000,
  });

  // Add auth token to requests
  instance.interceptors.request.use(
    config => {
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    },
    error => Promise.reject(error),
  );

  return instance;
};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({children}) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [api, setApi] = useState<AxiosInstance>(() => createApiInstance(null));

  // Load stored auth data on mount
  useEffect(() => {
    const loadStoredAuth = async () => {
      try {
        const storedToken = await AsyncStorage.getItem('authToken');
        const storedUser = await AsyncStorage.getItem('authUser');

        if (storedToken && storedUser) {
          setToken(storedToken);
          setUser(JSON.parse(storedUser));
          setApi(createApiInstance(storedToken));
        }
      } catch (error) {
        console.error('Error loading stored auth:', error);
      } finally {
        setLoading(false);
      }
    };

    loadStoredAuth();
  }, []);

  // Verify token is still valid
  useEffect(() => {
    const verifyToken = async () => {
      if (!token) return;

      try {
        const response = await api.get('/me');
        setUser(response.data);
        await AsyncStorage.setItem('authUser', JSON.stringify(response.data));
      } catch (error) {
        // Token is invalid, clear auth
        console.error('Token verification failed:', error);
        await logout();
      }
    };

    if (token && !loading) {
      verifyToken();
    }
  }, [token, loading]);

  const login = useCallback(async (email: string, password: string) => {
    try {
      const response = await axios.post(`${API_URL}/login`, {
        username: email,
        password,
      });

      const {token: newToken, user: userData} = response.data;

      await AsyncStorage.setItem('authToken', newToken);
      await AsyncStorage.setItem('authUser', JSON.stringify(userData));

      setToken(newToken);
      setUser(userData);
      setApi(createApiInstance(newToken));
    } catch (error: any) {
      const message = error.response?.data?.message || 'Login failed';
      throw new Error(message);
    }
  }, []);

  const logout = useCallback(async () => {
    await AsyncStorage.removeItem('authToken');
    await AsyncStorage.removeItem('authUser');
    setToken(null);
    setUser(null);
    setApi(createApiInstance(null));
  }, []);

  const register = useCallback(async (data: RegisterData) => {
    try {
      const response = await axios.post(`${API_URL}/register`, data);
      const {token: newToken, user: userData} = response.data;

      if (newToken) {
        await AsyncStorage.setItem('authToken', newToken);
        await AsyncStorage.setItem('authUser', JSON.stringify(userData));
        setToken(newToken);
        setUser(userData);
        setApi(createApiInstance(newToken));
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
