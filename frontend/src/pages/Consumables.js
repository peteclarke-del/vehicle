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
  Chip,
  FormControl,
  InputLabel,
  Select,
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import ConsumableDialog from '../components/ConsumableDialog';

const Consumables = () => {
  const [consumables, setConsumables] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedConsumable, setSelectedConsumable] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('consumablesSortBy') || 'specification');
  const [order, setOrder] = useState(() => localStorage.getItem('consumablesSortOrder') || 'asc');
  const { api } = useAuth();
  const { t } = useTranslation();
  const { convert, format, getLabel } = useDistance();

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    if (selectedVehicle) {
      loadConsumables();
    } else {
      setConsumables([]);
    }
  }, [selectedVehicle]);

  const loadVehicles = async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    if (data.length > 0) {
      setSelectedVehicle(data[0].id);
    }
    setLoading(false);
  };

  const loadConsumables = async () => {
    try {
      const response = await api.get(`/consumables?vehicleId=${selectedVehicle}`);
      setConsumables(response.data);
    } catch (error) {
      console.error('Error loading consumables:', error);
    }
  };

  const handleAdd = () => {
    setSelectedConsumable(null);
    setDialogOpen(true);
  };

  const handleEdit = (consumable) => {
    setSelectedConsumable(consumable);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('common.confirmDelete') || 'Are you sure you want to delete this consumable?')) {
      try {
        await api.delete(`/consumables/${id}`);
        loadConsumables();
      } catch (error) {
        console.error('Error deleting consumable:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setSelectedConsumable(null);
    if (reload) {
      loadConsumables();
    }
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('consumablesSortBy', property);
    localStorage.setItem('consumablesSortOrder', newOrder);
  };

  const sortedConsumables = React.useMemo(() => {
    const comparator = (a, b) => {
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      // Numeric conversions for cost and mileage fields
      if (['cost', 'mileageAtChange'].includes(orderBy)) {
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

    return [...consumables].sort(comparator);
  }, [consumables, order, orderBy]);

  const calculateTotalCost = () => {
    return consumables.reduce((sum, consumable) => sum + (parseFloat(consumable.cost) || 0), 0).toFixed(2);
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <CircularProgress />
      </Box>
    );
  }

  if (vehicles.length === 0) {
    return (
      <Box>
        <Typography variant="h4" gutterBottom>
          {t('consumables.title')}
        </Typography>
        <Typography color="textSecondary">
          {t('common.noVehicles')}
        </Typography>
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('consumables.title')}</Typography>
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
          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={handleAdd}
            disabled={!selectedVehicle}
          >
            {t('consumables.addConsumable')}
          </Button>
        </Box>
      </Box>

      {consumables.length > 0 && (
        <Box mb={2}>
          <Typography variant="h6">
            {t('consumables.totalCost')}: £{calculateTotalCost()}
          </Typography>
        </Box>
      )}

      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'consumableType'}
                  direction={orderBy === 'consumableType' ? order : 'asc'}
                  onClick={() => handleRequestSort('consumableType')}
                >
                  {t('consumables.type')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'specification'}
                  direction={orderBy === 'specification' ? order : 'asc'}
                  onClick={() => handleRequestSort('specification')}
                >
                  {t('consumables.specification')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'quantity'}
                  direction={orderBy === 'quantity' ? order : 'asc'}
                  onClick={() => handleRequestSort('quantity')}
                >
                  {t('consumables.quantity')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'brand'}
                  direction={orderBy === 'brand' ? order : 'asc'}
                  onClick={() => handleRequestSort('brand')}
                >
                  {t('consumables.brand')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'cost'}
                  direction={orderBy === 'cost' ? order : 'asc'}
                  onClick={() => handleRequestSort('cost')}
                >
                  {t('consumables.cost')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'lastChanged'}
                  direction={orderBy === 'lastChanged' ? order : 'asc'}
                  onClick={() => handleRequestSort('lastChanged')}
                >
                  {t('consumables.lastChanged')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'mileageAtChange'}
                  direction={orderBy === 'mileageAtChange' ? order : 'asc'}
                  onClick={() => handleRequestSort('mileageAtChange')}
                >
                  {t('consumables.mileageAtChange')} ({getLabel()})
                </TableSortLabel>
              </TableCell>
              <TableCell>{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedConsumables.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  <Typography color="textSecondary">{t('common.noRecords')}</Typography>
                </TableCell>
              </TableRow>
            ) : (
              sortedConsumables.map((consumable) => (
                <TableRow key={consumable.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>
                    <Chip 
                      label={consumable.consumableType?.name || '-'} 
                      size="small" 
                    />
                  </TableCell>
                  <TableCell>{consumable.specification}</TableCell>
                  <TableCell>
                    {consumable.quantity} {consumable.consumableType?.unit || ''}
                  </TableCell>
                  <TableCell>{consumable.brand || '-'}</TableCell>
                  <TableCell>£{parseFloat(consumable.cost).toFixed(2)}</TableCell>
                  <TableCell>{consumable.lastChanged || '-'}</TableCell>
                  <TableCell>{consumable.mileageAtChange ? format(convert(consumable.mileageAtChange)) : '-'}</TableCell>
                  <TableCell>
                      <Tooltip title={t('edit')}>
                        <IconButton size="small" onClick={() => handleEdit(consumable)}>
                          <Edit />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={t('delete')}>
                        <IconButton size="small" onClick={() => handleDelete(consumable.id)}>
                          <Delete />
                        </IconButton>
                      </Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <ConsumableDialog
        open={dialogOpen}
        onClose={handleDialogClose}
        consumable={selectedConsumable}
        vehicleId={selectedVehicle}
      />
    </Box>
  );
};

export default Consumables;
