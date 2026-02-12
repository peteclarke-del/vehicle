import React, { useEffect, useState, useCallback } from 'react';
import logger from '../utils/logger';
import { Box, Button, Typography, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, IconButton, Chip, Tooltip, TableSortLabel } from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import VehicleSelector from '../components/VehicleSelector';
import { useTranslation } from 'react-i18next';
import formatCurrency from '../utils/formatCurrency';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import useTablePagination from '../hooks/useTablePagination';
import usePersistedSort from '../hooks/usePersistedSort';
import useVehicleSelection from '../hooks/useVehicleSelection';
import { useRegistrationLabel } from '../utils/splitLabel';
import PartDialog from '../components/PartDialog';
import ServiceDialog from '../components/ServiceDialog';
import KnightRiderLoader from '../components/KnightRiderLoader';
import ViewAttachmentIconButton from '../components/ViewAttachmentIconButton';
import TablePaginationBar from '../components/TablePaginationBar';
import { demoGuard } from '../utils/demoMode';

const Parts = () => {
  const [parts, setParts] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedPart, setSelectedPart] = useState(null);
  const [openServiceDialog, setOpenServiceDialog] = useState(false);
  const [selectedServiceRecord, setSelectedServiceRecord] = useState(null);
  const { api } = useAuth();
  const { t, i18n } = useTranslation();
  const { regFirst, regLast } = useRegistrationLabel();
  const { orderBy, order, handleRequestSort } = usePersistedSort('parts', 'description', 'asc');
  const { selectedVehicle, handleVehicleChange } = useVehicleSelection(vehicles, { includeViewAll: true });
  const { convert, format, getLabel } = useDistance();

  const loadVehicles = useCallback(async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    setLoading(false);
  }, [api]);

  const loadParts = useCallback(async (signal) => {
    const url = (!selectedVehicle || selectedVehicle === '__all__') ? '/parts' : `/parts?vehicleId=${selectedVehicle}`;
    const data = await fetchArrayData(api, url, signal ? { signal } : {});
    setParts(data);
    return data;
  }, [api, selectedVehicle]);

  useEffect(() => {
    loadVehicles();
  }, [loadVehicles]);

  useEffect(() => {
    if (!selectedVehicle) {
      setParts([]);
      return;
    }
    
    const abortController = new AbortController();
    
    loadParts(abortController.signal);
    
    return () => {
      abortController.abort();
    };
  }, [selectedVehicle, loadParts]);

  const handleAdd = () => {
    setSelectedPart(null);
    setDialogOpen(true);
  };

  const handleEdit = (part) => {
    setSelectedPart(part);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (demoGuard(t)) return;
    if (window.confirm(t('common.confirmDelete'))) {
      try {
        await api.delete(`/parts/${id}`);
        loadParts();
      } catch (error) {
        logger.error('Error deleting part:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setSelectedPart(null);
    if (reload) {
      loadParts();
    }
  };

  const sortedParts = React.useMemo(() => {
    const comparator = (a, b) => {
      if (orderBy === 'registration') {
        const aReg = vehicles.find(v => String(v.id) === String(a.vehicleId))?.registration || '';
        const bReg = vehicles.find(v => String(v.id) === String(b.vehicleId))?.registration || '';
        if (aReg === bReg) return 0;
        return order === 'asc' ? (aReg > bReg ? 1 : -1) : (aReg < bReg ? 1 : -1);
      }

      let aValue = a[orderBy];
      let bValue = b[orderBy];

      // Numeric conversions for cost fields
      if (['cost'].includes(orderBy)) {
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

    return [...parts].sort(comparator);
  }, [parts, order, orderBy]);

  const { page, rowsPerPage, paginatedRows: paginatedParts, handleChangePage, handleChangeRowsPerPage } = useTablePagination(sortedParts);

  const calculateTotalCost = () => {
    return parts.reduce((sum, part) => sum + (parseFloat(part.cost) || 0), 0);
  };

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
        <Typography>{t('common.noVehicles')}</Typography>
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('parts.title')}</Typography>
        <Box display="flex" gap={2}>
          <VehicleSelector
            vehicles={vehicles}
            value={selectedVehicle}
            onChange={handleVehicleChange}
            includeViewAll={true}
            minWidth={360}
          />
          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={handleAdd}
            disabled={!selectedVehicle || selectedVehicle === '__all__'}
          >
            {t('parts.addPart')}
          </Button>
        </Box>
      </Box>

      {parts.length > 0 && (
        <Box mb={2}>
            <Typography variant="h6">
              {t('parts.totalCost')}: {formatCurrency(calculateTotalCost(), 'GBP', i18n.language)}
            </Typography>
          </Box>
      )}

      <TablePaginationBar
        page={page}
        rowsPerPage={rowsPerPage}
        count={sortedParts.length}
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
                  active={orderBy === 'description'}
                  direction={orderBy === 'description' ? order : 'asc'}
                  onClick={() => handleRequestSort('description')}
                >
                  {t('common.description')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'partNumber'}
                  direction={orderBy === 'partNumber' ? order : 'asc'}
                  onClick={() => handleRequestSort('partNumber')}
                >
                  {t('common.partNumber')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'category'}
                  direction={orderBy === 'category' ? order : 'asc'}
                  onClick={() => handleRequestSort('category')}
                >
                  {t('common.category')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'cost'}
                  direction={orderBy === 'cost' ? order : 'asc'}
                  onClick={() => handleRequestSort('cost')}
                >
                  {t('parts.cost')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'purchaseDate'}
                  direction={orderBy === 'purchaseDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('purchaseDate')}
                >
                  {t('common.purchaseDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>
                <TableSortLabel
                  active={orderBy === 'installationDate'}
                  direction={orderBy === 'installationDate' ? order : 'asc'}
                  onClick={() => handleRequestSort('installationDate')}
                >
                  {t('common.installationDate')}
                </TableSortLabel>
              </TableCell>
              <TableCell>{t('common.linkedRecords') || 'Linked Records'}</TableCell>
              <TableCell>{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedParts.length === 0 ? (
              <TableRow>
                <TableCell colSpan={9} align="center">
                  <Typography color="textSecondary">{t('common.noRecords')}</Typography>
                </TableCell>
              </TableRow>
            ) : (
              paginatedParts.map((part) => (
                <TableRow key={part.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{vehicles.find(v => String(v.id) === String(part.vehicleId))?.registrationNumber || '-'}</TableCell>
                  <TableCell>{part.description}</TableCell>
                  <TableCell>{part.partNumber || '-'}</TableCell>
                  <TableCell>{part.partCategory?.name || '-'}</TableCell>
                  <TableCell>{formatCurrency(parseFloat(part.cost) || 0, 'GBP', i18n.language)}</TableCell>
                  <TableCell>{part.purchaseDate || '-'}</TableCell>
                  <TableCell>{part.installationDate || '-'}</TableCell>
                  <TableCell>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                      <div>
                        {part.motTestNumber ? `${part.motTestNumber}${part.motTestDate ? ' (' + part.motTestDate + ')' : ''}` : '-'}
                      </div>
                      <div>
                        {part.serviceRecordId ? (
                          <button onClick={async (e) => { e.preventDefault(); try { const resp = await api.get(`/service-records/${part.serviceRecordId}`); setSelectedServiceRecord(resp.data); setOpenServiceDialog(true); } catch (err) { logger.error('Failed to load service record', err); } }} style={{ background: 'none', border: 'none', padding: 0, textDecoration: 'underline', cursor: 'pointer', color: 'inherit' }}>
                            {part.serviceRecordDate ? t('service.serviceLabelDate', { date: part.serviceRecordDate }) : t('service.serviceLabelId', { id: part.serviceRecordId })}
                          </button>
                        ) : '-'}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                        <ViewAttachmentIconButton record={part} />
                        <Tooltip title={t('common.edit')}>
                      <IconButton size="small" onClick={() => handleEdit(part)}>
                        <Edit />
                      </IconButton>
                    </Tooltip>
                        <Tooltip title={t('common.delete')}>
                      <IconButton size="small" onClick={() => handleDelete(part.id)}>
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

        <TablePaginationBar
          page={page}
          rowsPerPage={rowsPerPage}
          count={sortedParts.length}
          onPageChange={handleChangePage}
          onRowsPerPageChange={handleChangeRowsPerPage}
        />

      <PartDialog
        open={dialogOpen}
        onClose={handleDialogClose}
        part={selectedPart}
        vehicleId={selectedVehicle}
      />
      <ServiceDialog
        open={openServiceDialog}
        serviceRecord={selectedServiceRecord}
        vehicleId={selectedVehicle}
        onClose={(saved) => {
          setOpenServiceDialog(false);
          setSelectedServiceRecord(null);
          if (saved) loadParts();
        }}
      />
    </Box>
  );
};

export default Parts;
