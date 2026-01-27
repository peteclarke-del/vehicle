import React from 'react';
import { FormControl, InputLabel, Select, MenuItem, Box, Button } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';

const VehicleSelector = ({
  vehicles = [],
  value,
  onChange = () => {},
  showAddButton = false,
  onAddVehicle = () => {},
  minWidth = 300,
  includeViewAll = true,
  label = null,
  disabled = false,
}) => {
  const { t } = useTranslation();
  const handleChange = (e) => {
    onChange(e.target.value);
  };

  return (
    <Box display="flex" gap={2} alignItems="center">
      <FormControl size="small" sx={{ minWidth }}>
        <InputLabel>{label || t('common.selectVehicle')}</InputLabel>
        <Select
          value={value}
          label={label || t('common.selectVehicle')}
          onChange={handleChange}
          disabled={disabled}
        >
          {includeViewAll && (
            <MenuItem value="__all__">{t('common.viewAll') || 'View All'}</MenuItem>
          )}
          {vehicles.map((v) => (
            <MenuItem key={v.id} value={v.id}>
              {v.name}{v.registrationNumber ? ` (${v.registrationNumber})` : ''}
            </MenuItem>
          ))}
        </Select>
      </FormControl>
      {showAddButton && (
        <Button variant="outlined" startIcon={<AddIcon />} onClick={onAddVehicle}>
          {t('vehicle.add') || 'Add vehicle'}
        </Button>
      )}
    </Box>
  );
};

export default VehicleSelector;
