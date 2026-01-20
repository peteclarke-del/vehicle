import React, { useState, useEffect } from 'react';
import {
  Container,
  Card,
  CardContent,
  Typography,
  Box,
  Chip,
  Button,
  Tooltip,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Paper,
  IconButton,
  CircularProgress,
} from '@mui/material';
import { PieChart } from '@mui/x-charts/PieChart';
import {
  Add as AddIcon,
  DirectionsCar,
  Warning as WarningIcon,
  CheckCircle as CheckCircleIcon,
  TwoWheeler as MotorcycleIcon,
  LocalShipping as VanIcon,
  LocalShipping as TruckIcon,
  DirectionsCar as CarIcon,
  Event as EventIcon,
  Build as BuildIcon,
  Settings as SettingsIcon,
  Visibility as VisibilityIcon,
  VisibilityOff as VisibilityOffIcon,
} from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useApiData } from '../hooks/useApiData';
import { useDistance } from '../hooks/useDistance';
import VehicleDialog from '../components/VehicleDialog';
import PreferencesDialog from '../components/PreferencesDialog';

const Dashboard = () => {
  const { api } = useAuth();
  const { user } = useAuth();
  const { data: vehicles, loading, fetchData: loadVehicles } = useApiData('/vehicles');
  const { convert, format } = useDistance();
  const [last12FuelTotal, setLast12FuelTotal] = useState(0);
  const [avgServiceCost, setAvgServiceCost] = useState(0);
  const [totalsLoading, setTotalsLoading] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [preferencesOpen, setPreferencesOpen] = useState(false);
  const [orderedVehicles, setOrderedVehicles] = useState([]);
  const [activeFilter, setActiveFilter] = useState(null);
  const [showVehicleCards, setShowVehicleCards] = useState(() => {
    return localStorage.getItem('dashboardShowVehicles') !== 'false';
  });
  const [sortOrder, setSortOrder] = useState(() => {
    return localStorage.getItem('vehicleSortOrder') || 'name';
  });
  const { t } = useTranslation();
  const navigate = useNavigate();

  useEffect(() => {
    loadVehicles();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // If the first fetch returned an empty list due to a transient timing issue,
  // retry once after a short delay. This prevents the UI from showing "no
  // vehicles" briefly when a race or intermittent network issue occurs.
  const didRetryRef = React.useRef(false);
  useEffect(() => {
    if (!loading && (!vehicles || vehicles.length === 0) && !didRetryRef.current) {
      didRetryRef.current = true;
      const timer = setTimeout(() => {
        loadVehicles();
      }, 500);
      return () => clearTimeout(timer);
    }
    return undefined;
    // Only depend on loading and vehicles reference
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loading, vehicles]);

  // Load vehicles and apply sort order
  useEffect(() => {
    if (vehicles && vehicles.length > 0) {
      let ordered = [...vehicles];
      
      switch (sortOrder) {
        case 'name':
          ordered.sort((a, b) => a.name.localeCompare(b.name));
          break;
        case 'registration':
          ordered.sort((a, b) => (a.registrationNumber || '').localeCompare(b.registrationNumber || ''));
          break;
        case 'make':
          ordered.sort((a, b) => {
            const makeCompare = a.make.localeCompare(b.make);
            if (makeCompare !== 0) return makeCompare;
            return a.model.localeCompare(b.model);
          });
          break;
        case 'year':
          ordered.sort((a, b) => (b.year || 0) - (a.year || 0));
          break;
        default:
          // Default to name sort
          ordered.sort((a, b) => a.name.localeCompare(b.name));
          break;
      }
      
      setOrderedVehicles(ordered);
    }
  }, [vehicles, sortOrder]);

  // Fetch totals for the last 12 months (fuel + parts/consumables)
  useEffect(() => {
    const fetchTotals = async () => {
      if (!vehicles || vehicles.length === 0) {
        setLast12FuelTotal(0);
        setAvgServiceCost(0);
        return;
      }

      setTotalsLoading(true);
      try {
        const res = await api.get('/vehicles/totals?period=12');
        const data = res.data || {};
        setLast12FuelTotal(data.fuel || 0);
        setAvgServiceCost(data.averageServiceCost || 0);
      } catch (e) {
        console.error('Error fetching aggregated totals:', e);
        setLast12FuelTotal(0);
        setAvgServiceCost(0);
      } finally {
        setTotalsLoading(false);
      }
    };

    fetchTotals();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [vehicles]);

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    if (reload) {
      loadVehicles();
    }
  };

  const handleSortChange = (event) => {
    const newSort = event.target.value;
    setSortOrder(newSort);
    localStorage.setItem('vehicleSortOrder', newSort);
  };

  const getVehicleIcon = (vehicleType) => {
    const iconProps = { fontSize: 40, opacity: 0.7 };
    
    if (!vehicleType) {
      return <DirectionsCar sx={iconProps} />;
    }
    
    switch (vehicleType.name) {
      case 'Motorcycle':
        return <MotorcycleIcon sx={iconProps} />;
      case 'Van':
        return <VanIcon sx={iconProps} />;
      case 'Truck':
        return <TruckIcon sx={{ ...iconProps, fontSize: 45 }} />;
      case 'Car':
      default:
        return <DirectionsCar sx={iconProps} />;
    }
  };

  const isExpired = (dateString) => {
    if (!dateString) return null;
    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
  };

  // Treat missing service date as overdue (no records yet)
  const isServiceDue = (vehicle) => {
    if (!vehicle.lastServiceDate) {
      return { due: true, reason: 'No service record found' };
    }
    const lastService = new Date(vehicle.lastServiceDate);
    const today = new Date();
    const monthsDiff = (today - lastService) / (1000 * 60 * 60 * 24 * 30.44);
    const monthsOverdue = monthsDiff > vehicle.serviceIntervalMonths;
    if (monthsOverdue) {
      return { 
        due: true, 
        reason: `Over ${vehicle.serviceIntervalMonths} months since last service` 
      };
    }
    return { due: false, reason: null };
  };

  const getDaysUntil = (dateString) => {
    if (!dateString) return null;
    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diff = Math.ceil((date - today) / (1000 * 60 * 60 * 24));
    return diff;
  };

  const getCardStyle = (color) => {
    if (!color) {
      return {
        background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
      };
    }
    
    const colorMap = {
      'red': 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
      'blue': 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
      'black': 'linear-gradient(135deg, #434343 0%, #000000 100%)',
      'white': 'linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%)',
      'silver': 'linear-gradient(135deg, #bdc3c7 0%, #8e9eab 100%)',
      'grey': 'linear-gradient(135deg, #bdc3c7 0%, #8e9eab 100%)',
      'gray': 'linear-gradient(135deg, #bdc3c7 0%, #8e9eab 100%)',
      'green': 'linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)',
      'yellow': 'linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%)',
      'orange': 'linear-gradient(135deg, #ff9a56 0%, #ff6a88 100%)',
      'brown': 'linear-gradient(135deg, #a29bfe 0%, #af7070 100%)',
      'purple': 'linear-gradient(135deg, #c471f5 0%, #fa71cd 100%)',
    };
    
    const colorLower = color.toLowerCase();
    
    return {
      background: colorMap[colorLower] || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    };
  };

  const DateChip = ({ label, date }) => {
    if (!date) return null;
    
    const expired = isExpired(date);
    const daysUntil = getDaysUntil(date);
    
    let chipColor = 'success';
    let chipLabel = label;
    let icon = <CheckCircleIcon />;
    
    if (expired) {
      chipColor = 'error';
      chipLabel = `${label}: ${t('dashboard.expired')}`;
      icon = <WarningIcon />;
    } else if (daysUntil !== null && daysUntil <= 30) {
      chipColor = 'warning';
      chipLabel = `${label}: ${t('dashboard.daysRemaining', { count: daysUntil })}`;
      icon = <WarningIcon />;
    } else {
      chipLabel = `${label}: ${new Date(date).toLocaleDateString()}`;
    }
    
    return (
      <Chip
        size="small"
        label={chipLabel}
        color={chipColor}
        icon={icon}
        sx={{ mb: 0.5, mr: 0.5 }}
      />
    );
  };

  const ServiceChip = ({ vehicle }) => {
    const serviceDue = isServiceDue(vehicle);
    if (serviceDue.due) {
      return (
        <Tooltip title={serviceDue.reason}>
          <Chip
            size="small"
            label={vehicle.lastServiceDate
              ? `${t('dashboard.serviceDue')}: ${new Date(vehicle.lastServiceDate).toLocaleDateString()}`
              : t('dashboard.serviceDue')}
            color="error"
            icon={<WarningIcon />}
            sx={{ mb: 0.5, mr: 0.5 }}
          />
        </Tooltip>
      );
    }
    return (
      <Chip
        size="small"
        label={`${t('dashboard.lastService')}: ${new Date(vehicle.lastServiceDate).toLocaleDateString()}`}
        color="success"
        icon={<CheckCircleIcon />}
        sx={{ mb: 0.5, mr: 0.5 }}
      />
    );
  };

  const calculateStatistics = () => {
    if (!vehicles || vehicles.length === 0) return null;

    const now = new Date();
    const stats = {
      total: vehicles.length,
      expiredMot: 0,
      expiredTax: 0,
      expiredInsurance: 0,
      serviceDue: 0,
      totalValue: 0,
      byType: {},
      byMake: {},
    };

    vehicles.forEach(vehicle => {
      // Count expired items
        // Treat missing expiry dates as expired (no records yet) but only when country requires them
        if (user?.country === 'GB') {
          if (!vehicle.motExpiryDate || new Date(vehicle.motExpiryDate) < now) {
            stats.expiredMot++;
          }
          if (!vehicle.roadTaxExpiryDate || new Date(vehicle.roadTaxExpiryDate) < now) {
            stats.expiredTax++;
          }
        }
        if (!vehicle.insuranceExpiryDate || new Date(vehicle.insuranceExpiryDate) < now) {
          stats.expiredInsurance++;
        }
      
      // Check service due
      const serviceDue = isServiceDue(vehicle);
      if (serviceDue.due) {
        stats.serviceDue++;
      }

      // Total purchase value
      if (vehicle.purchaseCost) {
        stats.totalValue += parseFloat(vehicle.purchaseCost);
      }

      // Count by type
      const type = vehicle.vehicleType?.name || 'Unknown';
      stats.byType[type] = (stats.byType[type] || 0) + 1;

      // Count by make
      const make = vehicle.make || 'Unknown';
      stats.byMake[make] = (stats.byMake[make] || 0) + 1;
    });

    return stats;
  };

  const getFilteredVehicles = () => {
    if (!activeFilter) return orderedVehicles;

    const now = new Date();
    return orderedVehicles.filter(vehicle => {
      if (activeFilter === 'expiredMot') {
        return !vehicle.motExpiryDate || new Date(vehicle.motExpiryDate) < now;
      }
      if (activeFilter === 'expiredTax') {
        return !vehicle.roadTaxExpiryDate || new Date(vehicle.roadTaxExpiryDate) < now;
      }
      if (activeFilter === 'expiredInsurance') {
        return !vehicle.insuranceExpiryDate || new Date(vehicle.insuranceExpiryDate) < now;
      }
      if (activeFilter === 'serviceDue') {
        return isServiceDue(vehicle).due;
      }
      // Filter by vehicle type
      if (activeFilter.startsWith('type:')) {
        const type = activeFilter.replace('type:', '');
        return vehicle.vehicleType?.name === type;
      }
      return true;
    });
  };

  if (loading) {
    return (
      <Container>
        <Typography>{t('common.loading')}</Typography>
      </Container>
    );
  }

  const filteredVehicles = getFilteredVehicles();

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3} mt={2}>
        <Typography variant="h4">{t('dashboard.welcome')}</Typography>
        <Box display="flex" gap={1}>
          <Tooltip title={showVehicleCards ? t('dashboard.hideVehicleCards') : t('dashboard.showVehicleCards')}>
            <IconButton 
              color="primary"
              onClick={() => {
                const newValue = !showVehicleCards;
                setShowVehicleCards(newValue);
                localStorage.setItem('dashboardShowVehicles', newValue.toString());
              }}
            >
              {showVehicleCards ? <VisibilityIcon /> : <VisibilityOffIcon />}
            </IconButton>
          </Tooltip>
          <Tooltip title={t('preferences.title')}>
            <IconButton color="primary" onClick={() => setPreferencesOpen(true)}>
              <SettingsIcon />
            </IconButton>
          </Tooltip>
        </Box>
      </Box>

      {orderedVehicles.length === 0 ? (
        <Box textAlign="center" py={8}>
          <DirectionsCar sx={{ fontSize: 80, color: 'text.secondary', mb: 2 }} />
          <Typography variant="h6" color="text.secondary" gutterBottom>
            {t('common.noVehicles')}
          </Typography>
          <Button
            variant="contained"
            color="primary"
            startIcon={<AddIcon />}
            onClick={() => setDialogOpen(true)}
            sx={{ mt: 2 }}
          >
            {t('vehicle.addVehicle')}
          </Button>
        </Box>
      ) : (
        <>
          {/* Statistics Section */}
          {(() => {
            const stats = calculateStatistics();
            if (!stats) return null;

            const pieData = Object.entries(stats.byType).map(([type, count], index) => ({
              id: index,
              value: count,
              label: type,
            }));

            return (
              <Box 
                mb={4}
                sx={{
                  display: { xs: 'flex', lg: 'grid' },
                  flexDirection: { xs: 'column' },
                  gridTemplateColumns: { lg: 'repeat(12, 1fr)' },
                  gridTemplateRows: { lg: 'auto auto' },
                  gap: 3
                }}
              >
                {/* Row 1, Col 1-2: Total Vehicles */}
                <Box sx={{ gridColumn: { lg: '1 / 3' }, gridRow: { lg: '1' } }}>
                  <Paper 
                    sx={{ 
                      p: 2, 
                      textAlign: 'center', 
                      cursor: 'pointer', 
                      '&:hover': { boxShadow: 4 },
                      opacity: activeFilter ? 0.6 : 1,
                      height: 140,
                      display: 'flex',
                      flexDirection: 'column',
                      justifyContent: 'center'
                    }}
                    onClick={() => setActiveFilter(null)}
                  >
                    <CarIcon sx={{ fontSize: 40, color: 'primary.main', mb: 1 }} />
                    <Typography variant="h4" color="primary">{stats.total}</Typography>
                    <Typography variant="body2" color="text.secondary">{t('dashboard.totalVehicles')}</Typography>
                  </Paper>
                </Box>

                {/* Row 1, Col 3-4: Expired MOT */}
                <Box sx={{ gridColumn: { lg: '3 / 5' }, gridRow: { lg: '1' } }}>
                  <Paper 
                    sx={{ 
                      p: 2, 
                      textAlign: 'center', 
                      cursor: stats.expiredMot > 0 ? 'pointer' : 'default',
                      '&:hover': stats.expiredMot > 0 ? { boxShadow: 4 } : {},
                      opacity: activeFilter === 'expiredMot' ? 1 : (activeFilter ? 0.6 : 1),
                      height: 140,
                      display: 'flex',
                      flexDirection: 'column',
                      justifyContent: 'center'
                    }}
                    onClick={() => stats.expiredMot > 0 && setActiveFilter(activeFilter === 'expiredMot' ? null : 'expiredMot')}
                  >
                    <EventIcon sx={{ fontSize: 40, color: stats.expiredMot > 0 ? 'error.main' : 'success.main', mb: 1 }} />
                    <Typography variant="h4" color={stats.expiredMot > 0 ? 'error' : 'success'}>{stats.expiredMot}</Typography>
                    <Typography variant="body2" color="text.secondary">{t('dashboard.expiredMot')}</Typography>
                  </Paper>
                </Box>

                {/* Row 1, Col 5-6: Expired Tax */}
                <Box sx={{ gridColumn: { lg: '5 / 7' }, gridRow: { lg: '1' } }}>
                  <Paper 
                    sx={{ 
                      p: 2, 
                      textAlign: 'center', 
                      cursor: stats.expiredTax > 0 ? 'pointer' : 'default',
                      '&:hover': stats.expiredTax > 0 ? { boxShadow: 4 } : {},
                      opacity: activeFilter === 'expiredTax' ? 1 : (activeFilter ? 0.6 : 1),
                      height: 140,
                      display: 'flex',
                      flexDirection: 'column',
                      justifyContent: 'center'
                    }}
                    onClick={() => stats.expiredTax > 0 && setActiveFilter(activeFilter === 'expiredTax' ? null : 'expiredTax')}
                  >
                    <EventIcon sx={{ fontSize: 40, color: stats.expiredTax > 0 ? 'error.main' : 'success.main', mb: 1 }} />
                    <Typography variant="h4" color={stats.expiredTax > 0 ? 'error' : 'success'}>{stats.expiredTax}</Typography>
                    <Typography variant="body2" color="text.secondary">{t('dashboard.expiredTax')}</Typography>
                  </Paper>
                </Box>

                {/* Row 1, Col 7-8: Expired Insurance */}
                <Box sx={{ gridColumn: { lg: '7 / 9' }, gridRow: { lg: '1' } }}>
                  <Paper 
                    sx={{ 
                      p: 2, 
                      textAlign: 'center', 
                      cursor: stats.expiredInsurance > 0 ? 'pointer' : 'default',
                      '&:hover': stats.expiredInsurance > 0 ? { boxShadow: 4 } : {},
                      opacity: activeFilter === 'expiredInsurance' ? 1 : (activeFilter ? 0.6 : 1),
                      height: 140,
                      display: 'flex',
                      flexDirection: 'column',
                      justifyContent: 'center'
                    }}
                    onClick={() => stats.expiredInsurance > 0 && setActiveFilter(activeFilter === 'expiredInsurance' ? null : 'expiredInsurance')}
                  >
                    <WarningIcon sx={{ fontSize: 40, color: stats.expiredInsurance > 0 ? 'error.main' : 'success.main', mb: 1 }} />
                    <Typography variant="h4" color={stats.expiredInsurance > 0 ? 'error' : 'success'}>{stats.expiredInsurance}</Typography>
                    <Typography variant="body2" color="text.secondary">{t('dashboard.expiredInsurance')}</Typography>
                  </Paper>
                </Box>

                {/* Row 2, Col 1-2: Service Due */}
                <Box sx={{ gridColumn: { lg: '1 / 3' }, gridRow: { lg: '2' } }}>
                  <Paper 
                    sx={{ 
                      p: 2, 
                      textAlign: 'center', 
                      cursor: stats.serviceDue > 0 ? 'pointer' : 'default',
                      '&:hover': stats.serviceDue > 0 ? { boxShadow: 4 } : {},
                      opacity: activeFilter === 'serviceDue' ? 1 : (activeFilter ? 0.6 : 1),
                      height: 140,
                      display: 'flex',
                      flexDirection: 'column',
                      justifyContent: 'center'
                    }}
                    onClick={() => stats.serviceDue > 0 && setActiveFilter(activeFilter === 'serviceDue' ? null : 'serviceDue')}
                  >
                    <BuildIcon sx={{ fontSize: 40, color: stats.serviceDue > 0 ? 'warning.main' : 'success.main', mb: 1 }} />
                    <Typography variant="h4" color={stats.serviceDue > 0 ? 'warning' : 'success'}>{stats.serviceDue}</Typography>
                    <Typography variant="body2" color="text.secondary">{t('dashboard.serviceDue')}</Typography>
                  </Paper>
                </Box>

                {/* Row 2, Col 3-4: Total Value */}
                <Box sx={{ gridColumn: { lg: '3 / 5' }, gridRow: { lg: '2' } }}>
                  <Paper sx={{ 
                    p: 2, 
                    textAlign: 'center', 
                    height: 140,
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'center'
                  }}>
                    <Typography variant="h6" color="text.secondary" sx={{ mb: 1 }}>{t('dashboard.totalValue')}</Typography>
                    <Typography variant="h4" color="primary">£{stats.totalValue.toLocaleString('en-GB', { maximumFractionDigits: 0 })}</Typography>
                    <Typography variant="body2" color="text.secondary">{t('dashboard.purchaseCost')}</Typography>
                  </Paper>
                </Box>

                {/* Row 2, Col 5-6: Fuel spent (last 12 months) */}
                <Box sx={{ gridColumn: { lg: '5 / 7' }, gridRow: { lg: '2' } }}>
                  <Paper sx={{ 
                    p: 2, 
                    textAlign: 'center', 
                    height: 140,
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'center'
                  }}>
                    <Typography variant="h6" color="text.secondary" sx={{ mb: 1 }}>{t('dashboard.totalFuelCost')} (12m)</Typography>
                    <Typography variant="h4" color="primary">
                      {totalsLoading ? (
                        <CircularProgress size={24} />
                      ) : (
                        `£${last12FuelTotal.toLocaleString('en-GB', { maximumFractionDigits: 2 })}`
                      )}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">{t('dashboard.fuel')}</Typography>
                  </Paper>
                </Box>

                {/* Row 2, Col 7-8: Parts & Consumables spent (last 12 months) */}
                <Box sx={{ gridColumn: { lg: '7 / 9' }, gridRow: { lg: '2' } }}>
                  <Paper sx={{ p: 2, textAlign: 'center', height: 140, display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
                    <Typography variant="h6" color="text.secondary" sx={{ mb: 1 }}>{t('dashboard.averageServiceCost')} (12m)</Typography>
                    <Typography variant="h4" color="primary">
                      {totalsLoading ? (
                        <CircularProgress size={24} />
                      ) : (
                        `£${avgServiceCost.toLocaleString('en-GB', { maximumFractionDigits: 2 })}`
                      )}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">{t('dashboard.averageServiceCostDesc')}</Typography>
                  </Paper>
                </Box>

                {/* Pie Chart: Col 9-13, spans both rows */}
                <Box sx={{ gridColumn: { lg: '9 / 13' }, gridRow: { lg: '1 / 3' } }}>
                  <Paper sx={{ p: 2, display: 'flex', flexDirection: 'column', height: '100%', minHeight: 304 }}>
                    <Typography variant="h6" gutterBottom>{t('dashboard.byType')}</Typography>
                    <Box 
                      sx={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                      onClick={(event) => {
                        // Find the clicked arc element
                        const target = event.target.closest('path[d]');
                        if (target && target.getAttribute('d')) {
                          // Get the aria-label or find the slice index from DOM
                          const pieArcs = event.currentTarget.querySelectorAll('path[d]');
                          const clickedIndex = Array.from(pieArcs).indexOf(target);
                          
                          if (clickedIndex >= 0 && pieData[clickedIndex]) {
                            const clickedType = pieData[clickedIndex].label;
                            const filterKey = `type:${clickedType}`;
                            setActiveFilter(activeFilter === filterKey ? null : filterKey);
                          }
                        }
                      }}
                    >
                      <PieChart
                        series={[{
                          data: pieData,
                          highlightScope: { faded: 'global', highlighted: 'item' },
                          faded: { innerRadius: 30, additionalRadius: -30, color: 'gray' },
                        }]}
                        width={350}
                        height={280}
                        sx={{
                          cursor: 'pointer',
                          '& .MuiPieArc-root': {
                            cursor: 'pointer',
                          }
                        }}
                        slotProps={{
                          legend: {
                            direction: 'column',
                            position: { vertical: 'middle', horizontal: 'right' },
                            padding: 0,
                            itemMarkWidth: 10,
                            itemMarkHeight: 10,
                            markGap: 5,
                            itemGap: 8,
                          },
                        }}
                        margin={{ right: 120 }}
                      />
                    </Box>
                  </Paper>
                </Box>
              </Box>
            );
          })()}

          {showVehicleCards && (
            <Box sx={{ mb: 4 }}>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h6">
                  {t('dashboard.vehicles')} ({filteredVehicles.length})
                </Typography>
                <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
                  <FormControl size="small" sx={{ minWidth: 120 }}>
                    <InputLabel>{t('dashboard.sortBy')}</InputLabel>
                    <Select
                      value={sortOrder}
                      label={t('dashboard.sortBy')}
                      onChange={handleSortChange}
                    >
                      <MenuItem value="name">{t('dashboard.name')}</MenuItem>
                      <MenuItem value="registration">{t('dashboard.registration')}</MenuItem>
                      <MenuItem value="make">{t('dashboard.make')}</MenuItem>
                      <MenuItem value="year">{t('dashboard.year')}</MenuItem>
                    </Select>
                  </FormControl>
                </Box>
              </Box>
              
              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: {
                    xs: '1fr',
                    sm: 'repeat(2, 1fr)',
                    md: 'repeat(3, 1fr)',
                    lg: 'repeat(4, 1fr)',
                  },
                  gap: 3,
                  width: '100%',
                  boxSizing: 'border-box',
                  position: 'relative',
                }}
              >
                {filteredVehicles.map((vehicle, index) => (
                  <Box
                    key={`${vehicle.id}-${index}`}
                    sx={{
                      transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
                      cursor: 'pointer',
                      position: 'relative',
                      '&:hover': {
                        transform: 'translateY(-4px)',
                        '& .vehicle-card': {
                          boxShadow: '0 12px 24px rgba(0,0,0,0.3)',
                        }
                      },
                    }}
                    onClick={() => navigate(`/vehicles/${vehicle.id}`)}
                  >
                    <Card
                      className="vehicle-card"
                      sx={{
                        ...getCardStyle(vehicle.vehicleColor),
                        color: 'white',
                        height: '100%',
                        position: 'relative',
                        transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
                      }}
                    >
                      <CardContent>
                        <Box 
                          display="flex" 
                          justifyContent="space-between" 
                          alignItems="start" 
                          mb={2}
                        >
                          <Box flex={1}>
                            <Typography variant="h5" component="div" fontWeight="bold">
                              {vehicle.name}
                            </Typography>
                            <Typography variant="body2" sx={{ opacity: 0.9 }}>
                              {vehicle.registrationNumber || t('dashboard.noRegNumber')}
                            </Typography>
                          </Box>
                          {getVehicleIcon(vehicle.vehicleType)}
                        </Box>

                        <Box>
                          <Typography variant="body1" gutterBottom>
                            {vehicle.make} {vehicle.model}
                          </Typography>
                          {vehicle.year && (
                            <Typography variant="body2" sx={{ opacity: 0.9, mb: 2 }}>
                              {t('vehicle.year')}: {vehicle.year}
                            </Typography>
                          )}

                          <Box
                            sx={{
                              backgroundColor: 'rgba(255, 255, 255, 0.2)',
                              borderRadius: 1,
                              p: 1.5,
                              backdropFilter: 'blur(10px)',
                            }}
                          >
                            <DateChip
                              label={t('dashboard.mot')}
                              date={vehicle.motExpiryDate}
                            />
                            <DateChip
                              label={t('dashboard.roadTax')}
                              date={vehicle.roadTaxExpiryDate}
                            />
                            <ServiceChip vehicle={vehicle} />
                          </Box>

                          {vehicle.currentMileage && (
                            <Typography variant="caption" display="block" sx={{ mt: 1, opacity: 0.8 }}>
                              {t('dashboard.mileage')}: {format(convert(vehicle.currentMileage))}
                            </Typography>
                          )}
                        </Box>
                      </CardContent>
                    </Card>
                  </Box>
                ))}
              </Box>
            </Box>
          )}
        </>
      )}

      <VehicleDialog
        open={dialogOpen}
        vehicle={null}
        onClose={handleDialogClose}
      />
      
      <PreferencesDialog
        open={preferencesOpen}
        onClose={() => setPreferencesOpen(false)}
      />
    </Container>
  );
};

export default Dashboard;