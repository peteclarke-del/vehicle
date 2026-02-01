import React, { useEffect, useState, useCallback } from 'react';
import logger from '../utils/logger';
import SafeStorage from '../utils/SafeStorage';
import { Box, Button, Typography, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, IconButton, Tooltip, TableSortLabel } from '@mui/material';
import logger from '../utils/logger';
import { Add, Edit, Delete } from '@mui/icons-material';
import logger from '../utils/logger';
import { useAuth } from '../contexts/AuthContext';
import logger from '../utils/logger';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import logger from '../utils/logger';
import { useTranslation } from 'react-i18next';
import logger from '../utils/logger';
import formatCurrency from '../utils/formatCurrency';
import logger from '../utils/logger';
import { fetchArrayData } from '../hooks/useApiData';
import logger from '../utils/logger';
import { useDistance } from '../hooks/useDistance';
import logger from '../utils/logger';
import useTablePagination from '../hooks/useTablePagination';
import logger from '../utils/logger';
import FuelRecordDialog from '../components/FuelRecordDialog';
import logger from '../utils/logger';
import TablePaginationBar from '../components/TablePaginationBar';
import logger from '../utils/logger';
import VehicleSelector from '../components/VehicleSelector';
import logger from '../utils/logger';
import ViewAttachmentIconButton from '../components/ViewAttachmentIconButton';
import logger from '../utils/logger';
import KnightRiderLoader from '../components/KnightRiderLoader';
import logger from '../utils/logger';

