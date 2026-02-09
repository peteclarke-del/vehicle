import React, { createContext, useContext, useMemo } from 'react';
import { useAuth } from './AuthContext';

const PermissionsContext = createContext({
  features: {},
  vehicleAssignments: [],
  isAdmin: false,
  can: () => true,
  canAccessVehicle: () => true,
  canEditVehicle: () => true,
  canDeleteVehicle: () => true,
  canAddRecordsToVehicle: () => true,
});

export const PermissionsProvider = ({ children }) => {
  const { user } = useAuth();

  const value = useMemo(() => {
    const features = user?.features || {};
    const vehicleAssignments = user?.vehicleAssignments || [];
    const isAdmin = user?.roles?.includes('ROLE_ADMIN') || false;

    /**
     * Check if a feature is enabled for the current user.
     * Admins always return true. Unknown features default to true.
     */
    const can = (featureKey) => {
      if (isAdmin) return true;
      if (!features || Object.keys(features).length === 0) return true;
      return features[featureKey] !== false;
    };

    /**
     * Check if the user can access a specific vehicle.
     * If no assignments exist, all vehicles are accessible.
     * If assignments exist, only assigned vehicles (that the user doesn't own) are restricted.
     */
    const canAccessVehicle = (vehicleId, ownerId) => {
      if (isAdmin) return true;
      // User always has access to their own vehicles
      if (ownerId === user?.id) return true;
      // If there are assignments, check if this vehicle is assigned
      if (vehicleAssignments.length === 0) return true;
      return vehicleAssignments.some(a => a.vehicleId === vehicleId && a.canView);
    };

    const canEditVehicle = (vehicleId, ownerId) => {
      if (isAdmin) return true;
      if (ownerId === user?.id) return can('vehicles.edit');
      const assignment = vehicleAssignments.find(a => a.vehicleId === vehicleId);
      return assignment?.canEdit || false;
    };

    const canDeleteVehicle = (vehicleId, ownerId) => {
      if (isAdmin) return true;
      if (ownerId === user?.id) return can('vehicles.delete');
      const assignment = vehicleAssignments.find(a => a.vehicleId === vehicleId);
      return assignment?.canDelete || false;
    };

    const canAddRecordsToVehicle = (vehicleId, ownerId) => {
      if (isAdmin) return true;
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
  }, [user]);

  return (
    <PermissionsContext.Provider value={value}>
      {children}
    </PermissionsContext.Provider>
  );
};

export const usePermissions = () => useContext(PermissionsContext);
