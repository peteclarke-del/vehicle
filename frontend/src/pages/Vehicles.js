import React, { useEffect, useState, useCallback } from 'react';
import logger from '../utils/logger';
import {
  Box,
  Button,
  Card,
  CardContent,
  Grid,
  Typography,
  IconButton,
  ToggleButtonGroup,
  ToggleButton,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogContentText,
  DialogActions,
  Tooltip,
  TableSortLabel,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
} from '@mui/material';
import { 
  Add, 
  Edit, 
  Delete, 
  TrendingDown, 
  ViewModule as CardViewIcon,
  ViewList as TableViewIcon,
  DirectionsCar,
  TwoWheeler as MotorcycleIcon,
  LocalShipping as VanIcon,
  LocalShipping as TruckIcon,
  
  DeleteSweep as PurgeIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import logger from '../utils/logger';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import logger from '../utils/logger';
import { useAuth } from '../contexts/AuthContext';
import logger from '../utils/logger';
import { useTranslation } from 'react-i18next';
import logger from '../utils/logger';
import { fetchArrayData } from '../hooks/useApiData';
import logger from '../utils/logger';
import { useDistance } from '../hooks/useDistance';
import logger from '../utils/logger';
import useTablePagination from '../hooks/useTablePagination';
import logger from '../utils/logger';
import VehicleDialog from '../components/VehicleDialog';
import logger from '../utils/logger';
import VehicleSpecifications from '../components/VehicleSpecifications';
import logger from '../utils/logger';
import KnightRiderLoader from '../components/KnightRiderLoader';
import logger from '../utils/logger';
import TablePaginationBar from '../components/TablePaginationBar';
import logger from '../utils/logger';

const Vehicles = () => {
  const [vehicles, setVehicles] = useState([]);
  const [loading, setLoading] = useState(true);
  const { convert, format } = useDistance();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedVehicle, setSelectedVehicle] = useState(null);
  const [statusFilter, setStatusFilter] = useState(() => localStorage.getItem('vehiclesStatusFilter') || 'Live');
  const [viewMode, setViewMode] = useState(() => {
    return localStorage.getItem('vehiclesViewMode') || 'card';
  });
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('vehiclesSortBy') || 'name');
  const [order, setOrder] = useState(() => localStorage.getItem('vehiclesSortOrder') || 'asc');
  const [purgeDialogOpen, setPurgeDialogOpen] = useState(false);
  const [purgeMode, setPurgeMode] = useState('vehicles-only');
  const [detailsDialogOpen, setDetailsDialogOpen] = useState(false);
  const navigate = useNavigate();
  const { setDefaultVehicle } = useUserPreferences();
  const { api } = useAuth();
  const { t } = useTranslation();

  const loadVehicles = useCallback(async () => {
    setLoading(true);
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    setLoading(false);
  }, [api]);

  useEffect(() => {
    loadVehicles();
  }, [loadVehicles]);

  const handleViewModeChange = (event, newMode) => {
    if (newMode !== null) {
      setViewMode(newMode);
      localStorage.setItem('vehiclesViewMode', newMode);
    }
  };

  const handlePurgeClick = () => {
    setPurgeDialogOpen(true);
  };

  const handlePurgeCancel = () => {
    setPurgeDialogOpen(false);
  };

  const handlePurgeConfirm = async () => {
    setPurgeDialogOpen(false);

    try {
      const cascade = purgeMode === 'all' ? 'true' : 'false';
      const response = await api.delete(`/vehicles/purge-all?cascade=${cascade}`);
      alert(response.data.message || t('common.deleteSuccess', { count: response.data.deleted }));

      // Reload vehicles
      loadVehicles();
    } catch (error) {
      logger.error('Purge failed:', error);
      alert(t('common.deleteFailed'));
    }
  };

  const handleRequestSort = (property) => {
    const isAsc = orderBy === property && order === 'asc';
    const newOrder = isAsc ? 'desc' : 'asc';
    setOrder(newOrder);
    setOrderBy(property);
    localStorage.setItem('vehiclesSortBy', property);
    localStorage.setItem('vehiclesSortOrder', newOrder);
  };

  const sortedVehicles = React.useMemo(() => {
    const comparator = (a, b) => {
      let aValue = a[orderBy];
      let bValue = b[orderBy];

      if (orderBy === 'currentMileage') {
        aValue = parseFloat(a.currentMileage) || 0;
        bValue = parseFloat(b.currentMileage) || 0;
      }

      if (orderBy === 'purchaseMileage') {
        aValue = parseFloat(a.purchaseMileage) || 0;
        bValue = parseFloat(b.purchaseMileage) || 0;
      }

      if (orderBy === 'year') {
        aValue = parseInt(a.year) || 0;
        bValue = parseInt(b.year) || 0;
      }

      if (orderBy === 'vehicleType') {
        aValue = a.vehicleType?.name || '';
        bValue = b.vehicleType?.name || '';
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

    return [...vehicles].sort(comparator);
  }, [vehicles, order, orderBy]);

  const displayedVehicles = React.useMemo(() => {
    return sortedVehicles.filter(v => statusFilter === 'all' || (v.status || 'Live') === statusFilter);
  }, [sortedVehicles, statusFilter]);

  const { page, rowsPerPage, paginatedRows: paginatedVehicles, handleChangePage, handleChangeRowsPerPage } = useTablePagination(displayedVehicles);

  const handleAdd = () => {
    setSelectedVehicle(null);
    setDialogOpen(true);
  };

  const handleEdit = (vehicle) => {
    setSelectedVehicle(vehicle);
    setDialogOpen(true);
  };

  const handleViewDetails = (vehicle) => {
    // remember this vehicle as the default
    try { setDefaultVehicle(vehicle.id); } catch (e) {}
    setSelectedVehicle(vehicle);
    setDetailsDialogOpen(true);
  };

  const handleStatusFilterChange = (e) => {
    const val = e.target.value;
    setStatusFilter(val);
    try { localStorage.setItem('vehiclesStatusFilter', val); } catch (e) {}
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('vehicle.deleteConfirm'))) {
      try {
        await api.delete(`/vehicles/${id}`);
        loadVehicles();
      } catch (error) {
        logger.error('Error deleting vehicle:', error);
      }
    }
  };

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    setSelectedVehicle(null);
    if (reload) {
      loadVehicles();
    }
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <KnightRiderLoader size={32} />
      </Box>
    );
  }

  const getVehicleIcon = (vehicleType) => {
    const iconProps = { fontSize: 28, opacity: 0.9 };
    if (!vehicleType) return <DirectionsCar sx={iconProps} />;
    switch (vehicleType.name) {
      case 'Motorcycle':
        return <MotorcycleIcon sx={iconProps} />;
      case 'Van':
        return <VanIcon sx={iconProps} />;
      case 'Truck':
        return <TruckIcon sx={iconProps} />;
      case 'Car':
      default:
        return <DirectionsCar sx={iconProps} />;
    }
  };

  const regLabelParts = t('common.registrationNumber').split(' ');
  const purchaseLabelParts = t('vehicle.purchaseMileage').split(' ');
  const currentLabelParts = t('vehicle.currentMileage').split(' ');

  const statusOptions = [
    { key: 'all', label: t('vehicles.filterAll') || 'All' },
    { key: 'Live', label: t('vehicle.status.live') || 'Live' },
    { key: 'Sold', label: t('vehicle.status.sold') || 'Sold' },
    { key: 'Scrapped', label: t('vehicle.status.scrapped') || 'Scrapped' },
    { key: 'Exported', label: t('vehicle.status.exported') || 'Exported' },
  ];

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Box display="flex" alignItems="center" gap={2}>
          <Typography variant="h4">{(vehicles && vehicles.length > 0) ? t('vehicles.titleWithCount', { count: vehicles.filter(v => (v.status || 'Live') === 'Live').length }) : t('vehicle.title')}</Typography>
          <FormControl size="small" sx={{ minWidth: 160 }}>
            <InputLabel id="vehicle-status-filter-label">{t('vehicles.filterByStatus') || 'Status'}</InputLabel>
            <Select
              labelId="vehicle-status-filter-label"
              value={statusFilter}
              label={t('vehicles.filterByStatus') || 'Status'}
              onChange={handleStatusFilterChange}
              size="small"
            >
              {statusOptions.map(opt => (
                <MenuItem key={opt.key} value={opt.key}>{opt.label}</MenuItem>
              ))}
            </Select>
          </FormControl>
        </Box>
        <Box display="flex" gap={2} alignItems="center">
          <ToggleButtonGroup
            value={viewMode}
            exclusive
            onChange={handleViewModeChange}
            size="small"
          >
            <ToggleButton value="card">
              <CardViewIcon />
            </ToggleButton>
            <ToggleButton value="table">
              <TableViewIcon />
            </ToggleButton>
          </ToggleButtonGroup>

          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={handleAdd}
          >
            {t('vehicle.addVehicle')}
          </Button>
          
          <Button
            variant="outlined"
            color="error"
            startIcon={<PurgeIcon />}
            onClick={handlePurgeClick}
          >
            {t('deleteAll.button')}
          </Button>
        </Box>
      </Box>

      <Dialog
        open={purgeDialogOpen}
        onClose={handlePurgeCancel}
      >
        <DialogTitle>{t('deleteAll.title')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {t('deleteAll.message')}
          </DialogContentText>

          <Box sx={{ mt: 2 }}>
            <FormControl component="fieldset">
              <Typography variant="subtitle1" sx={{ mb: 1 }}>{t('deleteAll.chooseOption') || 'Choose deletion scope'}</Typography>
              <ToggleButtonGroup
                value={purgeMode}
                exclusive
                onChange={(e, val) => { if (val) setPurgeMode(val); }}
                size="small"
              >
                <ToggleButton value="vehicles-only">{t('deleteAll.vehiclesOnly') || 'Vehicles only'}</ToggleButton>
                <ToggleButton value="all">{t('deleteAll.vehiclesAndAllData') || 'Vehicles and ALL associated data'}</ToggleButton>
              </ToggleButtonGroup>
            </FormControl>
            <DialogContentText sx={{ mt: 2 }}>
              {t('deleteAll.confirm')}
            </DialogContentText>
          </Box>

        </DialogContent>
        <DialogActions>
          <Button onClick={handlePurgeCancel}>{t('common.cancel')}</Button>
          <Button onClick={handlePurgeConfirm} color="error" variant="contained">
            {t('deleteAll.button')}
          </Button>
        </DialogActions>
      </Dialog>

      {viewMode === 'card' ? (
        <Grid container spacing={3}>
          {displayedVehicles.map((vehicle) => (
            <Grid key={vehicle.id} item xs={12} sm={6} md={4}>
                      <Card sx={{ height: '100%', cursor: 'pointer' }} onClick={() => handleViewDetails(vehicle)}>
                <CardContent>
                  <Box display="flex" justifyContent="space-between" alignItems="start">
                    <Box display="flex" alignItems="center" gap={1} flex={1}>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        {getVehicleIcon(vehicle.vehicleType)}
                        <Typography variant="h6" gutterBottom sx={{ mb: 0 }}>
                          {vehicle.name}
                        </Typography>
                      </Box>
                    </Box>
                    <Box>
                      <Tooltip title={t('common.edit')}>
                        <IconButton size="small" onClick={(e) => { e.stopPropagation(); handleEdit(vehicle); }}>
                          <Edit fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={t('common.delete')}>
                        <IconButton size="small" onClick={(e) => { e.stopPropagation(); handleDelete(vehicle.id); }}>
                          <Delete fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </Box>
                  </Box>
                  <Typography color="textSecondary" gutterBottom>
                    {vehicle.make} {vehicle.model} ({vehicle.year})
                  </Typography>
                  <Box>
                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                      {regLabelParts[0]}
                    </Typography>
                    {regLabelParts.length > 1 && (
                      <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                        {regLabelParts.slice(1).join(' ')}
                      </Typography>
                    )}
                    <Typography variant="body2">
                      {vehicle.registrationNumber || t('na')}
                    </Typography>
                  </Box>
                  <Typography variant="body2">
                    {t('vehicle.colour')}: {vehicle.colour || t('na')}
                  </Typography>
                  <Typography variant="body2">
                    {t('vehicle.purchaseMileage')}: {vehicle.purchaseMileage ? format(convert(vehicle.purchaseMileage)) : t('na')}
                  </Typography>
                  <Typography variant="body2">
                    {t('vehicle.currentMileage')}: {vehicle.currentMileage ? format(convert(vehicle.currentMileage)) : t('na')}
                  </Typography>
                  <Box mt={2}>
                    <Button
                      fullWidth
                      variant="outlined"
                      startIcon={<TrendingDown />}
                      onClick={() => { setDefaultVehicle(vehicle.id); navigate(`/vehicles/${vehicle.id}`); }}
                    >
                      {t('vehicleCard.viewDetails')}
                    </Button>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      ) : (
            <>
              <TablePaginationBar
                count={displayedVehicles.length}
                page={page}
                rowsPerPage={rowsPerPage}
                onPageChange={handleChangePage}
                onRowsPerPageChange={handleChangeRowsPerPage}
              />
              <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
              <Table stickyHeader>
                <TableHead>
              <TableRow>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem', width: 72 }}>
                  <TableSortLabel
                    active={orderBy === 'vehicleType'}
                    direction={orderBy === 'vehicleType' ? order : 'asc'}
                    onClick={() => handleRequestSort('vehicleType')}
                  >
                    {t('vehicle.vehicleType')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'name'}
                    direction={orderBy === 'name' ? order : 'asc'}
                    onClick={() => handleRequestSort('name')}
                  >
                    {t('vehicle.name')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'make'}
                    direction={orderBy === 'make' ? order : 'asc'}
                    onClick={() => handleRequestSort('make')}
                  >
                    {t('vehicle.make')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'model'}
                    direction={orderBy === 'model' ? order : 'asc'}
                    onClick={() => handleRequestSort('model')}
                  >
                    {t('vehicle.model')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'year'}
                    direction={orderBy === 'year' ? order : 'asc'}
                    onClick={() => handleRequestSort('year')}
                  >
                    {t('common.year')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'registrationNumber'}
                    direction={orderBy === 'registrationNumber' ? order : 'asc'}
                    onClick={() => handleRequestSort('registrationNumber')}
                  >
                    <Box sx={{ display: 'flex', flexDirection: 'column', lineHeight: 1 }}>
                      <span>{regLabelParts[0]}</span>
                      <span>{regLabelParts.length > 1 ? regLabelParts.slice(1).join(' ') : ''}</span>
                    </Box>
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'colour'}
                    direction={orderBy === 'colour' ? order : 'asc'}
                    onClick={() => handleRequestSort('colour')}
                  >
                    {t('vehicle.colour')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'purchaseMileage'}
                    direction={orderBy === 'purchaseMileage' ? order : 'asc'}
                    onClick={() => handleRequestSort('purchaseMileage')}
                  >
                    <Box sx={{ display: 'flex', flexDirection: 'column', lineHeight: 1 }}>
                      <span>{purchaseLabelParts[0]}</span>
                      <span>{purchaseLabelParts.length > 1 ? purchaseLabelParts.slice(1).join(' ') : ''}</span>
                    </Box>
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'currentMileage'}
                    direction={orderBy === 'currentMileage' ? order : 'asc'}
                    onClick={() => handleRequestSort('currentMileage')}
                  >
                    <Box sx={{ display: 'flex', flexDirection: 'column', lineHeight: 1 }}>
                      <span>{currentLabelParts[0]}</span>
                      <span>{currentLabelParts.length > 1 ? currentLabelParts.slice(1).join(' ') : ''}</span>
                    </Box>
                  </TableSortLabel>
                </TableCell>
                <TableCell align="right" sx={{ fontWeight: 'bold', fontSize: '1rem' }}>{t('common.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {paginatedVehicles.map((vehicle, index) => (
                <TableRow 
                  key={vehicle.id}
                  hover
                  sx={{ 
                    cursor: 'pointer',
                    '&:nth-of-type(odd)': {
                      backgroundColor: 'action.hover',
                    },
                  }}
                  onClick={() => { setDefaultVehicle(vehicle.id); navigate(`/vehicles/${vehicle.id}`); }}
                >
                  <TableCell>
                    <Box display="flex" alignItems="center" gap={1}>
                      {getVehicleIcon(vehicle.vehicleType)}
                    </Box>
                  </TableCell>
                  <TableCell>{vehicle.name}</TableCell>
                  <TableCell>{vehicle.make}</TableCell>
                  <TableCell>{vehicle.model}</TableCell>
                  <TableCell>{vehicle.year || t('vehicleDetails.na')}</TableCell>
                  <TableCell>{vehicle.registrationNumber || t('vehicleDetails.na')}</TableCell>
                  <TableCell>{vehicle.colour || t('vehicleDetails.na')}</TableCell>
                  <TableCell>{vehicle.purchaseMileage ? format(convert(vehicle.purchaseMileage)) : t('vehicleDetails.na')}</TableCell>
                  <TableCell>{vehicle.currentMileage ? format(convert(vehicle.currentMileage)) : t('vehicleDetails.na')}</TableCell>
                  <TableCell align="right">
                    <Tooltip title={t('common.edit')}>
                      <IconButton 
                        size="small" 
                        onClick={(e) => {
                          e.stopPropagation();
                          handleEdit(vehicle);
                        }}
                      >
                        <Edit fontSize="small" />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title={t('common.delete')}>
                      <IconButton 
                        size="small" 
                        onClick={(e) => {
                          e.stopPropagation();
                          handleDelete(vehicle.id);
                        }}
                      >
                        <Delete fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
        <TablePaginationBar
          count={displayedVehicles.length}
          page={page}
          rowsPerPage={rowsPerPage}
          onPageChange={handleChangePage}
          onRowsPerPageChange={handleChangeRowsPerPage}
        />
      </>
      )}

      <VehicleDialog
        open={dialogOpen}
        vehicle={selectedVehicle}
        onClose={handleDialogClose}
      />

      <Dialog
        open={detailsDialogOpen}
        onClose={() => setDetailsDialogOpen(false)}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>
          {selectedVehicle?.name}
          <Typography variant="body2" color="text.secondary">
            {selectedVehicle?.make} {selectedVehicle?.model} {selectedVehicle?.year}
          </Typography>
        </DialogTitle>
        <DialogContent>
          <Box sx={{ mt: 2 }}>
            {selectedVehicle && (
              <VehicleSpecifications vehicle={selectedVehicle} />
            )}
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDetailsDialogOpen(false)}>{t('common.cancel')}</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default Vehicles;
