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
  Tooltip,
  TableSortLabel,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import logger from '../utils/logger';
import { formatDateISO } from '../utils/formatDate';
import { useVehicles } from '../contexts/VehiclesContext';
import { fetchArrayData } from '../hooks/useApiData';
import useTablePagination from '../hooks/useTablePagination';
import usePersistedSort from '../hooks/usePersistedSort';
import useVehicleSelection from '../hooks/useVehicleSelection';
import PolicyDialog from '../components/PolicyDialog';
import TablePaginationBar from '../components/TablePaginationBar';
import VehicleSelector from '../components/VehicleSelector';
import KnightRiderLoader from '../components/KnightRiderLoader';

const Insurance = () => {
  const { api } = useAuth();
  const { t } = useTranslation();

  // Policies state
  const [policies, setPolicies] = useState([]);
  const [loading, setLoading] = useState(true);
  const [policyDialogOpen, setPolicyDialogOpen] = useState(false);
  const [editingPolicy, setEditingPolicy] = useState(null);
  const { vehicles, loading: vehiclesLoading, fetchVehicles } = useVehicles();
  const { orderBy, order, handleRequestSort } = usePersistedSort('insurance', 'expiryDate', 'desc');
  const { selectedVehicle, handleVehicleChange } = useVehicleSelection(vehicles);

  const loadPolicies = useCallback(async (signal) => {
    setLoading(true);
    const url = !selectedVehicle || selectedVehicle === '__all__' ? '/insurance/policies' : `/insurance?vehicleId=${selectedVehicle}`;
    const data = await fetchArrayData(api, url, signal ? { signal } : {});
    setPolicies(data);
    setLoading(false);
    return data;
  }, [api, selectedVehicle]);

  useEffect(() => {
    fetchVehicles();
  }, [fetchVehicles]);

  useEffect(() => {
    if (!selectedVehicle) return;
    
    const abortController = new AbortController();
    loadPolicies(abortController.signal);
    
    return () => {
      abortController.abort();
    };
  }, [selectedVehicle, loadPolicies]);

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

  const { page, rowsPerPage, paginatedRows: paginatedPolicies, handleChangePage, handleChangeRowsPerPage } = useTablePagination(sortedPolicies);

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
      logger.error('Error deleting policy', err);
    }
  };

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
        <Typography variant="h4">{t('insurance.policies.title')}</Typography>
        <Box display="flex" gap={2}>
          <VehicleSelector
            vehicles={vehicles}
            value={selectedVehicle}
            onChange={handleVehicleChange}
            minWidth={300}
          />
          <Button variant="contained" startIcon={<AddIcon />} onClick={handleAddPolicy}>
            {t('insurance.policies.addPolicy')}
          </Button>
        </Box>
      </Box>

      <TablePaginationBar
        count={sortedPolicies.length}
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
              paginatedPolicies.map((p) => {
                // Display all vehicles on the policy
                const displayVehicles = p.vehicles || [];
                const vehicleDisplay = displayVehicles.map(v => v.registrationNumber || v.registration).join(', ') || '-';
                const isExpired = p.expiryDate && new Date(p.expiryDate) < new Date();

                return (
                  <TableRow key={p.id} sx={{ 
                    ...(!isExpired && { '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }),
                    ...(isExpired && { backgroundColor: 'rgba(255, 0, 0, 0.08)' })
                  }}>
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
      <TablePaginationBar
        count={sortedPolicies.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

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
    </Box>
  );
};

export default Insurance;
