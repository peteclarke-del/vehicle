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
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import { useTranslation } from 'react-i18next';
import { formatDateISO } from '../utils/formatDate';
import { fetchArrayData } from '../hooks/useApiData';
import PolicyDialog from '../components/PolicyDialog';
import VehicleSelector from '../components/VehicleSelector';

const Insurance = () => {
  const { api } = useAuth();
  const { t } = useTranslation();

  // Policies state
  const [policies, setPolicies] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [policyDialogOpen, setPolicyDialogOpen] = useState(false);
  const [editingPolicy, setEditingPolicy] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('insuranceSortBy') || 'expiryDate');
  const [order, setOrder] = useState(() => localStorage.getItem('insuranceSortOrder') || 'desc');

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    loadPolicies();
  }, [selectedVehicle]);

  const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();

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

  useEffect(() => {
    if (!defaultVehicleId) return;
    if (!vehicles || vehicles.length === 0) return;
    const found = vehicles.find((v) => String(v.id) === String(defaultVehicleId));
    if (found && String(selectedVehicle) !== String(defaultVehicleId)) {
      setSelectedVehicle(defaultVehicleId);
    }
  }, [defaultVehicleId, vehicles]);

  const loadPolicies = async () => {
    const url = !selectedVehicle || selectedVehicle === '__all__' ? '/insurance/policies' : `/insurance?vehicleId=${selectedVehicle}`;
    const data = await fetchArrayData(api, url);
    setPolicies(data);
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('insuranceSortBy', property);
    localStorage.setItem('insuranceSortOrder', newOrder);
  };

  const sortedPolicies = React.useMemo(() => {
    const comparator = (a, b) => {
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      if (orderBy === 'startDate' || orderBy === 'expiryDate') {
        const aTime = aValue ? new Date(aValue).getTime() : 0;
        const bTime = bValue ? new Date(bValue).getTime() : 0;
        if (aTime === bTime) return 0;
        return order === 'asc' ? (aTime - bTime) : (bTime - aTime);
      }

      if (orderBy === 'ncdYears') {
        const aNum = parseFloat(aValue) || 0;
        const bNum = parseFloat(bValue) || 0;
        if (aNum === bNum) return 0;
        return order === 'asc' ? (aNum - bNum) : (bNum - aNum);
      }

      aValue = aValue || '';
      bValue = bValue || '';
      if (aValue === bValue) return 0;
      return order === 'asc' ? (aValue > bValue ? 1 : -1) : (aValue < bValue ? 1 : -1);
    };

    return [...policies].sort(comparator);
  }, [policies, order, orderBy]);

  // Policy handlers
  const handleAddPolicy = () => {
    setEditingPolicy(null);
    setPolicyDialogOpen(true);
  };

  const handleEditPolicy = (p) => {
    setEditingPolicy(p);
    setPolicyDialogOpen(true);
  };

  const handleDeletePolicy = async (id) => {
    if (!window.confirm(t('common.confirmDelete'))) return;
    try {
      await api.delete(`/insurance/policies/${id}`);
      loadPolicies();
    } catch (err) {
      console.error('Error deleting policy', err);
    }
  };

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('insurance.policies.title')}</Typography>
        <Box display="flex" gap={2}>
          <VehicleSelector
            vehicles={vehicles}
            value={selectedVehicle}
            onChange={(v) => setSelectedVehicle(v)}
            minWidth={300}
          />
          <Button variant="contained" startIcon={<AddIcon />} onClick={handleAddPolicy} disabled={!selectedVehicle || selectedVehicle === '__all__'}>
            {t('insurance.policies.addPolicy')}
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
                  {t('insurance.policies.provider')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'policyNumber'}
                  direction={orderBy === 'policyNumber' ? order : 'asc'}
                  onClick={() => handleRequestSort('policyNumber')}
                >
                  {t('insurance.policies.policyNumber')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'startDate'}
                  direction={orderBy === 'startDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('startDate')}
                >
                  {t('common.startDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'expiryDate'}
                  direction={orderBy === 'expiryDate' ? order : 'desc'}
                  onClick={() => handleRequestSort('expiryDate')}
                >
                  {t('insurance.policies.expiryDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'ncdYears'}
                  direction={orderBy === 'ncdYears' ? order : 'asc'}
                  onClick={() => handleRequestSort('ncdYears')}
                >
                  {t('insurance.policies.ncdYears')}
                </TableSortLabel>
              </TableCell>
              <TableCell>{t('insurance.policies.vehicles')}</TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedPolicies.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} align="center">{t('common.noRecords')}</TableCell>
              </TableRow>
            ) : (
              sortedPolicies.map((p) => {
                // If a specific vehicle is selected, show only that vehicle's registration
                let displayVehicles = p.vehicles || [];
                if (selectedVehicle && selectedVehicle !== '__all__') {
                  displayVehicles = displayVehicles.filter(v => String(v.id) === String(selectedVehicle));
                }
                const vehicleDisplay = displayVehicles.map(v => v.registration).join(', ') || '-';

                return (
                  <TableRow key={p.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                    <TableCell>{p.provider}</TableCell>
                    <TableCell>{p.policyNumber || '-'}</TableCell>
                    <TableCell>{p.startDate ? formatDateISO(p.startDate) : '-'}</TableCell>
                    <TableCell>{p.expiryDate ? formatDateISO(p.expiryDate) : '-'}</TableCell>
                    <TableCell>{p.ncdYears ?? '-'} {t('insurance.policies.years')}</TableCell>
                    <TableCell>{vehicleDisplay}</TableCell>
                    <TableCell align="center">
                      <Tooltip title={t('common.edit')}>
                        <IconButton size="small" onClick={() => handleEditPolicy(p)}>
                          <EditIcon />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={t('common.delete')}>
                        <IconButton size="small" onClick={() => handleDeletePolicy(p.id)}>
                          <DeleteIcon />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                );
              })
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <PolicyDialog 
        open={policyDialogOpen} 
        policy={editingPolicy} 
        vehicles={vehicles} 
        selectedVehicleId={selectedVehicle}
        existingPolicies={policies}
        onClose={(reload) => { 
          setPolicyDialogOpen(false); 
          setEditingPolicy(null); 
          if (reload) loadPolicies(); 
        }} 
      />
    </Container>
  );
};

export default Insurance;
