import React, { useState, useMemo, useId } from 'react';
import { Box, Button, Typography, CircularProgress } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';

const VehicleSelector = React.memo(({
  vehicles = [],
  value,
  onChange = () => {},
  showAddButton = false,
  onAddVehicle = () => {},
  minWidth = 300,
  includeViewAll = false,
  label = null,
  disabled = false,
  formatVehicle = null,
  enableSearch = false,
  showCount = false,
  loading = false,
  error = null,
  groupByMake = false,
  showImages = false,
}) => {
  const { t } = useTranslation();
  const [searchTerm, setSearchTerm] = useState('');
  const uid = useId();
  const selectId = `vehicle-selector-${uid}`;

  const currentValue = value != null ? String(value) : '';

  const defaultFormat = (vehicle) => {
    const makeName = vehicle.make?.name || vehicle.make || '';
    const modelName = vehicle.model?.name || vehicle.model || '';
    return `${vehicle.registration || vehicle.registrationNumber || ''} - ${vehicle.year} ${makeName} ${modelName}`.trim();
  };

  const formatFn = formatVehicle || defaultFormat;

  const filteredVehicles = useMemo(() => {
    if (!searchTerm) return vehicles;
    const lower = searchTerm.toLowerCase();
    return vehicles.filter(v => {
      const makeName = v.make?.name || v.make || '';
      const modelName = v.model?.name || v.model || '';
      return (
        (v.registration || '').toLowerCase().includes(lower) ||
        (v.registrationNumber || '').toLowerCase().includes(lower) ||
        makeName.toLowerCase().includes(lower) ||
        modelName.toLowerCase().includes(lower) ||
        String(v.year || '').includes(lower)
      );
    });
  }, [vehicles, searchTerm]);

  const handleChange = React.useCallback((e) => {
    const rawValue = e.target.value;
    if (rawValue === '__all__') {
      onChange('__all__');
      return;
    }
    const id = parseInt(rawValue, 10);
    onChange(isNaN(id) ? null : id);
  }, [onChange]);

  // Group by make - must be called before any early returns (Rules of Hooks)
  const makeGroups = useMemo(() => {
    if (!groupByMake) return null;
    return filteredVehicles.reduce((acc, v) => {
      const makeName = v.make?.name || v.make || 'Unknown';
      if (!acc[makeName]) acc[makeName] = [];
      acc[makeName].push(v);
      return acc;
    }, {});
  }, [filteredVehicles, groupByMake]);

  if (loading) {
    return (
      <Box display="flex" alignItems="center" gap={1}>
        <CircularProgress size={16} />
        <Typography>{t('common.loading', 'Loading...')}</Typography>
      </Box>
    );
  }

  if (error) {
    return <Typography color="error">{error}</Typography>;
  }

  if (vehicles.length === 0) {
    return <Typography>{t('common.noVehicles', 'No vehicles')}</Typography>;
  }

  const renderOption = (v) => (
    <option key={v.id} value={v.id}>
      {formatFn(v)}
    </option>
  );

  return (
    <Box display="flex" gap={2} alignItems="center">
      <Box>
        {showCount && (
          <Typography variant="caption" display="block">
            {vehicles.length} {t('vehicles.title', 'vehicles')}
          </Typography>
        )}
        {enableSearch && (
          <Box mb={1}>
            <input
              placeholder={t('common.search', 'Search...')}
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              style={{ padding: '4px 8px', border: '1px solid #ccc', borderRadius: 4 }}
            />
          </Box>
        )}
        {label && <label htmlFor={selectId}>{label}</label>}
        <select
          id={selectId}
          aria-label={label || t('common.selectVehicle', 'Select a vehicle')}
          value={currentValue}
          onChange={handleChange}
          disabled={disabled}
          style={{ minWidth, padding: '8px', border: '1px solid #ccc', borderRadius: 4 }}
        >
          <option value="" disabled>
            {t('common.selectVehicle', 'Select a vehicle')}
          </option>
          {includeViewAll && (
            <option value="__all__">{t('common.viewAll', 'View All')}</option>
          )}
          {groupByMake && makeGroups
            ? Object.entries(makeGroups).map(([makeName, makeVehicles]) => (
                <optgroup key={`group-${makeName}`} label={makeName}>
                  {makeVehicles.map(renderOption)}
                </optgroup>
              ))
            : filteredVehicles.map(renderOption)}
        </select>
        {showImages && (
          <Box mt={1} display="flex" gap={1}>
            {filteredVehicles.filter(v => v.imageUrl).map(v => (
              <img key={v.id} src={v.imageUrl} alt={formatFn(v)} style={{ width: 32, height: 32 }} />
            ))}
          </Box>
        )}
      </Box>
      {showAddButton && (
        <Button variant="outlined" startIcon={<AddIcon />} onClick={onAddVehicle}>
          {t('vehicles.addVehicle', 'Add vehicle')}
        </Button>
      )}
    </Box>
  );
});

export default VehicleSelector;


