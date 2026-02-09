import React, {createContext, useContext, useMemo, ReactNode} from 'react';
import {useAuth} from './AuthContext';

interface VehicleAssignment {
  vehicleId: number;
  vehicleName: string;
  canView: boolean;
  canEdit: boolean;
  canAddRecords: boolean;
  canDelete: boolean;
}

interface PermissionsContextType {
  features: Record<string, boolean>;
  vehicleAssignments: VehicleAssignment[];
  isAdmin: boolean;
  can: (featureKey: string) => boolean;
  canAccessVehicle: (vehicleId: number, ownerId?: number) => boolean;
  canEditVehicle: (vehicleId: number, ownerId?: number) => boolean;
  canDeleteVehicle: (vehicleId: number, ownerId?: number) => boolean;
  canAddRecordsToVehicle: (vehicleId: number, ownerId?: number) => boolean;
}

const PermissionsContext = createContext<PermissionsContextType>({
  features: {},
  vehicleAssignments: [],
  isAdmin: false,
  can: () => true,
  canAccessVehicle: () => true,
  canEditVehicle: () => true,
  canDeleteVehicle: () => true,
  canAddRecordsToVehicle: () => true,
});

interface PermissionsProviderProps {
  children: ReactNode;
}

export const PermissionsProvider: React.FC<PermissionsProviderProps> = ({children}) => {
  const {user, isStandalone} = useAuth();

  const value = useMemo<PermissionsContextType>(() => {
    const features: Record<string, boolean> = (user as any)?.features || {};
    const vehicleAssignments: VehicleAssignment[] = (user as any)?.vehicleAssignments || [];
    const isAdmin = user?.roles?.includes('ROLE_ADMIN') || false;

    const can = (featureKey: string): boolean => {
      // In standalone mode, all features enabled
      if (isStandalone) return true;
      if (isAdmin) return true;
      if (!features || Object.keys(features).length === 0) return true;
      return features[featureKey] !== false;
    };

    const canAccessVehicle = (vehicleId: number, ownerId?: number): boolean => {
      if (isStandalone || isAdmin) return true;
      if (ownerId === user?.id) return true;
      if (vehicleAssignments.length === 0) return true;
      return vehicleAssignments.some(a => a.vehicleId === vehicleId && a.canView);
    };

    const canEditVehicle = (vehicleId: number, ownerId?: number): boolean => {
      if (isStandalone || isAdmin) return true;
      if (ownerId === user?.id) return can('vehicles.edit');
      const assignment = vehicleAssignments.find(a => a.vehicleId === vehicleId);
      return assignment?.canEdit || false;
    };

    const canDeleteVehicle = (vehicleId: number, ownerId?: number): boolean => {
      if (isStandalone || isAdmin) return true;
      if (ownerId === user?.id) return can('vehicles.delete');
      const assignment = vehicleAssignments.find(a => a.vehicleId === vehicleId);
      return assignment?.canDelete || false;
    };

    const canAddRecordsToVehicle = (vehicleId: number, ownerId?: number): boolean => {
      if (isStandalone || isAdmin) return true;
      if (ownerId === user?.id) return true;
      const assignment = vehicleAssignments.find(a => a.vehicleId === vehicleId);
      return assignment?.canAddRecords || false;
    };

    return {
      features,
      vehicleAssignments,
      isAdmin,
      can,
      canAccessVehicle,
      canEditVehicle,
      canDeleteVehicle,
      canAddRecordsToVehicle,
    };
  }, [user, isStandalone]);

  return (
    <PermissionsContext.Provider value={value}>
      {children}
    </PermissionsContext.Provider>
  );
};

export const usePermissions = (): PermissionsContextType => {
  const context = useContext(PermissionsContext);
  return context;
};
