import React, { createContext, useContext, useState, useEffect } from 'react';
import axios from 'axios';
import i18n from '../i18n';

const AuthContext = createContext();

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
    const token = localStorage.getItem('token');
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
  const [token, setToken] = useState(localStorage.getItem('token'));

  const logout = () => {
    (async () => {
      try {
        const refreshToken = localStorage.getItem('refreshToken');
        if (refreshToken) {
          await api.post('/auth/revoke', { refreshToken });
        } else {
          await api.post('/auth/revoke');
        }
      } catch (e) {
        // ignore network errors during logout
      }
      localStorage.removeItem('token');
      localStorage.removeItem('refreshToken');
      setToken(null);
      setUser(null);
    })();
  };

  // Add response interceptor to handle 401 errors
  useEffect(() => {
    const interceptor = api.interceptors.response.use(
      (response) => response,
      (error) => {
        if (error.response?.status === 401) {
          // Token expired or invalid - logout immediately
          logout();
          window.location.href = '/login';
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
        console.warn('Failed to apply user language', err);
      }
    } catch (error) {
      localStorage.removeItem('token');
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

    localStorage.setItem('token', newToken);
    setToken(newToken);
    await fetchUser();
    // Try to request a long-lived refresh token from the backend
    try {
      const resp = await api.post('/auth/issue-refresh');
      if (resp?.data?.refreshToken) {
        localStorage.setItem('refreshToken', resp.data.refreshToken);
      }
      // If server returned a token tailored to user's session TTL, adopt it
      if (resp?.data?.token) {
        localStorage.setItem('token', resp.data.token);
        setToken(resp.data.token);
      }
    } catch (e) {
      // non-fatal: continue without refresh token
      // eslint-disable-next-line no-console
      console.warn('Failed to obtain refresh token', e);
    }

    return response.data;
  };

  const register = async (userData) => {
    const response = await api.post('/register', userData);
    return response.data;
  };

  const updateToken = (newToken) => {
    localStorage.setItem('token', newToken);
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
        console.warn('Failed to change language after profile update', err);
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
