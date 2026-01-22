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
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { formatDateISO } from '../utils/formatDate';
import { fetchArrayData } from '../hooks/useApiData';
import PolicyDialog from '../components/PolicyDialog';

const Insurance = () => {
  const { api } = useAuth();
  const { t } = useTranslation();

  // Policies state
  const [policies, setPolicies] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [policyDialogOpen, setPolicyDialogOpen] = useState(false);
  const [editingPolicy, setEditingPolicy] = useState(null);

  useEffect(() => {
    loadVehicles();
    loadPolicies();
  }, []);

  const loadVehicles = async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
  };

  const loadPolicies = async () => {
    const data = await fetchArrayData(api, '/insurance/policies');
    setPolicies(data);
  };

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
    if (!window.confirm(t('insurance.policies.deleteConfirm', 'Delete this policy?'))) return;
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
        <Button variant="contained" startIcon={<AddIcon />} onClick={handleAddPolicy}>
          {t('insurance.policies.addPolicy')}
        </Button>
      </Box>

      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>{t('insurance.policies.provider')}</TableCell>
              <TableCell>{t('insurance.policies.policyNumber')}</TableCell>
              <TableCell>Start Date</TableCell>
              <TableCell>{t('insurance.policies.expiryDate')}</TableCell>
              <TableCell>NCB</TableCell>
              <TableCell>{t('insurance.policies.vehicles')}</TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {policies.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} align="center">{t('common.noRecords')}</TableCell>
              </TableRow>
            ) : (
              policies.map((p) => (
                <TableRow key={p.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{p.provider}</TableCell>
                  <TableCell>{p.policyNumber || '-'}</TableCell>
                  <TableCell>{p.startDate ? formatDateISO(p.startDate) : '-'}</TableCell>
                  <TableCell>{p.expiryDate ? formatDateISO(p.expiryDate) : '-'}</TableCell>
                  <TableCell>{p.ncdYears ?? '-'} Years</TableCell>
                  <TableCell>{(p.vehicles || []).map(v => v.registration).join(', ')}</TableCell>
                  <TableCell align="center">
                    <Tooltip title={t('edit')}>
                      <IconButton size="small" onClick={() => handleEditPolicy(p)}>
                        <EditIcon />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title={t('delete')}>
                      <IconButton size="small" onClick={() => handleDeletePolicy(p.id)}>
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

      <PolicyDialog open={policyDialogOpen} policy={editingPolicy} vehicles={vehicles} onClose={(reload) => { setPolicyDialogOpen(false); setEditingPolicy(null); if (reload) loadPolicies(); }} />
    </Container>
  );
};

export default Insurance;
