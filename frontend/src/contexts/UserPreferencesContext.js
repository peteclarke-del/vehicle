import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { useAuth } from './AuthContext';
import logger from '../utils/logger';

const UserPreferencesContext = createContext(null);

const DEFAULT_ROWS_PER_PAGE = 10;

export const UserPreferencesProvider = ({ children }) => {
  const { user, api, loading: authLoading } = useAuth();
  const [defaultVehicleId, setDefaultVehicleId] = useState(null);
  const [defaultRowsPerPage, setDefaultRowsPerPageState] = useState(DEFAULT_ROWS_PER_PAGE);
  const [pendingSave, setPendingSave] = useState(null);

  // load from server when user available
  useEffect(() => {
    if (!user || !user.id) {
      setDefaultVehicleId(null);
      setDefaultRowsPerPageState(DEFAULT_ROWS_PER_PAGE);
      return;
    }

    (async () => {
      if (!api) return;
      try {
        const res = await api.get('/user/preferences?key=vehicle.default');
        if (res && res.data) {
          setDefaultVehicleId(res.data.value ?? null);
        }
      } catch (e) {
        // ignore server errors for now
      }
      
      try {
        const res = await api.get('/user/preferences?key=defaultRowsPerPage');
        if (res && res.data && res.data.value) {
          setDefaultRowsPerPageState(parseInt(res.data.value, 10) || DEFAULT_ROWS_PER_PAGE);
        }
      } catch (e) {
        // ignore server errors for now
      }
    })();
  }, [user, api]);

  const persistToServer = useCallback(async (key, value) => {
    if (!api) {
      logger.warn('[UserPreferences] Cannot persist to server: API not available');
      return;
    }
    try {
      logger.log('[UserPreferences] Persisting to server:', { key, value });
      await api.post('/user/preferences', { key, value });
      logger.log('[UserPreferences] Successfully persisted to server');
    } catch (e) {
      logger.error('[UserPreferences] Failed to persist to server:', e);
    }
  }, [api]);

  // Process pending save when user becomes available
  useEffect(() => {
    if (pendingSave && user && user.id && !authLoading) {
      logger.log('[UserPreferences] Processing pending save:', pendingSave);
      persistToServer(pendingSave.key, pendingSave.value);
      setPendingSave(null);
    }
  }, [pendingSave, user, authLoading, persistToServer]);

  const setDefaultVehicle = useCallback((vehicleId) => {
    // Defensive: handle case where user might be stringified
    let userObj = user;
    if (typeof user === 'string') {
      try {
        userObj = JSON.parse(user);
      } catch (e) {
        logger.error('[UserPreferences] Failed to parse user string:', e);
        userObj = null;
      }
    }
    
    logger.log('[UserPreferences] Setting default vehicle:', { vehicleId, hasUser: !!userObj, authLoading });
    
    // Always update local state immediately for responsive UI
    setDefaultVehicleId(vehicleId);
    
    // If user not available yet (still loading), queue the save for later
    if (!userObj || !userObj.id) {
      if (authLoading) {
        logger.log('[UserPreferences] User still loading, queueing save for later');
        setPendingSave({ key: 'vehicle.default', value: vehicleId });
      } else {
        logger.warn('[UserPreferences] Cannot set default vehicle: User not available', { user: userObj, vehicleId });
      }
      return;
    }
    
    // User is available, save immediately
    persistToServer('vehicle.default', vehicleId);
  }, [user, authLoading, persistToServer]);

  const clearDefaultVehicle = useCallback(() => {
    if (!user || !user.id) {
      setDefaultVehicleId(null);
      return;
    }
    setDefaultVehicleId(null);
    persistToServer('vehicle.default', null);
  }, [user, persistToServer]);

  const setDefaultRowsPerPage = useCallback((rowsPerPage) => {
    const value = parseInt(rowsPerPage, 10) || DEFAULT_ROWS_PER_PAGE;
    setDefaultRowsPerPageState(value);
    
    if (!user || !user.id) {
      return;
    }
    
    persistToServer('defaultRowsPerPage', value);
  }, [user, persistToServer]);

  return (
    <UserPreferencesContext.Provider value={{ 
      defaultVehicleId, 
      setDefaultVehicle, 
      clearDefaultVehicle,
      defaultRowsPerPage,
      setDefaultRowsPerPage,
    }}>
      {children}
    </UserPreferencesContext.Provider>
  );
};

export const useUserPreferences = () => {
  const ctx = useContext(UserPreferencesContext);
  if (!ctx) throw new Error('useUserPreferences must be used within UserPreferencesProvider');
  return ctx;
};

export default UserPreferencesContext;
