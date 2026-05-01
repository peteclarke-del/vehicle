import React, { createContext, useContext, useState, useCallback, useEffect, useRef, useMemo } from 'react';
import { useAuth } from './AuthContext';
import logger from '../utils/logger';

const VehiclesContext = createContext();

export const useVehicles = () => {
  const context = useContext(VehiclesContext);
  if (!context) {
    throw new Error('useVehicles must be used within VehiclesProvider');
  }
  return context;
};

export const VehiclesProvider = ({ children }) => {
  const { api, user } = useAuth();
  const [vehicles, setVehicles] = useState([]);
  const [loading, setLoading] = useState(false);
  const [recordsVersion, setRecordsVersion] = useState(0);
  const lastFetchRef = useRef(null);
  const hasFetchedRef = useRef(false);
  // Keep a ref to the current vehicles array so fetchVehicles doesn't need
  // `vehicles` as a dep (which would recreate the function on every fetch).
  const vehiclesRef = useRef(vehicles);
  useEffect(() => { vehiclesRef.current = vehicles; }, [vehicles]);

  const fetchVehicles = useCallback(async (force = false, silent = false) => {
    // Cache for 30 seconds to avoid repeated fetches
    const now = Date.now();
    if (!force && lastFetchRef.current && (now - lastFetchRef.current) < 30000) {
      return vehiclesRef.current;
    }

    if (!silent && vehiclesRef.current.length === 0) {
      setLoading(true);
    }
    try {
      const response = await api.get('/vehicles');
      const data = Array.isArray(response.data) ? response.data : [];
      setVehicles(data);
      lastFetchRef.current = now;
      return data;
    } catch (error) {
      logger.error('Error fetching vehicles:', error);
      return [];
    } finally {
      if (!silent) {
        setLoading(false);
      }
    }
  }, [api]);

  // Auto-fetch on mount if user is logged in
  useEffect(() => {
    if (user && !hasFetchedRef.current) {
      hasFetchedRef.current = true;
      fetchVehicles();
    }
  }, [user, fetchVehicles]);

  // Invalidate cache when user changes
  useEffect(() => {
    setVehicles([]);
    lastFetchRef.current = null;
    hasFetchedRef.current = false;
  }, [user?.id]);

  // Memoize refresh function
  const refreshVehicles = useCallback((silent = false) => fetchVehicles(true, silent), [fetchVehicles]);

  // Call this after saving any record (fuel, part, service, etc.) so the
  // Dashboard re-fetches its totals without requiring a full page reload.
  const notifyRecordChange = useCallback(() => setRecordsVersion(v => v + 1), []);

  // Memoize context value to prevent re-renders
  const value = useMemo(
    () => ({
      vehicles,
      loading,
      fetchVehicles,
      refreshVehicles,
      recordsVersion,
      notifyRecordChange,
    }),
    [vehicles, loading, fetchVehicles, refreshVehicles, recordsVersion, notifyRecordChange]
  );

  return <VehiclesContext.Provider value={value}>{children}</VehiclesContext.Provider>;
};
