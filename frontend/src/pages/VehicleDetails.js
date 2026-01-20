import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Box,
  Card,
  CardContent,
  Grid,
  Typography,
  CircularProgress,
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
  CheckCircle as CheckIcon,
  Warning as WarningIcon,
  Error as ErrorIcon,
  ArrowBack as ArrowBackIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useDistance } from '../hooks/useDistance';
import VehicleSpecifications from '../components/VehicleSpecifications';
import VehicleImages from '../components/VehicleImages';
import VinDecoder from '../components/VinDecoder';
import LicensePlate from '../components/LicensePlate';
import { LineChart } from '@mui/x-charts/LineChart';

const VehicleDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [vehicle, setVehicle] = useState(null);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState(0);
  const { api, user } = useAuth();
  const { t, i18n } = useTranslation();
  const { convert, format, getLabel, convertFuelConsumption, getFuelConsumptionLabel, userUnit } = useDistance();

  console.log('VehicleDetails - userUnit:', userUnit);
  console.log('VehicleDetails - getFuelConsumptionLabel():', getFuelConsumptionLabel());
  console.log('VehicleDetails - getLabel():', getLabel());
  console.log('VehicleDetails - user:', user);

  useEffect(() => {
    loadVehicleData();
  }, [id]);

  const loadVehicleData = async () => {
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
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="60vh">
        <CircularProgress />
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
                  {vehicle.registrationNumber && (
                    <Box display="flex" alignItems="center" gap={1}>
                      <Typography variant="body2" color="textSecondary" sx={{ minWidth: 140 }}>
                        {t('vehicle.registrationNumber')}:
                      </Typography>
                      <LicensePlate registrationNumber={vehicle.registrationNumber} />
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
                        icon={<CheckIcon />}
                        label={vehicle.lastServiceDate}
                        color="success"
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
                        icon={new Date(vehicle.motExpiryDate) > new Date() ? <CheckIcon /> : <ErrorIcon />}
                        label={vehicle.motExpiryDate}
                        color={new Date(vehicle.motExpiryDate) > new Date() ? 'success' : 'error'}
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
                        icon={new Date(vehicle.roadTaxExpiryDate) > new Date() ? <CheckIcon /> : <WarningIcon />}
                        label={vehicle.roadTaxExpiryDate}
                        color={new Date(vehicle.roadTaxExpiryDate) > new Date() ? 'success' : 'warning'}
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
                        icon={new Date(vehicle.insuranceExpiryDate) > new Date() ? <CheckIcon /> : <ErrorIcon />}
                        label={vehicle.insuranceExpiryDate}
                        color={new Date(vehicle.insuranceExpiryDate) > new Date() ? 'success' : 'error'}
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
        <Grid container spacing={3}>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Tooltip title={t('vehicleDetails.tooltip.purchaseCost') || 'Original purchase cost of the vehicle'}>
                  <Typography color="textSecondary" gutterBottom>{t('vehicle.purchaseCost')}</Typography>
                </Tooltip>
                <Typography variant="h5">£{stats.stats.purchaseCost}</Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Tooltip title={t('vehicleDetails.tooltip.currentValue') || 'Estimated current market value'}>
                  <Typography color="textSecondary" gutterBottom>{t('stats.currentValue')}</Typography>
                </Tooltip>
                <Typography variant="h5">£{stats.stats.currentValue}</Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Tooltip title={t('vehicleDetails.tooltip.totalFuelCost') || 'Sum of fuel costs for this vehicle'}>
                  <Typography color="textSecondary" gutterBottom>{t('stats.totalFuelCost')}</Typography>
                </Tooltip>
                <Typography variant="h5">£{stats.stats.totalFuelCost}</Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Tooltip title={t('vehicleDetails.tooltip.totalPartsCost') || 'Sum of parts costs for this vehicle'}>
                  <Typography color="textSecondary" gutterBottom>{t('stats.totalPartsCost')}</Typography>
                </Tooltip>
                <Typography variant="h5">£{stats.stats.totalPartsCost}</Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Tooltip title={t('vehicleDetails.tooltip.totalRunningCost') || 'Fuel + parts + consumables costs'}>
                  <Typography color="textSecondary" gutterBottom>{t('stats.totalRunningCost')}</Typography>
                </Tooltip>
                <Typography variant="h5">£{stats.stats.totalRunningCost}</Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Tooltip title={t('vehicleDetails.tooltip.totalCostToDate') || 'Purchase cost + running costs to date'}>
                  <Typography color="textSecondary" gutterBottom>{t('stats.totalCostToDate')}</Typography>
                </Tooltip>
                <Typography variant="h5">£{stats.stats.totalCostToDate}</Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Tooltip title={t('vehicleDetails.tooltip.costPerDistance') || 'Total running cost divided by current mileage'}>
                  <Typography color="textSecondary" gutterBottom>
                    {t('vehicleDetails.costPerDistance')} ({getLabel()})
                  </Typography>
                </Tooltip>
                <Typography variant="h5">
                  {stats.stats.costPerMile !== null && stats.stats.costPerMile !== undefined
                    ? `£${(stats.stats.costPerMile * (userUnit === 'mi' ? 1.60934 : 1)).toFixed(2)}`
                    : 'N/A'}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
          {stats.stats.averageFuelConsumption && (
            <Grid item xs={12} sm={6} md={3}>
              <Card>
                <CardContent>
                  <Tooltip title={t('vehicleDetails.tooltip.avgFuelConsumption') || 'Average fuel consumption (litres/100km) calculated from fuel records'}>
                    <Typography color="textSecondary" gutterBottom>{t('vehicleDetails.avgFuelConsumption')}</Typography>
                  </Tooltip>
                  <Typography variant="h5">
                    {convertFuelConsumption(stats.stats.averageFuelConsumption).toFixed(1)} {getFuelConsumptionLabel()}
                  </Typography>
                </CardContent>
              </Card>
            </Grid>
          )}
        </Grid>
      )}

      {tab === 2 && stats.depreciationSchedule && (
        <Card>
          <CardContent>
            <Typography variant="h6" gutterBottom>{t('vehicleDetails.depreciationSchedule')}</Typography>
            <Grid container spacing={2} alignItems="flex-start">
              <Grid item xs={12} md={10}>
                {(() => {
                  const baseYear = vehicle.purchaseDate
                    ? new Date(vehicle.purchaseDate).getFullYear()
                    : (vehicle.year || new Date().getFullYear());
                  const categories = stats.depreciationSchedule.map((s) => String(Math.trunc(baseYear + Number(s.year))));
                  const values = stats.depreciationSchedule.map((s) => Number(s.value));

                  // If no data, show a small placeholder to avoid rendering issues
                  if (!categories.length || !values.length) {
                    return <Typography>{t('vehicleDetails.noDepreciationData') || 'No data'}</Typography>;
                  }

                  return (
                    <LineChart
                      xAxis={[{ data: categories, label: t('vehicleDetails.year'), scaleType: 'point' }]}
                      series={[{ data: values, label: t('vehicleDetails.value') }]}
                      height={340}
                    />
                  );
                })()}
              </Grid>

              <Grid item xs={12} md={2}>
                <Typography variant="subtitle1" gutterBottom>{t('vehicleDetails.valuesTableTitle')}</Typography>
                <TableContainer component={Paper} sx={{ overflow: 'visible' }}>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>{t('vehicleDetails.year')}</TableCell>
                        <TableCell align="right">{t('vehicleDetails.value')}</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {stats.depreciationSchedule.map((s, idx) => {
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
                      })}
                    </TableBody>
                  </Table>
                </TableContainer>
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
    </Box>
  );
};

export default VehicleDetails;
