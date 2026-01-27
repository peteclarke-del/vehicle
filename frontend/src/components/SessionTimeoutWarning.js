import React, { useState, useEffect, useCallback } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Typography,
  LinearProgress,
  Box,
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';

const SessionTimeoutWarning = () => {
  const { t } = useTranslation();
  const { api, logout, user, updateToken } = useAuth();
  const [open, setOpen] = useState(false);
  const [countdown, setCountdown] = useState(0);
  const [timeoutId, setTimeoutId] = useState(null);
  const [countdownId, setCountdownId] = useState(null);
  const [isRefreshing, setIsRefreshing] = useState(false);

  const [tokenTtl, setTokenTtl] = useState(3600);

  // load session timeout preference from server (do not expect user.sessionTimeout)
  useEffect(() => {
    let mounted = true;
    (async () => {
      if (!api) return;
      try {
        const resp = await api.get('/user/preferences?key=sessionTimeout');
        if (mounted && resp?.data?.value) {
          setTokenTtl(resp.data.value);
          return;
        }
      } catch (e) {
        // ignore and keep default
      }
      if (mounted) setTokenTtl(3600);
    })();
    return () => { mounted = false; };
  }, [api]);

  const TOKEN_TTL = tokenTtl; // Use loaded preference or default
  const WARNING_TIME = 300; // Show warning 5 minutes before expiry

  const getTokenTimestamp = useCallback(() => {
    const token = localStorage.getItem('token');
    if (!token) return null;
    
    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      return payload.iat; // issued at timestamp
    } catch (e) {
      return null;
    }
  }, []);

  const getTokenExpiry = useCallback(() => {
    const token = localStorage.getItem('token');
    if (!token) return null;

    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      // Prefer explicit 'exp' claim when present
      return payload.exp || null;
    } catch (e) {
      return null;
    }
  }, []);

  const scheduleWarning = useCallback(() => {
    // Don't schedule if we're refreshing or dialog is already open
    if (isRefreshing || open) return;
    // clear any existing scheduled timeout first
    if (timeoutId) {
      clearTimeout(timeoutId);
      setTimeoutId(null);
    }
    
    // Prefer explicit token expiry if present (keeps client in sync with server token TTL)
    const exp = getTokenExpiry();
    const now = Math.floor(Date.now() / 1000);

    let remaining = null;
    if (exp) {
      remaining = exp - now;
    } else {
      const iat = getTokenTimestamp();
      if (!iat) return;
      const elapsed = now - iat;
      remaining = TOKEN_TTL - elapsed;
    }

    const timeUntilWarning = (remaining - WARNING_TIME) * 1000;

    // If token has already expired, logout immediately
    if (remaining <= 0) {
      logout();
      return;
    }

    if (timeUntilWarning > 0) {
      const id = setTimeout(() => {
        setCountdown(WARNING_TIME);
        setOpen(true);
      }, timeUntilWarning);
      setTimeoutId(id);
    } else if (remaining > 0 && remaining <= WARNING_TIME) {
      // Token is already in warning period
      setCountdown(Math.max(0, remaining));
      setOpen(true);
    }
  }, [getTokenTimestamp, getTokenExpiry, TOKEN_TTL, isRefreshing, open, logout, timeoutId]);

  const handleExtendSession = async () => {
    // Set refreshing state BEFORE clearing timers to prevent periodic check from logging out
    setIsRefreshing(true);
    
    // Clear all timers immediately
    if (countdownId) {
      clearInterval(countdownId);
      setCountdownId(null);
    }
    if (timeoutId) {
      clearTimeout(timeoutId);
      setTimeoutId(null);
    }
    
    // Reset countdown but keep dialog open during refresh
    setCountdown(0);
    
    try {
      // Prefer refresh-by-token flow so we can refresh without a valid JWT
      const refreshToken = localStorage.getItem('refreshToken');
      if (!refreshToken) {
        // fall back to authenticated refresh (may fail if token expired)
        const response = await api.post('/refresh-token');
        if (response.data.token) {
          updateToken(response.data.token);
          setOpen(false);
          setTimeout(() => {
            setIsRefreshing(false);
            scheduleWarning();
          }, 100);
          return;
        }
        throw new Error('No refresh token available');
      }

      const resp = await api.post('/auth/refresh', { refreshToken });
      if (resp?.data?.token) {
        updateToken(resp.data.token);
        setOpen(false);
        setTimeout(() => {
          setIsRefreshing(false);
          scheduleWarning();
        }, 100);
      } else {
        throw new Error('No token in refresh response');
      }
    } catch (error) {
      console.error('Failed to refresh token:', error);
      setIsRefreshing(false);
      setOpen(false);
      logout();
    }
  };

  const handleLogout = () => {
    if (countdownId) {
      clearInterval(countdownId);
      setCountdownId(null);
    }
    if (timeoutId) {
      clearTimeout(timeoutId);
      setTimeoutId(null);
    }
    setOpen(false);
    logout();
  };

  useEffect(() => {
    if (user) {
      scheduleWarning();
    }

    return () => {
      if (timeoutId) clearTimeout(timeoutId);
      if (countdownId) clearInterval(countdownId);
    };
  }, [user, TOKEN_TTL]);

  // Periodic check for token expiration every 10 seconds
  useEffect(() => {
    if (!user) return;

    const checkInterval = setInterval(() => {
      // Skip check if we're currently refreshing the token
      if (isRefreshing) return;
      
      // Prefer explicit token expiry if present
      const exp = getTokenExpiry();
      const now = Math.floor(Date.now() / 1000);
      let remaining = null;
      if (exp) {
        remaining = exp - now;
      } else {
        const iat = getTokenTimestamp();
        if (!iat) {
          logout();
          return;
        }
        const elapsed = now - iat;
        remaining = TOKEN_TTL - elapsed;
      }

      // If token expired, logout immediately
      if (remaining <= 0) {
        clearInterval(checkInterval);
        logout();
      }
    }, 10000); // Check every 10 seconds

    return () => clearInterval(checkInterval);
  }, [user, getTokenTimestamp, TOKEN_TTL, logout, isRefreshing]);

  useEffect(() => {
    // Cleanup countdown on unmount or when dialog closes
    if (!open && countdownId) {
      clearInterval(countdownId);
      setCountdownId(null);
      return;
    }

    // Only start countdown if dialog is open and countdown is positive
    if (open && countdown > 0 && !countdownId) {
      const id = setInterval(() => {
        setCountdown(prev => {
          if (prev <= 1) {
            clearInterval(id);
            logout();
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
      setCountdownId(id);

      return () => {
        clearInterval(id);
      };
    }
  }, [open, countdown, countdownId, logout]);

  const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const progress = (countdown / WARNING_TIME) * 100;

  return (
    <Dialog 
      open={open} 
      disableEscapeKeyDown
      onClose={(event, reason) => {
        if (reason === 'backdropClick') {
          return;
        }
      }}
    >
      <DialogTitle>
        {t('session.expiring')}
      </DialogTitle>
      <DialogContent>
        <Typography variant="body1" gutterBottom>
          {t('session.timeoutMessage')}
        </Typography>
        <Box sx={{ my: 3, textAlign: 'center' }}>
          <Typography variant="h3" color="warning.main">
            {formatTime(countdown)}
          </Typography>
        </Box>
        <LinearProgress 
          variant="determinate" 
          value={progress} 
          color="warning"
          sx={{ height: 8, borderRadius: 4 }}
        />
        <Typography variant="body2" color="text.secondary" sx={{ mt: 2 }}>
          {t('session.extendPrompt')}
        </Typography>
      </DialogContent>
      <DialogActions>
        <Button onClick={handleLogout} color="inherit">
          {t('auth.logout')}
        </Button>
        <Button onClick={handleExtendSession} variant="contained" color="primary" autoFocus>
          {t('session.continueSession')}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default SessionTimeoutWarning;
