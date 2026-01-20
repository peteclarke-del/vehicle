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
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import ServiceDialog from '../components/ServiceDialog';

const ServiceRecords = () => {
  const [serviceRecords, setServiceRecords] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('serviceRecordsSortBy') || 'serviceDate');
  const [order, setOrder] = useState(() => localStorage.getItem('serviceRecordsSortOrder') || 'desc');
  const { api } = useAuth();
  const { t } = useTranslation();
  const { convert, format, getLabel } = useDistance();

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    if (selectedVehicle) {
      loadServiceRecords();
    }
  }, [selectedVehicle]);

  const loadVehicles = async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    if (data.length > 0 && !selectedVehicle) {
      setSelectedVehicle(data[0].id);
    }
  };

  const loadServiceRecords = async () => {
    if (!selectedVehicle) return;
    try {
      const response = await api.get(`/service-records?vehicleId=${selectedVehicle}`);
      setServiceRecords(response.data);
    } catch (error) {
      console.error('Error loading service records:', error);
    }
  };

  const handleAdd = () => {
    setEditingService(null);
    setDialogOpen(true);
  };

  const handleEdit = (service) => {
    setEditingService(service);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('service.deleteConfirm'))) {
      try {
        await api.delete(`/service-records/${id}`);
        loadServiceRecords();
      } catch (error) {
        console.error('Error deleting service record:', error);
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
    localStorage.setItem('serviceRecordsSortBy', property);
    localStorage.setItem('serviceRecordsSortOrder', newOrder);
  };

  const sortedServiceRecords = React.useMemo(() => {
    const comparator = (a, b) => {
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      if (['laborCost', 'partsCost', 'totalCost', 'mileage'].includes(orderBy)) {
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

    return [...serviceRecords].sort(comparator);
  }, [serviceRecords, order, orderBy]);

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(amount);
  };

  if (vehicles.length === 0) {
    return (
      <Container>
        <Typography variant="h4" gutterBottom>
          {t('service.title')}
        </Typography>
        <Typography>{t('common.noVehicles')}</Typography>
      </Container>
    );
  }

  const totalLaborCost = serviceRecords.reduce((sum, svc) => sum + parseFloat(svc.laborCost || 0), 0);
  const totalPartsCost = serviceRecords.reduce((sum, svc) => sum + parseFloat(svc.partsCost || 0), 0);

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('service.title')}</Typography>
        <Box display="flex" gap={2}>
          <FormControl size="small" sx={{ width: 240 }}>
            <InputLabel>{t('common.selectVehicle')}</InputLabel>
            <Select
              value={selectedVehicle}
              label={t('common.selectVehicle')}
              onChange={(e) => setSelectedVehicle(e.target.value)}
            >
              {vehicles.map((vehicle) => (
                <MenuItem key={vehicle.id} value={vehicle.id}>
                  {vehicle.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
          <Button variant="contained" color="primary" startIcon={<AddIcon />} onClick={handleAdd}>
            {t('service.addService')}
          </Button>
        </Box>
      </Box>

      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
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
                  {t('service.mileage')} ({getLabel()})
                </TableSortLabel>
              </TableCell>
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
                <TableCell colSpan={8} align="center">
                  {t('common.noRecords')}
                </TableCell>
              </TableRow>
            ) : (
              sortedServiceRecords.map((service) => (
                <TableRow key={service.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{new Date(service.serviceDate).toLocaleDateString()}</TableCell>
                  <TableCell>{service.serviceType}</TableCell>
                  <TableCell align="right">{formatCurrency(service.laborCost)}</TableCell>
                  <TableCell align="right">{formatCurrency(service.partsCost)}</TableCell>
                  <TableCell align="right">{formatCurrency(service.totalCost)}</TableCell>
                  <TableCell>{service.mileage ? format(convert(service.mileage)) : '-'}</TableCell>
                  <TableCell>{service.serviceProvider || '-'}</TableCell>
                  <TableCell align="center">
                    <Tooltip title={t('edit')}>
                      <IconButton size="small" onClick={() => handleEdit(service)}>
                        <EditIcon />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title={t('delete')}>
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

      <Box mt={2} display="flex" gap={4}>
        <Typography variant="h6">
          {t('service.laborCost')} {t('common.total')}: {formatCurrency(totalLaborCost)}
        </Typography>
        <Typography variant="h6">
          {t('service.partsCost')} {t('common.total')}: {formatCurrency(totalPartsCost)}
        </Typography>
        <Typography variant="h6" color="primary">
          {t('service.totalCost')}: {formatCurrency(totalLaborCost + totalPartsCost)}
        </Typography>
      </Box>

      <ServiceDialog
        open={dialogOpen}
        serviceRecord={editingService}
        vehicleId={selectedVehicle}
        onClose={handleDialogClose}
      />
    </Container>
  );
};

export default ServiceRecords;
