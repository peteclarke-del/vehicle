import React, { useEffect, useState, useCallback } from 'react';
import logger from '../utils/logger';
import { Box, Button, Typography, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, IconButton, Tooltip, TableSortLabel } from '@mui/material';
import logger from '../utils/logger';
import { Add, Edit, Delete } from '@mui/icons-material';
import logger from '../utils/logger';
import { useAuth } from '../contexts/AuthContext';
import logger from '../utils/logger';
import { useTranslation } from 'react-i18next';
import logger from '../utils/logger';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import logger from '../utils/logger';
import formatCurrency from '../utils/formatCurrency';
import logger from '../utils/logger';
import { fetchArrayData } from '../hooks/useApiData';
import logger from '../utils/logger';
import { useDistance } from '../hooks/useDistance';
import logger from '../utils/logger';
import useTablePagination from '../hooks/useTablePagination';
import logger from '../utils/logger';
import ConsumableDialog from '../components/ConsumableDialog';
import logger from '../utils/logger';
import ServiceDialog from '../components/ServiceDialog';
import logger from '../utils/logger';
import KnightRiderLoader from '../components/KnightRiderLoader';
import logger from '../utils/logger';
import ViewAttachmentIconButton from '../components/ViewAttachmentIconButton';
import logger from '../utils/logger';
import TablePaginationBar from '../components/TablePaginationBar';
import logger from '../utils/logger';
import VehicleSelector from '../components/VehicleSelector';
import logger from '../utils/logger';

