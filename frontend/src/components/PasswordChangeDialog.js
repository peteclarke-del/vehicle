import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Alert,
  Box,
  Typography
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';

export default function PasswordChangeDialog({ open, onClose, required = false }) {
  const { t } = useTranslation();
  const { api, fetchUser } = useAuth();
  const [formData, setFormData] = useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: ''
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (open) {
      setFormData({ currentPassword: '', newPassword: '', confirmPassword: '' });
      setError('');
    }
  }, [open]);

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (formData.newPassword !== formData.confirmPassword) {
      setError(t('password.passwordsDoNotMatch'));
      return;
    }

    if (formData.newPassword.length < 8) {
      setError(t('password.passwordMinLength'));
      return;
    }

    setLoading(true);

    try {
      await api.post('/change-password', {
        currentPassword: formData.currentPassword,
        newPassword: formData.newPassword
      });

      // Refresh user data to clear passwordChangeRequired flag
      await fetchUser();
      onClose();
    } catch (err) {
      setError(err.response?.data?.error || t('password.changeFailed'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog 
      open={open} 
      onClose={required ? undefined : onClose}
      maxWidth="sm"
      fullWidth
    >
      <DialogTitle>
        {required ? t('password.changeRequired') : t('password.change')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          {required && (
            <Alert severity="warning" sx={{ mb: 2 }}>
              <Typography variant="body2">
                {t('password.passwordChangeRequiredMessage')}
              </Typography>
            </Alert>
          )}

          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}

          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <TextField
              label={t('password.currentPassword')}
              type="password"
              name="currentPassword"
              value={formData.currentPassword}
              onChange={handleChange}
              required
              fullWidth
              autoFocus
            />
            <TextField
              label={t('password.newPassword')}
              type="password"
              name="newPassword"
              value={formData.newPassword}
              onChange={handleChange}
              required
              fullWidth
              helperText={t('password.passwordRequirements')}
            />
            <TextField
              label={t('password.confirmPassword')}
              type="password"
              name="confirmPassword"
              value={formData.confirmPassword}
              onChange={handleChange}
              required
              fullWidth
            />
          </Box>
        </DialogContent>
        <DialogActions>
          {!required && (
            <Button onClick={onClose} disabled={loading}>
              {t('cancel') || 'Cancel'}
            </Button>
          )}
          <Button type="submit" variant="contained" disabled={loading}>
            {loading ? t('password.changing') : t('password.change')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
