import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { useAuth } from './AuthContext';

const UserPreferencesContext = createContext(null);

function prefsStorageKey(userId) {
  return `vehicle.default.${userId}`;
}

export const UserPreferencesProvider = ({ children }) => {
  const { user, api } = useAuth();
  const [defaultVehicleId, setDefaultVehicleId] = useState(null);

  // load from localStorage when user available
  useEffect(() => {
    if (!user || !user.id) {
      setDefaultVehicleId(null);
      return;
    }
    const key = prefsStorageKey(user.id);
    try {
      const stored = localStorage.getItem(key);
      if (stored) setDefaultVehicleId(stored);
    } catch (e) {
      // ignore
    }

    // try to fetch server-side preference (non-blocking)
    (async () => {
      if (!api) return;
      try {
        const res = await api.get('/user/preferences?key=vehicle.default');
        if (res && res.data && res.data.value) {
          setDefaultVehicleId(res.data.value);
        }
      } catch (e) {
        // ignore server errors; localStorage still applies
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
    const key = prefsStorageKey(user.id);
    try {
      if (vehicleId) localStorage.setItem(key, String(vehicleId));
      else localStorage.removeItem(key);
    } catch (e) {}
    setDefaultVehicleId(vehicleId);
    // sync to server
    persistToServer('vehicle.default', vehicleId);
  }, [user, persistToServer]);

  const clearDefaultVehicle = useCallback(() => {
    if (!user || !user.id) {
      setDefaultVehicleId(null);
      return;
    }
    const key = prefsStorageKey(user.id);
    try { localStorage.removeItem(key); } catch (e) {}
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
