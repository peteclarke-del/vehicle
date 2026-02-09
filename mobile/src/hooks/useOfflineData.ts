/**
 * useOfflineData – a hook that wraps API calls with offline caching.
 *
 * Strategy:
 * - GET requests: serve cached data immediately, then try the network.
 *   If the network succeeds, update cache + state. If it fails (offline), keep cached data.
 * - POST/PUT/DELETE: if online, execute directly. If offline, queue in SyncContext
 *   and return optimistic result so the UI stays responsive.
 *
 * Every list screen can use:
 *   const { data, loading, refresh } = useOfflineData<Part[]>('/parts', params);
 */

import {useState, useEffect, useCallback, useRef} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';

const CACHE_PREFIX = 'offline_cache_';
const CACHE_TTL = 24 * 60 * 60 * 1000; // 24 hours

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  url: string;
}

/**
 * Build a deterministic cache key from endpoint + params
 */
function buildCacheKey(endpoint: string, params?: Record<string, any>): string {
  const paramStr = params ? JSON.stringify(params, Object.keys(params).sort()) : '';
  return `${CACHE_PREFIX}${endpoint}${paramStr}`;
}

/**
 * Hook for fetching data with offline-first caching.
 * Returns cached data immediately, then refreshes from network when possible.
 */
export function useOfflineData<T = any>(
  endpoint: string,
  params?: Record<string, any>,
  options?: {enabled?: boolean},
) {
  const {api} = useAuth();
  const {isOnline} = useSync();
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isFromCache, setIsFromCache] = useState(false);
  const mountedRef = useRef(true);
  const enabled = options?.enabled !== false;

  const cacheKey = buildCacheKey(endpoint, params);

  // Load cached data
  const loadFromCache = useCallback(async (): Promise<T | null> => {
    try {
      const stored = await AsyncStorage.getItem(cacheKey);
      if (stored) {
        const entry: CacheEntry<T> = JSON.parse(stored);
        // Check TTL
        if (Date.now() - entry.timestamp < CACHE_TTL) {
          return entry.data;
        }
      }
    } catch (e) {
      // Cache read failure is not critical
    }
    return null;
  }, [cacheKey]);

  // Save to cache
  const saveToCache = useCallback(async (newData: T) => {
    try {
      const entry: CacheEntry<T> = {
        data: newData,
        timestamp: Date.now(),
        url: endpoint,
      };
      await AsyncStorage.setItem(cacheKey, JSON.stringify(entry));
    } catch (e) {
      // Cache write failure is not critical
    }
  }, [cacheKey, endpoint]);

  // Fetch from network
  const fetchFromNetwork = useCallback(async (): Promise<T | null> => {
    try {
      const response = await api.get(endpoint, {params});
      return response.data;
    } catch (e: any) {
      // Network error — we're offline or server is down
      console.warn(`Network fetch failed for ${endpoint}:`, e.message);
      return null;
    }
  }, [api, endpoint, params]);

  // Main load function: cache-first, then network
  const load = useCallback(async () => {
    if (!enabled) return;

    setLoading(true);
    setError(null);

    // Step 1: Try cache first for instant display
    const cached = await loadFromCache();
    if (cached !== null && mountedRef.current) {
      setData(cached);
      setIsFromCache(true);
      setLoading(false);
    }

    // Step 2: Try network for fresh data
    const fresh = await fetchFromNetwork();
    if (!mountedRef.current) return;

    if (fresh !== null) {
      setData(fresh);
      setIsFromCache(false);
      setError(null);
      await saveToCache(fresh);
    } else if (cached === null) {
      // No cache and no network — show error
      setError('No connection and no cached data available');
    }
    // else: we already showed cached data, leave it

    setLoading(false);
  }, [enabled, loadFromCache, fetchFromNetwork, saveToCache]);

  // Refresh — forces a network fetch
  const refresh = useCallback(async () => {
    setError(null);
    const fresh = await fetchFromNetwork();
    if (!mountedRef.current) return;

    if (fresh !== null) {
      setData(fresh);
      setIsFromCache(false);
      await saveToCache(fresh);
    }
  }, [fetchFromNetwork, saveToCache]);

  useEffect(() => {
    mountedRef.current = true;
    load();
    return () => {
      mountedRef.current = false;
    };
  }, [load]);

  // When we come back online, auto-refresh
  useEffect(() => {
    if (isOnline && isFromCache && enabled) {
      refresh();
    }
  }, [isOnline]);

  return {data, loading, error, isFromCache, refresh, isOnline};
}

/**
 * Hook to perform offline-aware mutations (create/update/delete).
 * If online, executes immediately. If offline, queues in SyncContext.
 */
export function useOfflineMutation() {
  const {api} = useAuth();
  const {isOnline, addPendingChange} = useSync();

  const mutate = useCallback(async (
    method: 'create' | 'update' | 'delete',
    entityType: string,
    endpoint: string,
    data?: any,
    entityId?: number,
  ): Promise<{success: boolean; offline: boolean; data?: any}> => {
    if (isOnline) {
      try {
        let response;
        switch (method) {
          case 'create':
            response = await api.post(endpoint, data);
            break;
          case 'update':
            response = await api.put(`${endpoint}/${entityId}`, data);
            break;
          case 'delete':
            response = await api.delete(`${endpoint}/${entityId}`);
            break;
        }
        return {success: true, offline: false, data: response?.data};
      } catch (error: any) {
        // If it's a network error, queue it
        if (!error.response) {
          await addPendingChange({type: method, entityType, entityId, data});
          return {success: true, offline: true};
        }
        throw error;
      }
    } else {
      // Offline — queue the change
      await addPendingChange({type: method, entityType, entityId, data});
      return {success: true, offline: true};
    }
  }, [api, isOnline, addPendingChange]);

  return {mutate, isOnline};
}

/**
 * Utility to clear all offline caches (e.g., on logout)
 */
export async function clearOfflineCache(): Promise<void> {
  try {
    const keys = await AsyncStorage.getAllKeys();
    const cacheKeys = keys.filter(k => k.startsWith(CACHE_PREFIX));
    if (cacheKeys.length > 0) {
      await AsyncStorage.multiRemove(cacheKeys);
    }
  } catch (e) {
    console.error('Error clearing offline cache:', e);
  }
}