const FuelRecords = () => {
  const [records, setRecords] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [loading, setLoading] = useState(true);
  const [loadingRecords, setLoadingRecords] = useState(false);
  const recordsAbortRef = React.useRef(null);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedRecord, setSelectedRecord] = useState(null);
  const [orderBy, setOrderBy] = useState(() => SafeStorage.get('fuelRecordsSortBy', 'date'));
  const [order, setOrder] = useState(() => SafeStorage.get('fuelRecordsSortOrder', 'desc'));
  const { api } = useAuth();
  const { t, i18n } = useTranslation();
  const registrationLabelText = t('common.registrationNumber');
  const regWords = (registrationLabelText || '').split(/\s+/).filter(Boolean);
  const regLast = regWords.length > 0 ? regWords[regWords.length - 1] : '';
  const regFirst = regWords.length > 1 ? regWords.slice(0, regWords.length - 1).join(' ') : (regWords[0] || 'Registration');
  const { convert, format, getLabel } = useDistance();
  const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();
  const [hasManualSelection, setHasManualSelection] = useState(false);

  // Send client-side logs to backend if endpoint exists, otherwise logger.warn/error
  const sendClientLog = useCallback(async (level, message, context = {}) => {
    const payload = { level, message, context, ts: new Date().toISOString() };
    try {
      // attempt to post to /client-logs; ignore failures
      await api.post('/client-logs', payload);
    } catch (err) {
      if (level === 'error') logger.error('[client-log]', message, context, err);
      else logger.warn('[client-log]', message, context, err);
    }
  }, [api]);

  const loadVehicles = useCallback(async () => {
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
  }, [api, defaultVehicleId, selectedVehicle]);

  const loadRecords = useCallback(async () => {
    if (recordsAbortRef.current) {
      try { recordsAbortRef.current.abort(); } catch (e) {}
      recordsAbortRef.current = null;
    }
    const controller = new AbortController();
    recordsAbortRef.current = controller;
    setLoadingRecords(true);
    let timeoutId;
    try {
      timeoutId = setTimeout(() => {
        try { controller.abort(); } catch (e) {}
        sendClientLog('error', 'fuel_records_request_timeout', { vehicleId: selectedVehicle });
      }, 15000);

      const url = (!selectedVehicle || selectedVehicle === '__all__') ? '/fuel-records' : `/fuel-records?vehicleId=${selectedVehicle}`;
      const response = await api.get(url, { signal: controller.signal });
      setRecords(response.data);
    } catch (error) {
      if (error.name !== 'CanceledError' && error.name !== 'AbortError') {
        logger.error('Error loading fuel records:', error);
        sendClientLog('error', 'fuel_records_error', { vehicleId: selectedVehicle, error: String(error) });
      }
      setRecords([]);
    } finally {
      clearTimeout(timeoutId);
      recordsAbortRef.current = null;
      setLoadingRecords(false);
    }
  }, [api, selectedVehicle, sendClientLog]);

  useEffect(() => {
    loadVehicles();
  }, [loadVehicles]);

  useEffect(() => {
    if (selectedVehicle) {
      loadRecords();
    }
  }, [selectedVehicle, loadRecords]);

  // react to changes in defaultVehicleId while on the page
  useEffect(() => {
    if (!defaultVehicleId) return;
    if (hasManualSelection) return;
    if (!vehicles || vehicles.length === 0) return;
    const found = vehicles.find((v) => String(v.id) === String(defaultVehicleId));
    if (found && String(selectedVehicle) !== String(defaultVehicleId)) {
      setSelectedVehicle(defaultVehicleId);
    }
  }, [defaultVehicleId, vehicles, hasManualSelection, selectedVehicle]);
  
  const handleAdd = () => {
    setSelectedRecord(null);
    setDialogOpen(true);
  };

  const handleEdit = (record) => {
    setSelectedRecord(record);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('common.confirmDelete'))) {
      try {
        await api.delete(`/fuel-records/${id}`);
        loadRecords();
      } catch (error) {
        logger.error('Error deleting fuel record:', error);
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
    SafeStorage.set('fuelRecordsSortBy', property);
    SafeStorage.set('fuelRecordsSortOrder', newOrder);
  };

  const sortedRecords = React.useMemo(() => {
    const comparator = (a, b) => {
      if (orderBy === 'registration') {
        const aReg = vehicles.find(v => String(v.id) === String(a.vehicleId))?.registrationNumber || '';
        const bReg = vehicles.find(v => String(v.id) === String(b.vehicleId))?.registrationNumber || '';
        if (aReg === bReg) return 0;
        return order === 'asc' ? (aReg > bReg ? 1 : -1) : (aReg < bReg ? 1 : -1);
      }
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

  const { page, rowsPerPage, paginatedRows: paginatedRecords, handleChangePage, handleChangeRowsPerPage } = useTablePagination(sortedRecords);

  // For UX: show the Date column's sort arrow so that newest-first (internal 'desc') appears as an up arrow.
  const dateDisplayDirection = orderBy === 'date' ? (order === 'desc' ? 'asc' : 'desc') : order;

  const headingText = (records && records.length > 0) ? t('fuel.titleWithCount', { count: records.length }) : t('fuel.title');

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <KnightRiderLoader size={32} />
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{headingText}</Typography>
        <Box display="flex" gap={2}>
          <VehicleSelector
            vehicles={vehicles}
            value={selectedVehicle}
            onChange={(v) => { setHasManualSelection(true); setSelectedVehicle(v); setDefaultVehicle(v); }}
            includeViewAll={true}
            minWidth={360}
          />
          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={handleAdd}
            disabled={!selectedVehicle || selectedVehicle === '__all__'}
          >
            {t('fuel.addRecord')}
          </Button>
        </Box>
      </Box>

      {loadingRecords ? (
        <Box display="flex" justifyContent="center" alignItems="center" minHeight="40vh">
          <KnightRiderLoader size={28} />
        </Box>
      ) : (
        <>
          <TablePaginationBar
            count={sortedRecords.length}
            page={page}
            rowsPerPage={rowsPerPage}
            onPageChange={handleChangePage}
            onRowsPerPageChange={handleChangeRowsPerPage}
          />
          <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
            <Table stickyHeader>
            <TableHead>
            <TableRow>
                <TableCell>
                  <TableSortLabel
                    active={orderBy === 'registration'}
                    direction={orderBy === 'registration' ? order : 'asc'}
                    onClick={() => handleRequestSort('registration')}
                  >
                    <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1 }}>
                      <span>{regFirst}</span>
                      <span>{regLast}</span>
                    </div>
                  </TableSortLabel>
                </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'date'}
                  direction={dateDisplayDirection}
                  onClick={() => handleRequestSort('date')}
                >
                  {t('common.date')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'mileage'}
                  direction={orderBy === 'mileage' ? order : 'asc'}
                  onClick={() => handleRequestSort('mileage')}
                >
                  {t('common.mileage')} ({getLabel()})
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
            {paginatedRecords.map((record) => (
              <TableRow key={record.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                <TableCell>{vehicles.find(v => String(v.id) === String(record.vehicleId))?.registrationNumber || '-'}</TableCell>
                <TableCell>{record.date}</TableCell>
                <TableCell>{record.mileage ? format(convert(record.mileage)) : '-'}</TableCell>
                <TableCell>{record.litres}</TableCell>
                <TableCell>{formatCurrency(record.cost, 'GBP', i18n.language)}</TableCell>
                <TableCell>{record.fuelType || t('vehicleDetails.na')}</TableCell>
                <TableCell>{record.station || t('vehicleDetails.na')}</TableCell>
                <TableCell>
                  <ViewAttachmentIconButton record={record} />
                  <Tooltip title={t('common.edit')}>
                    <IconButton size="small" onClick={() => handleEdit(record)}>
                      <Edit fontSize="small" />
                    </IconButton>
                  </Tooltip>
                  <Tooltip title={t('common.delete')}>
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
          <TablePaginationBar
            count={sortedRecords.length}
            page={page}
            rowsPerPage={rowsPerPage}
            onPageChange={handleChangePage}
            onRowsPerPageChange={handleChangeRowsPerPage}
          />
        </>
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
