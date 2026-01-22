import React, { useState, useEffect } from 'react';
import {
  Container,
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
  MenuItem,
  Select,
  FormControl,
  InputLabel,
  Chip,
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon, Visibility as VisibilityIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import formatCurrency from '../utils/formatCurrency';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import { formatDateISO } from '../utils/formatDate';
import MotDialog from '../components/MotDialog';

const MotRecords = () => {
  const [motRecords, setMotRecords] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingMot, setEditingMot] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('motRecordsSortBy') || 'testDate');
  const [order, setOrder] = useState(() => localStorage.getItem('motRecordsSortOrder') || 'desc');
  const { api } = useAuth();
  const { t, i18n } = useTranslation();
  const { convert, format, getLabel } = useDistance();
  const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();
  const [hasManualSelection, setHasManualSelection] = useState(false);

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    if (!defaultVehicleId) return;
    if (hasManualSelection) return;
    if (!vehicles || vehicles.length === 0) return;
    const found = vehicles.find((v) => String(v.id) === String(defaultVehicleId));
    if (found && String(selectedVehicle) !== String(defaultVehicleId)) {
      setSelectedVehicle(defaultVehicleId);
    }
  }, [defaultVehicleId, vehicles, hasManualSelection]);

  useEffect(() => {
    if (selectedVehicle) {
      loadMotRecords();
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
  };

  const loadMotRecords = async () => {
    if (!selectedVehicle) return;
    try {
      const response = await api.get(`/mot-records?vehicleId=${selectedVehicle}`);
      setMotRecords(response.data);
    } catch (error) {
      console.error('Error loading MOT records:', error);
    }
  };

  const handleAdd = () => {
    setEditingMot(null);
    setDialogOpen(true);
  };

  const handleEdit = (mot) => {
    setEditingMot(mot);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('mot.deleteConfirm'))) {
      try {
        await api.delete(`/mot-records/${id}`);
        loadMotRecords();
      } catch (error) {
        console.error('Error deleting MOT record:', error);
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

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('motRecordsSortBy', property);
    localStorage.setItem('motRecordsSortOrder', newOrder);
  };

  const sortedMotRecords = React.useMemo(() => {
    const comparator = (a, b) => {
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

  const getResultChip = (result) => {
    const colors = { Pass: 'success', Fail: 'error', Advisory: 'warning' };
    return <Chip label={result} color={colors[result] || 'default'} size="small" />;
  };

  

  if (vehicles.length === 0) {
    return (
      <Container>
        <Typography variant="h4" gutterBottom>
          {t('mot.title')}
        </Typography>
        <Typography>{t('common.noVehicles')}</Typography>
      </Container>
    );
  }

  const totalTestCost = motRecords.reduce((sum, mot) => sum + parseFloat(mot.testCost || 0), 0);
  const totalRepairCost = motRecords.reduce((sum, mot) => sum + parseFloat(mot.repairCost || 0), 0);

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('mot.title')}</Typography>
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
                    console.error('Error importing MOT history:', err);
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

      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
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
                  direction={orderBy === 'expiryDate' ? order : 'asc'}
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
                  {t('mot.mileage')} ({getLabel()})
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
              sortedMotRecords.map((mot) => (
                <TableRow key={mot.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
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
                          <Tooltip title={t('edit')}>
                            <span>
                              <IconButton size="small" onClick={() => handleEdit(mot)} disabled={disabled}>
                                <EditIcon />
                              </IconButton>
                            </span>
                          </Tooltip>
                          <Tooltip title={t('delete')}>
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
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

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
        vehicleId={selectedVehicle}
        onClose={handleDialogClose}
      />
    </Container>
  );
};

export default MotRecords;
