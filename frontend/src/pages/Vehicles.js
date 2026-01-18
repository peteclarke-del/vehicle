import React, { useEffect, useState } from 'react';
import {
  Box,
  Button,
  Card,
  CardContent,
  Grid,
  Typography,
  CircularProgress,
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
  Tabs,
  Tab,
} from '@mui/material';
import { 
  Add, 
  Edit, 
  Delete, 
  TrendingDown, 
  ViewModule as CardViewIcon,
  ViewList as TableViewIcon,
  DragIndicator as DragIndicatorIcon,
  FileDownload as ExportIcon,
  FileUpload as ImportIcon,
  DeleteSweep as PurgeIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { fetchArrayData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import VehicleDialog from '../components/VehicleDialog';
import VehicleSpecifications from '../components/VehicleSpecifications';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

const Vehicles = () => {
  const [vehicles, setVehicles] = useState([]);
  const [loading, setLoading] = useState(true);
  const { convert, format } = useDistance();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedVehicle, setSelectedVehicle] = useState(null);
  const [orderedVehicles, setOrderedVehicles] = useState([]);
  const [viewMode, setViewMode] = useState(() => {
    return localStorage.getItem('vehiclesViewMode') || 'card';
  });
  const [orderBy, setOrderBy] = useState(() => localStorage.getItem('vehiclesSortBy') || 'name');
  const [order, setOrder] = useState(() => localStorage.getItem('vehiclesSortOrder') || 'asc');
  const [purgeDialogOpen, setPurgeDialogOpen] = useState(false);
  const [detailsDialogOpen, setDetailsDialogOpen] = useState(false);
  const [selectedTabValue, setSelectedTabValue] = useState(0);
  const fileInputRef = React.useRef(null);
  const navigate = useNavigate();
  const { api } = useAuth();
  const { t } = useTranslation();

  useEffect(() => {
    loadVehicles();
  }, []);

  useEffect(() => {
    setOrderedVehicles(vehicles);
  }, [vehicles]);

  const loadVehicles = async () => {
    setLoading(true);
    const data = await fetchArrayData(api, '/vehicles');
    setVehicles(data);
    setLoading(false);
  };

  const handleViewModeChange = (event, newMode) => {
    if (newMode !== null) {
      setViewMode(newMode);
      localStorage.setItem('vehiclesViewMode', newMode);
    }
  };

  const handleDragEnd = (result) => {
    if (!result.destination) return;

    const items = Array.from(orderedVehicles);
    const [reorderedItem] = items.splice(result.source.index, 1);
    items.splice(result.destination.index, 0, reorderedItem);

    setOrderedVehicles(items);

    const orderMap = {};
    items.forEach((vehicle, index) => {
      orderMap[vehicle.id] = index;
    });
    localStorage.setItem('vehiclesCustomOrder', JSON.stringify(orderMap));
  };

  const handleDragStart = () => {
    // Custom ordering handled by drag and drop
  };

  const handleExport = async () => {
    try {
      const response = await api.get('/vehicles/export');
      const json = JSON.stringify(response.data, null, 2);
      const blob = new Blob([json], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `vehicles_export_${new Date().toISOString().split('T')[0]}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Export failed:', error);
      alert(t('common.exportFailed'));
    }
  };

  const handleImportClick = () => {
    fileInputRef.current?.click();
  };

  const handleImportFile = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    try {
      const text = await file.text();
      const data = JSON.parse(text);
      
      const response = await api.post('/vehicles/import', data);
      
      if (response.data.errors && response.data.errors.length > 0) {
        alert(t('common.importWarnings', { 
          imported: response.data.imported, 
          total: response.data.total, 
          errors: response.data.errors.join('\n')
        }));
      } else {
        alert(t('common.importSuccess', { count: response.data.imported }));
      }
      
      // Reload vehicles
      loadVehicles();
    } catch (error) {
      console.error('Import failed:', error);
      alert(t('common.importFailed'));
    } finally {
      // Reset file input
      event.target.value = '';
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
      const response = await api.delete('/vehicles/purge-all');
      alert(response.data.message || t('common.deleteSuccess', { count: response.data.deleted }));
      
      // Reload vehicles
      loadVehicles();
    } catch (error) {
      console.error('Purge failed:', error);
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

      if (orderBy === 'year') {
        aValue = parseInt(a.year) || 0;
        bValue = parseInt(b.year) || 0;
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

  const handleAdd = () => {
    setSelectedVehicle(null);
    setDialogOpen(true);
  };

  const handleEdit = (vehicle) => {
    setSelectedVehicle(vehicle);
    setDialogOpen(true);
  };

  const handleViewDetails = (vehicle) => {
    setSelectedVehicle(vehicle);
    setSelectedTabValue(0);
    setDetailsDialogOpen(true);
  };

  const handleTabChange = (event, newValue) => {
    setSelectedTabValue(newValue);
  };

  const handleDelete = async (id) => {
    if (window.confirm(t('vehicle.deleteConfirm'))) {
      try {
        await api.delete(`/vehicles/${id}`);
        loadVehicles();
      } catch (error) {
        console.error('Error deleting vehicle:', error);
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
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <input
        type="file"
        ref={fileInputRef}
        onChange={handleImportFile}
        accept=".json"
        style={{ display: 'none' }}
      />
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">{t('vehicle.title')}</Typography>
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
            variant="outlined"
            startIcon={<ImportIcon />}
            onClick={handleImportClick}
          >
            Import
          </Button>
          
          <Button
            variant="outlined"
            startIcon={<ExportIcon />}
            onClick={handleExport}
          >
            Export
          </Button>
          
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
            <br /><br />
            {t('deleteAll.confirm')}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={handlePurgeCancel}>{t('common.cancel')}</Button>
          <Button onClick={handlePurgeConfirm} color="error" variant="contained">
            {t('deleteAll.button')}
          </Button>
        </DialogActions>
      </Dialog>

      {viewMode === 'card' ? (
        <DragDropContext onDragEnd={handleDragEnd} onDragStart={handleDragStart}>
          <Droppable droppableId="vehicles">
            {(provided) => (
              <Box
                ref={provided.innerRef}
                {...provided.droppableProps}
              >
                <Grid container spacing={3}>
                  {sortedVehicles.map((vehicle, index) => (
                    <Draggable key={vehicle.id} draggableId={String(vehicle.id)} index={index}>
                      {(provided, snapshot) => (
                        <Grid 
                          item 
                          xs={12} 
                          sm={6} 
                          md={4}
                          ref={provided.innerRef}
                          {...provided.draggableProps}
                          style={{
                            ...provided.draggableProps.style,
                            transition: 'transform 0.2s ease',
                          }}
                        >
                          <Card sx={{
                            transform: snapshot.isDragging ? 'rotate(2deg)' : 'none',
                            boxShadow: snapshot.isDragging ? 8 : 1,
                            opacity: snapshot.isDragging ? 0.9 : 1,
                            height: '100%',
                            cursor: 'pointer',
                          }}
                          onClick={() => handleViewDetails(vehicle)}
                          >
                                <CardContent>
                                  <Box display="flex" justifyContent="space-between" alignItems="start">
                                    <Box display="flex" alignItems="center" gap={1} flex={1}>
                                      <Box 
                                        {...provided.dragHandleProps} 
                                        sx={{ 
                                          display: 'flex', 
                                          alignItems: 'center', 
                                          cursor: 'grab',
                                          '&:active': {
                                            cursor: 'grabbing',
                                          }
                                        }}
                                      >
                                        <DragIndicatorIcon sx={{ fontSize: 24, color: 'text.primary' }} />
                                      </Box>
                                      <Typography variant="h6" gutterBottom sx={{ mb: 0 }}>
                                        {vehicle.name}
                                      </Typography>
                                    </Box>
                                    <Box>
                                      <Tooltip title="Edit">
                                        <IconButton size="small" onClick={(e) => { e.stopPropagation(); handleEdit(vehicle); }}>
                                          <Edit fontSize="small" />
                                        </IconButton>
                                      </Tooltip>
                                      <Tooltip title="Delete">
                                        <IconButton size="small" onClick={(e) => { e.stopPropagation(); handleDelete(vehicle.id); }}>
                                          <Delete fontSize="small" />
                                        </IconButton>
                                      </Tooltip>
                                    </Box>
                                  </Box>
                                  <Typography color="textSecondary" gutterBottom>
                                    {vehicle.make} {vehicle.model} ({vehicle.year})
                                  </Typography>
                                  <Typography variant="body2">
                                    {t('vehicle.registrationNumber')}: {vehicle.registrationNumber || 'N/A'}
                                  </Typography>
                                  <Typography variant="body2">
                                    {t('vehicle.currentMileage')}: {vehicle.currentMileage ? format(convert(vehicle.currentMileage)) : 'N/A'}
                                  </Typography>
                                  <Box mt={2}>
                                    <Button
                                      fullWidth
                                      variant="outlined"
                                      startIcon={<TrendingDown />}
                                      onClick={() => !snapshot.isDragging && navigate(`/vehicles/${vehicle.id}`)}
                                    >
                                      {t('vehicleCard.viewDetails')}
                                    </Button>
                                  </Box>
                                </CardContent>
                              </Card>
                            </Grid>
                          )}
                        </Draggable>
                      ))}
                    </Grid>
                    {provided.placeholder}
                  </Box>
                )}
              </Droppable>
            </DragDropContext>
          ) : (
            <TableContainer component={Paper} sx={{ maxHeight: 'calc(100vh - 180px)', overflow: 'auto' }}>
              <Table stickyHeader>
                <TableHead>
              <TableRow>
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
                    {t('vehicle.year')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'registrationNumber'}
                    direction={orderBy === 'registrationNumber' ? order : 'asc'}
                    onClick={() => handleRequestSort('registrationNumber')}
                  >
                    {t('vehicle.registrationNumber')}
                  </TableSortLabel>
                </TableCell>
                <TableCell sx={{ fontWeight: 'bold', fontSize: '1rem' }}>
                  <TableSortLabel
                    active={orderBy === 'currentMileage'}
                    direction={orderBy === 'currentMileage' ? order : 'asc'}
                    onClick={() => handleRequestSort('currentMileage')}
                  >
                    {t('vehicle.currentMileage')}
                  </TableSortLabel>
                </TableCell>
                <TableCell align="right" sx={{ fontWeight: 'bold', fontSize: '1rem' }}>{t('common.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {sortedVehicles.map((vehicle, index) => (
                <TableRow 
                  key={vehicle.id}
                  hover
                  sx={{ 
                    cursor: 'pointer',
                    '&:nth-of-type(odd)': {
                      backgroundColor: 'action.hover',
                    },
                  }}
                  onClick={() => navigate(`/vehicles/${vehicle.id}`)}
                >
                  <TableCell>{vehicle.name}</TableCell>
                  <TableCell>{vehicle.make}</TableCell>
                  <TableCell>{vehicle.model}</TableCell>
                  <TableCell>{vehicle.year || 'N/A'}</TableCell>
                  <TableCell>{vehicle.registrationNumber || 'N/A'}</TableCell>
                  <TableCell>{vehicle.currentMileage ? format(convert(vehicle.currentMileage)) : 'N/A'}</TableCell>
                  <TableCell align="right">
                    <Tooltip title="Edit">
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
                    <Tooltip title="Delete">
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
          <Tabs value={selectedTabValue} onChange={handleTabChange} sx={{ borderBottom: 1, borderColor: 'divider' }}>
            <Tab label="Details" />
            <Tab label="Specifications" />
          </Tabs>
          <Box sx={{ mt: 2 }}>
            {selectedTabValue === 0 && (
              <Box>
                <Typography variant="body1" paragraph>
                  <strong>Registration:</strong> {selectedVehicle?.registrationNumber || 'N/A'}
                </Typography>
                <Typography variant="body1" paragraph>
                  <strong>VIN:</strong> {selectedVehicle?.vin || 'N/A'}
                </Typography>
                <Typography variant="body1" paragraph>
                  <strong>Current Mileage:</strong> {selectedVehicle?.currentMileage ? format(convert(selectedVehicle.currentMileage)) : 'N/A'}
                </Typography>
                <Typography variant="body1" paragraph>
                  <strong>Type:</strong> {selectedVehicle?.vehicleType?.name || 'N/A'}
                </Typography>
              </Box>
            )}
            {selectedTabValue === 1 && selectedVehicle && (
              <VehicleSpecifications vehicle={selectedVehicle} />
            )}
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDetailsDialogOpen(false)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default Vehicles;
