import { useState, useEffect, useCallback, useRef } from 'react';
import { useUserPreferences } from '../contexts/UserPreferencesContext';

/**
 * Custom hook for managing vehicle selection with default vehicle support
 * Consolidates the common patterns for:
 * - Initial vehicle selection when vehicles load
 * - Syncing with defaultVehicleId preference
 * - Tracking manual selection to prevent auto-switching
 * 
 * @param {Array} vehicles - Array of vehicle objects
 * @param {Object} options - Configuration options
 * @param {boolean} options.includeViewAll - Whether to allow '__all__' selection (default: false)
 * @returns {Object} { selectedVehicle, setSelectedVehicle, hasManualSelection, handleVehicleChange }
 */
const useVehicleSelection = (vehicles, options = {}) => {
  const { includeViewAll = false } = options;
  const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [hasManualSelection, setHasManualSelection] = useState(false);
  const initializedRef = useRef(false);

  // Initial vehicle selection when vehicles load
  useEffect(() => {
    // Only run initialization once per mount, and only when we have vehicles
    if (vehicles.length > 0 && !initializedRef.current) {
      initializedRef.current = true;
      if (defaultVehicleId && vehicles.find((v) => String(v.id) === String(defaultVehicleId))) {
        setSelectedVehicle(defaultVehicleId);
      } else if (includeViewAll) {
        setSelectedVehicle('__all__');
      } else {
        setSelectedVehicle(vehicles[0].id);
      }
    }
  }, [vehicles, defaultVehicleId, includeViewAll]);

  // Sync with defaultVehicleId changes (only if user hasn't manually selected)
  useEffect(() => {
    if (!defaultVehicleId) return;
    if (hasManualSelection) return;
    if (!vehicles || vehicles.length === 0) return;
    if (!initializedRef.current) return; // Don't sync before initialization
    
    const found = vehicles.find((v) => String(v.id) === String(defaultVehicleId));
    if (found && String(selectedVehicle) !== String(defaultVehicleId)) {
      setSelectedVehicle(defaultVehicleId);
    }
  }, [defaultVehicleId, vehicles, hasManualSelection, selectedVehicle]);

  // Re-select when the vehicles array changes and current selection is no longer present
  // (e.g., when a status filter is applied after initialization)
  useEffect(() => {
    if (!initializedRef.current) return;
    if (!selectedVehicle || selectedVehicle === '__all__') return;
    if (!vehicles || vehicles.length === 0) return;
    const found = vehicles.find((v) => String(v.id) === String(selectedVehicle));
    if (!found) {
      const fallback = includeViewAll ? '__all__' : vehicles[0].id;
      setSelectedVehicle(fallback);
    }
  }, [vehicles, selectedVehicle, includeViewAll]);

  // Handler for manual vehicle changes - sets the flag and updates default
  const handleVehicleChange = useCallback((vehicleId) => {
    setHasManualSelection(true);
    setSelectedVehicle(vehicleId);
    // Only set as default if it's an actual vehicle (not '__all__')
    if (vehicleId && vehicleId !== '__all__') {
      setDefaultVehicle(vehicleId);
    }
  }, [setDefaultVehicle]);

  return {
    selectedVehicle,
    setSelectedVehicle,
    hasManualSelection,
    handleVehicleChange,
  };
};

export default useVehicleSelection;
