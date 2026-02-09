import React, {createContext, useContext, useState, useCallback, useEffect} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

const STORAGE_KEY = 'global_selected_vehicle_id';

interface VehicleSelectionContextType {
  /** The globally-selected vehicle ID, shared across all screens. 'all' means no filter. */
  globalVehicleId: number | 'all';
  /** Update the global vehicle selection (persists to AsyncStorage). */
  setGlobalVehicleId: (id: number | 'all') => void;
}

const VehicleSelectionContext = createContext<VehicleSelectionContextType>({
  globalVehicleId: 'all',
  setGlobalVehicleId: () => {},
});

export const VehicleSelectionProvider: React.FC<{children: React.ReactNode}> = ({children}) => {
  const [globalVehicleId, setGlobalVehicleIdState] = useState<number | 'all'>('all');

  // Restore from storage on mount
  useEffect(() => {
    AsyncStorage.getItem(STORAGE_KEY)
      .then(val => {
        if (val && val !== 'all') {
          const parsed = parseInt(val, 10);
          if (!isNaN(parsed)) {
            setGlobalVehicleIdState(parsed);
          }
        }
      })
      .catch(() => {});
  }, []);

  const setGlobalVehicleId = useCallback((id: number | 'all') => {
    setGlobalVehicleIdState(id);
    AsyncStorage.setItem(STORAGE_KEY, String(id)).catch(() => {});
  }, []);

  return (
    <VehicleSelectionContext.Provider value={{globalVehicleId, setGlobalVehicleId}}>
      {children}
    </VehicleSelectionContext.Provider>
  );
};

export const useVehicleSelection = () => useContext(VehicleSelectionContext);
