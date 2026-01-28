import React, { useEffect, useState, useCallback } from 'react';
import { Box, Button, Typography, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, IconButton, CircularProgress, Chip, Tooltip, TableSortLabel } from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import VehicleSelector from '../components/VehicleSelector';
import { useTranslation } from 'react-i18next';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import formatCurrency from '../utils/formatCurrency';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import PartDialog from '../components/PartDialog';
import ServiceDialog from '../components/ServiceDialog';

const Parts = () => {
  const [parts, setParts] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState('');
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedPart, setSelectedPart] = useState(null);
  const [openServiceDialog, setOpenServiceDialog] = useState(false);
  const [selectedServiceRecord, setSelectedServiceRecord] = useState(null);
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('partsSortBy') || 'description');
  const [order, setOrder] = useState(() => localStorage.getItem('partsSortOrder') || 'asc');
  const { api } = useAuth();
  const { t, i18n } = useTranslation();
  const registrationLabelText = t('common.registrationNumber');
  const regWords = (registrationLabelText || '').split(/\s+/).filter(Boolean);
  const regLast = regWords.length > 0 ? regWords[regWords.length - 1] : '';
  const regFirst = regWords.length > 1 ? regWords.slice(0, regWords.length - 1).join(' ') : (regWords[0] || 'Registration');
  const { defaultVehicleId, setDefaultVehicle } = useUserPreferences();
  const [hasManualSelection, setHasManualSelection] = useState(false);
  const { convert, format, getLabel } = useDistance();

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

  const loadParts = useCallback(async () => {
    try {
      let response;
      if (!selectedVehicle || selectedVehicle === '__all__') {
        response = await api.get('/parts');
      } else {
        response = await api.get(`/parts?vehicleId=${selectedVehicle}`);
      }
      setParts(response.data);
    } catch (error) {
      console.error('Error loading parts:', error);
    }
  }, [api, selectedVehicle]);

  useEffect(() => {
    loadVehicles();
  }, [loadVehicles]);

  useEffect(() => {
    if (selectedVehicle) {
      loadParts();
    } else {
      setParts([]);
    }
  }, [selectedVehicle, loadParts]);

  useEffect(() => {
    if (!defaultVehicleId) return;
    if (hasManualSelection) return;
    if (!vehicles || vehicles.length === 0) return;
    const found = vehicles.find((v) => String(v.id) === String(defaultVehicleId));
    if (found && String(selectedVehicle) !== String(defaultVehicleId)) {
      setSelectedVehicle(defaultVehicleId);
    }
  }, [defaultVehicleId, vehicles, hasManualSelection]);

  const handleAdd = () => {
    setSelectedPart(null);
    setDialogOpen(true);
  };

  const handleEdit = (part) => {
    setSelectedPart(part);
    setDialogOpen(true);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('common.confirmDelete'))) {
      try {
        await api.delete(`/parts/${id}`);
        loadParts();
      } catch (error) {
        console.error('Error deleting part:', error);
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

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('partsSortBy', property);
    localStorage.setItem('partsSortOrder', newOrder);
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

  const calculateTotalCost = () => {
    return parts.reduce((sum, part) => sum + (parseFloat(part.cost) || 0), 0);
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <CircularProgress />
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
                  active={orderBy === 'manufacturer'}
                  direction={orderBy === 'manufacturer' ? order : 'asc'}
                  onClick={() => handleRequestSort('manufacturer')}
                >
                  {t('common.manufacturer')}
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
              <TableCell>{t('mot.title') || 'MOT'}</TableCell>
              <TableCell>{t('common.actions')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {sortedParts.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} align="center">
                  <Typography color="textSecondary">{t('common.noRecords')}</Typography>
                </TableCell>
              </TableRow>
            ) : (
              sortedParts.map((part) => (
                <TableRow key={part.id} sx={{ '&:nth-of-type(odd)': { backgroundColor: 'action.hover' } }}>
                  <TableCell>{vehicles.find(v => String(v.id) === String(part.vehicleId))?.registrationNumber || '-'}</TableCell>
                  <TableCell>{part.description}</TableCell>
                  <TableCell>{part.partNumber || '-'}</TableCell>
                  <TableCell>{part.category || '-'}</TableCell>
                  <TableCell>{part.manufacturer || '-'}</TableCell>
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
                          <button onClick={async (e) => { e.preventDefault(); try { const resp = await api.get(`/service-records/${part.serviceRecordId}`); setSelectedServiceRecord(resp.data); setOpenServiceDialog(true); } catch (err) { console.error('Failed to load service record', err); } }} style={{ background: 'none', border: 'none', padding: 0, textDecoration: 'underline', cursor: 'pointer', color: 'inherit' }}>
                            {part.serviceRecordDate ? t('service.serviceLabelDate', { date: part.serviceRecordDate }) : t('service.serviceLabelId', { id: part.serviceRecordId })}
                          </button>
                        ) : '-'}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
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
