import React, { useState } from 'react';
import logger from '../utils/logger';
import {
  Box,
  Button,
  ButtonBase,
  Container,
  Paper,
  TextField,
  Typography,
  Alert,
} from '@mui/material';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import DEMO_MODE from '../utils/demoMode';

const DEMO_ACCOUNTS = [
  { label: 'Admin',  email: 'demo-admin@vehicle.local', password: 'DemoAdmin123!' },
  { label: 'John',   email: 'john.smith@example.com',   password: 'DemoUser123!' },
  { label: 'Sarah',  email: 'sarah.jones@example.com',  password: 'DemoUser123!' },
  { label: 'Mike',   email: 'mike.wilson@example.com',  password: 'DemoUser123!' },
];

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const { login } = useAuth();
  const { t } = useTranslation();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login(email, password);
      navigate('/');
    } catch (err) {
      // Log full error for debugging (server response may contain details)
      // eslint-disable-next-line no-console
      logger.error('Login error:', err?.response?.data ?? err.message ?? err);
      setError(t('common.invalidCredentials'));
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
            {t('auth.login')}
          </Typography>
          {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
          <Box component="form" onSubmit={handleSubmit} sx={{ mt: 1 }}>
            <TextField
              margin="normal"
              required
              fullWidth
              id="email"
              label={t('auth.email')}
              name="email"
              autoComplete="email"
              autoFocus
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
            <TextField
              margin="normal"
              required
              fullWidth
              name="password"
              label={t('auth.password')}
              type="password"
              id="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
            <Button
              type="submit"
              fullWidth
              variant="contained"
              sx={{ mt: 3, mb: 2 }}
              disabled={loading}
            >
              {loading ? t('common.loading') : t('auth.login')}
            </Button>
            {!DEMO_MODE && (
              <Box textAlign="center">
                <Link to="/register" style={{ textDecoration: 'none' }}>
                  <Typography variant="body2" color="primary">
                    {t('auth.dontHaveAccount')} {t('auth.register')}
                  </Typography>
                </Link>
              </Box>
            )}
          </Box>
          {DEMO_MODE && (
            <Box
              component="fieldset"
              sx={{
                mt: 3,
                border: '1px solid',
                borderColor: 'divider',
                borderRadius: 1,
                p: 0,
                mx: 0,
              }}
            >
              <Typography
                component="legend"
                variant="overline"
                sx={{
                  px: 1,
                  ml: 1.5,
                  color: 'text.secondary',
                  letterSpacing: '0.1em',
                }}
              >
                Demo Accounts
              </Typography>
              {DEMO_ACCOUNTS.map((acct, idx) => (
                <ButtonBase
                  key={acct.email}
                  onClick={() => {
                    setEmail(acct.email);
                    setPassword(acct.password);
                    setError('');
                  }}
                  sx={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    width: '100%',
                    px: 2.5,
                    py: 1.5,
                    borderTop: idx > 0 ? '1px solid' : 'none',
                    borderColor: 'divider',
                    textAlign: 'left',
                    transition: 'background-color 0.15s',
                    '&:hover': { bgcolor: 'action.hover' },
                  }}
                >
                  <Typography variant="body1" sx={{ fontWeight: 600 }}>
                    {acct.label}
                  </Typography>
                  <Typography
                    variant="body2"
                    sx={{
                      color: 'text.secondary',
                      fontFamily: 'monospace',
                      fontSize: '0.8rem',
                    }}
                  >
                    {acct.email}
                  </Typography>
                </ButtonBase>
              ))}
            </Box>
          )}
        </Paper>
      </Box>
    </Container>
  );
};

export default Login;
