import React, { createContext, useContext, useState, useEffect } from 'react';
import axios from 'axios';
import i18n from '../i18n';
import logger from '../utils/logger';
import SafeStorage from '../utils/SafeStorage';

const AuthContext = createContext();

export { AuthContext };

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};

const api = axios.create({
  // Default to the nginx host port used by the dev environment (8081).
  baseURL: process.env.REACT_APP_API_URL || 'http://localhost:8081/api',
});

api.interceptors.request.use(
  (config) => {
    const token = SafeStorage.get('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState(SafeStorage.get('token'));

  const logout = () => {
    (async () => {
      try {
        const refreshToken = SafeStorage.get('refreshToken');
        if (refreshToken) {
          await api.post('/auth/revoke', { refreshToken });
        } else {
          await api.post('/auth/revoke');
        }
      } catch (e) {
        // ignore network errors during logout
      }
      SafeStorage.remove('token');
      SafeStorage.remove('refreshToken');
      setToken(null);
      setUser(null);
    })();
  };

  // Add response interceptor to handle 401 errors
  useEffect(() => {
    // Track in-flight refresh so we don't stack multiple refresh calls
    let isRefreshing = false;
    let pendingRequests = [];

    const interceptor = api.interceptors.response.use(
      (response) => response,
      async (error) => {
        const originalRequest = error.config;

        if (error.response?.status === 401 && !originalRequest._retry) {
          // Avoid retrying the refresh endpoint itself to prevent infinite loops
          if (originalRequest.url?.includes('/auth/refresh') || originalRequest.url?.includes('/login')) {
            logout();
            window.location.href = '/login';
            return Promise.reject(error);
          }

          // Try to use the stored refresh token before giving up
          const refreshToken = SafeStorage.get('refreshToken');
          if (!refreshToken) {
            logout();
            window.location.href = '/login';
            return Promise.reject(error);
          }

          if (isRefreshing) {
            // Queue this request until the refresh completes
            return new Promise((resolve, reject) => {
              pendingRequests.push({ resolve, reject });
            }).then(() => api(originalRequest)).catch(() => Promise.reject(error));
          }

          originalRequest._retry = true;
          isRefreshing = true;

          try {
            const resp = await api.post('/auth/refresh', { refreshToken });
            const newToken = resp?.data?.token;
            if (!newToken) throw new Error('No token in refresh response');

            SafeStorage.set('token', newToken);
            setToken(newToken);

            // Unblock queued requests
            pendingRequests.forEach(({ resolve }) => resolve());
            pendingRequests = [];
            isRefreshing = false;

            // Retry original request with new token
            originalRequest.headers = {
              ...originalRequest.headers,
              Authorization: `Bearer ${newToken}`,
            };
            return api(originalRequest);
          } catch (refreshError) {
            pendingRequests.forEach(({ reject }) => reject(refreshError));
            pendingRequests = [];
            isRefreshing = false;
            logout();
            window.location.href = '/login';
            return Promise.reject(refreshError);
          }
        }

        return Promise.reject(error);
      }
    );

    return () => {
      api.interceptors.response.eject(interceptor);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (token) {
      fetchUser();
    } else {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const fetchUser = async () => {
    try {
      const response = await api.get('/me');
      setUser(response.data);
      // Apply saved user language preference if present
      try {
        const lang = response.data?.preferredLanguage || response.data?.preferred_language || null;
        if (lang) {
          await i18n.changeLanguage(lang);
        }
      } catch (err) {
        // ignore i18n errors
        // eslint-disable-next-line no-console
        logger.warn('Failed to apply user language', err);
      }
    } catch (error) {
      SafeStorage.remove('token');
    } finally {
      setLoading(false);
    }
  };

  const login = async (email, password) => {
    const response = await api.post('/login', { email, password });
    const newToken = response.data?.token;

    if (!newToken) {
      // Defensive: avoid storing an undefined/invalid token which will cause
      // immediate 401s and automatic logout. Surface a useful error to caller.
      throw new Error('Authentication failed: no token returned by server');
    }

    SafeStorage.set('token', newToken);
    setToken(newToken);
    await fetchUser();
    // Try to request a long-lived refresh token from the backend
    try {
      const resp = await api.post('/auth/issue-refresh');
      if (resp?.data?.refreshToken) {
        SafeStorage.set('refreshToken', resp.data.refreshToken);
      }
      // If server returned a token tailored to user's session TTL, adopt it
      if (resp?.data?.token) {
        SafeStorage.set('token', resp.data.token);
        setToken(resp.data.token);
      }
    } catch (e) {
      // non-fatal: continue without refresh token
      // eslint-disable-next-line no-console
      logger.warn('Failed to obtain refresh token', e);
    }

    return response.data;
  };

  const register = async (userData) => {
    const response = await api.post('/register', userData);
    return response.data;
  };

  const updateToken = (newToken) => {
    SafeStorage.set('token', newToken);
    setToken(newToken);
  };

  const updateProfile = async (profileData) => {
    const response = await api.put('/profile', profileData);
    // If language was updated, apply immediately
    const lang = profileData?.preferredLanguage || profileData?.language;
    if (lang) {
      try {
        await i18n.changeLanguage(lang);
      } catch (err) {
        // eslint-disable-next-line no-console
        logger.warn('Failed to change language after profile update', err);
      }
    }
    await fetchUser();
    return response.data;
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        token,
        login,
        register,
        logout,
        updateProfile,
        updateToken,
        fetchUser,
        api,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};
