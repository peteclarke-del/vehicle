import React from 'react';
import { FormControl, InputLabel, Select, MenuItem } from '@mui/material';
import { useTranslation } from 'react-i18next';
import VehicleSelector from './VehicleSelector';

/**
 * Combines a status filter dropdown with a vehicle selector dropdown.
 * Used in page headers wherever both filters are needed together.
 */
const FilteredVehicleSelector = ({
  statusFilter,
  onStatusFilterChange,
  statusOptions,
  vehicles,
  selectedVehicle,
  onVehicleChange,
  includeViewAll = false,
  minWidth = 360,
  id = 'vehicle',
}) => {
  const { t } = useTranslation();

  return (
    <>
      <FormControl size="small" sx={{ minWidth: 160 }}>
        <InputLabel id={`${id}-status-filter-label`}>
          {t('vehicles.filterByStatus') || 'Status'}
        </InputLabel>
        <Select
          labelId={`${id}-status-filter-label`}
          value={statusFilter}
          label={t('vehicles.filterByStatus') || 'Status'}
          onChange={onStatusFilterChange}
          size="small"
        >
          {statusOptions.map(opt => (
            <MenuItem key={opt.key} value={opt.key}>
              {t(opt.labelKey) || opt.fallback}
            </MenuItem>
          ))}
        </Select>
      </FormControl>
      <VehicleSelector
        vehicles={vehicles}
        value={selectedVehicle}
        onChange={onVehicleChange}
        includeViewAll={includeViewAll}
        minWidth={minWidth}
      />
    </>
  );
};

export default FilteredVehicleSelector;
