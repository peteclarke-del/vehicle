import React, {createContext, useContext, useState, useEffect, useCallback, ReactNode} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {useAuth} from './AuthContext';

interface Preferences {
  defaultVehicleId: number | null;
  defaultRowsPerPage: number;
  theme: 'light' | 'dark' | 'system';
  preferredLanguage: string;
  distanceUnit: 'km' | 'mi';
  volumeUnit: 'l' | 'gal';
  currency: string;
  serviceReminders: boolean;
  motReminders: boolean;
  insuranceReminders: boolean;
}

interface UserPreferencesContextType {
  preferences: Preferences;
  loading: boolean;
  updatePreference: (key: keyof Preferences, value: any) => Promise<void>;
  updatePreferences: (updates: Partial<Preferences>) => Promise<void>;
  refreshPreferences: () => Promise<void>;
  setDefaultVehicle: (vehicleId: number | null) => Promise<void>;
}

const defaultPreferences: Preferences = {
  defaultVehicleId: null,
  defaultRowsPerPage: 10,
  theme: 'system',
  preferredLanguage: 'en',
  distanceUnit: 'mi',
  volumeUnit: 'l',
  currency: 'GBP',
  serviceReminders: true,
  motReminders: true,
  insuranceReminders: true,
};

const UserPreferencesContext = createContext<UserPreferencesContextType | undefined>(undefined);

interface UserPreferencesProviderProps {
  children: ReactNode;
}

export const UserPreferencesProvider: React.FC<UserPreferencesProviderProps> = ({children}) => {
  const {api, isAuthenticated} = useAuth();
  const [preferences, setPreferences] = useState<Preferences>(defaultPreferences);
  const [loading, setLoading] = useState(true);

  const loadPreferences = useCallback(async () => {
    try {
      // First try to load from local storage
      const stored = await AsyncStorage.getItem('userPreferences');
      if (stored) {
        setPreferences({...defaultPreferences, ...JSON.parse(stored)});
      }

      // Then fetch from server if authenticated
      if (isAuthenticated) {
        const response = await api.get('/user/preferences');
        // API returns {data: {preferredLanguage, distanceUnit, ...}} - unwrap the envelope
        const serverPrefs = response.data?.data || response.data || {};
        
        const merged = {
          ...defaultPreferences,
          ...serverPrefs,
        };
        
        setPreferences(merged);
        await AsyncStorage.setItem('userPreferences', JSON.stringify(merged));
      }
    } catch (error) {
      console.error('Error loading preferences:', error);
    } finally {
      setLoading(false);
    }
  }, [api, isAuthenticated]);

  useEffect(() => {
    loadPreferences();
  }, [loadPreferences]);

  const updatePreference = useCallback(async (key: keyof Preferences, value: any) => {
    try {
      const newPreferences = {...preferences, [key]: value};
      setPreferences(newPreferences);
      await AsyncStorage.setItem('userPreferences', JSON.stringify(newPreferences));

      if (isAuthenticated) {
        await api.post('/user/preferences', {[key]: value}).catch(() => {});
      }
    } catch (error) {
      console.error('Error updating preference:', error);
    }
  }, [api, isAuthenticated, preferences]);

  const updatePreferences = useCallback(async (updates: Partial<Preferences>) => {
    try {
      const newPreferences = {...preferences, ...updates};
      setPreferences(newPreferences);
      await AsyncStorage.setItem('userPreferences', JSON.stringify(newPreferences));

      if (isAuthenticated) {
        await api.post('/user/preferences', updates).catch(() => {});
      }
    } catch (error) {
      console.error('Error updating preferences:', error);
    }
  }, [api, isAuthenticated, preferences]);

  const setDefaultVehicle = useCallback(async (vehicleId: number | null) => {
    await updatePreference('defaultVehicleId', vehicleId);
  }, [updatePreference]);

  const refreshPreferences = useCallback(async () => {
    setLoading(true);
    await loadPreferences();
  }, [loadPreferences]);

  const value: UserPreferencesContextType = {
    preferences,
    loading,
    updatePreference,
    updatePreferences,
    refreshPreferences,
    setDefaultVehicle,
  };

  return (
    <UserPreferencesContext.Provider value={value}>
      {children}
    </UserPreferencesContext.Provider>
  );
};

export const useUserPreferences = (): UserPreferencesContextType => {
  const context = useContext(UserPreferencesContext);
  if (context === undefined) {
    throw new Error('useUserPreferences must be used within a UserPreferencesProvider');
  }
  return context;
};

export default UserPreferencesContext;
