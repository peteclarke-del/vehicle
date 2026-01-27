import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Box,
  Typography,
  Divider,
  Alert,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';

const PreferencesDialog = ({ open, onClose }) => {
  const { user, api, fetchUser } = useAuth();
  const { t } = useTranslation();
  const [sessionTimeout, setSessionTimeout] = useState(3600);
  const [distanceUnit, setDistanceUnit] = useState('km');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    let mounted = true;
    (async () => {
      if (!api) return;
      try {
        // load session timeout preference
        const sess = await api.get('/user/preferences?key=sessionTimeout');
        if (mounted) setSessionTimeout(sess?.data?.value ?? 3600);
      } catch (e) {
        // ignore and keep default
      }

      try {
        const resp = await api.get('/user/preferences?key=distanceUnit');
        const raw = resp?.data?.value;
        if (mounted && raw) {
          setDistanceUnit(raw);
          return;
        }
      } catch (e) {
        // ignore
      }

      // fallback to locale-based default
      try {
        const mod = await import('../utils/countryUtils');
        if (mounted) setDistanceUnit(mod.getUserDefaultDistanceUnit());
      } catch (e) {
        // ignore
      }
    })();
    return () => { mounted = false; };
  }, [user, api]);

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    setSuccess(false);

    try {
      await api.put('/profile', {
        sessionTimeout,
        distanceUnit,
      });

      // Refresh user data
      await fetchUser();
      
      setSuccess(true);
      setTimeout(() => {
        setSuccess(false);
        onClose();
      }, 1500);
    } catch (err) {
      setError(err.response?.data?.error || t('errors.failedToSavePreferences'));
    } finally {
      setSaving(false);
    }
  };

  const sessionTimeoutOptions = [
    { value: 300, label: t('preferences.minutes_5') },
    { value: 600, label: t('preferences.minutes_10') },
    { value: 900, label: t('preferences.minutes_15') },
    { value: 1800, label: t('preferences.minutes_30') },
    { value: 3600, label: t('preferences.hour_1') },
    { value: 7200, label: t('preferences.hours_2') },
    { value: 14400, label: t('preferences.hours_4') },
    { value: 28800, label: t('preferences.hours_8') },
    { value: 86400, label: t('preferences.hours_24') },
  ];

  const distanceUnitOptions = [
    { value: 'km', label: t('preferences.kilometers') },
    { value: 'mi', label: t('preferences.miles') },
  ];

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>{t('preferences.title')}</DialogTitle>
      <DialogContent>
        <Box sx={{ mt: 2 }}>
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          
          {success && (
            <Alert severity="success" sx={{ mb: 2 }}>
              {t('preferences.saved')}
            </Alert>
          )}

          <Typography variant="h6" gutterBottom>
            {t('preferences.sessionSettings')}
          </Typography>
          
          <FormControl fullWidth sx={{ mb: 3 }}>
            <InputLabel>{t('preferences.sessionTimeout')}</InputLabel>
            <Select
              value={sessionTimeout}
              onChange={(e) => setSessionTimeout(e.target.value)}
              label={t('preferences.sessionTimeout')}
            >
              {sessionTimeoutOptions.map((option) => (
                <MenuItem key={option.value} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <Divider sx={{ my: 2 }} />

          <Typography variant="h6" gutterBottom>
            {t('preferences.displaySettings')}
          </Typography>

          <FormControl fullWidth sx={{ mb: 2 }}>
            <InputLabel>{t('preferences.distanceUnit')}</InputLabel>
            <Select
              value={distanceUnit}
              onChange={(e) => setDistanceUnit(e.target.value)}
              label={t('preferences.distanceUnit')}
            >
              {distanceUnitOptions.map((option) => (
                <MenuItem key={option.value} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Box>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={saving}>
          {t('common.cancel')}
        </Button>
        <Button onClick={handleSave} variant="contained" disabled={saving}>
          {saving ? t('preferences.saving') : t('common.save')}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default PreferencesDialog;
