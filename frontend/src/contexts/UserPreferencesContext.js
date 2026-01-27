import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { useAuth } from './AuthContext';

const UserPreferencesContext = createContext(null);

function prefsStorageKey(userId) {
  return `vehicle.default.${userId}`;
}

export const UserPreferencesProvider = ({ children }) => {
  const { user, api } = useAuth();
  const [defaultVehicleId, setDefaultVehicleId] = useState(null);

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
    if (!api) return;
    try {
      await api.post('/user/preferences', { key, value });
    } catch (e) {
      // ignore failures for now
    }
  }, [api]);

  const setDefaultVehicle = useCallback((vehicleId) => {
    if (!user || !user.id) return;
    setDefaultVehicleId(vehicleId);
    // sync to server
    persistToServer('vehicle.default', vehicleId);
  }, [user, persistToServer]);

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
