import React, { useState, useEffect, useCallback } from 'react';
import {
  Container,
  Typography,
  Button,
  Chip,
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
  Snackbar,
  Button as MuiButton,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { formatDateISO } from '../utils/formatDate';
import { fetchArrayData } from '../hooks/useApiData';
import useTablePagination from '../hooks/useTablePagination';
import PolicyDialog from '../components/PolicyDialog';
import TablePaginationBar from '../components/TablePaginationBar';

const Policies = () => {
  const [policies, setPolicies] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingPolicy, setEditingPolicy] = useState(null);
  const [snackOpen, setSnackOpen] = useState(false);
  const [lastDeleted, setLastDeleted] = useState(null);
  const pendingDeletes = React.useRef(new Map());
  const { api } = useAuth();
  const { t } = useTranslation();

  const { page, rowsPerPage, paginatedRows: paginatedPolicies, handleChangePage, handleChangeRowsPerPage } = useTablePagination(policies);

  const loadVehicles = useCallback(async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
  }, [api]);

  const loadPolicies = useCallback(async () => {
    const data = await fetchArrayData(api, '/insurance/policies');
    setPolicies(data);
  }, [api]);

  useEffect(() => {
    loadVehicles();
    loadPolicies();
  }, [loadVehicles, loadPoliciesloadVehicles, loadPolicies]);

  const handleAdd = () => {
    setEditingPolicy(null);
    setDialogOpen(true);
  };

  const handleEdit = (p) => {
    setEditingPolicy(p);
    setDialogOpen(true);
  };

  const handleDelete = (id) => {
    const p = policies.find(x => x.id === id);
    if (!p) return;

    // Optimistically remove from UI
    setPolicies(prev => prev.filter(x => x.id !== id));
    setLastDeleted(p);
    setSnackOpen(true);

    // Schedule actual delete after undo window
    const timer = setTimeout(async () => {
      try {
        await api.delete(`/insurance/policies/${id}`);
      } catch (err) {
        console.error('Error deleting policy', err);
        // Revert locally if delete failed
        setPolicies(prev => [p, ...prev]);
      } finally {
        pendingDeletes.current.delete(id);
      }
    }, 5000);

    pendingDeletes.current.set(id, { timer, policy: p });
  };

  const handleUndo = () => {
    if (!lastDeleted) return;
    const entry = pendingDeletes.current.get(lastDeleted.id);
    if (entry) {
      clearTimeout(entry.timer);
      pendingDeletes.current.delete(lastDeleted.id);
    }
    setPolicies(prev => [lastDeleted, ...prev]);
    setLastDeleted(null);
    setSnackOpen(false);
  };

  const handleSnackClose = () => {
    setSnackOpen(false);
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setEditingPolicy(null);
    if (reload) loadPolicies();
  };

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('insurance.policies.title')}</Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={handleAdd}>
          {t('insurance.policies.addPolicy')}
        </Button>
      </Box>

      <TablePaginationBar
        count={policies.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />
      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>{t('insurance.policies.provider')}</TableCell>
              <TableCell>{t('insurance.policies.policyNumber')}</TableCell>
              <TableCell>{t('insurance.policies.ncdYears')}</TableCell>
              <TableCell>{t('insurance.policies.expiryDate')}</TableCell>
              <TableCell>{t('insurance.policies.vehicles')}</TableCell>
              <TableCell align="center">{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {policies.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} align="center">{t('common.noRecords')}</TableCell>
              </TableRow>
            ) : (
              paginatedPolicies.map((p) => (
                <TableRow key={p.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>
                    {p.provider}
                    {(() => {
                      const now = new Date();
                      const expiry = p.expiryDate ? new Date(p.expiryDate) : null;
                      const isExpired = expiry && expiry < now;
                      const isCancelled = Boolean(p.cancelled === true || p.status === 'cancelled' || p.isActive === false);
                      if (isCancelled) {
                        return <Chip label={t('insurance.policies.status.cancelled')} size="small" color="default" sx={{ ml: 1 }} />;
                      }
                      if (isExpired) {
                        return <Chip label={t('insurance.policies.status.expired')} size="small" color="warning" sx={{ ml: 1 }} />;
                      }
                      return null;
                    })()}
                  </TableCell>
                  <TableCell>{p.policyNumber || '-'}</TableCell>
                  <TableCell>{p.ncdYears ?? '-'}</TableCell>
                  <TableCell>{p.expiryDate ? formatDateISO(p.expiryDate) : '-'}</TableCell>
                  <TableCell>{(p.vehicles || []).map(v => v.registration).join(', ')}</TableCell>
                  <TableCell align="center">
                    <Tooltip title={t('common.edit')}>
                      <IconButton size="small" onClick={() => handleEdit(p)}>
                        <EditIcon />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title={t('common.delete')}>
                      <IconButton size="small" onClick={() => handleDelete(p.id)}>
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
        count={policies.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

      <PolicyDialog open={dialogOpen} policy={editingPolicy} vehicles={vehicles} onClose={handleDialogClose} />
      <Snackbar
        open={snackOpen}
        onClose={handleSnackClose}
        message={t('insurance.policies.deleteUndo')}
        action={
          <React.Fragment>
            <MuiButton color="secondary" size="small" onClick={handleUndo}>
              {t('common.undo')}
            </MuiButton>
          </React.Fragment>
        }
        autoHideDuration={5000}
      />
    </Container>
  );
};

export default Policies;