const Consumables = () => {
  const [consumables, setConsumables] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedConsumable, setSelectedConsumable] = useState(null);
  const [openServiceDialog, setOpenServiceDialog] = useState(false);
  const [selectedServiceRecord, setSelectedServiceRecord] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('consumablesSortBy') || 'description');
  const [order, setOrder] = useState(() => localStorage.getItem('consumablesSortOrder') || 'asc');
  const { api } = useAuth();
  const { t, i18n } = useTranslation();
  const registrationLabelText = t('common.registrationNumber');
  const regWords = (registrationLabelText || '').split(/\s+/).filter(Boolean);
  const regLast = regWords.length > 0 ? regWords[regWords.length - 1] : '';
  const regFirst = regWords.length > 1 ? regWords.slice(0, regWords.length - 1).join(' ') : (regWords[0] || 'Registration');
  const { convert, format, getLabel } = useDistance();
  const mileageLabelText = t('consumables.mileageAtChange');
  const mileageWords = (mileageLabelText || '').split(/\s+/).filter(Boolean);
  // Put the last word on the second line and everything else on the first line
  const mileageLast = mileageWords.length > 0 ? mileageWords[mileageWords.length - 1] : '';
  const mileageFirst = mileageWords.length > 1 ? mileageWords.slice(0, mileageWords.length - 1).join(' ') : (mileageWords[0] || 'Mileage at');
  let mileageRest = mileageLast || 'Change';
    const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();
    const [hasManualSelection, setHasManualSelection] = useState(false);

  const loadVehicles = useCallback(async () => {
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    if (data.length > 0 && !selectedVehicle) {
      if (defaultVehicleId && data.find((v) => String(v.id) === String(defaultVehicleId))) {
        setSelectedVehicle(defaultVehicleId);
      } else {
        setSelectedVehicle(data[0].id);
      }
    }
    setLoading(false);
  }, [api, selectedVehicle, defaultVehicleId]);

  const loadConsumables = useCallback(async () => {
    try {
      const url = (!selectedVehicle || selectedVehicle === '__all__') ? '/consumables' : `/consumables?vehicleId=${selectedVehicle}`;
      const response = await api.get(url);
      setConsumables(response.data);
    } catch (error) {
      logger.error('Error loading consumables:', error);
    }
  }, [api, selectedVehicle]);

  useEffect(() => {
    loadVehicles();
  }, [loadVehicles]);

  useEffect(() => {
    if (!defaultVehicleId) return;
    if (hasManualSelection) return;
    if (!vehicles || vehicles.length === 0) return;
    const found = vehicles.find((v) => String(v.id) === String(defaultVehicleId));
    if (found && String(selectedVehicle) !== String(defaultVehicleId)) {
      setSelectedVehicle(defaultVehicleId);
    }
  }, [defaultVehicleId, vehicles, hasManualSelection]);

  useEffect(() => {
    if (selectedVehicle) {
      loadConsumables();
    } else {
      setConsumables([]);
    }
  }, [selectedVehicle, loadConsumables]);

  const handleAdd = () => {
    setSelectedConsumable(null);
    setDialogOpen(true);
  };

  const handleEdit = (consumable) => {
    setSelectedConsumable(consumable);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (!id) return;
    if (window.confirm(t('common.confirmDelete'))) {
      try {
        await api.delete(`/consumables/${id}`);
        loadConsumables();
      } catch (error) {
        logger.error('Error deleting consumable:', error);
        window.alert(t('common.deleteFailed'));
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
      if (orderBy === 'registration') {
        const aReg = vehicles.find(v => String(v.id) === String(a.vehicleId))?.registrationNumber || '';
        const bReg = vehicles.find(v => String(v.id) === String(b.vehicleId))?.registrationNumber || '';
        if (aReg === bReg) return 0;
        return order === 'asc' ? (aReg > bReg ? 1 : -1) : (aReg < bReg ? 1 : -1);
      }
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

  const { page, rowsPerPage, paginatedRows: paginatedConsumables, handleChangePage, handleChangeRowsPerPage } = useTablePagination(sortedConsumables);

  const calculateTotalCost = () => {
    return consumables.reduce((sum, consumable) => sum + (parseFloat(consumable.cost) || 0), 0);
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
          <VehicleSelector
            vehicles={vehicles}
            value={selectedVehicle}
            onChange={(v) => { setHasManualSelection(true); setSelectedVehicle(v); setDefaultVehicle(v); }}
            includeViewAll={true}
            minWidth={360}
          />
          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={handleAdd}
            disabled={!selectedVehicle || selectedVehicle === '__all__'}
          >
            {t('consumables.addConsumable')}
          </Button>
        </Box>
      </Box>

      {consumables.length > 0 && (
        <Box mb={2}>
            <Typography variant="h6">
              {t('consumables.totalCost')}: {formatCurrency(calculateTotalCost(), 'GBP', i18n.language)}
            </Typography>
          </Box>
      )}

      <TablePaginationBar
        count={sortedConsumables.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />
      <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
        <Table stickyHeader>
          <TableHead>
            <TableRow>
                <TableCell sx={{ width: 120 }}>
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
                <TableCell sx={{ width: 180 }}>
                      <TableSortLabel
                        active={orderBy === 'consumableType'}
                        direction={orderBy === 'consumableType' ? order : 'asc'}
                        onClick={() => handleRequestSort('consumableType')}
                      >
                        {t('common.type')}
                      </TableSortLabel>
                    </TableCell>
              <TableCell sx={{ minWidth: 240 }}>
                <TableSortLabel
                  active={orderBy === 'description'}
                  direction={orderBy === 'description' ? order : 'asc'}
                  onClick={() => handleRequestSort('description')}
                >
                  {t('consumables.name')}
                </TableSortLabel>
              </TableCell>
                <TableCell sx={{ width: 140 }}>
                  <TableSortLabel
                    active={orderBy === 'cost'}
                    direction={orderBy === 'cost' ? order : 'asc'}
                    onClick={() => handleRequestSort('cost')}
                  >
                    {t('consumables.cost')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ width: 140 }}>
                  <TableSortLabel
                    active={orderBy === 'quantity'}
                    direction={orderBy === 'quantity' ? order : 'asc'}
                    onClick={() => handleRequestSort('quantity')}
                  >
                    {t('consumables.quantity')}
                  </TableSortLabel>
                </TableCell>
              <TableCell sx={{ width: 160 }}>
                <TableSortLabel
                  active={orderBy === 'lastChanged'}
                  direction={orderBy === 'lastChanged' ? order : 'asc'}
                  onClick={() => handleRequestSort('lastChanged')}
                >
                  {t('consumables.lastChanged')}
                </TableSortLabel>
              </TableCell>
              <TableCell sx={{ width: 180 }}>
                <TableSortLabel
                  active={orderBy === 'mileageAtChange'}
                  direction={orderBy === 'mileageAtChange' ? order : 'asc'}
                  onClick={() => handleRequestSort('mileageAtChange')}
                >
                  <div style={{ display: 'flex', flexDirection: 'column', whiteSpace: 'normal', lineHeight: 1 }}>
                    <span>{mileageFirst}</span>
                    <span style={{ color: 'inherit' }}>{mileageRest ? `${mileageRest} (${getLabel()})` : `(${getLabel()})`}</span>
                  </div>
                </TableSortLabel>
              </TableCell>
              <TableCell sx={{ minWidth: 200 }}>{t('common.linkedRecords') || 'Linked Records'}</TableCell>
              <TableCell sx={{ width: 140 }}>{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedConsumables.length === 0 ? (
              <TableRow>
                <TableCell colSpan={9} align="center">
                  <Typography color="textSecondary">{t('common.noRecords')}</Typography>
                </TableCell>
              </TableRow>
            ) : (
              paginatedConsumables.map((consumable) => (
                <TableRow key={consumable.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{vehicles.find(v => String(v.id) === String(consumable.vehicleId))?.registrationNumber || '-'}</TableCell>
                  <TableCell>
                    {consumable.consumableType?.name || '-'}
                  </TableCell>
                  <TableCell>{consumable.description || '-'}</TableCell>
                  <TableCell>{formatCurrency(parseFloat(consumable.cost) || 0, 'GBP', i18n.language)}</TableCell>
                  <TableCell>
                    {consumable.quantity} {consumable.consumableType?.unit || ''}
                  </TableCell>
                  <TableCell>{consumable.lastChanged || '-'}</TableCell>
                  <TableCell>{consumable.mileageAtChange ? format(convert(consumable.mileageAtChange)) : '-'}</TableCell>
                  <TableCell>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                      <div>
                        {consumable.motTestNumber ? `${consumable.motTestNumber}${consumable.motTestDate ? ' (' + consumable.motTestDate + ')' : ''}` : '-'}
                      </div>
                      <div>
                        {consumable.serviceRecordId ? (
                          <button onClick={async (e) => { e.preventDefault(); try { const resp = await api.get(`/service-records/${consumable.serviceRecordId}`); setSelectedServiceRecord(resp.data); setOpenServiceDialog(true); } catch (err) { logger.error('Failed to load service record', err); } }} style={{ background: 'none', border: 'none', padding: 0, textDecoration: 'underline', cursor: 'pointer', color: 'inherit' }}>
                            {consumable.serviceRecordDate ? t('service.serviceLabelDate', { date: consumable.serviceRecordDate }) : t('service.serviceLabelId', { id: consumable.serviceRecordId })}
                          </button>
                        ) : '-'}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <ViewAttachmentIconButton record={consumable} />
                    <Tooltip title={t('common.edit')}>
                      <IconButton size="small" onClick={() => handleEdit(consumable)}>
                        <Edit />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title={t('common.delete')}>
                      <IconButton
                        size="small"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleDelete(consumable.id);
                        }}
                      >
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
        count={sortedConsumables.length}
        page={page}
        rowsPerPage={rowsPerPage}
        onPageChange={handleChangePage}
        onRowsPerPageChange={handleChangeRowsPerPage}
      />

      <ConsumableDialog
        open={dialogOpen}
        onClose={handleDialogClose}
        consumable={selectedConsumable}
        vehicleId={selectedVehicle}
      />
      <ServiceDialog
        open={openServiceDialog}
        serviceRecord={selectedServiceRecord}
        vehicleId={selectedVehicle}
        onClose={(saved) => {
          setOpenServiceDialog(false);
          setSelectedServiceRecord(null);
          if (saved) loadConsumables();
        }}
      />
    </Box>
  );
};

export default Consumables;
