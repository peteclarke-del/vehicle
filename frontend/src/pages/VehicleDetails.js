import React, { useEffect, useState, useRef, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Box,
  Card,
  CardContent,
  Grid,
  Typography,
  Tabs,
  Tab,
  Chip,
  Tooltip,
  Divider,
  Stack,
  IconButton,
  Table,
  TableHead,
  TableBody,
  TableRow,
  TableCell,
  TableContainer,
  Paper,
} from '@mui/material';
import {
  DirectionsCar as CarIcon,
  Event as EventIcon,
  Speed as SpeedIcon,
  Assignment as AssignmentIcon,
  Build as BuildIcon,
  MonetizationOn as MoneyIcon,
  ShoppingCart as ShoppingCartIcon,
  TrendingUp as TrendingUpIcon,
  LocalGasStation as GasIcon,
  Construction as ConstructionIcon,
  Opacity as OpacityIcon,
  CheckCircle as CheckIcon,
  Warning as WarningIcon,
  Error as ErrorIcon,
  ArrowBack as ArrowBackIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useDistance } from '../hooks/useDistance';
import useTablePagination from '../hooks/useTablePagination';
import formatCurrency from '../utils/formatCurrency';
import VehicleSpecifications from '../components/VehicleSpecifications';
import VehicleImages from '../components/VehicleImages';
import VehicleDocuments from '../components/VehicleDocuments';
import VinDecoder from '../components/VinDecoder';
import LicensePlate from '../components/LicensePlate';
import KnightRiderLoader from '../components/KnightRiderLoader';
import TablePaginationBar from '../components/TablePaginationBar';
import { LineChart } from '@mui/x-charts/LineChart';
import { PieChart } from '@mui/x-charts/PieChart';

const VehicleDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [vehicle, setVehicle] = useState(null);
  const [stats, setStats] = useState(null);
  const [depreciationSchedule, setDepreciationSchedule] = useState(null);
  const [depreciationLoading, setDepreciationLoading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState(0);
  const { api, user } = useAuth();
  const { t, i18n } = useTranslation();
  const { convert, format, getLabel, convertFuelConsumption, getFuelConsumptionLabel, userUnit } = useDistance();
  const { page, rowsPerPage, paginatedRows: paginatedDepreciation, handleChangePage, handleChangeRowsPerPage } = useTablePagination(depreciationSchedule || []);

  const isExpired = (dateString) => {
    if (!dateString) return null;
    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
  };

  const getDaysUntil = (dateString) => {
    if (!dateString) return null;
    const date = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diff = Math.ceil((date - today) / (1000 * 60 * 60 * 24));
    return diff;
  };

  const getDateStatus = (dateString) => {
    if (!dateString) return null;
    const expired = isExpired(dateString);
    const daysUntil = getDaysUntil(dateString);
    if (expired) return { color: 'error', icon: <WarningIcon /> };
    if (daysUntil !== null && daysUntil <= 30) return { color: 'warning', icon: <WarningIcon /> };
    return { color: 'success', icon: <CheckIcon /> };
  };

  const getServiceStatus = (vehicleData) => {
    if (!vehicleData?.lastServiceDate) {
      return { color: 'error', icon: <WarningIcon /> };
    }
    const intervalMonths = Number(vehicleData.serviceIntervalMonths || 12);
    const lastService = new Date(vehicleData.lastServiceDate);
    const dueDate = new Date(lastService);
    dueDate.setMonth(dueDate.getMonth() + intervalMonths);
    const daysUntil = getDaysUntil(dueDate.toISOString());
    if (daysUntil !== null && daysUntil < 0) return { color: 'error', icon: <WarningIcon /> };
    if (daysUntil !== null && daysUntil <= 30) return { color: 'warning', icon: <WarningIcon /> };
    return { color: 'success', icon: <CheckIcon /> };
  };

  // responsive pie sizing: measure container and set pie size accordingly
  const pieRef = useRef(null);
  const [pieSize, setPieSize] = useState(280);
  useEffect(() => {
    const el = pieRef.current;
    if (!el) return;

    const calculate = (width) => {
      // keep pie between 160 and 420 and roughly 60% of container width
      const size = Math.max(160, Math.min(420, Math.floor(width * 0.6)));
      setPieSize(size);
    };

    if (typeof ResizeObserver !== 'undefined') {
      const ro = new ResizeObserver((entries) => {
        for (const entry of entries) {
          calculate(entry.contentRect.width);
        }
      });
      ro.observe(el);
      // initial
      calculate(el.offsetWidth || 480);
      return () => ro.disconnect();
    }

    const onResize = () => calculate(el.offsetWidth || 480);
    window.addEventListener('resize', onResize);
    onResize();
    return () => window.removeEventListener('resize', onResize);
  }, [pieRef]);

  const loadVehicleData = useCallback(async () => {
    try {
      const [vehicleRes, statsRes] = await Promise.all([
        api.get(`/vehicles/${id}`),
        api.get(`/vehicles/${id}/stats`),
      ]);
      setVehicle(vehicleRes.data);
      setStats(statsRes.data);
    } catch (error) {
      console.error('Error loading vehicle data:', error);
    } finally {
      setLoading(false);
    }
  }, [api, id]);

  useEffect(() => {
    loadVehicleData();
  }, [loadVehicleData]);

  useEffect(() => {
    // When user navigates to the Depreciation tab, fetch the full schedule
    if (tab === 2 && !depreciationSchedule) {
      setDepreciationLoading(true);
      api.get(`/vehicles/${id}/depreciation`)
        .then((res) => {
          setDepreciationSchedule(res.data.schedule || []);
        })
        .catch((err) => {
          console.error('Error loading depreciation schedule:', err);
          setDepreciationSchedule([]);
        })
        .finally(() => setDepreciationLoading(false));
    }
  }, [tab, id, depreciationSchedule, api]);

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <KnightRiderLoader size={32} />
      </Box>
    );
  }

  if (!vehicle || !stats || !stats.stats) {
    return <Typography>{t('vehicleDetails.vehicleNotFound')}</Typography>;
  }

  return (
    <Box>
      <Box display="flex" alignItems="center" gap={2} mb={2}>
        <IconButton 
          onClick={() => navigate(-1)} 
          sx={{ 
            color: 'primary.main',
            '&:hover': { backgroundColor: 'primary.light', color: 'primary.contrastText' }
          }}
          aria-label={t('common.back')}
        >
          <ArrowBackIcon />
        </IconButton>
        <Box>
          <Typography variant="h4">
            {vehicle.name}
          </Typography>
          <Typography variant="subtitle1" color="textSecondary">
            {vehicle.make} {vehicle.model} ({vehicle.year})
          </Typography>
        </Box>
      </Box>

      <Tabs value={tab} onChange={(e, v) => setTab(v)} sx={{ mb: 3 }}>
        <Tab label={t('vehicleDetails.overview')} />
        <Tab label={t('vehicleDetails.statistics')} />
        <Tab label={t('stats.depreciation')} />
        <Tab label={t('vehicleDetails.specifications')} />
        <Tab label={t('vehicleDetails.pictures')} />
        <Tab label={t('vehicleDetails.documentation')} />
        <Tab label={t('vehicleDetails.userManual')} />
        <Tab label={t('vehicleDetails.serviceManual')} />
      </Tabs>

      {tab === 0 && (
        <Grid container spacing={3}>
          <Grid item xs={12} md={6}>
            <Card sx={{ height: '100%' }}>
              <CardContent>
                <Box display="flex" alignItems="center" gap={1} mb={2}>
                  <CarIcon color="primary" />
                  <Typography variant="h6">{t('vehicleDetails.vehicleInformation')}</Typography>
                </Box>
                <Divider sx={{ mb: 2 }} />
                <Stack spacing={1.5}>
                  {vehicle.registrationNumber && (
                    <Box display="flex" alignItems="center" gap={1}>
                      <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                        {t('common.registrationNumber')}:
                      </Typography>
                      <LicensePlate registrationNumber={vehicle.registrationNumber} />
                    </Box>
                  )}
                  {vehicle.vin && (
                    <Box display="flex" alignItems="center" gap={1}>
                      <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                        {t('vehicle.vin')}:
                      </Typography>
                      <Typography variant="body1" fontFamily="monospace" fontWeight="500">
                        {vehicle.vin}
                      </Typography>
                    </Box>
                  )}
                  {vehicle.engineNumber && (
                    <Box display="flex" alignItems="center" gap={1}>
                      <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                        {t('vehicle.engineNumber')}:
                      </Typography>
                      <Typography variant="body1">{vehicle.engineNumber}</Typography>
                    </Box>
                  )}
                  {vehicle.v5DocumentNumber && (
                    <Box display="flex" alignItems="center" gap={1}>
                      <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                        {t('vehicle.v5DocumentNumber')}:
                      </Typography>
                      <Typography variant="body1">{vehicle.v5DocumentNumber}</Typography>
                    </Box>
                  )}
                  <Box display="flex" alignItems="center" gap={1}>
                    <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                      {t('vehicle.purchaseMileage')}:
                    </Typography>
                    <Box display="flex" alignItems="center" gap={0.5}>
                      <SpeedIcon fontSize="small" color="action" />
                      <Typography variant="h6" color="primary">
                        {vehicle.purchaseMileage ? format(convert(vehicle.purchaseMileage)) : t('vehicleDetails.na')}
                      </Typography>
                    </Box>
                  </Box>
                  <Box display="flex" alignItems="center" gap={1}>
                    <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                      {t('vehicle.currentMileage')}:
                    </Typography>
                    <Box display="flex" alignItems="center" gap={0.5}>
                      <SpeedIcon fontSize="small" color="action" />
                      <Typography variant="h6" color="primary">
                        {vehicle.currentMileage ? format(convert(vehicle.currentMileage)) : t('vehicleDetails.na')}
                      </Typography>
                    </Box>
                  </Box>
                </Stack>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} md={6}>
            <Card sx={{ height: '100%' }}>
              <CardContent>
                <Box display="flex" alignItems="center" gap={1} mb={2}>
                  <EventIcon color="primary" />
                  <Typography variant="h6">{t('vehicleDetails.serviceDates')}</Typography>
                </Box>
                <Divider sx={{ mb: 2 }} />
                <Stack spacing={1.5}>
                  <Box display="flex" alignItems="center" gap={1}>
                    <BuildIcon fontSize="small" color="action" />
                    <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                      {t('vehicleDetails.lastService')}:
                    </Typography>
                    {vehicle.lastServiceDate ? (
                      <Chip
                        icon={getServiceStatus(vehicle).icon}
                        label={vehicle.lastServiceDate}
                        color={getServiceStatus(vehicle).color}
                        variant="outlined"
                        size="small"
                      />
                    ) : (
                      <Typography variant="body1">{t('vehicleDetails.na')}</Typography>
                    )}
                  </Box>
                  <Box display="flex" alignItems="center" gap={1}>
                    <AssignmentIcon fontSize="small" color="action" />
                    <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                      {t('vehicleDetails.motExpiry')}:
                    </Typography>
                    {vehicle.motExpiryDate ? (
                      <Chip
                        icon={getDateStatus(vehicle.motExpiryDate)?.icon}
                        label={vehicle.motExpiryDate}
                        color={getDateStatus(vehicle.motExpiryDate)?.color}
                        variant="outlined"
                        size="small"
                      />
                    ) : (
                      <Typography variant="body1">{t('vehicleDetails.na')}</Typography>
                    )}
                  </Box>
                  <Box display="flex" alignItems="center" gap={1}>
                    <AssignmentIcon fontSize="small" color="action" />
                    <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                      {t('vehicleDetails.roadTaxExpiry')}:
                    </Typography>
                    {vehicle.roadTaxExpiryDate ? (
                      <Chip
                        icon={getDateStatus(vehicle.roadTaxExpiryDate)?.icon}
                        label={vehicle.roadTaxExpiryDate}
                        color={getDateStatus(vehicle.roadTaxExpiryDate)?.color}
                        variant="outlined"
                        size="small"
                      />
                    ) : (
                      <Typography variant="body1">{t('vehicleDetails.na')}</Typography>
                    )}
                  </Box>
                  <Box display="flex" alignItems="center" gap={1}>
                    <AssignmentIcon fontSize="small" color="action" />
                    <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                      {t('vehicleDetails.insuranceExpiry')}:
                    </Typography>
                    {vehicle.insuranceExpiryDate ? (
                      <Chip
                        icon={getDateStatus(vehicle.insuranceExpiryDate)?.icon}
                        label={vehicle.insuranceExpiryDate}
                        color={getDateStatus(vehicle.insuranceExpiryDate)?.color}
                        variant="outlined"
                        size="small"
                      />
                    ) : (
                      <Typography variant="body1">{t('vehicleDetails.na')}</Typography>
                    )}
                  </Box>
                </Stack>
              </CardContent>
            </Card>
          </Grid>
          {vehicle.vin && (
            <Grid item xs={12}>
              <VinDecoder vehicle={vehicle} />
            </Grid>
          )}
        </Grid>
      )}

      {tab === 1 && (
        <Grid container spacing={3} alignItems="flex-start">
          <Grid item xs={12} md={8}>
            <Grid container spacing={3}>
              {/* Row 1: Key totals */}
              <Grid item xs={12} sm={6} md={3}>
                  <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                    <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.totalCostToDate') || 'Purchase cost + running costs to date'}>
                      <Typography color="textSecondary" gutterBottom>{t('stats.totalCostToDate')}</Typography>
                    </Tooltip>
                    <Typography variant="h5">{formatCurrency(stats.stats.totalCostToDate, 'GBP', i18n.language)}</Typography>
                    </CardContent>
                    <MoneyIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                  </Card>
              </Grid>

              <Grid item xs={12} sm={6} md={3}>
                  <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                    <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.purchaseCost') || 'Original purchase cost of the vehicle'}>
                      <Typography color="textSecondary" gutterBottom>{t('vehicle.purchaseCost')}</Typography>
                    </Tooltip>
                    <Typography variant="h5">{formatCurrency(stats.stats.purchaseCost, 'GBP', i18n.language)}</Typography>
                  </CardContent>
                  <ShoppingCartIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                </Card>
              </Grid>

              <Grid item xs={12} sm={6} md={3}>
                <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                  <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.currentValue') || 'Estimated current market value'}>
                      <Typography color="textSecondary" gutterBottom>{t('stats.currentValue')}</Typography>
                    </Tooltip>
                    <Typography variant="h5">{formatCurrency(stats.stats.currentValue, 'GBP', i18n.language)}</Typography>
                  </CardContent>
                  <TrendingUpIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                </Card>
              </Grid>

              <Grid item xs={12} sm={6} md={3}>
                <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                  <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.totalRunningCost') || 'Fuel + service + standalone parts/consumables (excluding items already counted in services or MOT records)'}>
                      <Typography color="textSecondary" gutterBottom>{t('stats.totalRunningCost')}</Typography>
                    </Tooltip>
                    <Typography variant="h5">{formatCurrency(stats.stats.totalRunningCost, 'GBP', i18n.language)}</Typography>
                  </CardContent>
                  <BuildIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                </Card>
              </Grid>

              {/* Row 2: fuel/parts/service */}
              <Grid item xs={12} sm={6} md={3}>
                <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                  <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.totalFuelCost') || 'Sum of fuel costs for this vehicle'}>
                      <Typography color="textSecondary" gutterBottom>{t('stats.totalFuelCost')}</Typography>
                    </Tooltip>
                    <Typography variant="h5">{formatCurrency(stats.stats.totalFuelCost, 'GBP', i18n.language)}</Typography>
                  </CardContent>
                  <GasIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                </Card>
              </Grid>

              <Grid item xs={12} sm={6} md={3}>
                <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                  <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.totalPartsCost') || 'Total parts costs (excluding items linked to services or MOT records)'}>
                      <Typography color="textSecondary" gutterBottom>{t('stats.totalPartsCost')}</Typography>
                    </Tooltip>
                    <Typography variant="h5">{formatCurrency(stats.stats.totalPartsCost, 'GBP', i18n.language)}</Typography>
                  </CardContent>
                  <ConstructionIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                </Card>
              </Grid>

              <Grid item xs={12} sm={6} md={3}>
                <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                  <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.totalConsumablesCost') || 'Total consumables costs (excluding items linked to services or MOT records)'}>
                      <Typography color="textSecondary" gutterBottom>{t('consumables.totalCost') || 'Total Consumables Cost'}</Typography>
                    </Tooltip>
                    <Typography variant="h5">{formatCurrency(stats.stats.totalConsumablesCost ?? 0, 'GBP', i18n.language)}</Typography>
                  </CardContent>
                  <OpacityIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                </Card>
              </Grid>

              <Grid item xs={12} sm={6} md={3}>
                <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                  <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.totalServiceCost') || 'Service costs (includes parts/consumables installed/used and MOT records)'}>
                      <Typography color="textSecondary" gutterBottom>{t('vehicleDetails.serviceCosts') || 'Service Costs'}</Typography>
                    </Tooltip>
                    <Typography variant="h5">{formatCurrency(stats.stats.totalServiceCost ?? 0, 'GBP', i18n.language)}</Typography>
                  </CardContent>
                  <BuildIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                </Card>
              </Grid>

              {stats.stats.averageFuelConsumption && (
                <Grid item xs={12} sm={6} md={3}>
                  <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                    <CardContent>
                      <Tooltip title={t('vehicleDetails.tooltip.avgFuelConsumption') || 'Average fuel consumption (litres/100km) calculated from fuel records'}>
                        <Typography color="textSecondary" gutterBottom>{t('vehicleDetails.avgFuelConsumption')}</Typography>
                      </Tooltip>
                      <Typography variant="h5">
                        {convertFuelConsumption(stats.stats.averageFuelConsumption).toFixed(1)} {getFuelConsumptionLabel()}
                      </Typography>
                    </CardContent>
                    <GasIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                  </Card>
                </Grid>
              )}

              <Grid item xs={12} sm={6} md={3}>
                <Card sx={{ position: 'relative', overflow: 'hidden' }}>
                  <CardContent>
                    <Tooltip title={t('vehicleDetails.tooltip.costPerDistance') || 'Total running cost divided by current mileage'}>
                      <Typography color="textSecondary" gutterBottom>
                        {t('vehicleDetails.costPerDistance')} ({getLabel()})
                      </Typography>
                    </Tooltip>
                    <Typography variant="h5">
                      {stats.stats.costPerMile !== null && stats.stats.costPerMile !== undefined
                        ? formatCurrency((stats.stats.costPerMile * (userUnit === 'mi' ? 1.60934 : 1)), 'GBP', i18n.language)
                        : t('na')}
                    </Typography>
                  </CardContent>
                  <MoneyIcon sx={{ position: 'absolute', right: 8, bottom: 8, fontSize: 84, color: 'primary.main', opacity: 0.06, pointerEvents: 'none' }} />
                </Card>
              </Grid>
            </Grid>
          </Grid>

          {/* Right column: pie chart */}
          <Grid item xs={12} md={4}>
            <Card sx={{ height: 'auto', overflow: 'visible' }}>
              <CardContent sx={{ height: '100%', display: 'flex', flexDirection: 'column', alignItems: 'stretch', justifyContent: 'flex-start', p: 1, position: 'relative', overflow: 'visible' }}>
                <Typography variant="h6" sx={{ textAlign: 'center', mb: 1 }}>{t('vehicleDetails.costBreakdown') || 'Cost Breakdown'}</Typography>
                <Box sx={{ display: 'flex', flexDirection: { xs: 'column', md: 'row' }, alignItems: 'flex-start', width: '100%', overflow: 'visible' }}>
                  {(() => {
                    const purchase = Number(stats.stats.purchaseCost || 0);
                    const fuel = Number(stats.stats.totalFuelCost || 0);
                    const parts = Number(stats.stats.totalPartsCost || 0);
                    const service = Number(stats.stats.totalServiceCost ?? 0);
                    const total = purchase + fuel + parts + service || 1;
                    const pieData = [
                      { id: 'purchase', label: t('vehicle.purchaseCost') || 'Purchase Cost', value: purchase, color: '#4caf50' },
                      { id: 'fuel', label: t('stats.totalFuelCost') || 'Total Fuel Cost', value: fuel, color: '#2196f3' },
                      { id: 'parts', label: t('stats.totalPartsCost') || 'Total Parts Cost', value: parts, color: '#ff9800' },
                      { id: 'service', label: t('vehicleDetails.serviceCosts') || 'Service Costs', value: service, color: '#f44336' },
                    ];

                    const pieWithPct = pieData.map((p) => ({ value: p.value, label: p.label, color: p.color, pct: ((p.value / total) * 100).toFixed(1) }));

                    return (
                      <Box sx={{ width: '100%', display: 'flex', flexDirection: { xs: 'column', md: 'row' }, alignItems: 'flex-start', justifyContent: 'space-between', overflow: 'visible' }}>
                        <Box sx={{ width: { xs: '100%', md: '40%' }, pr: { md: 2 }, boxSizing: 'border-box' }}>
                          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, pl: 1 }}>
                            {pieWithPct.map((p) => (
                              <Box key={p.id} sx={{ display: 'flex', alignItems: 'center', gap: 1, py: 0.5 }}>
                                <Box sx={{ width: 14, height: 14, backgroundColor: p.color, borderRadius: 2, flex: '0 0 auto' }} />
                                <Typography variant="body2" sx={{ color: 'text.secondary', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{p.label}</Typography>
                                <Box sx={{ ml: '6px', flex: '0 0 auto' }}>
                                  <Typography variant="body2">{p.pct}%</Typography>
                                </Box>
                              </Box>
                            ))}
                          </Box>
                        </Box>

                        <Box sx={{ width: { xs: '100%', md: '58%' }, display: 'flex', alignItems: 'flex-start', justifyContent: 'center', pt: 0 }}>
                          <Box ref={pieRef} sx={{ width: '100%', maxWidth: 420, height: pieSize, display: 'flex', alignItems: 'flex-start', justifyContent: 'center', mt: '-6px', '& .MuiChartLegend-root, & .MuiLegend-root, & .MuiPieLegend-root, & .recharts-legend-wrapper, & g[role="legend"], & .vx-legend, & .legend, & foreignObject, & .MuiPieArcLabel-root, & text, & svg rect': { display: 'none !important' } }}>
                            <PieChart
                              series={[{ data: pieWithPct.map(p => ({ value: p.value, label: p.label, color: p.color })), label: { visible: false } }]}
                              width={pieSize}
                              height={pieSize}
                              slotProps={{ legend: { visible: false }, arc: { label: { visible: false } } }}
                            />
                          </Box>
                        </Box>
                      </Box>
                    );
                  })()}
                </Box>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}

      {tab === 2 && (
        <Card>
          <CardContent>
            <Typography variant="h6" gutterBottom>{t('vehicleDetails.depreciationSchedule')}</Typography>
            <Grid container spacing={2} alignItems="flex-start">
              <Grid item xs={12} md={10}>
                {(() => {
                  if (depreciationLoading) {
                    return <KnightRiderLoader size={28} />;
                  }

                  const schedule = depreciationSchedule || [];
                  if (!schedule.length) {
                    return <Typography>{t('vehicleDetails.noDepreciationData') || 'No data'}</Typography>;
                  }

                  const baseYear = vehicle.purchaseDate
                    ? new Date(vehicle.purchaseDate).getFullYear()
                    : (vehicle.year || new Date().getFullYear());

                  const categories = schedule.map((s) => String(Math.trunc(baseYear + Number(s.year))));
                  const values = schedule.map((s) => Number(s.value));

                  return (
                    <LineChart
                      xAxis={[{ data: categories, label: t('common.year'), scaleType: 'point' }]}
                      series={[{ data: values, label: t('vehicleDetails.value') }]}
                      height={340}
                    />
                  );
                })()}
              </Grid>

              <Grid item xs={12} md={2}>
                <Typography variant="subtitle1" gutterBottom>{t('vehicleDetails.valuesTableTitle')}</Typography>
                <TablePaginationBar
                  count={(depreciationSchedule || []).length}
                  page={page}
                  rowsPerPage={rowsPerPage}
                  onPageChange={handleChangePage}
                  onRowsPerPageChange={handleChangeRowsPerPage}
                />
                <TableContainer component={Paper} sx={{ overflow: 'visible' }}>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>{t('common.year')}</TableCell>
                        <TableCell align="right">{t('vehicleDetails.value')}</TableCell>
                      </TableRow>
                    </TableHead>
                        <TableBody>
                          {(depreciationSchedule || []).length === 0 ? (
                            <TableRow>
                              <TableCell colSpan={2} align="center">
                                {t('common.noRecords')}
                              </TableCell>
                            </TableRow>
                          ) : (
                            paginatedDepreciation.map((s, idx) => {
                            const baseYear = vehicle.purchaseDate
                              ? new Date(vehicle.purchaseDate).getFullYear()
                              : (vehicle.year || new Date().getFullYear());
                            const yearLabel = Math.trunc(baseYear + Number(s.year));
                            const formatter = new Intl.NumberFormat(i18n.language || undefined, { style: 'currency', currency: 'GBP' });
                            const valueLabel = formatter.format(Number(s.value));
                            return (
                              <TableRow key={idx}>
                                <TableCell>{yearLabel}</TableCell>
                                <TableCell align="right">{valueLabel}</TableCell>
                              </TableRow>
                            );
                          })
                          )}
                        </TableBody>
                  </Table>
                </TableContainer>
                <TablePaginationBar
                  count={(depreciationSchedule || []).length}
                  page={page}
                  rowsPerPage={rowsPerPage}
                  onPageChange={handleChangePage}
                  onRowsPerPageChange={handleChangeRowsPerPage}
                />
              </Grid>
            </Grid>
          </CardContent>
        </Card>
      )}

      {tab === 3 && vehicle && (
        <VehicleSpecifications vehicle={vehicle} />
      )}

      {tab === 4 && vehicle && (
        <VehicleImages vehicle={vehicle} />
      )}

      {tab === 5 && vehicle && (
        <VehicleDocuments vehicle={vehicle} category="documentation" />
      )}

      {tab === 6 && vehicle && (
        <VehicleDocuments vehicle={vehicle} category="user_manual" />
      )}

      {tab === 7 && vehicle && (
        <VehicleDocuments vehicle={vehicle} category="service_manual" />
      )}
    </Box>
  );
};

export default VehicleDetails;
