import React, { useState, useEffect, useCallback } from 'react';
import {
  Box,
  Paper,
  Typography,
  TextField,
  Button,
  Grid,
  Alert,
  IconButton,
  Tooltip,
  Accordion,
  AccordionSummary,
  AccordionDetails,
  Chip,
} from '@mui/material';
import { Edit, Save, Cancel, Download, ExpandMore } from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import KnightRiderLoader from './KnightRiderLoader';

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
    <Box
      sx={{
        p: 1.25,
        borderRadius: 1,
        transition: 'background-color 0.2s ease, transform 0.2s ease',
        '&:hover': {
          backgroundColor: 'action.hover',
          transform: 'translateY(-1px)',
        },
      }}
    >
      <Typography variant="overline" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="body1" fontWeight={600}>
        {value}
      </Typography>
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

  const loadSpecifications = useCallback(async () => {
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
        setError(t('vehicleSpecifications.failedLoad'));
      }
    } finally {
      setLoading(false);
    }
  }, [api, vehicle.id, t]);

  useEffect(() => {
    if (vehicle) {
      loadSpecifications();
    }
  }, [vehicle, loadSpecifications]);

  const handleScrape = async () => {
    try {
      setScraping(true);
      setError(null);
      setSuccess(null);
      const response = await api.post(`/vehicles/${vehicle.id}/specifications/scrape`);
      setSpecifications(response.data.specification);
      setFormData(response.data.specification);
      setSuccess(t('vehicleSpecifications.scrapedSuccess'));
    } catch (err) {
      setError(err.response?.data?.message || t('vehicleSpecifications.failedScrape'));
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
      setSuccess(t('vehicleSpecifications.savedSuccess'));
      setEditing(false);
    } catch (err) {
      setError(t('vehicleSpecifications.failedSave'));
    } finally {
      setSaving(false);
    }
  };

  const handleChange = useCallback((field) => (event) => {
    const val = event.target.value;
    setFormData(prev => ({ ...prev, [field]: val }));
  }, []);

  const getFilledCount = useCallback((fields) => {
    if (!specifications) return 0;
    return fields.reduce((count, field) => (specifications?.[field] ? count + 1 : count), 0);
  }, [specifications]);

  const renderSection = useCallback((title, fields, children, defaultExpanded = false, showWhenEmpty = false) => {
    const filled = getFilledCount(fields);
    const total = fields.length;

    if (!editing && filled === 0 && !showWhenEmpty) {
      return null;
    }

    return (
      <Accordion defaultExpanded={defaultExpanded} sx={{ mb: 2 }}>
        <AccordionSummary expandIcon={<ExpandMore />}>
          <Box display="flex" alignItems="center" gap={1} flexWrap="wrap">
            <Typography variant="subtitle1" sx={{ fontWeight: 'bold' }}>
              {title}
            </Typography>
            {!editing && (
              <Chip
                size="small"
                label={`${filled}/${total}`}
                color={filled ? 'success' : 'default'}
                variant={filled ? 'filled' : 'outlined'}
              />
            )}
          </Box>
        </AccordionSummary>
        <AccordionDetails>{children}</AccordionDetails>
      </Accordion>
    );
  }, [editing, getFilledCount]);


  if (loading) {
    return (
      <Box display="flex" justifyContent="center" p={3}>
        <KnightRiderLoader size={28} />
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
              <Tooltip title={t('vehicleSpecifications.editTooltip')}>
                <IconButton onClick={handleEdit} color="primary">
                  <Edit />
                </IconButton>
              </Tooltip>
              <Tooltip title={t('vehicleSpecifications.scrapeTooltip')}>
                <Button
                  variant="outlined"
                  startIcon={scraping ? <KnightRiderLoader size={16} /> : <Download />}
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
            </>
          )}
          {editing && (
            <>
                <Button
                variant="contained"
                startIcon={saving ? <KnightRiderLoader size={16} /> : <Save />}
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
          {renderSection(
            t('vehicleSpecifications.engine'),
            ['engineType', 'displacement', 'compression', 'bore', 'stroke', 'fuelSystem', 'cooling', 'sparkplugType'],
            (
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.engineType')} field="engineType" editing={editing} formValue={formData.engineType} onChange={handleChange('engineType')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.displacement')} field="displacement" editing={editing} formValue={formData.displacement} onChange={handleChange('displacement')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.compression')} field="compression" editing={editing} formValue={formData.compression} onChange={handleChange('compression')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.bore')} field="bore" editing={editing} formValue={formData.bore} onChange={handleChange('bore')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.stroke')} field="stroke" editing={editing} formValue={formData.stroke} onChange={handleChange('stroke')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.fuelSystem')} field="fuelSystem" editing={editing} formValue={formData.fuelSystem} onChange={handleChange('fuelSystem')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.cooling')} field="cooling" editing={editing} formValue={formData.cooling} onChange={handleChange('cooling')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.sparkplugType')} field="sparkplugType" editing={editing} formValue={formData.sparkplugType} onChange={handleChange('sparkplugType')} specifications={specifications} />
                </Grid>
              </Grid>
            ),
            true
          )}

          {renderSection(
            t('vehicleSpecifications.transmission'),
            ['gearbox', 'transmission', 'finalDrive', 'clutch'],
            (
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.gearbox')} field="gearbox" editing={editing} formValue={formData.gearbox} onChange={handleChange('gearbox')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.transmission')} field="transmission" editing={editing} formValue={formData.transmission} onChange={handleChange('transmission')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.finalDrive')} field="finalDrive" editing={editing} formValue={formData.finalDrive} onChange={handleChange('finalDrive')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.clutch')} field="clutch" editing={editing} formValue={formData.clutch} onChange={handleChange('clutch')} specifications={specifications} />
                </Grid>
              </Grid>
            )
          )}

          {renderSection(
            t('vehicleSpecifications.oilsAndCapacities'),
            ['engineOilType', 'engineOilCapacity', 'transmissionOilType', 'transmissionOilCapacity', 'middleDriveOilType', 'middleDriveOilCapacity', 'coolantType', 'coolantCapacity'],
            (
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.engineOilType')} field="engineOilType" editing={editing} formValue={formData.engineOilType} onChange={handleChange('engineOilType')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.engineOilCapacity')} field="engineOilCapacity" editing={editing} formValue={formData.engineOilCapacity} onChange={handleChange('engineOilCapacity')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.coolantType')} field="coolantType" editing={editing} formValue={formData.coolantType} onChange={handleChange('coolantType')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.coolantCapacity')} field="coolantCapacity" editing={editing} formValue={formData.coolantCapacity} onChange={handleChange('coolantCapacity')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.transmissionOilType')} field="transmissionOilType" editing={editing} formValue={formData.transmissionOilType} onChange={handleChange('transmissionOilType')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.transmissionOilCapacity')} field="transmissionOilCapacity" editing={editing} formValue={formData.transmissionOilCapacity} onChange={handleChange('transmissionOilCapacity')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.middleDriveOilType')} field="middleDriveOilType" editing={editing} formValue={formData.middleDriveOilType} onChange={handleChange('middleDriveOilType')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.middleDriveOilCapacity')} field="middleDriveOilCapacity" editing={editing} formValue={formData.middleDriveOilCapacity} onChange={handleChange('middleDriveOilCapacity')} specifications={specifications} />
                </Grid>
              </Grid>
            ),
            false,
            true
          )}

          {renderSection(
            t('vehicleSpecifications.wheelsTyresBrakes'),
            ['frontBrakes', 'rearBrakes', 'frontTyre', 'frontTyrePressure', 'rearTyre', 'rearTyrePressure'],
            (
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.frontBrakes')} field="frontBrakes" editing={editing} formValue={formData.frontBrakes} onChange={handleChange('frontBrakes')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.rearBrakes')} field="rearBrakes" editing={editing} formValue={formData.rearBrakes} onChange={handleChange('rearBrakes')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.frontTyre')} field="frontTyre" editing={editing} formValue={formData.frontTyre} onChange={handleChange('frontTyre')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.frontTyrePressure')} field="frontTyrePressure" editing={editing} formValue={formData.frontTyrePressure} onChange={handleChange('frontTyrePressure')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.rearTyre')} field="rearTyre" editing={editing} formValue={formData.rearTyre} onChange={handleChange('rearTyre')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.rearTyrePressure')} field="rearTyrePressure" editing={editing} formValue={formData.rearTyrePressure} onChange={handleChange('rearTyrePressure')} specifications={specifications} />
                </Grid>
              </Grid>
            )
          )}

          {renderSection(
            'Chassis & Suspension',
            ['frame', 'frontSuspension', 'rearSuspension', 'frontWheelTravel', 'rearWheelTravel', 'staticSagFront', 'staticSagRear'],
            (
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.frame')} field="frame" editing={editing} formValue={formData.frame} onChange={handleChange('frame')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.frontSuspension')} field="frontSuspension" editing={editing} formValue={formData.frontSuspension} onChange={handleChange('frontSuspension')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.rearSuspension')} field="rearSuspension" editing={editing} formValue={formData.rearSuspension} onChange={handleChange('rearSuspension')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.frontWheelTravel')} field="frontWheelTravel" editing={editing} formValue={formData.frontWheelTravel} onChange={handleChange('frontWheelTravel')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.rearWheelTravel')} field="rearWheelTravel" editing={editing} formValue={formData.rearWheelTravel} onChange={handleChange('rearWheelTravel')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.staticSagFront')} field="staticSagFront" editing={editing} formValue={formData.staticSagFront} onChange={handleChange('staticSagFront')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.staticSagRear')} field="staticSagRear" editing={editing} formValue={formData.staticSagRear} onChange={handleChange('staticSagRear')} specifications={specifications} />
                </Grid>
              </Grid>
            )
          )}

          {renderSection(
            'Dimensions & Weight',
            ['wheelbase', 'seatHeight', 'groundClearance', 'dryWeight', 'wetWeight', 'fuelCapacity'],
            (
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.wheelbase')} field="wheelbase" editing={editing} formValue={formData.wheelbase} onChange={handleChange('wheelbase')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.seatHeight')} field="seatHeight" editing={editing} formValue={formData.seatHeight} onChange={handleChange('seatHeight')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.groundClearance')} field="groundClearance" editing={editing} formValue={formData.groundClearance} onChange={handleChange('groundClearance')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.dryWeight')} field="dryWeight" editing={editing} formValue={formData.dryWeight} onChange={handleChange('dryWeight')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.wetWeight')} field="wetWeight" editing={editing} formValue={formData.wetWeight} onChange={handleChange('wetWeight')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.fuelCapacity')} field="fuelCapacity" editing={editing} formValue={formData.fuelCapacity} onChange={handleChange('fuelCapacity')} specifications={specifications} />
                </Grid>
              </Grid>
            )
          )}

          {renderSection(
            t('vehicleSpecifications.performance'),
            ['power', 'torque', 'topSpeed'],
            (
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.power')} field="power" editing={editing} formValue={formData.power} onChange={handleChange('power')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.torque')} field="torque" editing={editing} formValue={formData.torque} onChange={handleChange('torque')} specifications={specifications} />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                  <SpecField label={t('vehicleSpecifications.topSpeed')} field="topSpeed" editing={editing} formValue={formData.topSpeed} onChange={handleChange('topSpeed')} specifications={specifications} />
                </Grid>
              </Grid>
            )
          )}

          {renderSection(
            t('vehicleSpecifications.additionalInfo'),
            ['additionalInfo'],
            (
              <Grid container spacing={2}>
                <Grid item xs={12}>
                  <SpecField label={t('vehicleSpecifications.notes')} field="additionalInfo" multiline editing={editing} formValue={formData.additionalInfo} onChange={handleChange('additionalInfo')} specifications={specifications} />
                </Grid>
              </Grid>
            )
          )}

          {specifications?.scrapedAt && (
            <Box mt={3}>
              <Typography variant="caption" color="text.secondary">
                Last scraped: {new Date(specifications.scrapedAt).toLocaleString()}
                {specifications.sourceUrl && (
                  <> {t('vehicleSpecifications.scrapedFrom')} <a href={specifications.sourceUrl} target="_blank" rel="noopener noreferrer">{t('vehicleSpecifications.source')}</a></>
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
