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
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import { useVehicles } from '../contexts/VehiclesContext';
import useTablePagination from '../hooks/useTablePagination';
import RoadTaxDialog from '../components/RoadTaxDialog';
import TablePaginationBar from '../components/TablePaginationBar';
import VehicleSelector from '../components/VehicleSelector';
import KnightRiderLoader from '../components/KnightRiderLoader';

const RoadTax = () => {
  const [records, setRecords] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingRecord, setEditingRecord] = useState(null);
  const { api } = useAuth();
  const { vehicles, fetchVehicles } = useVehicles();
  const { t } = useTranslation();
  const registrationLabelText = t('common.registrationNumber');
  const regWords = (registrationLabelText || '').split(/\s+/).filter(Boolean);
  const regLast = regWords.length > 0 ? regWords[regWords.length - 1] : '';
  const regFirst = regWords.length > 1 ? regWords.slice(0, regWords.length - 1).join(' ') : (regWords[0] || 'Registration');
  const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();
  const [hasManualSelection, setHasManualSelection] = useState(false);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('roadTaxSortBy') || 'expiryDate');
  const [order, setOrder] = useState(() => localStorage.getItem('roadTaxSortOrder') || 'desc');

  useEffect(() => {
    fetchVehicles();
  }, [fetchVehicles]);

  const loadRecords = useCallback(async () => {
    setLoading(true);
    try {
      const url = !selectedVehicle || selectedVehicle === '__all__' ? '/road-tax' : `/road-tax?vehicleId=${selectedVehicle}`;
      const response = await api.get(url);
      setRecords(response.data);
    } catch (error) {
      console.error('Error loading road tax records:', error);
    } finally {
      setLoading(false);
    }
  }, [api, selectedVehicle]);

  useEffect(() => {
    if (!defaultVehicleId) return;
    if (hasManualSelection) return;
    if (!vehicles || vehicles.length === 0) return;
    const found = vehicles.find((v) => String(v.id) === String(defaultVehicleId));
    if (found && String(selectedVehicle) !== String(defaultVehicleId)) {
      setSelectedVehicle(defaultVehicleId);
    }
  }, [defaultVehicleId, vehicles, hasManualSelection, selectedVehicle]);

  useEffect(() => {
    if (selectedVehicle) {
      loadRecords();
    }
  }, [selectedVehicle, loadRecords]);

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
        console.error('Error deleting road tax record:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setEditingRecord(null);
    if (reload) loadRecords();
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('roadTaxSortBy', property);
    localStorage.setItem('roadTaxSortOrder', newOrder);
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
            onChange={(v) => { setHasManualSelection(true); setSelectedVehicle(v); setDefaultVehicle(v); }}
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
              paginatedRecords.map(r => (
                <TableRow key={r.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
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
              ))
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
