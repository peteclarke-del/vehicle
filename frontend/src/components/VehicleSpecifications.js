import React, { useState, useEffect, useCallback } from 'react';
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
import { useTranslation } from 'react-i18next';

// Stable memoized spec field component to avoid remounting and losing focus
const SpecField = React.memo(function SpecField({ label, field, multiline = false, editing, formValue, onChange, specifications }) {
  if (editing) {
    return (
      <TextField
        label={label}
        value={formValue || ''}
        onChange={onChange}
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
        import { useTranslation } from 'react-i18next';
      </Typography>
      <Typography variant="body2">{value}</Typography>
    </Box>
  );
});

const VehicleSpecifications = ({ vehicle }) => {
  const { api } = useAuth();
  const { t } = useTranslation();
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

  const handleChange = useCallback((field) => (event) => {
    const val = event.target.value;
    setFormData(prev => ({ ...prev, [field]: val }));
  }, []);


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
        <Typography variant="h6">{t('vehicleSpecifications.title')}</Typography>
        <Box display="flex" gap={1}>
          {!editing && (
            <>
              <Tooltip title={t('vehicleSpecifications.scrapeTooltip')}>
                <Button
                  variant="outlined"
                  startIcon={scraping ? <CircularProgress size={20} /> : <Download />}
                  onClick={handleScrape}
                  disabled={scraping || !vehicle.make || !vehicle.model}
                >
                  {scraping ? t('vehicleSpecifications.scraping') : t('vehicleSpecifications.scrapeOnline')}
                </Button>
              </Tooltip>
              {!vehicle.make || !vehicle.model ? (
                <Typography variant="caption" color="text.secondary" sx={{ ml: 1 }}>
                  ({t('vehicleSpecifications.requiresMakeModel')})
                </Typography>
              ) : null}
              <Tooltip title={t('vehicleSpecifications.editTooltip')}>
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
                {t('common.save')}
              </Button>
              <Button
                variant="outlined"
                startIcon={<Cancel />}
                onClick={handleCancel}
                disabled={saving}
              >
                {t('common.cancel')}
              </Button>
            </>
          )}
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {t('vehicleSpecifications.errorPrefix') + ': ' + error}
        </Alert>
      )}

      {success && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess(null)}>
          {success}
        </Alert>
      )}

      {!specifications && !editing && (
        <Alert severity="info">
          {t('vehicleSpecifications.noSpecificationsInfo')}
        </Alert>
      )}

      {(specifications || editing) && (
        <>
          <Typography variant="subtitle1" gutterBottom sx={{ mt: 2, fontWeight: 'bold' }}>
            {t('vehicleSpecifications.engine')}
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label={t('vehicleSpecifications.engineType')} field="engineType" editing={editing} formValue={formData.engineType} onChange={handleChange('engineType')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label={t('vehicleSpecifications.displacement')} field="displacement" editing={editing} formValue={formData.displacement} onChange={handleChange('displacement')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label={t('vehicleSpecifications.power')} field="power" editing={editing} formValue={formData.power} onChange={handleChange('power')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label={t('vehicleSpecifications.torque')} field="torque" editing={editing} formValue={formData.torque} onChange={handleChange('torque')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label={t('vehicleSpecifications.compression')} field="compression" editing={editing} formValue={formData.compression} onChange={handleChange('compression')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label={t('vehicleSpecifications.bore')} field="bore" editing={editing} formValue={formData.bore} onChange={handleChange('bore')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label={t('vehicleSpecifications.stroke')} field="stroke" editing={editing} formValue={formData.stroke} onChange={handleChange('stroke')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label={t('vehicleSpecifications.fuelSystem')} field="fuelSystem" editing={editing} formValue={formData.fuelSystem} onChange={handleChange('fuelSystem')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Cooling" field="cooling" editing={editing} formValue={formData.cooling} onChange={handleChange('cooling')} specifications={specifications} />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Transmission
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Gearbox" field="gearbox" editing={editing} formValue={formData.gearbox} onChange={handleChange('gearbox')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Transmission" field="transmission" editing={editing} formValue={formData.transmission} onChange={handleChange('transmission')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Clutch" field="clutch" editing={editing} formValue={formData.clutch} onChange={handleChange('clutch')} specifications={specifications} />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Chassis & Suspension
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Frame" field="frame" editing={editing} formValue={formData.frame} onChange={handleChange('frame')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Front Suspension" field="frontSuspension" editing={editing} formValue={formData.frontSuspension} onChange={handleChange('frontSuspension')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Rear Suspension" field="rearSuspension" editing={editing} formValue={formData.rearSuspension} onChange={handleChange('rearSuspension')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Front Wheel Travel" field="frontWheelTravel" editing={editing} formValue={formData.frontWheelTravel} onChange={handleChange('frontWheelTravel')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Rear Wheel Travel" field="rearWheelTravel" editing={editing} formValue={formData.rearWheelTravel} onChange={handleChange('rearWheelTravel')} specifications={specifications} />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Brakes & Tyres
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Front Brakes" field="frontBrakes" editing={editing} formValue={formData.frontBrakes} onChange={handleChange('frontBrakes')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Rear Brakes" field="rearBrakes" editing={editing} formValue={formData.rearBrakes} onChange={handleChange('rearBrakes')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Front Tyre" field="frontTyre" editing={editing} formValue={formData.frontTyre} onChange={handleChange('frontTyre')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Rear Tyre" field="rearTyre" editing={editing} formValue={formData.rearTyre} onChange={handleChange('rearTyre')} specifications={specifications} />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Dimensions & Weight
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Wheelbase" field="wheelbase" editing={editing} formValue={formData.wheelbase} onChange={handleChange('wheelbase')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Seat Height" field="seatHeight" editing={editing} formValue={formData.seatHeight} onChange={handleChange('seatHeight')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Ground Clearance" field="groundClearance" editing={editing} formValue={formData.groundClearance} onChange={handleChange('groundClearance')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Dry Weight" field="dryWeight" editing={editing} formValue={formData.dryWeight} onChange={handleChange('dryWeight')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Wet Weight" field="wetWeight" editing={editing} formValue={formData.wetWeight} onChange={handleChange('wetWeight')} specifications={specifications} />
            </Grid>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Fuel Capacity" field="fuelCapacity" editing={editing} formValue={formData.fuelCapacity} onChange={handleChange('fuelCapacity')} specifications={specifications} />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Performance
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={4}>
              <SpecField label="Top Speed" field="topSpeed" editing={editing} formValue={formData.topSpeed} onChange={handleChange('topSpeed')} specifications={specifications} />
            </Grid>
          </Grid>

          <Typography variant="subtitle1" gutterBottom sx={{ mt: 3, fontWeight: 'bold' }}>
            Additional Information
          </Typography>
          <Divider sx={{ mb: 2 }} />
          <Grid container spacing={2}>
            <Grid item xs={12}>
              <SpecField label="Notes" field="additionalInfo" multiline editing={editing} formValue={formData.additionalInfo} onChange={handleChange('additionalInfo')} specifications={specifications} />
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
