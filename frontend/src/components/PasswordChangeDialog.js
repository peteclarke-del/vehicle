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
  Typography,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
} from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import RadioButtonUncheckedIcon from '@mui/icons-material/RadioButtonUnchecked';
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

  const passwordRegex = process.env.REACT_APP_PASSWORD_POLICY;
  const parseRequirementsFromRegex = (regex) => {
    if (!regex) return null;
    const reqs = {
      length: 8, // default
      upper: false,
      lower: false,
      digit: false,
      special: false,
    };
    // Check for length
    const lengthMatch = regex.match(/\{(\d+),?\d*\}/);
    if (lengthMatch) reqs.length = parseInt(lengthMatch[1]);
    // Check for requirements
    reqs.upper = /(?=.*[A-Z])/.test(regex);
    reqs.lower = /(?=.*[a-z])/.test(regex);
    reqs.digit = /(?=.*\d)/.test(regex);
    reqs.special = /(?=.*[^A-Za-z0-9])/.test(regex);
    return reqs;
  };

  const dynamicReqs = parseRequirementsFromRegex(passwordRegex);
  const criteria = dynamicReqs ? {
    length: formData.newPassword.length >= dynamicReqs.length,
    upper: dynamicReqs.upper ? /[A-Z]/.test(formData.newPassword) : true,
    lower: dynamicReqs.lower ? /[a-z]/.test(formData.newPassword) : true,
    digit: dynamicReqs.digit ? /[0-9]/.test(formData.newPassword) : true,
    special: dynamicReqs.special ? /[^A-Za-z0-9]/.test(formData.newPassword) : true,
  } : {
    length: formData.newPassword.length >= 8,
    upper: /[A-Z]/.test(formData.newPassword),
    lower: /[a-z]/.test(formData.newPassword),
    digit: /[0-9]/.test(formData.newPassword),
    special: /[^A-Za-z0-9]/.test(formData.newPassword),
  };

  const isValidPassword = passwordRegex ? new RegExp(passwordRegex).test(formData.newPassword) : Object.values(criteria).every(Boolean);

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

    if (!isValidPassword) {
      setError(passwordRegex ? t('password.passwordDoesNotMatchPolicy') : t('password.passwordMinLength'));
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
            <Box sx={{ alignSelf: 'center', textAlign: 'left', maxWidth: 'fit-content' }}>
              <Typography variant="caption">{t('password.passwordRequirements')}</Typography>
              <List dense>
                {(dynamicReqs ? dynamicReqs.length > 0 : true) && (
                  <ListItem>
                    <ListItemIcon>
                      {criteria.length ? (
                        <CheckCircleIcon color="success" fontSize="small" />
                      ) : (
                        <RadioButtonUncheckedIcon fontSize="small" />
                      )}
                    </ListItemIcon>
                    <ListItemText primary={t('password.requireLength', { count: dynamicReqs ? dynamicReqs.length : 8 })} />
                  </ListItem>
                )}
                {(dynamicReqs ? dynamicReqs.upper : true) && (
                  <ListItem>
                    <ListItemIcon>
                      {criteria.upper ? (
                        <CheckCircleIcon color="success" fontSize="small" />
                      ) : (
                        <RadioButtonUncheckedIcon fontSize="small" />
                      )}
                    </ListItemIcon>
                    <ListItemText primary={t('password.requireUpper')} />
                  </ListItem>
                )}
                {(dynamicReqs ? dynamicReqs.lower : true) && (
                  <ListItem>
                    <ListItemIcon>
                      {criteria.lower ? (
                        <CheckCircleIcon color="success" fontSize="small" />
                      ) : (
                        <RadioButtonUncheckedIcon fontSize="small" />
                      )}
                    </ListItemIcon>
                    <ListItemText primary={t('password.requireLower')} />
                  </ListItem>
                )}
                {(dynamicReqs ? dynamicReqs.digit : true) && (
                  <ListItem>
                    <ListItemIcon>
                      {criteria.digit ? (
                        <CheckCircleIcon color="success" fontSize="small" />
                      ) : (
                        <RadioButtonUncheckedIcon fontSize="small" />
                      )}
                    </ListItemIcon>
                    <ListItemText primary={t('password.requireDigit')} />
                  </ListItem>
                )}
                {(dynamicReqs ? dynamicReqs.special : true) && (
                  <ListItem>
                    <ListItemIcon>
                      {criteria.special ? (
                        <CheckCircleIcon color="success" fontSize="small" />
                      ) : (
                        <RadioButtonUncheckedIcon fontSize="small" />
                      )}
                    </ListItemIcon>
                    <ListItemText primary={t('password.requireSpecial')} />
                  </ListItem>
                )}
              </List>
            </Box>
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
