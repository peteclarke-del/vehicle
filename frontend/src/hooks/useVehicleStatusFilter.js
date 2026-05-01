import { useState, useMemo } from 'react';
import SafeStorage from '../utils/SafeStorage';

export const VEHICLE_STATUS_OPTIONS = [
  { key: 'all',      labelKey: 'vehicles.filterAll',       fallback: 'All' },
  { key: 'Live',     labelKey: 'vehicle.status.live',      fallback: 'Live' },
  { key: 'Sold',     labelKey: 'vehicle.status.sold',      fallback: 'Sold' },
  { key: 'Scrapped', labelKey: 'vehicle.status.scrapped',  fallback: 'Scrapped' },
  { key: 'Exported', labelKey: 'vehicle.status.exported',  fallback: 'Exported' },
];

/**
 * Manages a vehicle status filter with persistence.
 *
 * @param {Array}  vehicles   - Full vehicle list from VehiclesContext
 * @param {string} storageKey - localStorage key to persist the selected status
 * @returns {{ statusFilter, filteredVehicles, handleStatusFilterChange, STATUS_OPTIONS }}
 */
export default function useVehicleStatusFilter(vehicles, storageKey) {
  const [statusFilter, setStatusFilter] = useState(() =>
    SafeStorage.get(storageKey, 'Live')
  );

  const filteredVehicles = useMemo(() => {
    if (statusFilter === 'all') return vehicles;
    return vehicles.filter(v => (v.status || 'Live') === statusFilter);
  }, [vehicles, statusFilter]);

  const handleStatusFilterChange = (e) => {
    const val = e.target.value;
    setStatusFilter(val);
    SafeStorage.set(storageKey, val);
  };

  return { statusFilter, filteredVehicles, handleStatusFilterChange, STATUS_OPTIONS: VEHICLE_STATUS_OPTIONS };
}
