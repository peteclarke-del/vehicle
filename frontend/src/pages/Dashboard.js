import React, { useState, useEffect, useRef, useLayoutEffect, useCallback } from 'react';
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
  Tabs,
  Tab,
  Menu,
} from '@mui/material';
import logger from '../utils/logger';
import { PieChart } from '@mui/x-charts/PieChart';
import { useTranslation } from 'react-i18next';
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import formatCurrency from '../utils/formatCurrency';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useVehicles } from '../contexts/VehiclesContext';
import { useDistance } from '../hooks/useDistance';
import { formatDateISO } from '../utils/formatDate';
import VehicleDialog from '../components/VehicleDialog';
import StatusChangeDialog from '../components/StatusChangeDialog';
import KnightRiderLoader from '../components/KnightRiderLoader';
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
  Visibility as VisibilityIcon,
  VisibilityOff as VisibilityOffIcon,
  MoreVert as MoreVertIcon,
} from '@mui/icons-material';

const Dashboard = () => {
  const { api, user } = useAuth();
  const { vehicles, loading, refreshVehicles } = useVehicles();
  const { convert, format } = useDistance();
  const [last12FuelTotal, setLast12FuelTotal] = useState(0);
  const [last12PartsTotal, setLast12PartsTotal] = useState(0);
  const [last12ConsumablesTotal, setLast12ConsumablesTotal] = useState(0);
  const [avgServiceCost, setAvgServiceCost] = useState(0);
  const [totalsLoading, setTotalsLoading] = useState(false);
  const [sornVehicles, setSornVehicles] = useState(0);
  const [roadTaxData, setRoadTaxData] = useState([]);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [orderedVehicles, setOrderedVehicles] = useState([]);
  const [activeFilter, setActiveFilter] = useState(null);
  const [showVehicleCards, setShowVehicleCards] = useState(() => {
    return localStorage.getItem('dashboardShowVehicles') !== 'false';
  });
  const [selectedStatus, setSelectedStatus] = useState('Live');
  const [menuAnchorEl, setMenuAnchorEl] = useState(null);
  const [menuVehicleId, setMenuVehicleId] = useState(null);
  const [sortOrder, setSortOrder] = useState(() => {
    return localStorage.getItem('vehicleSortOrder') || 'name';
  });
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const { setDefaultVehicle } = useUserPreferences();
  const [statusDialogOpen, setStatusDialogOpen] = useState(false);
  const [statusDialogData, setStatusDialogData] = useState({ vehicleId: null, newStatus: null, date: '', notes: '' });
  const CARD_HEIGHT = 163;

  // Responsive stat card that scales typography based on card size and text length
  const StatCard = React.memo(({ title, value, subtitle, loading, topRightIcon, onClick, children, isBottom = false, isCircular = false }) => {
    const ref = useRef(null);
    const [dims, setDims] = useState({ width: 0, height: 0 });

    useLayoutEffect(() => {
      if (!ref.current) return;
      const el = ref.current;
      const ro = new ResizeObserver(entries => {
        for (const entry of entries) {
          const cr = entry.contentRect;
          setDims({ width: cr.width, height: cr.height });
        }
      });
      ro.observe(el);
      return () => ro.disconnect();
    }, [ref]);

    const computeSizes = useCallback((w, h, t, v, s, bottom) => {
      const pad = 16; // matches p:2 (theme spacing)
      const availW = Math.max(50, w - pad * 2);
      const availH = Math.max(50, h - pad * 2);

      // title size scales with width and shortens with long text
      const titleBase = Math.min(20, Math.max(14, availW / Math.max(12, (t || '').length * 0.8)));
      // value is emphasized - scale with height; bottom cards slightly larger
      const valueScale = bottom ? 0.40 : 0.36;
      const valueBase = Math.min(64, Math.max(20, availH * valueScale));
      // subtitle smaller
      const subtitleBase = Math.min(14, Math.max(12, availH * 0.1));

      // gap between elements computed to visually match spacing above and below value
      const gap = Math.max(8, Math.round(availH * 0.06));

      return {
        titleSize: `${Math.round(titleBase)}px`,
        valueSize: `${Math.round(valueBase)}px`,
        subtitleSize: `${Math.round(subtitleBase)}px`,
        gapPx: gap,
      };
    }, []);

    const { titleSize, valueSize, subtitleSize, gapPx } = computeSizes(dims.width, dims.height, title, value, subtitle, isBottom);

    if (isCircular) {
      return (
        <Paper
          ref={ref}
          onClick={onClick}
          sx={{ 
            p: 2, 
            textAlign: 'center', 
            aspectRatio: '1/1',
            borderRadius: '50%',
            display: 'flex', 
            flexDirection: 'column', 
            justifyContent: 'center', 
            alignItems: 'center',
            width: '100%', 
            minWidth: 0, 
            position: 'relative',
            cursor: onClick ? 'pointer' : 'default',
            gap: 0.5,
            boxShadow: `
              0 0 0 3px #c0c0c0,
              0 0 0 4px #e8e8e8,
              0 0 0 5px #ffffff,
              0 0 0 6px #d4d4d4,
              0 0 0 7px #b0b0b0,
              inset 0 2px 6px rgba(255, 255, 255, 0.5),
              inset 0 -2px 6px rgba(0, 0, 0, 0.15),
              0 4px 12px rgba(0, 0, 0, 0.2)
            `
          }}
        >
          {title ? (
            <Typography variant="body2" color="text.secondary" sx={{ fontSize: '12px', lineHeight: 1.1, flexShrink: 0 }}>{title}</Typography>
          ) : (
            <Box sx={{ height: '16px' }} />
          )}
          <Box
            sx={{
              backgroundColor: 'rgba(0, 0, 0, 0.85)',
              borderRadius: '8px',
              px: 1,
              py: 2,
              width: '100%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              position: 'relative',
              flexShrink: 0
            }}
          >
            {topRightIcon && (
              <Box sx={{ position: 'absolute', top: 8, right: 8 }}>{topRightIcon}</Box>
            )}
            {loading ? (
              <KnightRiderLoader size={28} />
            ) : children ? (
              children
            ) : (
              <Typography variant="h4" sx={{ color: 'primary.main', fontWeight: 'bold', fontSize: '28px' }}>{value}</Typography>
            )}
          </Box>
          {subtitle ? (
            <Typography variant="caption" color="text.secondary" sx={{ fontSize: '11px', flexShrink: 0 }}>{subtitle}</Typography>
          ) : (
            <Box sx={{ height: '15px' }} />
          )}
        </Paper>
      );
    }

    return (
      <Paper
        ref={ref}
        onClick={onClick}
        sx={{ p: 2, textAlign: 'center', height: CARD_HEIGHT, display: 'flex', flexDirection: 'column', justifyContent: 'center', width: '100%', minWidth: 0, position: 'relative', gap: `${gapPx}px` }}
      >
        {topRightIcon && <Box sx={{ position: 'absolute', top: 8, right: 8 }}>{topRightIcon}</Box>}
        {title ? (
          <Typography variant="h6" color="text.secondary" sx={{ fontSize: titleSize, lineHeight: 1.1 }}>{title}</Typography>
        ) : null}
        {children ? (
          <Box sx={{ fontSize: valueSize }}>{children}</Box>
        ) : (
          <Typography variant="h4" color="primary" sx={{ fontSize: valueSize }}>{loading ? <KnightRiderLoader size={18} /> : value}</Typography>
        )}
        {subtitle ? <Typography variant="body2" color="text.secondary" sx={{ fontSize: subtitleSize }}>{subtitle}</Typography> : null}
      </Paper>
    );
  });

  // No need to load vehicles - VehiclesContext handles it automatically

  // Fetch and set dashboard totals (extracted as a named function)
  const fetchDashboardTotals = useCallback(async (periodMonths = 12) => {
    setTotalsLoading(true);
    try {
      const resp = await api.get(`/vehicles/totals?period=${periodMonths}`);
      setLast12FuelTotal(resp.data.fuel ?? 0);
      setLast12PartsTotal(resp.data.parts ?? 0);
      setLast12ConsumablesTotal(resp.data.consumables ?? 0);
      setAvgServiceCost(resp.data.averageServiceCost ?? 0);
    } catch (err) {
      logger.warn('Failed to load vehicle totals', err);
    } finally {
      setTotalsLoading(false);
    }
  }, [api]);

  // Fetch road tax data and count SORN vehicles (only Live vehicles with SORN as latest record)
  const fetchRoadTaxData = useCallback(async () => {
    try {
      const resp = await api.get('/road-tax');
      const data = Array.isArray(resp.data)
        ? resp.data
        : Array.isArray(resp.data?.records)
          ? resp.data.records
          : Array.isArray(resp.data?.roadTaxRecords)
            ? resp.data.roadTaxRecords
            : [];
      setRoadTaxData(data);
      // Count unique vehicles with SORN status (latest record per vehicle, only Live vehicles)
      const vehicleLatestRecord = {};
      (data || []).forEach(record => {
        const vId = record.vehicleId;
        if (!vehicleLatestRecord[vId] || new Date(record.startDate) > new Date(vehicleLatestRecord[vId].startDate)) {
          vehicleLatestRecord[vId] = record;
        }
      });
      // Filter to only count SORN vehicles that are Live status and have SORN as latest record
      const liveVehicleIds = (vehicles || []).filter(v => (v.status || 'Live') === 'Live').map(v => v.id);
      const sornCount = Object.entries(vehicleLatestRecord)
        .filter(([vId, record]) => record.sorn === true && liveVehicleIds.includes(parseInt(vId)))
        .length;
      setSornVehicles(sornCount);
    } catch (err) {
      logger.warn('Failed to load road tax data', err);
    }
  }, [api, vehicles]);

  useEffect(() => {
    // Fetch both dashboard totals and road tax data in parallel
    fetchDashboardTotals(12);
    fetchRoadTaxData();
  }, [fetchDashboardTotals, fetchRoadTaxData]);

  // Apply sort order when vehicles are loaded or sortOrder changes
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
            const makeCompare = (a.make || '').localeCompare(b.make || '');
            if (makeCompare !== 0) return makeCompare;
            return (a.model || '').localeCompare(b.model || '');
          });
          break;
        case 'year':
          ordered.sort((a, b) => (b.year || 0) - (a.year || 0));
          break;
        default:
          ordered.sort((a, b) => a.name.localeCompare(b.name));
          break;
      }
      setOrderedVehicles(ordered);
    }
  }, [vehicles, sortOrder]);

  const handleDialogClose = (reload) => {
    setDialogOpen(false);
    if (reload) {
      refreshVehicles();
    }
  };

  const openStatusDialog = (vehicleId, status) => {
    // Prefill date with today
    const today = new Date().toISOString().slice(0, 10);
    setStatusDialogData({ vehicleId, newStatus: status, date: today, notes: '' });
    setStatusDialogOpen(true);
    handleCloseMenu();
  };

  const handleConfirmStatusChange = async () => {
    const { vehicleId, newStatus, date, notes } = statusDialogData;
    try {
      const payload = { status: newStatus };
      // include optional metadata; backend may ignore unknown fields if not supported
      if (date) payload.statusChangeDate = date;
      if (notes) payload.statusChangeNotes = notes;
      await api.put(`/vehicles/${vehicleId}`, payload);
      await refreshVehicles();
    } catch (e) {
      logger.error('Error updating vehicle status with metadata', e);
    } finally {
      setStatusDialogOpen(false);
    }
  };

  const handleSortChange = (event) => {
    const newSort = event.target.value;
    setSortOrder(newSort);
    localStorage.setItem('vehicleSortOrder', newSort);
  };

  const handleOpenMenu = (event, vehicleId) => {
    setMenuAnchorEl(event.currentTarget);
    setMenuVehicleId(vehicleId);
  };

  const handleCloseMenu = () => {
    setMenuAnchorEl(null);
    setMenuVehicleId(null);
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

  // Helper to pick a status colour based on counts. Pass total explicitly.
  const statusColor = (count, total) => {
    if (!total) return 'success.main';
    if (count === 0) return 'success.main';
    if (count >= Math.ceil(total / 2)) return 'error.main';
    return 'warning.main';
  };

  // Helper to get the actual color value for text/icons
  const getStatusColorValue = (count, total) => {
    const status = statusColor(count, total);
    if (status === 'success.main') return '#4caf50'; // green
    if (status === 'error.main') return '#f44336'; // red
    if (status === 'warning.main') return '#ff9800'; // orange
    return '#29b6f6'; // blue fallback
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
      chipLabel = `${label}: ${formatDateISO(date)}`;
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
              ? `${t('dashboard.serviceDue')}: ${formatDateISO(vehicle.lastServiceDate)}`
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
        label={`${t('dashboard.lastService')}: ${formatDateISO(vehicle.lastServiceDate)}`}
        color="success"
        icon={<CheckCircleIcon />}
        sx={{ mb: 0.5, mr: 0.5 }}
      />
    );
  };

  // Helper to get latest road tax record for a vehicle
  const getLatestRoadTaxRecord = (vehicleId) => {
    const safeRoadTaxData = Array.isArray(roadTaxData) ? roadTaxData : [];
    const vehicleRecords = safeRoadTaxData.filter(r => r.vehicleId === vehicleId);
    if (vehicleRecords.length === 0) return null;
    return vehicleRecords.reduce((latest, current) => 
      new Date(current.startDate) > new Date(latest.startDate) ? current : latest
    );
  };

  const calculateStatistics = () => {
    if (!vehicles || vehicles.length === 0) return null;

    const now = new Date();
    // Only count Live vehicles for statistics (except cost totals which include all)
    const liveVehicles = vehicles.filter(v => (v.status || 'Live') === 'Live');
    
    const stats = {
      total: liveVehicles.length,
      expiredMot: 0,
      expiredTax: 0,
      expiredInsurance: 0,
      serviceDue: 0,
      totalValue: 0,
      byType: {},
      byMake: {},
    };

    liveVehicles.forEach(vehicle => {
      // Count expired items
        // Treat missing expiry dates as expired (no records yet) but only when country requires them
        if (!vehicle.motExpiryDate || new Date(vehicle.motExpiryDate) < now) {
          stats.expiredMot++;
        }
        
        // For expired tax, exclude vehicles with SORN as the latest record
        const latestRoadTax = getLatestRoadTaxRecord(vehicle.id);
        const hasActiveSorn = latestRoadTax && latestRoadTax.sorn === true;
        if (!hasActiveSorn && (!vehicle.roadTaxExpiryDate || new Date(vehicle.roadTaxExpiryDate) < now)) {
          stats.expiredTax++;
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
      if (activeFilter === 'sorn') {
        // Check if vehicle has SORN status from latest road tax record
        const vehicleRecords = roadTaxData.filter(r => r.vehicleId === vehicle.id);
        if (vehicleRecords.length === 0) return false;
        const latestRecord = vehicleRecords.reduce((latest, current) => 
          new Date(current.startDate) > new Date(latest.startDate) ? current : latest
        );
        return latestRecord.sorn === true;
      }
      // Filter by vehicle type
      if (activeFilter.startsWith('type:')) {
        const type = activeFilter.replace('type:', '');
        return vehicle.vehicleType?.name === type;
      }
      return true;
    });
  };

  if (loading && (!vehicles || vehicles.length === 0)) {
    return (
      <Container>
        <Typography>{t('common.loading')}</Typography>
      </Container>
    );
  }

  const allFiltered = getFilteredVehicles();
  const statusCounts = (allFiltered || []).reduce((acc, v) => {
    const s = v.status || 'Live';
    acc[s] = (acc[s] || 0) + 1;
    return acc;
  }, {});
  const filteredVehicles = (allFiltered || []).filter(v => (v.status || 'Live') === selectedStatus);

  return (
    <Container maxWidth="xl">
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3} mt={2}>
        <Typography variant="h4">{t('dashboard.welcome')}</Typography>
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

            const statusColor = (count) => {
              if (!stats || !stats.total) return 'success.main';
              if (count === 0) return 'success.main';
              if (count >= Math.ceil(stats.total / 2)) return 'error.main';
              return 'warning.main';
            };

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
                {/* Left area: cards arranged into two rows (top: 5, bottom: 6) */}
                <Box sx={{ gridColumn: { lg: '1 / 10' }, gridRow: { lg: '1 / 3' } }}>
                  <Box sx={{ display: 'grid', gridTemplateColumns: { lg: 'repeat(5, 1fr)' }, gap: 3, mb: 3, alignItems: 'stretch' }}>
                    {/* Top row: Total Value, Total Fuel Cost, Total Parts Cost, Total Consumables Cost, Average Service Cost */}
                    <StatCard
                      isCircular={true}
                      title={t('dashboard.totalValue')}
                      value={new Intl.NumberFormat(i18n.language || undefined, { style: 'currency', currency: 'GBP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Math.ceil(parseFloat(stats.totalValue || 0)))}
                      subtitle={t('dashboard.purchaseCost')}
                    />

                    <StatCard
                      isCircular={true}
                      title={`${t('dashboard.totalFuelCost')} (12m)`}
                      value={formatCurrency(last12FuelTotal, 'GBP', i18n.language)}
                      loading={totalsLoading}
                      subtitle={t('dashboard.fuel')}
                    />

                    <StatCard
                      isCircular={true}
                      title={`${t('dashboard.totalPartsCost')} (12m)`}
                      value={formatCurrency(last12PartsTotal, 'GBP', i18n.language)}
                      loading={totalsLoading}
                      subtitle={t('dashboard.parts')}
                    />

                    <StatCard
                      isCircular={true}
                      title={`${t('dashboard.totalConsumablesCost') || 'Total Consumables Cost'} (12m)`}
                      value={formatCurrency(last12ConsumablesTotal, 'GBP', i18n.language)}
                      loading={totalsLoading}
                      subtitle={t('dashboard.consumables')}
                    />

                    <StatCard
                      isCircular={true}
                      title={`${t('dashboard.averageServiceCost')} (12m)`}
                      value={`£${avgServiceCost.toLocaleString('en-GB', { maximumFractionDigits: 2 })}`}
                      loading={totalsLoading}
                      subtitle={t('dashboard.averageServiceCostDesc')}
                    />
                  </Box>

                  <Box sx={{ display: 'grid', gridTemplateColumns: { lg: 'repeat(6, 1fr)' }, gap: 3, alignItems: 'stretch' }}>
                    {/* Bottom row: total vehicles filter, expired mot, expired tax, expired insurance, service due, sorn vehicles */}
                    <StatCard
                      isCircular={true}
                      value={stats.total}
                      subtitle={t('dashboard.totalVehicles')}
                      topRightIcon={<CarIcon sx={{ fontSize: 24, color: 'primary.main' }} />}
                      onClick={() => setActiveFilter(null)}
                      isBottom={true}
                    >
                      <Typography variant="h4" sx={{ fontSize: '32px', color: '#29b6f6' }}>{stats.total}</Typography>
                    </StatCard>

                    <StatCard
                      isCircular={true}
                      value={stats.expiredMot}
                      subtitle={t('dashboard.expiredMot')}
                      topRightIcon={<EventIcon sx={{ fontSize: 24, color: statusColor(stats.expiredMot, stats.total) }} />}
                      onClick={() => stats.expiredMot > 0 && setActiveFilter(activeFilter === 'expiredMot' ? null : 'expiredMot')}
                      isBottom={true}
                    >
                      <Typography variant="h4" sx={{ fontSize: '32px', color: getStatusColorValue(stats.expiredMot, stats.total) }}>{stats.expiredMot}</Typography>
                    </StatCard>

                    <StatCard
                      isCircular={true}
                      value={stats.expiredTax}
                      subtitle={t('dashboard.expiredTax')}
                      topRightIcon={<EventIcon sx={{ fontSize: 24, color: statusColor(stats.expiredTax, stats.total) }} />}
                      onClick={() => stats.expiredTax > 0 && setActiveFilter(activeFilter === 'expiredTax' ? null : 'expiredTax')}
                      isBottom={true}
                    >
                      <Typography variant="h4" sx={{ fontSize: '32px', color: getStatusColorValue(stats.expiredTax, stats.total) }}>{stats.expiredTax}</Typography>
                    </StatCard>

                    <StatCard
                      isCircular={true}
                      value={stats.expiredInsurance}
                      subtitle={t('dashboard.expiredInsurance')}
                      topRightIcon={<WarningIcon sx={{ fontSize: 24, color: statusColor(stats.expiredInsurance, stats.total) }} />}
                      onClick={() => stats.expiredInsurance > 0 && setActiveFilter(activeFilter === 'expiredInsurance' ? null : 'expiredInsurance')}
                      isBottom={true}
                    >
                      <Typography variant="h4" sx={{ fontSize: '32px', color: getStatusColorValue(stats.expiredInsurance, stats.total) }}>{stats.expiredInsurance}</Typography>
                    </StatCard>

                    <StatCard
                      isCircular={true}
                      value={stats.serviceDue}
                      subtitle={t('dashboard.serviceDue')}
                      topRightIcon={<BuildIcon sx={{ fontSize: 24, color: statusColor(stats.serviceDue, stats.total) }} />}
                      onClick={() => stats.serviceDue > 0 && setActiveFilter(activeFilter === 'serviceDue' ? null : 'serviceDue')}
                      isBottom={true}
                    >
                      <Typography variant="h4" sx={{ fontSize: '32px', color: getStatusColorValue(stats.serviceDue, stats.total) }}>{stats.serviceDue}</Typography>
                    </StatCard>

                    <StatCard
                      isCircular={true}
                      value={sornVehicles}
                      subtitle={t('dashboard.sornVehicles')}
                      topRightIcon={<EventIcon sx={{ fontSize: 24, color: 'primary.main' }} />}
                      onClick={() => sornVehicles > 0 && setActiveFilter(activeFilter === 'sorn' ? null : 'sorn')}
                      isBottom={true}
                    >
                      <Typography variant="h4" sx={{ fontSize: '32px', color: '#29b6f6' }}>{sornVehicles}</Typography>
                    </StatCard>
                  </Box>
                </Box>

                {/* Pie Chart: Col 10-13, spans both rows */}
                <Box sx={{ gridColumn: { lg: '10 / 13' }, gridRow: { lg: '1 / 3' } }}>
                  <Paper sx={{ p: 2, display: 'flex', flexDirection: 'column', height: '100%' }}>
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
                        width={300}
                        height={300}
                        sx={{
                          cursor: 'pointer',
                          '& .MuiPieArc-root': {
                            cursor: 'pointer',
                          }
                        }}
                        slotProps={{
                          legend: {
                            direction: 'row',
                            position: { vertical: 'bottom', horizontal: 'middle' },
                            padding: { bottom: 8 },
                            itemMarkWidth: 10,
                            itemMarkHeight: 10,
                            markGap: 5,
                            itemGap: 10,
                          },
                        }}
                        margin={{ bottom: 35, left: 50, right: 50 }}
                      />
                    </Box>
                  </Paper>
                </Box>
              </Box>
            );
          })()}

          <Box sx={{ mb: 4 }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
              <Tabs
                value={selectedStatus}
                onChange={(e, value) => setSelectedStatus(value)}
                textColor="primary"
                indicatorColor="primary"
              >
                <Tab value="Live" label={`Live (${statusCounts['Live'] || 0})`} />
                <Tab value="Sold" label={`Sold (${statusCounts['Sold'] || 0})`} />
                <Tab value="Scrapped" label={`Scrapped (${statusCounts['Scrapped'] || 0})`} />
                <Tab value="Exported" label={`Exported (${statusCounts['Exported'] || 0})`} />
              </Tabs>
              <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
                <FormControl size="small" sx={{ minWidth: 120 }}>
                  <InputLabel>{t('dashboard.sortBy')}</InputLabel>
                  <Select
                    value={sortOrder}
                    label={t('dashboard.sortBy')}
                    onChange={handleSortChange}
                  >
                    <MenuItem value="name">{t('common.name')}</MenuItem>
                    <MenuItem value="registration">{t('common.registrationNumber')}</MenuItem>
                    <MenuItem value="make">{t('dashboard.make')}</MenuItem>
                    <MenuItem value="year">{t('common.year')}</MenuItem>
                  </Select>
                </FormControl>

                <Tooltip title={showVehicleCards ? t('dashboard.hideVehicleCards') : t('dashboard.showVehicleCards')}>
                  <IconButton
                    color="primary"
                    onClick={() => {
                      const newValue = !showVehicleCards;
                      setShowVehicleCards(newValue);
                      localStorage.setItem('dashboardShowVehicles', newValue.toString());
                    }}
                    sx={{ ml: 1, color: 'text.primary' }}
                  >
                    {showVehicleCards ? <VisibilityIcon /> : <VisibilityOffIcon />}
                  </IconButton>
                </Tooltip>
              </Box>
            </Box>

            {showVehicleCards && (
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
                    onClick={() => { setDefaultVehicle(vehicle.id); navigate(`/vehicles/${vehicle.id}`); }}
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
                        <Box mb={2} sx={{ position: 'relative' }}>
                          {/* Icon positioned top-left */}
                          <Box sx={{ position: 'absolute', top: 8, left: 8, zIndex: 3, display: 'flex', alignItems: 'center' }}>
                            {getVehicleIcon(vehicle.vehicleType)}
                          </Box>

                          {/* Menu remains top-right */}
                          <IconButton
                            size="small"
                            sx={{ position: 'absolute', top: 8, right: 8, zIndex: 3, color: 'rgba(255,255,255,0.95)' }}
                            onClick={(e) => { e.stopPropagation(); handleOpenMenu(e, vehicle.id); }}
                          >
                            <MoreVertIcon />
                          </IconButton>

                          {/* Title and reg should reserve space for icon + menu so title can wrap */}
                          <Box sx={{ pl: '64px', pr: '56px' }}>
                            <Typography
                              variant="h5"
                              component="div"
                              fontWeight="bold"
                              sx={{ whiteSpace: 'normal', overflowWrap: 'anywhere', wordBreak: 'break-word' }}
                            >
                              {vehicle.name}
                            </Typography>
                            <Typography variant="body2" sx={{ opacity: 0.9 }}>
                              {vehicle.registrationNumber || t('dashboard.noRegNumber')}
                            </Typography>
                          </Box>
                        </Box>

                        <Box>
                          <Typography variant="body1" gutterBottom>
                            {vehicle.make} {vehicle.model}
                          </Typography>
                          {vehicle.year && (
                            <Typography variant="body2" sx={{ opacity: 0.9, mb: 2 }}>
                              {t('common.year')}: {vehicle.year}
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
                              {t('common.mileage')}: {format(convert(vehicle.currentMileage))}
                            </Typography>
                          )}
                        </Box>
                      </CardContent>
                    </Card>
                  </Box>
                ))}
              </Box>
            )}
          </Box>
        </>
      )}

      <VehicleDialog
        open={dialogOpen}
        vehicle={null}
        onClose={handleDialogClose}
      />
      <Menu
        anchorEl={menuAnchorEl}
        open={Boolean(menuAnchorEl)}
        onClose={handleCloseMenu}
      >
        {(() => {
          const menuVehicle = (orderedVehicles || []).find(v => v.id === menuVehicleId) || (vehicles || []).find(v => v.id === menuVehicleId);
          const allStatuses = ['Live', 'Sold', 'Scrapped', 'Exported'];
          const currentStatus = menuVehicle?.status || 'Live';
          const options = allStatuses.filter(s => s !== currentStatus);

          if (!menuVehicle) {
            return <MenuItem disabled>{t('common.loading')}</MenuItem>;
          }

          return options.map(s => (
            <MenuItem key={s} onClick={() => openStatusDialog(menuVehicleId, s)}>
              {t('vehicle.markStatus', { status: s })}
            </MenuItem>
          ));
        })()}
      </Menu>

      <StatusChangeDialog
        open={statusDialogOpen}
        initialData={statusDialogData}
        onClose={() => setStatusDialogOpen(false)}
        onConfirm={handleConfirmStatusChange}
      />
      
      
    </Container>
  );
};

export default Dashboard;