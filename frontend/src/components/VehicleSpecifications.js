import React, { useState, useEffect } from 'react';
import {
  Box,
  Paper,
  Typography,
  TextField,
  Button,
  Grid,
  CircularProgress,
  Alert,
  Divider,
  IconButton,
  Tooltip,
} from '@mui/material';
import { Edit, Save, Cancel, Download } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';

const VehicleSpecifications = ({ vehicle }) => {
  const { api } = useAuth();
  const [specifications, setSpecifications] = useState(null);
  const [loading, setLoading] = useState(true);
  const [scraping, setScraping] = useState(false);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [formData, setFormData] = useState({});

  useEffect(() => {
    if (vehicle) {
      loadSpecifications();
    }
  }, [vehicle]);

  const loadSpecifications = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.get(`/vehicles/${vehicle.id}/specifications`);
      setSpecifications(response.data);
      if (response.data) {
        setFormData(response.data);
      }
    } catch (err) {
      if (err.response?.status !== 404) {
        setError('Failed to load specifications');
      }
    } finally {
      setLoading(false);
    }
  };

  const handleScrape = async () => {
    try {
      setScraping(true);
      setError(null);
      setSuccess(null);
      const response = await api.post(`/vehicles/${vehicle.id}/specifications/scrape`);
      setSpecifications(response.data.specification);
      setFormData(response.data.specification);
      setSuccess('Specifications scraped successfully!');
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to scrape specifications. Please enter manually.');
    } finally {
      setScraping(false);
    }
  };

  const handleEdit = () => {
    setEditing(true);
    setError(null);
    setSuccess(null);
  };

  const handleCancel = () => {
    setEditing(false);
    setFormData(specifications || {});
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      setError(null);
      const response = await api.put(`/vehicles/${vehicle.id}/specifications`, formData);
      setSpecifications(response.data.specification);
      setFormData(response.data.specification);
      setSuccess('Specifications saved successfully!');
      setEditing(false);
    } catch (err) {
      setError('Failed to save specifications');
    } finally {
      setSaving(false);
    }
  };

  const handleChange = (field) => (event) => {
    setFormData({ ...formData, [field]: event.target.value });
  };

  const SpecField = ({ label, field, multiline = false }) => {
    if (editing) {
      return (
        <TextField
          label={label}
          value={formData[field] || ''}
          onChange={handleChange(field)}
          fullWidth
          size="small"
          multiline={multiline}
          rows={multiline ? 3 : 1}
        />
      );
    }
    
    const value = specifications?.[field];
    if (!value) return null;
    
    return (
      <Box>
        <Typography variant="caption" color="text.secondary">
          {label}
        </Typography>
        <Typography variant="body2">{value}</Typography>
      </Box>
    );
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" p={3}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Paper sx={{ p: 3 }}>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h6">Vehicle Specifications</Typography>
        <Box display="flex" gap={1}>
          {!editing && (
            <>
              <Tooltip title="Scrape specifications from online sources">
                <Button
                  variant="outlined"
                  startIcon={scraping ? <CircularProgress size={20} /> : <Download />}
                  onClick={handleScrape}
                  disabled={scraping || !vehicle.make || !vehicle.model}
                >
                  {scraping ? 'Scraping...' : 'Scrape Online'}
                </Button>
              </Tooltip>
              {!vehicle.make || !vehicle.model ? (
                <Typography variant="caption" color="text.secondary" sx={{ ml: 1 }}>
                  (Requires make and model)
                </Typography>
              ) : null}
              <Tooltip title="Edit specifications">
                <IconButton onClick={handleEdit} color="primary">
                  <Edit />
                </IconButton>
              </Tooltip>
            </>
          )}
          {editing && (
            <>
              <Button
                variant="contained"
                startIcon={saving ? <CircularProgress size={20} /> : <Save />}
                onClick={handleSave}
                disabled={saving}
              >
                Save
              </Button>
              <Button
                variant="outlined"
                startIcon={<Cancel />}
                onClick={handleCancel}
                disabled={saving}
              >
                Cancel
              </Button>
            </>
          )}
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}

      {!specifications && !editing && (
        <Alert severity="info">
          No specifications found. Click "Scrape Online" to fetch from online sources (currently supports motorcycles), or click the edit button to enter manually. All vehicle types (cars, trucks, vans, motorcycles) are supported - not all fields may be applicable to your vehicle type.
        </Alert>
      )}

      {(specifications || editing) && (
        <>
          <Typography variant="subtitle1" gutterBottom sx={{ mt: 2, fontWeight: 'bold' }}>
            Engine
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Engine Type" field="engineType" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Displacement" field="displacement" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Power" field="power" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Torque" field="torque" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Compression" field="compression" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Bore" field="bore" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Stroke" field="stroke" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Fuel System" field="fuelSystem" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Cooling" field="cooling" />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Transmission
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Gearbox" field="gearbox" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Transmission" field="transmission" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Clutch" field="clutch" />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Chassis & Suspension
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Frame" field="frame" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Front Suspension" field="frontSuspension" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Rear Suspension" field="rearSuspension" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Front Wheel Travel" field="frontWheelTravel" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Rear Wheel Travel" field="rearWheelTravel" />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Brakes & Tyres
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Front Brakes" field="frontBrakes" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Rear Brakes" field="rearBrakes" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Front Tyre" field="frontTyre" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Rear Tyre" field="rearTyre" />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Dimensions & Weight
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Wheelbase" field="wheelbase" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Seat Height" field="seatHeight" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Ground Clearance" field="groundClearance" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Dry Weight" field="dryWeight" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Wet Weight" field="wetWeight" />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Fuel Capacity" field="fuelCapacity" />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Performance
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Top Speed" field="topSpeed" />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Additional Information
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12}>
              <SpecField label="Notes" field="additionalInfo" multiline />
            </Grid>
          </Grid>

          {specifications?.scrapedAt && (
            <Box mt={3}>
              <Typography variant="caption" color="text.secondary">
                Last scraped: {new Date(specifications.scrapedAt).toLocaleString()}
                {specifications.sourceUrl && (
                  <> from <a href={specifications.sourceUrl} target="_blank" rel="noopener noreferrer">source</a></>
                )}
              </Typography>
            </Box>
          )}
        </>
      )}
    </Paper>
  );
};

export default VehicleSpecifications;
