import React, {createContext, useContext, useState, useEffect, useCallback, useRef, ReactNode} from 'react';
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
  retryCount: number;
}

interface SyncResult {
  synced: number;
  failed: number;
  remaining: number;
  timestamp: Date;
}

interface SyncContextType {
  isOnline: boolean;
  isSyncing: boolean;
  pendingChanges: PendingChange[];
  lastSyncTime: Date | null;
  lastSyncResult: SyncResult | null;
  syncNow: () => Promise<void>;
  addPendingChange: (change: Omit<PendingChange, 'id' | 'timestamp' | 'retryCount'>) => Promise<void>;
  clearPendingChanges: () => Promise<void>;
}

const SyncContext = createContext<SyncContextType | undefined>(undefined);

const STORAGE_KEY = 'pendingChanges';
const SYNC_TIME_KEY = 'lastSyncTime';
const MAX_RETRIES = 5;

interface SyncProviderProps {
  children: ReactNode;
}

export const SyncProvider: React.FC<SyncProviderProps> = ({children}) => {
  const {api, isAuthenticated} = useAuth();
  const [isOnline, setIsOnline] = useState(true);
  const [isSyncing, setIsSyncing] = useState(false);
  const [pendingChanges, setPendingChanges] = useState<PendingChange[]>([]);
  const [lastSyncTime, setLastSyncTime] = useState<Date | null>(null);
  const [lastSyncResult, setLastSyncResult] = useState<SyncResult | null>(null);

  // Use refs to avoid stale closures in callbacks
  const pendingRef = useRef<PendingChange[]>([]);
  const syncingRef = useRef(false);
  const wasOffline = useRef(false);
  const onlineRef = useRef(true);
  const authRef = useRef(false);

  // Keep refs in sync with state
  useEffect(() => { pendingRef.current = pendingChanges; }, [pendingChanges]);
  useEffect(() => { onlineRef.current = isOnline; }, [isOnline]);
  useEffect(() => { authRef.current = isAuthenticated; }, [isAuthenticated]);

  const persistChanges = useCallback(async (changes: PendingChange[]) => {
    await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(changes));
  }, []);

  // The core sync function (not wrapped in useCallback to avoid dependency issues)
  const doSync = async () => {
    const changes = pendingRef.current;
    if (!onlineRef.current || !authRef.current || syncingRef.current || changes.length === 0) {
      return;
    }

    syncingRef.current = true;
    setIsSyncing(true);

    let syncedCount = 0;
    let failedCount = 0;

    try {
      const sortedChanges = [...changes].sort((a, b) => a.timestamp - b.timestamp);
      const successfulIds: string[] = [];
      const updatedRetries: Record<string, number> = {};

      for (const change of sortedChanges) {
        try {
          const endpoint = getEndpointForEntityType(change.entityType);

          switch (change.type) {
            case 'create':
              await api.post(endpoint, change.data);
              break;
            case 'update':
              if (change.entityId) {
                await api.put(`${endpoint}/${change.entityId}`, change.data);
              }
              break;
            case 'delete':
              if (change.entityId) {
                await api.delete(`${endpoint}/${change.entityId}`);
              }
              break;
          }

          successfulIds.push(change.id);
          syncedCount++;
        } catch (error: any) {
          console.error(`[Sync] Failed to sync change ${change.id}:`, error.message);

          if (error.response?.status >= 400 && error.response?.status < 500) {
            // Client error (4xx) - discard, no point retrying
            successfulIds.push(change.id);
            failedCount++;
          } else {
            // Server/network error - retry later
            const newRetryCount = (change.retryCount || 0) + 1;
            if (newRetryCount >= MAX_RETRIES) {
              successfulIds.push(change.id);
              failedCount++;
              console.warn(`[Sync] Discarding change ${change.id} after ${MAX_RETRIES} retries`);
            } else {
              updatedRetries[change.id] = newRetryCount;
            }
          }
        }
      }

      // Update pending changes
      const remaining = changes
        .filter(c => !successfulIds.includes(c.id))
        .map(c => ({...c, retryCount: updatedRetries[c.id] ?? c.retryCount}));

      pendingRef.current = remaining;
      setPendingChanges(remaining);
      await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(remaining));

      // Update last sync time
      const now = new Date();
      setLastSyncTime(now);
      await AsyncStorage.setItem(SYNC_TIME_KEY, now.toISOString());

      setLastSyncResult({
        synced: syncedCount,
        failed: failedCount,
        remaining: remaining.length,
        timestamp: now,
      });

      console.log(`[Sync] Complete: ${syncedCount} synced, ${failedCount} failed, ${remaining.length} remaining`);
    } catch (error) {
      console.error('[Sync] Sync failed:', error);
    } finally {
      syncingRef.current = false;
      setIsSyncing(false);
    }
  };

  // Monitor network connectivity
  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener(state => {
      const online = state.isConnected ?? false;

      if (online && wasOffline.current) {
        // Just came back online - trigger auto-sync
        console.log('[Sync] Back online - auto-syncing pending changes');
        setTimeout(() => {
          if (pendingRef.current.length > 0) {
            doSync();
          }
        }, 2000);
      }

      wasOffline.current = !online;
      onlineRef.current = online;
      setIsOnline(online);
    });

    return () => unsubscribe();
  }, []);

  // Load pending changes from storage on mount
  useEffect(() => {
    const loadPendingChanges = async () => {
      try {
        const stored = await AsyncStorage.getItem(STORAGE_KEY);
        if (stored) {
          const parsed = JSON.parse(stored);
          setPendingChanges(parsed);
          pendingRef.current = parsed;
        }

        const syncTime = await AsyncStorage.getItem(SYNC_TIME_KEY);
        if (syncTime) {
          setLastSyncTime(new Date(syncTime));
        }
      } catch (error) {
        console.error('[Sync] Error loading pending changes:', error);
      }
    };

    loadPendingChanges();
  }, []);

  // Auto-sync when auth state changes and we're online with pending changes
  useEffect(() => {
    if (isOnline && isAuthenticated && pendingRef.current.length > 0 && !syncingRef.current) {
      doSync();
    }
  }, [isAuthenticated, isOnline]);

  const addPendingChange = useCallback(async (
    change: Omit<PendingChange, 'id' | 'timestamp' | 'retryCount'>,
  ) => {
    const newChange: PendingChange = {
      ...change,
      id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      timestamp: Date.now(),
      retryCount: 0,
    };

    const updated = [...pendingRef.current, newChange];
    pendingRef.current = updated;
    setPendingChanges(updated);
    await persistChanges(updated);

    console.log(`[Sync] Queued ${change.type} for ${change.entityType} (${updated.length} pending)`);

    // Try to sync immediately if online
    if (onlineRef.current && authRef.current && !syncingRef.current) {
      setTimeout(() => doSync(), 500);
    }
  }, [persistChanges]);

  const syncNow = useCallback(async () => {
    await doSync();
  }, []);

  const clearPendingChanges = useCallback(async () => {
    pendingRef.current = [];
    setPendingChanges([]);
    await AsyncStorage.removeItem(STORAGE_KEY);
  }, []);

  const value: SyncContextType = {
    isOnline,
    isSyncing,
    pendingChanges,
    lastSyncTime,
    lastSyncResult,
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
