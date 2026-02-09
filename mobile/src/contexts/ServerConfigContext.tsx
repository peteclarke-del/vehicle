import React, {createContext, useContext, useState, useEffect, useCallback, ReactNode} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

export type AppMode = 'standalone' | 'web';

interface ServerConfigContextType {
  mode: AppMode;
  serverUrl: string;
  isConfigured: boolean;
  loading: boolean;
  setConfig: (mode: AppMode, serverUrl?: string) => Promise<void>;
  resetConfig: () => Promise<void>;
}

const STORAGE_KEY = 'serverConfig';

const ServerConfigContext = createContext<ServerConfigContextType | undefined>(undefined);

interface ServerConfigProviderProps {
  children: ReactNode;
}

export const ServerConfigProvider: React.FC<ServerConfigProviderProps> = ({children}) => {
  const [mode, setMode] = useState<AppMode>('standalone');
  const [serverUrl, setServerUrl] = useState('');
  const [isConfigured, setIsConfigured] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      try {
        const raw = await AsyncStorage.getItem(STORAGE_KEY);
        if (raw) {
          const config = JSON.parse(raw);
          setMode(config.mode || 'standalone');
          setServerUrl(config.serverUrl || '');
          setIsConfigured(true);
        }
      } catch (e) {
        console.error('Error loading server config:', e);
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  const setConfig = useCallback(async (newMode: AppMode, newUrl?: string) => {
    const config = {mode: newMode, serverUrl: newUrl || ''};
    await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(config));
    setMode(newMode);
    setServerUrl(newUrl || '');
    setIsConfigured(true);
  }, []);

  const resetConfig = useCallback(async () => {
    await AsyncStorage.removeItem(STORAGE_KEY);
    setMode('standalone');
    setServerUrl('');
    setIsConfigured(false);
  }, []);

  return (
    <ServerConfigContext.Provider value={{mode, serverUrl, isConfigured, loading, setConfig, resetConfig}}>
      {children}
    </ServerConfigContext.Provider>
  );
};

export const useServerConfig = (): ServerConfigContextType => {
  const context = useContext(ServerConfigContext);
  if (!context) {
    throw new Error('useServerConfig must be used within a ServerConfigProvider');
  }
  return context;
};

export default ServerConfigContext;
