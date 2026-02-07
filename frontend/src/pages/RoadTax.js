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
import { useVehicles } from '../contexts/VehiclesContext';
import { fetchArrayData } from '../hooks/useApiData';
import useTablePagination from '../hooks/useTablePagination';
import usePersistedSort from '../hooks/usePersistedSort';
import useVehicleSelection from '../hooks/useVehicleSelection';
import { useRegistrationLabel } from '../utils/splitLabel';
import RoadTaxDialog from '../components/RoadTaxDialog';
import TablePaginationBar from '../components/TablePaginationBar';
import VehicleSelector from '../components/VehicleSelector';
import CenteredLoader from '../components/CenteredLoader';

const RoadTax = () => {
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingRecord, setEditingRecord] = useState(null);
  const { api } = useAuth();
  const { vehicles, loading: vehiclesLoading, fetchVehicles } = useVehicles();
  const { t } = useTranslation();
  const { regFirst, regLast } = useRegistrationLabel();
  const { selectedVehicle, handleVehicleChange } = useVehicleSelection(vehicles);
  const { orderBy, order, handleRequestSort } = usePersistedSort('roadTax', 'expiryDate', 'desc');

  useEffect(() => {
    fetchVehicles();
  }, [fetchVehicles]);

  const loadRecords = useCallback(async (signal) => {
    setLoading(true);
    const url = !selectedVehicle || selectedVehicle === '__all__' ? '/road-tax' : `/road-tax?vehicleId=${selectedVehicle}`;
    const data = await fetchArrayData(api, url, signal ? { signal } : {});
    return data;
  }, [api, selectedVehicle]);

  useEffect(() => {
    if (!selectedVehicle) return;
    
    const abortController = new AbortController();
    let mounted = true;
    
    loadRecords(abortController.signal).then((data) => {
      if (mounted) {
        setRecords(data);
        setLoading(false);
      }
    });
    
    return () => {
      mounted = false;
      abortController.abort();
    };
  }, [selectedVehicle, loadRecords]);
  
  
  const handleAdd = () => {
    setEditingRecord(null);
    setDialogOpen(true);
  };

  const handleEdit = (r) => {
    setEditingRecord(r);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('common.confirmDelete'))) {
      try {
        await api.delete(`/road-tax/${id}`);
        loadRecords();
      } catch (error) {
        logger.error('Error deleting road tax record:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setEditingRecord(null);
    if (reload) loadRecords();
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

      // handle dates
      if (orderBy === 'startDate' || orderBy === 'expiryDate') {
        const aTime = aValue ? new Date(aValue).getTime() : 0;
        const bTime = bValue ? new Date(bValue).getTime() : 0;
        if (aTime === bTime) return 0;
        return order === 'asc' ? (aTime - bTime) : (bTime - aTime);
      }

      // numeric compare for amount
      if (orderBy === 'amount') {
        const aNum = parseFloat(aValue) || 0;
        const bNum = parseFloat(bValue) || 0;
        if (aNum === bNum) return 0;
        return order === 'asc' ? (aNum - bNum) : (bNum - aNum);
      }

      // boolean compare for sorn
      if (orderBy === 'sorn') {
        const aBool = aValue ? 1 : 0;
        const bBool = bValue ? 1 : 0;
        if (aBool === bBool) return 0;
        return order === 'asc' ? (aBool - bBool) : (bBool - aBool);
      }

      // fallback string compare
      aValue = aValue || '';
      bValue = bValue || '';
      if (aValue === bValue) return 0;
      return order === 'asc' ? (aValue > bValue ? 1 : -1) : (aValue < bValue ? 1 : -1);
    };

    return [...records].sort(comparator);
  }, [records, order, orderBy]);

  const { page, rowsPerPage, paginatedRows: paginatedRecords, handleChangePage, handleChangeRowsPerPage } = useTablePagination(sortedRecords);

  if (loading || vehiclesLoading) {
    return <CenteredLoader />;
  }

  if (vehicles.length === 0) {
    return (
      <Box>
        <Typography variant="h4" gutterBottom>{t('roadTax.title')}</Typography>
        <Typography>{t('common.noVehicles')}</Typography>
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('roadTax.title')}</Typography>
        <Box display="flex" gap={2}>
          <VehicleSelector
            vehicles={vehicles}
            value={selectedVehicle}
            onChange={handleVehicleChange}
            minWidth={360}
          />
          <Button variant="contained" color="primary" startIcon={<AddIcon />} onClick={handleAdd}>
            {t('roadTax.add')}
          </Button>
        </Box>
      </Box>

      <TablePaginationBar
        rowsPerPage={rowsPerPage}
        page={page}
        count={sortedRecords.length}
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
                  {t('roadTax.expiryDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'sorn'}
                  direction={orderBy === 'sorn' ? order : 'asc'}
                  onClick={() => handleRequestSort('sorn')}
                >
                  {t('roadTax.sorn')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="right">
                <TableSortLabel
                  active={orderBy === 'amount'}
                  direction={orderBy === 'amount' ? order : 'asc'}
                  onClick={() => handleRequestSort('amount')}
                >
                  {t('roadTax.amount')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'notes'}
                  direction={orderBy === 'notes' ? order : 'asc'}
                  onClick={() => handleRequestSort('notes')}
                >
                  {t('common.notes')}
                </TableSortLabel>
              </TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedRecords.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} align="center">{t('common.noRecords')}</TableCell>
              </TableRow>
            ) : (
              paginatedRecords.map(r => {
                const isExpired = r.expiryDate && new Date(r.expiryDate) < new Date();
                // SORN records are superseded if there's a later record for the same vehicle
                const isSornSuperseded = r.sorn && records.some(other => 
                  other.id !== r.id && 
                  String(other.vehicleId) === String(r.vehicleId) && 
                  other.startDate && r.startDate && 
                  new Date(other.startDate) > new Date(r.startDate)
                );
                const showRed = isExpired || isSornSuperseded;
                return (
                <TableRow key={r.id} sx={{ 
                  ...(!showRed && { '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }),
                  ...(showRed && { backgroundColor: 'rgba(255, 0, 0, 0.08)' })
                }}>
                  <TableCell>{vehicles.find(v => String(v.id) === String(r.vehicleId))?.registrationNumber || '-'}</TableCell>
                  <TableCell>{r.startDate || '-'}</TableCell>
                  <TableCell>{r.expiryDate || '-'}</TableCell>
                  <TableCell>{r.sorn ? 'Yes' : 'No'}</TableCell>
                  <TableCell align="right">{r.amount != null ? r.amount : '-'}</TableCell>
                  <TableCell>{r.notes || '-'}</TableCell>
                  <TableCell align="center">
                    <Tooltip title={t('common.edit')}><IconButton size="small" onClick={() => handleEdit(r)}><EditIcon /></IconButton></Tooltip>
                    <Tooltip title={t('common.delete')}><IconButton size="small" onClick={() => handleDelete(r.id)}><DeleteIcon /></IconButton></Tooltip>
                  </TableCell>
                </TableRow>
              );})
            )}
          </TableBody>
        </Table>
      </TableContainer>
      <TablePaginationBar
        rowsPerPage={rowsPerPage}
        page={page}
        count={sortedRecords.length}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

      <RoadTaxDialog 
        open={dialogOpen} 
        roadTaxRecord={editingRecord} 
        vehicleId={selectedVehicle !== '__all__' ? selectedVehicle : null} 
        vehicles={vehicles}
        onClose={handleDialogClose} 
      />
    </Box>
  );
};

export default RoadTax;
