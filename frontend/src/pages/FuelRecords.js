import React, { useEffect, useState } from 'react';
import {
  Box,
  Button,
  Typography,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  IconButton,
  MenuItem,
  TextField,
  CircularProgress,
  FormControl,
  InputLabel,
  Select,
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import { useTranslation } from 'react-i18next';
import formatCurrency from '../utils/formatCurrency';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import FuelRecordDialog from '../components/FuelRecordDialog';

const FuelRecords = () => {
  const [records, setRecords] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [loading, setLoading] = useState(true);
  const [loadingRecords, setLoadingRecords] = useState(false);
  const recordsAbortRef = React.useRef(null);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedRecord, setSelectedRecord] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('fuelRecordsSortBy') || 'date');
  const [order, setOrder] = useState(() => localStorage.getItem('fuelRecordsSortOrder') || 'desc');
  const { api } = useAuth();
  const { t, i18n } = useTranslation();
  const { convert, format, getLabel } = useDistance();
  const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();
  const [hasManualSelection, setHasManualSelection] = useState(false);

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    if (selectedVehicle) {
      loadRecords();
    }
  }, [selectedVehicle]);

  const loadVehicles = async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    if (data.length > 0 && !selectedVehicle) {
      if (defaultVehicleId && data.find((v) => String(v.id) === String(defaultVehicleId))) {
        setSelectedVehicle(defaultVehicleId);
      } else {
        setSelectedVehicle(data[0].id);
      }
    }
    setLoading(false);
  };

  // react to changes in defaultVehicleId while on the page
  useEffect(() => {
    if (!defaultVehicleId) return;
    if (hasManualSelection) return;
    if (!vehicles || vehicles.length === 0) return;
    const found = vehicles.find((v) => String(v.id) === String(defaultVehicleId));
    if (found && String(selectedVehicle) !== String(defaultVehicleId)) {
      setSelectedVehicle(defaultVehicleId);
    }
  }, [defaultVehicleId, vehicles, hasManualSelection]);
  
  // Send client-side logs to backend if endpoint exists, otherwise console.warn/error
  const sendClientLog = async (level, message, context = {}) => {
    const payload = { level, message, context, ts: new Date().toISOString() };
    try {
      // attempt to post to /client-logs; ignore failures
      await api.post('/client-logs', payload);
    } catch (err) {
      if (level === 'error') console.error('[client-log]', message, context, err);
      else console.warn('[client-log]', message, context, err);
    }
  };

  const loadRecords = async () => {
    // Cancel any outstanding records request
    if (recordsAbortRef.current) {
      try { recordsAbortRef.current.abort(); } catch (e) {}
      recordsAbortRef.current = null;
    }
    const controller = new AbortController();
    recordsAbortRef.current = controller;
    setLoadingRecords(true);
    let timeoutId;
    try {
      // Safety: abort the request if it takes too long
      timeoutId = setTimeout(() => {
        try { controller.abort(); } catch (e) {}
        sendClientLog('error', 'fuel_records_request_timeout', { vehicleId: selectedVehicle });
      }, 15000);

      const response = await api.get(`/fuel-records?vehicleId=${selectedVehicle}`, { signal: controller.signal });
      setRecords(response.data);
    } catch (error) {
      const isCanceled = (error && (error.name === 'CanceledError' || error.name === 'AbortError' || error.code === 'ERR_CANCELED' || error.message === 'canceled'));
      if (isCanceled) {
        // request was cancelled, ignore
        sendClientLog('debug', 'fuel_records_request_cancelled', { vehicleId: selectedVehicle });
      } else {
        console.error('Error loading fuel records:', error);
        sendClientLog('error', 'Error loading fuel records', { vehicleId: selectedVehicle, error: (error && error.toString && error.toString()) || error });
      }
    } finally {
      if (timeoutId) clearTimeout(timeoutId);
      setLoadingRecords(false);
      recordsAbortRef.current = null;
    }
  };

  const handleAdd = () => {
    setSelectedRecord(null);
    setDialogOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedRecord(record);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('fuel.deleteConfirm'))) {
      try {
        await api.delete(`/fuel-records/${id}`);
        loadRecords();
      } catch (error) {
        console.error('Error deleting fuel record:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setSelectedRecord(null);
    if (reload) {
      loadRecords();
    }
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('fuelRecordsSortBy', property);
    localStorage.setItem('fuelRecordsSortOrder', newOrder);
  };

  const sortedRecords = React.useMemo(() => {
    const comparator = (a, b) => {
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      // Handle mileage conversion
      if (orderBy === 'mileage') {
        aValue = a.mileage ? parseFloat(a.mileage) : 0;
        bValue = b.mileage ? parseFloat(b.mileage) : 0;
      }
      
      // Handle cost conversion
      if (orderBy === 'cost') {
        aValue = parseFloat(a.cost) || 0;
        bValue = parseFloat(b.cost) || 0;
      }
      
      // Handle litres conversion
      if (orderBy === 'litres') {
        aValue = parseFloat(a.litres) || 0;
        bValue = parseFloat(b.litres) || 0;
      }

      // Handle null/undefined values
      if (!aValue) aValue = '';
      if (!bValue) bValue = '';

      if (bValue < aValue) {
        return order === 'asc' ? 1 : -1;
      }
      if (bValue > aValue) {
        return order === 'asc' ? -1 : 1;
      }
      return 0;
    };

    return [...records].sort(comparator);
  }, [records, order, orderBy]);

  // For UX: show the Date column's sort arrow so that newest-first (internal 'desc') appears as an up arrow.
  const dateDisplayDirection = orderBy === 'date' ? (order === 'desc' ? 'asc' : 'desc') : order;

  const headingText = (records && records.length > 0) ? `Fuel Records (${records.length})` : t('fuel.title');

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{headingText}</Typography>
        <Box display="flex" gap={2}>
          <FormControl size="small" sx={{ width: 360, maxWidth: '100%' }}>
            <InputLabel>{t('common.selectVehicle')}</InputLabel>
            <Select
              value={selectedVehicle}
              label={t('common.selectVehicle')}
              onChange={(e) => { setHasManualSelection(true); setSelectedVehicle(e.target.value); setDefaultVehicle(e.target.value); }}
              MenuProps={{ PaperProps: { sx: { minWidth: 360 } } }}
            >
              {vehicles.map((vehicle) => (
                <MenuItem key={vehicle.id} value={vehicle.id}>
                  {vehicle.name}{vehicle.registrationNumber ? ` (${vehicle.registrationNumber})` : ''}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={handleAdd}
            disabled={!selectedVehicle}
          >
            {t('fuel.addRecord')}
          </Button>
        </Box>
      </Box>

      {loadingRecords ? (
        <Box display="flex" justifyContent="center" alignItems="center" minHeight="40vh">
          <CircularProgress />
        </Box>
      ) : (
        <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
          <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'date'}
                  direction={dateDisplayDirection}
                  onClick={() => handleRequestSort('date')}
                >
                  {t('fuel.date')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'mileage'}
                  direction={orderBy === 'mileage' ? order : 'asc'}
                  onClick={() => handleRequestSort('mileage')}
                >
                  {t('fuel.mileage')} ({getLabel()})
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'litres'}
                  direction={orderBy === 'litres' ? order : 'asc'}
                  onClick={() => handleRequestSort('litres')}
                >
                  {t('fuel.litres')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'cost'}
                  direction={orderBy === 'cost' ? order : 'asc'}
                  onClick={() => handleRequestSort('cost')}
                >
                  {t('fuel.cost')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'fuelType'}
                  direction={orderBy === 'fuelType' ? order : 'asc'}
                  onClick={() => handleRequestSort('fuelType')}
                >
                  {t('fuel.fuelType')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'station'}
                  direction={orderBy === 'station' ? order : 'asc'}
                  onClick={() => handleRequestSort('station')}
                >
                  {t('fuel.station')}
                </TableSortLabel>
              </TableCell>
              <TableCell>{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedRecords.map((record) => (
              <TableRow key={record.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                <TableCell>{record.date}</TableCell>
                <TableCell>{record.mileage ? format(convert(record.mileage)) : '-'}</TableCell>
                <TableCell>{record.litres}</TableCell>
                <TableCell>{formatCurrency(record.cost, 'GBP', i18n.language)}</TableCell>
                <TableCell>{record.fuelType || 'N/A'}</TableCell>
                <TableCell>{record.station || 'N/A'}</TableCell>
                <TableCell>
                  <Tooltip title={t('edit')}>
                    <IconButton size="small" onClick={() => handleEdit(record)}>
                      <Edit fontSize="small" />
                    </IconButton>
                  </Tooltip>
                  <Tooltip title={t('delete')}>
                    <IconButton size="small" onClick={() => handleDelete(record.id)}>
                      <Delete fontSize="small" />
                    </IconButton>
                  </Tooltip>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
          </Table>
        </TableContainer>
      )}

      <FuelRecordDialog
        open={dialogOpen}
        record={selectedRecord}
        vehicleId={selectedVehicle}
        onClose={handleDialogClose}
      />
    </Box>
  );
};

export default FuelRecords;
