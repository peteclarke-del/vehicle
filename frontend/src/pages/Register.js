import React, { useState } from 'react';
import {
  Box,
  Button,
  Container,
  Paper,
  TextField,
  Typography,
  Alert,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
} from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import RadioButtonUncheckedIcon from '@mui/icons-material/RadioButtonUnchecked';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';

const Register = () => {
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    confirmPassword: '',
    firstName: '',
    lastName: '',
  });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const { register } = useAuth();
  const { t } = useTranslation();

  // Password policy from environment or default
  const passwordRegex = process.env.REACT_APP_PASSWORD_POLICY;
  const parseRequirementsFromRegex = (regex) => {
    if (!regex) return null;
    const reqs = {
      length: 8,
      upper: false,
      lower: false,
      digit: false,
      special: false,
    };
    const lengthMatch = regex.match(/\{(\d+),?\d*\}/);
    if (lengthMatch) reqs.length = parseInt(lengthMatch[1]);
    reqs.upper = /(?=.*[A-Z])/.test(regex);
    reqs.lower = /(?=.*[a-z])/.test(regex);
    reqs.digit = /(?=.*\d)/.test(regex);
    reqs.special = /(?=.*[^A-Za-z0-9])/.test(regex);
    return reqs;
  };

  const dynamicReqs = parseRequirementsFromRegex(passwordRegex);
  const criteria = dynamicReqs ? {
    length: formData.password.length >= dynamicReqs.length,
    upper: dynamicReqs.upper ? /[A-Z]/.test(formData.password) : true,
    lower: dynamicReqs.lower ? /[a-z]/.test(formData.password) : true,
    digit: dynamicReqs.digit ? /[0-9]/.test(formData.password) : true,
    special: dynamicReqs.special ? /[^A-Za-z0-9]/.test(formData.password) : true,
  } : {
    length: formData.password.length >= 8,
    upper: /[A-Z]/.test(formData.password),
    lower: /[a-z]/.test(formData.password),
    digit: /[0-9]/.test(formData.password),
    special: /[^A-Za-z0-9]/.test(formData.password),
  };

  const isValidPassword = passwordRegex 
    ? new RegExp(passwordRegex).test(formData.password) 
    : Object.values(criteria).every(Boolean);
  
  const passwordsMatch = formData.password === formData.confirmPassword;

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (!passwordsMatch) {
      setError(t('password.passwordsDoNotMatch'));
      return;
    }

    if (!isValidPassword) {
      setError(passwordRegex ? t('password.passwordDoesNotMatchPolicy') : t('password.passwordMinLength'));
      return;
    }

    setLoading(true);
    try {
      const { confirmPassword, ...registerData } = formData;
      await register(registerData);
      setSuccess(true);
      setTimeout(() => navigate('/login'), 2000);
    } catch (err) {
      setError(err.response?.data?.error || t('errors.registrationFailed'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Container maxWidth="sm">
      <Box
        sx={{
          marginTop: 8,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
        }}
      >
        <Paper elevation={3} sx={{ p: 4, width: '100%' }}>
          <Typography component="h1" variant="h5" align="center" gutterBottom>
            {t('auth.register')}
          </Typography>
          {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
          {success && (
            <Alert severity="success" sx={{ mb: 2 }}>
              {t('register.success')}
            </Alert>
          )}
          <Box component="form" onSubmit={handleSubmit} sx={{ mt: 1 }}>
            <TextField
              margin="normal"
              required
              fullWidth
              name="firstName"
              label={t('auth.firstName')}
              value={formData.firstName}
              onChange={handleChange}
            />
            <TextField
              margin="normal"
              required
              fullWidth
              name="lastName"
              label={t('auth.lastName')}
              value={formData.lastName}
              onChange={handleChange}
            />
            <TextField
              margin="normal"
              required
              fullWidth
              name="email"
              label={t('auth.email')}
              type="email"
              value={formData.email}
              onChange={handleChange}
            />
            <TextField
              margin="normal"
              required
              fullWidth
              name="password"
              label={t('auth.password')}
              type="password"
              value={formData.password}
              onChange={handleChange}
              error={formData.password.length > 0 && !isValidPassword}
            />
            {formData.password.length > 0 && (
              <Box sx={{ mt: 1, mb: 1 }}>
                <Typography variant="caption" color="text.secondary">
                  {t('password.passwordRequirements')}:
                </Typography>
                <List dense sx={{ py: 0 }}>
                  <ListItem sx={{ py: 0 }}>
                    <ListItemIcon sx={{ minWidth: 28 }}>
                      {criteria.length ? <CheckCircleIcon color="success" fontSize="small" /> : <RadioButtonUncheckedIcon fontSize="small" />}
                    </ListItemIcon>
                    <ListItemText 
                      primary={t('password.requireLength', { count: dynamicReqs?.length || 8 })} 
                      primaryTypographyProps={{ variant: 'caption', color: criteria.length ? 'success.main' : 'text.secondary' }}
                    />
                  </ListItem>
                  {(dynamicReqs?.upper !== false) && (
                    <ListItem sx={{ py: 0 }}>
                      <ListItemIcon sx={{ minWidth: 28 }}>
                        {criteria.upper ? <CheckCircleIcon color="success" fontSize="small" /> : <RadioButtonUncheckedIcon fontSize="small" />}
                      </ListItemIcon>
                      <ListItemText 
                        primary={t('password.requireUpper')} 
                        primaryTypographyProps={{ variant: 'caption', color: criteria.upper ? 'success.main' : 'text.secondary' }}
                      />
                    </ListItem>
                  )}
                  {(dynamicReqs?.lower !== false) && (
                    <ListItem sx={{ py: 0 }}>
                      <ListItemIcon sx={{ minWidth: 28 }}>
                        {criteria.lower ? <CheckCircleIcon color="success" fontSize="small" /> : <RadioButtonUncheckedIcon fontSize="small" />}
                      </ListItemIcon>
                      <ListItemText 
                        primary={t('password.requireLower')} 
                        primaryTypographyProps={{ variant: 'caption', color: criteria.lower ? 'success.main' : 'text.secondary' }}
                      />
                    </ListItem>
                  )}
                  {(dynamicReqs?.digit !== false) && (
                    <ListItem sx={{ py: 0 }}>
                      <ListItemIcon sx={{ minWidth: 28 }}>
                        {criteria.digit ? <CheckCircleIcon color="success" fontSize="small" /> : <RadioButtonUncheckedIcon fontSize="small" />}
                      </ListItemIcon>
                      <ListItemText 
                        primary={t('password.requireDigit')} 
                        primaryTypographyProps={{ variant: 'caption', color: criteria.digit ? 'success.main' : 'text.secondary' }}
                      />
                    </ListItem>
                  )}
                  {(dynamicReqs?.special !== false) && (
                    <ListItem sx={{ py: 0 }}>
                      <ListItemIcon sx={{ minWidth: 28 }}>
                        {criteria.special ? <CheckCircleIcon color="success" fontSize="small" /> : <RadioButtonUncheckedIcon fontSize="small" />}
                      </ListItemIcon>
                      <ListItemText 
                        primary={t('password.requireSpecial')} 
                        primaryTypographyProps={{ variant: 'caption', color: criteria.special ? 'success.main' : 'text.secondary' }}
                      />
                    </ListItem>
                  )}
                </List>
              </Box>
            )}
            <TextField
              margin="normal"
              required
              fullWidth
              name="confirmPassword"
              label={t('password.confirmPassword')}
              type="password"
              value={formData.confirmPassword}
              onChange={handleChange}
              error={formData.confirmPassword.length > 0 && !passwordsMatch}
              helperText={formData.confirmPassword.length > 0 && !passwordsMatch ? t('password.passwordsDoNotMatch') : ''}
            />
            <Button
              type="submit"
              fullWidth
              variant="contained"
              sx={{ mt: 3, mb: 2 }}
              disabled={loading}
            >
              {loading ? t('common.loading') : t('auth.register')}
            </Button>
            <Box textAlign="center">
              <Link to="/login" style={{ textDecoration: 'none' }}>
                <Typography variant="body2" color="primary">
                  {t('auth.alreadyHaveAccount')} {t('auth.login')}
                </Typography>
              </Link>
            </Box>
          </Box>
        </Paper>
      </Box>
    </Container>
  );
};

export default Register;
