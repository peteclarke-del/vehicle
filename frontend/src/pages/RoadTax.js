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
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { fetchArrayData } from '../hooks/useApiData';
import RoadTaxDialog from '../components/RoadTaxDialog';

const RoadTax = () => {
  const [records, setRecords] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingRecord, setEditingRecord] = useState(null);
  const { api } = useAuth();
  const { t } = useTranslation();

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    if (selectedVehicle) loadRecords();
  }, [selectedVehicle]);

  const loadVehicles = async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    if (data.length > 0 && !selectedVehicle) setSelectedVehicle(data[0].id);
  };

  const loadRecords = async () => {
    if (!selectedVehicle) return;
    try {
      const response = await api.get(`/road-tax?vehicleId=${selectedVehicle}`);
      setRecords(response.data);
    } catch (error) {
      console.error('Error loading road tax records:', error);
    }
  };

  const handleAdd = () => {
    setEditingRecord(null);
    setDialogOpen(true);
  };

  const handleEdit = (r) => {
    setEditingRecord(r);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('roadTax.confirmDelete'))) {
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

  if (vehicles.length === 0) {
    return (
      <Container>
        <Typography variant="h4" gutterBottom>{t('roadTax.title')}</Typography>
        <Typography>{t('common.noVehicles')}</Typography>
      </Container>
    );
  }

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('roadTax.title')}</Typography>
        <Box display="flex" gap={2}>
          <FormControl size="small" sx={{ width: 240 }}>
            <InputLabel>{t('common.selectVehicle')}</InputLabel>
            <Select
              value={selectedVehicle}
              label={t('common.selectVehicle')}
              onChange={(e) => setSelectedVehicle(e.target.value)}
            >
              {vehicles.map(v => (<MenuItem key={v.id} value={v.id}>{v.name}</MenuItem>))}
            </Select>
          </FormControl>
          <Button variant="contained" color="primary" startIcon={<AddIcon />} onClick={handleAdd}>
            {t('roadTax.add')}
          </Button>
        </Box>
      </Box>

      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>{t('roadTax.startDate')}</TableCell>
              <TableCell>{t('roadTax.expiryDate')}</TableCell>
              <TableCell align="right">{t('roadTax.amount')}</TableCell>
              <TableCell>{t('roadTax.notes')}</TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {records.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} align="center">{t('common.noRecords')}</TableCell>
              </TableRow>
            ) : (
              records.map(r => (
                <TableRow key={r.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{r.startDate || '-'}</TableCell>
                  <TableCell>{r.expiryDate || '-'}</TableCell>
                  <TableCell align="right">{r.amount != null ? r.amount : '-'}</TableCell>
                  <TableCell>{r.notes || '-'}</TableCell>
                  <TableCell align="center">
                    <Tooltip title={t('edit')}><IconButton size="small" onClick={() => handleEdit(r)}><EditIcon /></IconButton></Tooltip>
                    <Tooltip title={t('delete')}><IconButton size="small" onClick={() => handleDelete(r.id)}><DeleteIcon /></IconButton></Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <RoadTaxDialog open={dialogOpen} roadTaxRecord={editingRecord} vehicleId={selectedVehicle} onClose={handleDialogClose} />
    </Container>
  );
};

export default RoadTax;
