/**
 * Single source of truth for the vehicle list on the device.
 *
 * Every screen that shows or picks vehicles reads this list, so a change made
 * anywhere (this app, the web app, an import) shows up everywhere once it is
 * refreshed. It is refreshed:
 *   - when the user logs in / the server changes
 *   - when the app returns to the foreground
 *   - when the device comes back online, and after an offline sync completes
 *   - whenever a screen calls refreshVehicles() (screens do so on focus)
 *
 * When the server can't be reached the last known list (also kept in
 * AsyncStorage) is used, and offline edits are applied to it straight away
 * via updateVehicleLocally() so the app reflects them before they sync.
 */
import React, {createContext, useCallback, useContext, useEffect, useRef, useState} from 'react';
import {AppState} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {useAuth} from './AuthContext';
import {useSync} from './SyncContext';

export const VEHICLES_CACHE_KEY = 'cache_vehicles';

export interface VehiclesContextType {
  vehicles: any[];
  loading: boolean;
  /** Fetch the latest list from the server. Never throws: falls back to the last known list. */
  refreshVehicles: () => Promise<any[]>;
  /** Apply a change locally (e.g. an edit queued while offline). */
  updateVehicleLocally: (id: number | string, changes: Record<string, any>) => void;
}

const VehiclesContext = createContext<VehiclesContextType | null>(null);

export const VehiclesProvider: React.FC<{children: React.ReactNode}> = ({children}) => {
  const {api, isAuthenticated} = useAuth();
  const {isOnline, lastSyncResult} = useSync();
  const [vehicles, setVehicles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const vehiclesRef = useRef<any[]>([]);
  const inFlight = useRef<Promise<any[]> | null>(null);
  // Read the client through a ref so refreshVehicles stays stable across
  // renders (a changing callback would re-trigger the effects below).
  const apiRef = useRef(api);
  apiRef.current = api;

  const store = useCallback((list: any[]) => {
    vehiclesRef.current = list;
    setVehicles(list);
    AsyncStorage.setItem(VEHICLES_CACHE_KEY, JSON.stringify(list)).catch(() => {});
  }, []);

  const refreshVehicles = useCallback((): Promise<any[]> => {
    // Many screens refresh at once (e.g. on login); share one request.
    if (inFlight.current) {
      return inFlight.current;
    }
    const request = (async () => {
      try {
        const response = await apiRef.current.get('/vehicles');
        const list = Array.isArray(response.data) ? response.data : [];
        store(list);
        return list;
      } catch (error) {
        console.warn('[Vehicles] Refresh failed, using last known list');
        return vehiclesRef.current;
      } finally {
        setLoading(false);
        inFlight.current = null;
      }
    })();
    inFlight.current = request;
    return request;
  }, [store]);

  const updateVehicleLocally = useCallback((id: number | string, changes: Record<string, any>) => {
    // Route params may carry the id as a string.
    store(vehiclesRef.current.map(v => (String(v.id) === String(id) ? {...v, ...changes} : v)));
  }, [store]);

  // On login show the last known list straight away (it is replaced by fresh
  // server data from the effect below, or kept if the server is unreachable).
  useEffect(() => {
    if (!isAuthenticated) {
      vehiclesRef.current = [];
      setVehicles([]);
      return;
    }
    let cancelled = false;
    AsyncStorage.getItem(VEHICLES_CACHE_KEY)
      .then(raw => {
        if (!cancelled && raw && vehiclesRef.current.length === 0) {
          const cached = JSON.parse(raw);
          if (Array.isArray(cached)) {
            vehiclesRef.current = cached;
            setVehicles(cached);
          }
        }
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [isAuthenticated]);

  // Back in the foreground: pick up changes made elsewhere (e.g. the web app).
  useEffect(() => {
    const sub = AppState.addEventListener('change', state => {
      if (state === 'active' && isAuthenticated) {
        refreshVehicles();
      }
    });
    return () => sub.remove();
  }, [isAuthenticated, refreshVehicles]);

  // Logged in, server changed, reconnected, or offline changes just synced:
  // reload from the server. When offline the last known list is kept.
  useEffect(() => {
    if (isAuthenticated && isOnline) {
      refreshVehicles();
    } else if (isAuthenticated) {
      setLoading(false);
    }
  }, [isOnline, lastSyncResult, isAuthenticated, api, refreshVehicles]);

  return (
    <VehiclesContext.Provider value={{vehicles, loading, refreshVehicles, updateVehicleLocally}}>
      {children}
    </VehiclesContext.Provider>
  );
};

export const useVehicles = (): VehiclesContextType => {
  const context = useContext(VehiclesContext);
  if (!context) {
    throw new Error('useVehicles must be used within VehiclesProvider');
  }
  return context;
};
