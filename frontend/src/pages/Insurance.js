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
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { fetchArrayData } from '../hooks/useApiData';
import InsuranceDialog from '../components/InsuranceDialog';

const Insurance = () => {
  const [insurance, setInsurance] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingInsurance, setEditingInsurance] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('insuranceSortBy') || 'expiryDate');
  const [order, setOrder] = useState(() => localStorage.getItem('insuranceSortOrder') || 'asc');
  const { api } = useAuth();
  const { t } = useTranslation();

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    if (selectedVehicle) {
      loadInsurance();
    }
  }, [selectedVehicle]);

  const loadVehicles = async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    if (data.length > 0 && !selectedVehicle) {
      setSelectedVehicle(data[0].id);
    }
  };

  const loadInsurance = async () => {
    if (!selectedVehicle) return;
    try {
      const response = await api.get(`/insurance?vehicleId=${selectedVehicle}`);
      setInsurance(response.data);
    } catch (error) {
      console.error('Error loading insurance:', error);
    }
  };

  const handleAdd = () => {
    setEditingInsurance(null);
    setDialogOpen(true);
  };

  const handleEdit = (ins) => {
    setEditingInsurance(ins);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('insurance.deleteConfirm'))) {
      try {
        await api.delete(`/insurance/${id}`);
        loadInsurance();
      } catch (error) {
        console.error('Error deleting insurance:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setEditingInsurance(null);
    if (reload) {
      loadInsurance();
    }
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('insuranceSortBy', property);
    localStorage.setItem('insuranceSortOrder', newOrder);
  };

  const sortedInsurance = React.useMemo(() => {
    const comparator = (a, b) => {
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      if (orderBy === 'annualCost') {
        aValue = parseFloat(a.annualCost) || 0;
        bValue = parseFloat(b.annualCost) || 0;
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

    return [...insurance].sort(comparator);
  }, [insurance, order, orderBy]);

  const isExpired = (expiryDate) => {
    return new Date(expiryDate) < new Date();
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(amount);
  };

  if (vehicles.length === 0) {
    return (
      <Container>
        <Typography variant="h4" gutterBottom>
          {t('insurance.title')}
        </Typography>
        <Typography>{t('common.noVehicles')}</Typography>
      </Container>
    );
  }

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('insurance.title')}</Typography>
        <Box display="flex" gap={2}>
          <FormControl size="small" sx={{ width: 240 }}>
            <InputLabel>Select Vehicle</InputLabel>
            <Select
              value={selectedVehicle}
              label="Select Vehicle"
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
            color="primary"
            startIcon={<AddIcon />}
            onClick={handleAdd}
          >
            {t('insurance.addInsurance')}
          </Button>
        </Box>
      </Box>

      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'provider'}
                  direction={orderBy === 'provider' ? order : 'asc'}
                  onClick={() => handleRequestSort('provider')}
                >
                  {t('insurance.provider')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'policyNumber'}
                  direction={orderBy === 'policyNumber' ? order : 'asc'}
                  onClick={() => handleRequestSort('policyNumber')}
                >
                  {t('insurance.policyNumber')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'coverageType'}
                  direction={orderBy === 'coverageType' ? order : 'asc'}
                  onClick={() => handleRequestSort('coverageType')}
                >
                  {t('insurance.coverageType')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'annualCost'}
                  direction={orderBy === 'annualCost' ? order : 'asc'}
                  onClick={() => handleRequestSort('annualCost')}
                >
                  {t('insurance.annualCost')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'startDate'}
                  direction={orderBy === 'startDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('startDate')}
                >
                  {t('insurance.startDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'expiryDate'}
                  direction={orderBy === 'expiryDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('expiryDate')}
                >
                  {t('insurance.expiryDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {insurance.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} align="center">
                  {t('common.noRecords')}
                </TableCell>
              </TableRow>
            ) : (
              sortedInsurance.map((ins) => (
                <TableRow key={ins.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{ins.provider}</TableCell>
                  <TableCell>{ins.policyNumber || '-'}</TableCell>
                  <TableCell>{ins.coverageType}</TableCell>
                  <TableCell align="right">{formatCurrency(ins.annualCost)}</TableCell>
                  <TableCell>{new Date(ins.startDate).toLocaleDateString()}</TableCell>
                  <TableCell>
                    <Box display="flex" alignItems="center" gap={1}>
                      {new Date(ins.expiryDate).toLocaleDateString()}
                      {isExpired(ins.expiryDate) && (
                        <Chip label={t('common.expired')} color="error" size="small" />
                      )}
                    </Box>
                  </TableCell>
                  <TableCell align="center">
                    <Tooltip title="Edit">
                      <IconButton size="small" onClick={() => handleEdit(ins)}>
                        <EditIcon />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title="Delete">
                      <IconButton size="small" onClick={() => handleDelete(ins.id)}>
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

      <Box mt={2}>
        <Typography variant="h6">
          {t('insurance.title')} {t('common.total')}: {formatCurrency(insurance.reduce((sum, ins) => sum + parseFloat(ins.annualCost), 0))}
        </Typography>
      </Box>

      <InsuranceDialog
        open={dialogOpen}
        insurance={editingInsurance}
        vehicleId={selectedVehicle}
        onClose={handleDialogClose}
      />
    </Container>
  );
};

export default Insurance;
