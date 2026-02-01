import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { useAuth } from './AuthContext';

const UserPreferencesContext = createContext(null);

function prefsStorageKey(userId) {
  return `vehicle.default.${userId}`;
}

export const UserPreferencesProvider = ({ children }) => {
  const { user, api, loading: authLoading } = useAuth();
  const [defaultVehicleId, setDefaultVehicleId] = useState(null);
  const [pendingSave, setPendingSave] = useState(null);

  // load from server when user available
  useEffect(() => {
    if (!user || !user.id) {
      setDefaultVehicleId(null);
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
    })();
  }, [user, api]);

  const persistToServer = useCallback(async (key, value) => {
    if (!api) {
      console.warn('[UserPreferences] Cannot persist to server: API not available');
      return;
    }
    try {
      console.log('[UserPreferences] Persisting to server:', { key, value });
      await api.post('/user/preferences', { key, value });
      console.log('[UserPreferences] Successfully persisted to server');
    } catch (e) {
      console.error('[UserPreferences] Failed to persist to server:', e);
    }
  }, [api]);

  // Process pending save when user becomes available
  useEffect(() => {
    if (pendingSave && user && user.id && !authLoading) {
      console.log('[UserPreferences] Processing pending save:', pendingSave);
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
        console.error('[UserPreferences] Failed to parse user string:', e);
        userObj = null;
      }
    }
    
    console.log('[UserPreferences] Setting default vehicle:', { vehicleId, hasUser: !!userObj, authLoading });
    
    // Always update local state immediately for responsive UI
    setDefaultVehicleId(vehicleId);
    
    // If user not available yet (still loading), queue the save for later
    if (!userObj || !userObj.id) {
      if (authLoading) {
        console.log('[UserPreferences] User still loading, queueing save for later');
        setPendingSave({ key: 'vehicle.default', value: vehicleId });
      } else {
        console.warn('[UserPreferences] Cannot set default vehicle: User not available', { user: userObj, vehicleId });
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

  return (
    <UserPreferencesContext.Provider value={{ defaultVehicleId, setDefaultVehicle, clearDefaultVehicle }}>
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
