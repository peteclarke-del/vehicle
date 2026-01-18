import React, { useState, useEffect } from 'react';
import {
  Card,
  CardContent,
  Typography,
  Button,
  Box,
  Grid,
  Alert,
  CircularProgress,
  Chip,
  Divider,
} from '@mui/material';
import {
  QrCode as QrCodeIcon,
  Refresh as RefreshIcon,
  Info as InfoIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';

const VinDecoder = ({ vehicle }) => {
  const { api } = useAuth();
  const [loading, setLoading] = useState(false);
  const [vinData, setVinData] = useState(null);
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [cached, setCached] = useState(false);

  // Initialize with cached data from vehicle object if available
  useEffect(() => {
    if (vehicle?.vinDecodedData && !vinData) {
      setVinData(vehicle.vinDecodedData);
      setCached(true);
      if (vehicle.vinDecodedAt) {
        setInfo('VIN data loaded from cache (decoded on ' + vehicle.vinDecodedAt + ')');
      }
    }
  }, [vehicle?.vinDecodedData, vehicle?.vinDecodedAt]);

  const decodeVin = async (forceRefresh = false) => {
    if (!vehicle?.vin) {
      setError('No VIN number available for this vehicle');
      return;
    }

    setLoading(true);
    setError('');
    setInfo('');
    
    // Don't clear vinData immediately unless force refresh
    if (forceRefresh) {
      setVinData(null);
      setCached(false);
    }

    try {
      const url = forceRefresh 
        ? `/vehicles/${vehicle.id}/vin-decode?refresh=true`
        : `/vehicles/${vehicle.id}/vin-decode`;
      const response = await api.get(url);
      if (response.data.success && response.data.data) {
        setVinData(response.data.data);
        setCached(response.data.cached || false);
        if (response.data.cached) {
          setInfo('VIN data loaded from cache (decoded on ' + response.data.decoded_at + ')');
        } else {
          setInfo('VIN successfully decoded from API');
        }
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to decode VIN';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  // Auto-decode on mount only if we don't have cached data
  useEffect(() => {
    if (vehicle?.vin && !vinData && !error && !loading && !vehicle?.vinDecodedData) {
      decodeVin();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [vehicle?.id]);

  if (!vehicle?.vin) {
    return (
      <Card>
        <CardContent>
          <Box display="flex" alignItems="center" gap={1} mb={2}>
            <QrCodeIcon color="action" />
            <Typography variant="h6">VIN Decoding</Typography>
          </Box>
          <Alert severity="info">
            No VIN number stored for this vehicle. Add a VIN to enable automatic decoding.
          </Alert>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardContent>
        <Box display="flex" alignItems="center" justifyContent="space-between" mb={2}>
          <Box display="flex" alignItems="center" gap={1}>
            <QrCodeIcon color="primary" />
            <Typography variant="h6">VIN Decoding</Typography>
          </Box>
          <Button
            variant="outlined"
            size="small"
            startIcon={loading ? <CircularProgress size={16} /> : <RefreshIcon />}
            onClick={() => decodeVin(true)}
            disabled={loading}
            title="Force refresh from API (ignores cache)"
          >
            {loading ? 'Decoding...' : cached ? 'Refresh from API' : 'Refresh'}
          </Button>
        </Box>

        <Box mb={2}>
          <Typography variant="body2" color="textSecondary" gutterBottom>
            Vehicle Identification Number
          </Typography>
          <Typography variant="h5" fontFamily="monospace" fontWeight="bold">
            {vehicle.vin}
          </Typography>
        </Box>

        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}

        {info && !error && (
          <Alert severity="success" sx={{ mb: 2 }} onClose={() => setInfo('')}>
            {info}
          </Alert>
        )}

        {loading && !vinData && (
          <Box display="flex" justifyContent="center" alignItems="center" py={4}>
            <CircularProgress />
          </Box>
        )}

        {vinData && !loading && (
          <>
            <Divider sx={{ my: 2 }} />

            <Grid container spacing={2}>
              {vinData.make && (
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="textSecondary">
                    Manufacturer
                  </Typography>
                  <Typography variant="body1" fontWeight="medium">
                    {vinData.make}
                  </Typography>
                </Grid>
              )}

              {vinData.model && (
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="textSecondary">
                    Model
                  </Typography>
                  <Typography variant="body1" fontWeight="medium">
                    {vinData.model}
                  </Typography>
                </Grid>
              )}

              {vinData.year && (
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="textSecondary">
                    Year
                  </Typography>
                  <Typography variant="body1" fontWeight="medium">
                    {vinData.year}
                  </Typography>
                </Grid>
              )}

              {vinData.class && (
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="textSecondary">
                    Vehicle Class
                  </Typography>
                  <Typography variant="body1" fontWeight="medium">
                    {vinData.class}
                  </Typography>
                </Grid>
              )}

              {vinData.country && (
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="textSecondary">
                    Country of Origin
                  </Typography>
                  <Typography variant="body1" fontWeight="medium">
                    {vinData.country}
                  </Typography>
                </Grid>
              )}

              {vinData.region && (
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="textSecondary">
                    Region
                  </Typography>
                  <Typography variant="body1" fontWeight="medium">
                    {vinData.region}
                  </Typography>
                </Grid>
              )}
            </Grid>

            {(vinData.wmi || vinData.vds || vinData.vis) && (
              <>
                <Divider sx={{ my: 2 }} />
                <Typography variant="subtitle2" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <InfoIcon fontSize="small" />
                  VIN Structure
                </Typography>
                <Box display="flex" flexWrap="wrap" gap={1} mt={1}>
                  {vinData.wmi && (
                    <Chip
                      label={`WMI: ${vinData.wmi}`}
                      size="small"
                      variant="outlined"
                      title="World Manufacturer Identifier (characters 1-3)"
                    />
                  )}
                  {vinData.vds && (
                    <Chip
                      label={`VDS: ${vinData.vds}`}
                      size="small"
                      variant="outlined"
                      title="Vehicle Descriptor Section (characters 4-9)"
                    />
                  )}
                  {vinData.vis && (
                    <Chip
                      label={`VIS: ${vinData.vis}`}
                      size="small"
                      variant="outlined"
                      title="Vehicle Identifier Section (characters 10-17)"
                    />
                  )}
                </Box>
              </>
            )}
          </>
        )}
      </CardContent>
    </Card>
  );
};

export default VinDecoder;
