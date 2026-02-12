import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useSearchParams } from 'react-router-dom';
import logger from '../utils/logger';
import {
  Typography,
  Button,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  IconButton,
  Box,
  Chip,
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon, Visibility as VisibilityIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import formatCurrency from '../utils/formatCurrency';
import { useVehicles } from '../contexts/VehiclesContext';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import { formatDateISO } from '../utils/formatDate';
import useTablePagination from '../hooks/useTablePagination';
import usePersistedSort from '../hooks/usePersistedSort';
import useVehicleSelection from '../hooks/useVehicleSelection';
import { useRegistrationLabel } from '../utils/splitLabel';
import MotDialog from '../components/MotDialog';
import TablePaginationBar from '../components/TablePaginationBar';
import VehicleSelector from '../components/VehicleSelector';
import ViewAttachmentIconButton from '../components/ViewAttachmentIconButton';
import { demoGuard } from '../utils/demoMode';
import KnightRiderLoader from '../components/KnightRiderLoader';

const MotRecords = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [motRecords, setMotRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingMot, setEditingMot] = useState(null);
  const { vehicles, loading: vehiclesLoading, fetchVehicles } = useVehicles();
  const { api } = useAuth();
  const { t, i18n } = useTranslation();
  const { regFirst, regLast } = useRegistrationLabel();
  const { convert, format, getLabel } = useDistance();
  const { orderBy, order, handleRequestSort } = usePersistedSort('motRecords', 'testDate', 'desc');
  const { selectedVehicle, setSelectedVehicle, hasManualSelection, handleVehicleChange } = useVehicleSelection(vehicles);
  const urlMotIdHandledRef = useRef(false);

  const loadMotRecords = useCallback(async (signal) => {
    setLoading(true);
    const url = !selectedVehicle || selectedVehicle === '__all__' ? '/mot-records' : `/mot-records?vehicleId=${selectedVehicle}`;
    const data = await fetchArrayData(api, url, signal ? { signal } : {});
    setMotRecords(data);
    setLoading(false);
    return data;
  }, [api, selectedVehicle]);

  useEffect(() => {
    fetchVehicles();
  }, [fetchVehicles]);

  useEffect(() => {
    if (!selectedVehicle) return;
    
    const abortController = new AbortController();
    loadMotRecords(abortController.signal);
    
    return () => {
      abortController.abort();
    };
  }, [selectedVehicle, loadMotRecords]);

  // Handle URL params to auto-open a specific MOT record
  useEffect(() => {
    const urlVehicleId = searchParams.get('vehicleId');
    const urlMotId = searchParams.get('motId');
    
    // Set vehicle from URL if provided
    if (urlVehicleId && vehicles.length > 0 && !hasManualSelection) {
      const found = vehicles.find((v) => String(v.id) === String(urlVehicleId));
      if (found && String(selectedVehicle) !== String(urlVehicleId)) {
        setSelectedVehicle(urlVehicleId);
      }
    }
    
    // Open MOT dialog if motId in URL and records loaded
    if (urlMotId && motRecords.length > 0 && !urlMotIdHandledRef.current) {
      const mot = motRecords.find((m) => String(m.id) === String(urlMotId));
      if (mot) {
        setEditingMot(mot);
        setDialogOpen(true);
        urlMotIdHandledRef.current = true;
        // Clear URL params after opening
        setSearchParams({}, { replace: true });
      }
    }
  }, [searchParams, vehicles, motRecords, selectedVehicle, hasManualSelection, setSearchParams]);

  const handleAdd = () => {
    setEditingMot(null);
    setDialogOpen(true);
  };

  const handleEdit = (mot) => {
    setEditingMot(mot);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (demoGuard(t)) return;
    if (window.confirm(t('common.confirmDelete'))) {
      try {
        await api.delete(`/mot-records/${id}`);
        loadMotRecords();
      } catch (error) {
        logger.error('Error deleting MOT record:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setEditingMot(null);
    if (reload) {
      loadMotRecords();
    }
  };

  const sortedMotRecords = React.useMemo(() => {
    const comparator = (a, b) => {
      if (orderBy === 'registration') {
        const aReg = vehicles.find(v => String(v.id) === String(a.vehicleId))?.registrationNumber || '';
        const bReg = vehicles.find(v => String(v.id) === String(b.vehicleId))?.registrationNumber || '';
        if (aReg === bReg) return 0;
        return order === 'asc' ? (aReg > bReg ? 1 : -1) : (aReg < bReg ? 1 : -1);
      }
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      // Numeric conversions for cost and mileage fields
      if (['testCost', 'repairCost', 'totalCost', 'mileage'].includes(orderBy)) {
        aValue = parseFloat(aValue) || 0;
        bValue = parseFloat(bValue) || 0;
      }
      
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

    return [...motRecords].sort(comparator);
  }, [motRecords, order, orderBy]);

  const { page, rowsPerPage, paginatedRows: paginatedMotRecords, handleChangePage, handleChangeRowsPerPage } = useTablePagination(sortedMotRecords);

  const getResultChip = (result) => {
    const colors = { Pass: 'success', Fail: 'error', Advisory: 'warning' };
    return <Chip label={result} color={colors[result] || 'default'} size="small" />;
  };

  

  if (loading || vehiclesLoading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <KnightRiderLoader size={32} />
      </Box>
    );
  }

  if (vehicles.length === 0) {
    return (
      <Box>
        <Typography variant="h4" gutterBottom>
          {t('mot.title')}
        </Typography>
        <Typography>{t('common.noVehicles')}</Typography>
      </Box>
    );
  }

  const totalTestCost = motRecords.reduce((sum, mot) => sum + parseFloat(mot.testCost || 0), 0);
  const totalRepairCost = motRecords.reduce((sum, mot) => sum + parseFloat(mot.repairCost || 0), 0);

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('mot.title')}</Typography>
        <Box display="flex" gap={2}>
          <VehicleSelector
            vehicles={vehicles}
            value={selectedVehicle}
            onChange={handleVehicleChange}
            minWidth={360}
          />
          {(() => {
            const sel = vehicles.find(v => v.id === selectedVehicle);
            const disabled = sel ? (sel.isMotExempt || sel.motExempt) : false;
            return (
              <Button variant="contained" color="primary" startIcon={<AddIcon />} onClick={handleAdd} disabled={disabled}>
                {t('mot.addMot')}
              </Button>
            );
          })()}
          {(() => {
            const sel = vehicles.find(v => v.id === selectedVehicle);
            const disabled = sel ? (sel.isMotExempt || sel.motExempt) : true;
            return (
              <Button
                variant="outlined"
                color="secondary"
                startIcon={<VisibilityIcon />}
                onClick={async () => {
                  if (!sel) return;
                  const regToUse = sel.registrationNumber || sel.registration || '';
                  if (!regToUse) {
                    // eslint-disable-next-line no-alert
                    alert(t('mot.importFailed'));
                    return;
                  }
                  if (!window.confirm(t('mot.importConfirm', { reg: regToUse }))) return;
                  try {
                    const resp = await api.post('/mot-records/import-dvsa', {
                      vehicleId: selectedVehicle,
                      registration: regToUse,
                    });
                    const imported = resp.data?.imported ?? 0;
                    const ids = resp.data?.importedIds ?? [];
                    // eslint-disable-next-line no-alert
                    alert(t('mot.importResult', { count: imported }) + (ids.length ? '\nIDs: ' + ids.join(', ') : ''));
                    loadMotRecords();
                  } catch (err) {
                    // eslint-disable-next-line no-console
                    logger.error('Error importing MOT history:', err);
                    // eslint-disable-next-line no-alert
                    alert(t('mot.importFailed'));
                  }
                }}
                disabled={disabled}
              >
                {t('mot.importFromDvsa', 'Import MOT history')}
              </Button>
            );
          })()}
        </Box>
      </Box>

      <TablePaginationBar
        count={sortedMotRecords.length}
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
                    active={orderBy === 'testDate'}
                    direction={orderBy === 'testDate' ? order : 'asc'}
                    onClick={() => handleRequestSort('testDate')}
                  >
                    {t('mot.testDate')}
                  </TableSortLabel>
                </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'result'}
                  direction={orderBy === 'result' ? order : 'asc'}
                  onClick={() => handleRequestSort('result')}
                >
                  {t('mot.result')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'expiryDate'}
                  direction={orderBy === 'expiryDate' ? order : 'desc'}
                  onClick={() => handleRequestSort('expiryDate')}
                >
                  {t('mot.expiryDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'testCost'}
                  direction={orderBy === 'testCost' ? order : 'asc'}
                  onClick={() => handleRequestSort('testCost')}
                >
                  {t('mot.testCost')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'repairCost'}
                  direction={orderBy === 'repairCost' ? order : 'asc'}
                  onClick={() => handleRequestSort('repairCost')}
                >
                  {t('mot.repairCost')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'totalCost'}
                  direction={orderBy === 'totalCost' ? order : 'asc'}
                  onClick={() => handleRequestSort('totalCost')}
                >
                  {t('mot.totalCost')}
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
                  active={orderBy === 'testCenter'}
                  direction={orderBy === 'testCenter' ? order : 'asc'}
                  onClick={() => handleRequestSort('testCenter')}
                >
                  {t('mot.testCenter')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
              {sortedMotRecords.length === 0 ? (
              <TableRow>
                <TableCell colSpan={9} align="center">
                  {t('common.noRecords')}
                </TableCell>
              </TableRow>
            ) : (
              paginatedMotRecords.map((mot) => {
                const isExpired = mot.expiryDate && new Date(mot.expiryDate) < new Date();
                const isFail = mot.result === 'Fail';
                const showRed = isExpired || isFail;
                return (
                <TableRow key={mot.id} sx={{ 
                  ...(!showRed && { '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }),
                  ...(showRed && { backgroundColor: 'rgba(255, 0, 0, 0.08)' })
                }}>
                  <TableCell>{vehicles.find(v => String(v.id) === String(mot.vehicleId))?.registrationNumber || '-'}</TableCell>
                  <TableCell>{formatDateISO(mot.testDate)}</TableCell>
                  <TableCell>{getResultChip(mot.result)}</TableCell>
                  <TableCell>{mot.expiryDate ? formatDateISO(mot.expiryDate) : '-'}</TableCell>
                  <TableCell align="right">{formatCurrency(mot.testCost, 'GBP', i18n.language)}</TableCell>
                  <TableCell align="right">{formatCurrency(mot.repairCost, 'GBP', i18n.language)}</TableCell>
                  <TableCell align="right">{formatCurrency(mot.totalCost, 'GBP', i18n.language)}</TableCell>
                  <TableCell>{mot.mileage ? format(convert(mot.mileage)) : '-'}</TableCell>
                  <TableCell>{mot.testCenter || '-'}</TableCell>
                  <TableCell align="center">
                    {(() => {
                      const sel = vehicles.find(v => v.id === selectedVehicle);
                      const disabled = sel ? (sel.isMotExempt || sel.motExempt) : false;
                      return (
                        <>
                          <ViewAttachmentIconButton record={mot} />
                          <Tooltip title={t('common.edit')}>
                            <span>
                              <IconButton size="small" onClick={() => handleEdit(mot)} disabled={disabled}>
                                <EditIcon />
                              </IconButton>
                            </span>
                          </Tooltip>
                          <Tooltip title={t('common.delete')}>
                            <span>
                              <IconButton size="small" onClick={() => handleDelete(mot.id)} disabled={disabled}>
                                <DeleteIcon />
                              </IconButton>
                            </span>
                          </Tooltip>
                        </>
                      );
                    })()}
                  </TableCell>
                </TableRow>
              );})
            )}
          </TableBody>
        </Table>
      </TableContainer>
      <TablePaginationBar
        count={sortedMotRecords.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

      <Box mt={2} display="flex" gap={4}>
        <Typography variant="h6">
          {t('mot.testCost')} {t('common.total')}: {formatCurrency(totalTestCost, 'GBP', i18n.language)}
        </Typography>
        <Typography variant="h6">
          {t('mot.repairCost')} {t('common.total')}: {formatCurrency(totalRepairCost, 'GBP', i18n.language)}
        </Typography>
        <Typography variant="h6" color="primary">
          {t('mot.totalCost')}: {formatCurrency(totalTestCost + totalRepairCost, 'GBP', i18n.language)}
        </Typography>
      </Box>

      <MotDialog
        open={dialogOpen}
        motRecord={editingMot}
        vehicleId={selectedVehicle !== '__all__' ? selectedVehicle : null}
        vehicles={vehicles}
        onClose={handleDialogClose}
      />
    </Box>
  );
};

export default MotRecords;
