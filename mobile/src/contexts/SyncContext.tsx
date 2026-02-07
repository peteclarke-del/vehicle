import React, {createContext, useContext, useState, useEffect, useCallback, ReactNode} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import {useAuth} from './AuthContext';

interface PendingChange {
  id: string;
  type: 'create' | 'update' | 'delete';
  entityType: string;
  entityId?: number;
  data: any;
  timestamp: number;
}

interface SyncContextType {
  isOnline: boolean;
  isSyncing: boolean;
  pendingChanges: PendingChange[];
  lastSyncTime: Date | null;
  syncNow: () => Promise<void>;
  addPendingChange: (change: Omit<PendingChange, 'id' | 'timestamp'>) => Promise<void>;
  clearPendingChanges: () => Promise<void>;
}

const SyncContext = createContext<SyncContextType | undefined>(undefined);

interface SyncProviderProps {
  children: ReactNode;
}

export const SyncProvider: React.FC<SyncProviderProps> = ({children}) => {
  const {api, isAuthenticated} = useAuth();
  const [isOnline, setIsOnline] = useState(true);
  const [isSyncing, setIsSyncing] = useState(false);
  const [pendingChanges, setPendingChanges] = useState<PendingChange[]>([]);
  const [lastSyncTime, setLastSyncTime] = useState<Date | null>(null);

  // Monitor network connectivity
  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener(state => {
      setIsOnline(state.isConnected ?? false);
    });

    return () => unsubscribe();
  }, []);

  // Load pending changes from storage
  useEffect(() => {
    const loadPendingChanges = async () => {
      try {
        const stored = await AsyncStorage.getItem('pendingChanges');
        if (stored) {
          setPendingChanges(JSON.parse(stored));
        }

        const syncTime = await AsyncStorage.getItem('lastSyncTime');
        if (syncTime) {
          setLastSyncTime(new Date(syncTime));
        }
      } catch (error) {
        console.error('Error loading pending changes:', error);
      }
    };

    loadPendingChanges();
  }, []);

  // Auto-sync when coming online
  useEffect(() => {
    if (isOnline && isAuthenticated && pendingChanges.length > 0) {
      syncNow();
    }
  }, [isOnline, isAuthenticated]);

  const addPendingChange = useCallback(async (change: Omit<PendingChange, 'id' | 'timestamp'>) => {
    const newChange: PendingChange = {
      ...change,
      id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      timestamp: Date.now(),
    };

    const updated = [...pendingChanges, newChange];
    setPendingChanges(updated);
    await AsyncStorage.setItem('pendingChanges', JSON.stringify(updated));

    // Try to sync immediately if online
    if (isOnline && isAuthenticated) {
      syncNow();
    }
  }, [pendingChanges, isOnline, isAuthenticated]);

  const syncNow = useCallback(async () => {
    if (!isOnline || !isAuthenticated || isSyncing || pendingChanges.length === 0) {
      return;
    }

    setIsSyncing(true);

    try {
      const sortedChanges = [...pendingChanges].sort((a, b) => a.timestamp - b.timestamp);
      const successfulIds: string[] = [];

      for (const change of sortedChanges) {
        try {
          const endpoint = getEndpointForEntityType(change.entityType);
          
          switch (change.type) {
            case 'create':
              await api.post(endpoint, change.data);
              break;
            case 'update':
              await api.put(`${endpoint}/${change.entityId}`, change.data);
              break;
            case 'delete':
              await api.delete(`${endpoint}/${change.entityId}`);
              break;
          }
          
          successfulIds.push(change.id);
        } catch (error: any) {
          console.error(`Failed to sync change ${change.id}:`, error);
          // If it's a 4xx error (client error), mark as processed anyway
          if (error.response?.status >= 400 && error.response?.status < 500) {
            successfulIds.push(change.id);
          }
        }
      }

      // Remove successful changes
      const remaining = pendingChanges.filter(c => !successfulIds.includes(c.id));
      setPendingChanges(remaining);
      await AsyncStorage.setItem('pendingChanges', JSON.stringify(remaining));

      // Update last sync time
      const now = new Date();
      setLastSyncTime(now);
      await AsyncStorage.setItem('lastSyncTime', now.toISOString());
    } catch (error) {
      console.error('Sync failed:', error);
    } finally {
      setIsSyncing(false);
    }
  }, [api, isAuthenticated, isOnline, isSyncing, pendingChanges]);

  const clearPendingChanges = useCallback(async () => {
    setPendingChanges([]);
    await AsyncStorage.removeItem('pendingChanges');
  }, []);

  const value: SyncContextType = {
    isOnline,
    isSyncing,
    pendingChanges,
    lastSyncTime,
    syncNow,
    addPendingChange,
    clearPendingChanges,
  };

  return <SyncContext.Provider value={value}>{children}</SyncContext.Provider>;
};

// Helper function to map entity types to API endpoints
function getEndpointForEntityType(entityType: string): string {
  const endpoints: Record<string, string> = {
    vehicle: '/vehicles',
    fuelRecord: '/fuel-records',
    serviceRecord: '/service-records',
    motRecord: '/mot-records',
    part: '/parts',
    consumable: '/consumables',
    attachment: '/attachments',
    todo: '/todos',
    roadTax: '/road-tax',
    insurance: '/insurance/policies',
  };
  
  return endpoints[entityType] || `/${entityType}s`;
}

export const useSync = (): SyncContextType => {
  const context = useContext(SyncContext);
  if (context === undefined) {
    throw new Error('useSync must be used within a SyncProvider');
  }
  return context;
};

export default SyncContext;
