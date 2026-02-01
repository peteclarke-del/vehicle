import React, { useState, useEffect, useCallback } from 'react';
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
  MenuItem,
  Select,
  FormControl,
  InputLabel,
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import logger from '../utils/logger';
import SafeStorage from '../utils/SafeStorage';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import formatCurrency from '../utils/formatCurrency';
import { useVehicles } from '../contexts/VehiclesContext';
import { useDistance } from '../hooks/useDistance';
import { formatDateISO } from '../utils/formatDate';
import useTablePagination from '../hooks/useTablePagination';
import ServiceDialog from '../components/ServiceDialog';
import TablePaginationBar from '../components/TablePaginationBar';
import VehicleSelector from '../components/VehicleSelector';
import ViewAttachmentIconButton from '../components/ViewAttachmentIconButton';
import KnightRiderLoader from '../components/KnightRiderLoader';

const ServiceRecords = () => {
  const [serviceRecords, setServiceRecords] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const { vehicles, fetchVehicles } = useVehicles();
  const [orderBy, setOrderBy] = useState(() => SafeStorage.get('serviceRecordsSortBy', 'serviceDate'));
  const [order, setOrder] = useState(() => SafeStorage.get('serviceRecordsSortOrder', 'desc'));
  const { api } = useAuth();
  const { t, i18n } = useTranslation();
  const registrationLabelText = t('common.registrationNumber');
  const regWords = (registrationLabelText || '').split(/\s+/).filter(Boolean);
  const regLast = regWords.length > 0 ? regWords[regWords.length - 1] : '';
  const regFirst = regWords.length > 1 ? regWords.slice(0, regWords.length - 1).join(' ') : (regWords[0] || 'Registration');
  const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();
  const [hasManualSelection, setHasManualSelection] = useState(false);
  const { convert, format, getLabel } = useDistance();

  const loadServiceRecords = useCallback(async () => {
    setLoading(true);
    try {
      const url = !selectedVehicle || selectedVehicle === '__all__' ? '/service-records' : `/service-records?vehicleId=${selectedVehicle}`;
      const response = await api.get(url);
      // Ensure we always store an array: some API responses may wrap the list in an object
      const data = response.data;
      if (Array.isArray(data)) setServiceRecords(data);
      else if (data && Array.isArray(data.serviceRecords)) setServiceRecords(data.serviceRecords);
      else setServiceRecords([]);
    } catch (error) {
      logger.error('Error loading service records:', error);
    } finally {
      setLoading(false);
    }
  }, [api, selectedVehicle]);

  useEffect(() => {
    fetchVehicles();
  }, [fetchVehicles]);

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
      loadServiceRecords();
    }
  }, [selectedVehicle, loadServiceRecords]);

  useEffect(() => {
    if (vehicles.length > 0 && !selectedVehicle) {
      if (defaultVehicleId && vehicles.find((v) => String(v.id) === String(defaultVehicleId))) {
        setSelectedVehicle(defaultVehicleId);
      } else {
        setSelectedVehicle(vehicles[0].id);
      }
    }
  }, [vehicles, selectedVehicle, defaultVehicleId]);

  const handleAdd = () => {
    setEditingService(null);
    setDialogOpen(true);
  };

  const handleEdit = async (service) => {
    try {
      // Fetch detailed service record and associated items/consumables
      const resp = await api.get(`/service-records/${service.id}/items`);
      const svc = resp.data.serviceRecord || resp.data;
      const parts = resp.data.parts || [];
      const consumables = resp.data.consumables || [];

      // Use items from API; only fallback to parts/consumables when items missing
      const items = Array.isArray(svc.items) ? svc.items : [];
      if (items.length === 0) {
        svc.items = items.concat(
          parts.map((p) => ({
            type: 'part',
            description: p.description || p.name || '',
            cost: String(p.cost || 0),
            quantity: p.quantity || 1,
            partId: p.id,
          })),
          consumables.map((c) => ({
            type: 'consumable',
            description: c.name || c.description || '',
            cost: String(c.cost || 0),
            quantity: c.quantity || 1,
            consumableId: c.id,
          }))
        );
      } else {
        svc.items = items;
      }
      setEditingService(svc);
      setDialogOpen(true);
    } catch (err) {
      logger.error('Error loading service details', err);
      // fallback to minimal object
      setEditingService(service);
      setDialogOpen(true);
    }
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('common.confirmDelete'))) {
      try {
        await api.delete(`/service-records/${id}`);
        loadServiceRecords();
      } catch (error) {
        logger.error('Error deleting service record:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setEditingService(null);
    if (reload) {
      loadServiceRecords();
    }
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    SafeStorage.set('serviceRecordsSortBy', property);
    SafeStorage.set('serviceRecordsSortOrder', newOrder);
  };

  const sortedServiceRecords = React.useMemo(() => {
    const comparator = (a, b) => {
      if (orderBy === 'registration') {
        const aReg = vehicles.find(v => String(v.id) === String(a.vehicleId))?.registrationNumber || '';
        const bReg = vehicles.find(v => String(v.id) === String(b.vehicleId))?.registrationNumber || '';
        if (aReg === bReg) return 0;
        return order === 'asc' ? (aReg > bReg ? 1 : -1) : (aReg < bReg ? 1 : -1);
      }
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      if (['laborCost', 'partsCost', 'consumablesCost', 'additionalCosts', 'totalCost', 'mileage'].includes(orderBy)) {
        aValue = parseFloat(a[orderBy]) || 0;
        bValue = parseFloat(b[orderBy]) || 0;
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

    const base = Array.isArray(serviceRecords) ? serviceRecords : [];
    return [...base].sort(comparator);
  }, [serviceRecords, order, orderBy]);

    const { page, rowsPerPage, paginatedRows: paginatedServiceRecords, handleChangePage, handleChangeRowsPerPage } = useTablePagination(sortedServiceRecords);

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(amount);
  };

  if (loading) {
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
          {t('service.title')}
        </Typography>
        <Typography>{t('common.noVehicles')}</Typography>
      </Box>
    );
  }

  const _svc = Array.isArray(serviceRecords) ? serviceRecords : [];
  const totalLaborCost = _svc.reduce((sum, svc) => sum + parseFloat(svc.laborCost || 0), 0);
  const totalPartsCost = _svc.reduce((sum, svc) => sum + parseFloat(svc.partsCost || 0), 0);
  const totalConsumablesCost = _svc.reduce((sum, svc) => sum + parseFloat(svc.consumablesCost || 0), 0);
  const totalAdditionalCost = _svc.reduce((sum, svc) => sum + parseFloat(svc.additionalCosts || 0), 0);

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('service.title')}</Typography>
        <Box display="flex" gap={2}>
            <VehicleSelector
              vehicles={vehicles}
              value={selectedVehicle}
              onChange={(v) => { setHasManualSelection(true); setSelectedVehicle(v); setDefaultVehicle(v); }}
              minWidth={360}
            />
          <Button variant="contained" color="primary" startIcon={<AddIcon />} onClick={handleAdd} disabled={!selectedVehicle || selectedVehicle === '__all__'}>
            {t('service.addService')}
          </Button>
        </Box>
      </Box>

      <TablePaginationBar
        count={sortedServiceRecords.length}
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
                  active={orderBy === 'serviceDate'}
                  direction={orderBy === 'serviceDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('serviceDate')}
                >
                  {t('service.serviceDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'serviceType'}
                  direction={orderBy === 'serviceType' ? order : 'asc'}
                  onClick={() => handleRequestSort('serviceType')}
                >
                  {t('service.serviceType')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'laborCost'}
                  direction={orderBy === 'laborCost' ? order : 'asc'}
                  onClick={() => handleRequestSort('laborCost')}
                >
                  {t('service.laborCost')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'partsCost'}
                  direction={orderBy === 'partsCost' ? order : 'asc'}
                  onClick={() => handleRequestSort('partsCost')}
                >
                  {t('service.partsCost')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'consumablesCost'}
                  direction={orderBy === 'consumablesCost' ? order : 'asc'}
                  onClick={() => handleRequestSort('consumablesCost')}
                >
                  {t('service.consumablesCost')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'additionalCosts'}
                  direction={orderBy === 'additionalCosts' ? order : 'asc'}
                  onClick={() => handleRequestSort('additionalCosts')}
                >
                  {t('service.additionalCosts')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'totalCost'}
                  direction={orderBy === 'totalCost' ? order : 'asc'}
                  onClick={() => handleRequestSort('totalCost')}
                >
                  {t('service.totalCost')}
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
              <TableCell>{t('mot.title') || 'MOT'}</TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'serviceProvider'}
                  direction={orderBy === 'serviceProvider' ? order : 'asc'}
                  onClick={() => handleRequestSort('serviceProvider')}
                >
                  {t('service.serviceProvider')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {serviceRecords.length === 0 ? (
              <TableRow>
                <TableCell colSpan={12} align="center">
                  {t('common.noRecords')}
                </TableCell>
              </TableRow>
            ) : (
              paginatedServiceRecords.map((service) => (
                <TableRow key={service.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{vehicles.find(v => String(v.id) === String(service.vehicleId))?.registrationNumber || '-'}</TableCell>
                  <TableCell>{formatDateISO(service.serviceDate)}</TableCell>
                  <TableCell>{service.serviceType}</TableCell>
                  <TableCell align="right">{formatCurrency(service.laborCost, 'GBP', i18n.language)}</TableCell>
                  <TableCell align="right">{formatCurrency(service.partsCost, 'GBP', i18n.language)}</TableCell>
                  <TableCell align="right">{formatCurrency(service.consumablesCost || 0, 'GBP', i18n.language)}</TableCell>
                  <TableCell align="right">{formatCurrency(service.additionalCosts || 0, 'GBP', i18n.language)}</TableCell>
                  <TableCell align="right">{formatCurrency(service.totalCost, 'GBP', i18n.language)}</TableCell>
                  <TableCell>{service.mileage ? format(convert(service.mileage)) : '-'}</TableCell>
                    <TableCell>{service.motTestNumber ? `${service.motTestNumber}${service.motTestDate ? ' (' + service.motTestDate + ')' : ''}` : '-'}</TableCell>
                  <TableCell>{service.serviceProvider || '-'}</TableCell>
                  <TableCell align="center">
                    <ViewAttachmentIconButton record={service} />
                    <Tooltip title={t('common.edit')}>
                      <IconButton size="small" onClick={() => handleEdit(service)}>
                        <EditIcon />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title={t('common.delete')}>
                      <IconButton size="small" onClick={() => handleDelete(service.id)}>
                        <DeleteIcon />
                      </IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>
      <TablePaginationBar
        count={sortedServiceRecords.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

      <Box mt={2} display="flex" gap={4}>
        <Typography variant="h6">
          {t('service.laborCost')} {t('common.total')}: {formatCurrency(totalLaborCost, 'GBP', i18n.language)}
        </Typography>
        <Typography variant="h6">
          {t('service.partsCost')} {t('common.total')}: {formatCurrency(totalPartsCost, 'GBP', i18n.language)}
        </Typography>
        <Typography variant="h6">
          {t('service.consumablesCost')} {t('common.total')}: {formatCurrency(totalConsumablesCost, 'GBP', i18n.language)}
        </Typography>
        <Typography variant="h6" color="primary">
          {t('service.totalCost')}: {formatCurrency(totalLaborCost + totalPartsCost + totalConsumablesCost + totalAdditionalCost, 'GBP', i18n.language)}
        </Typography>
      </Box>

      <ServiceDialog
        open={dialogOpen}
        serviceRecord={editingService}
        vehicleId={selectedVehicle}
        onClose={handleDialogClose}
      />
    </Box>
  );
};

export default ServiceRecords;
