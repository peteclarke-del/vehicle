import React, { useState, useEffect, useCallback, useRef } from 'react';
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
import logger from '../utils/logger';
import SafeStorage from '../utils/SafeStorage';

const SessionTimeoutWarning = () => {
  const { t } = useTranslation();
  const { api, logout, user, updateToken } = useAuth();
  const [open, setOpen] = useState(false);
  const [countdown, setCountdown] = useState(0);
  const timeoutIdRef = useRef(null);
  const countdownIdRef = useRef(null);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [extendedSuccess, setExtendedSuccess] = useState(false);
  const [extensionTime, setExtensionTime] = useState(0);

  const [tokenTtl, setTokenTtl] = useState(3600);
  const WARNING_TIME = 300; // Show warning 5 minutes before expiry

  // load session timeout preference from server (do not expect user.sessionTimeout)
  useEffect(() => {
    let mounted = true;
    (async () => {
      if (!api || !user) return;
      try {
        const resp = await api.get('/user/preferences?key=sessionTimeout');
        if (mounted && resp?.data?.value) {
          const newTtl = parseInt(resp.data.value, 10);
          if (!isNaN(newTtl) && newTtl > 0) {
            setTokenTtl(newTtl);
            return;
          }
        }
      } catch (e) {
        logger.warn('Failed to load session timeout preference:', e);
      }
      if (mounted) setTokenTtl(3600);
    })();
    return () => { mounted = false; };
  }, [api, user]);

  const getTokenTimestamp = useCallback(() => {
    const token = SafeStorage.get('token');
    if (!token) return null;
    
    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      return payload.iat; // issued at timestamp
    } catch (e) {
      return null;
    }
  }, []);

  const getTokenExpiry = useCallback(() => {
    const token = SafeStorage.get('token');
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
    if (timeoutIdRef.current) {
      clearTimeout(timeoutIdRef.current);
      timeoutIdRef.current = null;
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
      remaining = tokenTtl - elapsed;
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
      timeoutIdRef.current = id;
    } else if (remaining > 0 && remaining <= WARNING_TIME) {
      // Token is already in warning period
      setCountdown(Math.max(0, remaining));
      setOpen(true);
    }
  }, [getTokenTimestamp, getTokenExpiry, tokenTtl, isRefreshing, open, logout]);

  const handleExtendSession = async () => {
    // Set refreshing state BEFORE clearing timers to prevent periodic check from logging out
    setIsRefreshing(true);
    
    // Clear countdown timer immediately to stop the clock
    if (countdownIdRef.current) {
      clearInterval(countdownIdRef.current);
      countdownIdRef.current = null;
    }
    if (timeoutIdRef.current) {
      clearTimeout(timeoutIdRef.current);
      timeoutIdRef.current = null;
    }
    
    try {
      // Prefer refresh-by-token flow so we can refresh without a valid JWT
      const refreshToken = SafeStorage.get('refreshToken');
      if (!refreshToken) {
        // fall back to authenticated refresh (may fail if token expired)
        const response = await api.post('/refresh-token');
        if (response.data.token) {
          updateToken(response.data.token);
          // Calculate extension time (default to tokenTtl)
          const extensionMinutes = Math.floor(tokenTtl / 60);
          setExtensionTime(extensionMinutes);
          setExtendedSuccess(true);
          setIsRefreshing(false);
          // Show success message for 2 seconds then close
          setTimeout(() => {
            setOpen(false);
            setExtendedSuccess(false);
            scheduleWarning();
          }, 2000);
          return;
        }
        throw new Error('No refresh token available');
      }

      const resp = await api.post('/auth/refresh', { refreshToken });
      if (resp?.data?.token) {
        updateToken(resp.data.token);
        // Calculate extension time (default to tokenTtl)
        const extensionMinutes = Math.floor(tokenTtl / 60);
        setExtensionTime(extensionMinutes);
        setExtendedSuccess(true);
        setIsRefreshing(false);
        // Show success message for 2 seconds then close
        setTimeout(() => {
          setOpen(false);
          setExtendedSuccess(false);
          scheduleWarning();
        }, 2000);
      } else {
        throw new Error('No token in refresh response');
      }
    } catch (error) {
      logger.error('Failed to refresh token:', error);
      setIsRefreshing(false);
      setExtendedSuccess(false);
      setOpen(false);
      logout();
    }
  };

  const handleLogout = () => {
    if (countdownIdRef.current) {
      clearInterval(countdownIdRef.current);
      countdownIdRef.current = null;
    }
    if (timeoutIdRef.current) {
      clearTimeout(timeoutIdRef.current);
      timeoutIdRef.current = null;
    }
    setOpen(false);
    logout();
  };

  useEffect(() => {
    if (user) {
      scheduleWarning();
    }

    return () => {
      if (timeoutIdRef.current) clearTimeout(timeoutIdRef.current);
      if (countdownIdRef.current) clearInterval(countdownIdRef.current);
    };
  }, [user, tokenTtl, scheduleWarning]);

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
        remaining = tokenTtl - elapsed;
      }

      // If token expired, logout immediately
      if (remaining <= 0) {
        clearInterval(checkInterval);
        logout();
      }
    }, 10000); // Check every 10 seconds

    return () => clearInterval(checkInterval);
  }, [user, getTokenTimestamp, getTokenExpiry, tokenTtl, logout, isRefreshing]);

  useEffect(() => {
    // Cleanup countdown on unmount or when dialog closes
    if (!open) {
      if (countdownIdRef.current) {
        clearInterval(countdownIdRef.current);
        countdownIdRef.current = null;
      }
      return;
    }

    // Start countdown when dialog opens
    const id = setInterval(() => {
      setCountdown(prev => {
        const newValue = prev - 1;
        if (newValue <= 0) {
          clearInterval(id);
          logout();
          return 0;
        }
        return newValue;
      });
    }, 1000);
    countdownIdRef.current = id;

    return () => {
      clearInterval(id);
      countdownIdRef.current = null;
    };
  }, [open, logout]);

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
        {extendedSuccess ? t('session.extended') : t('session.expiring')}
      </DialogTitle>
      <DialogContent>
        {extendedSuccess ? (
          <>
            <Typography variant="body1" gutterBottom color="success.main">
              {t('session.extendedMessage', { minutes: extensionTime })}
            </Typography>
            <Box sx={{ my: 3, textAlign: 'center' }}>
              <Typography variant="h3" color="success.main">
                ✓
              </Typography>
            </Box>
          </>
        ) : isRefreshing ? (
          <>
            <Typography variant="body1" gutterBottom>
              {t('session.extending')}
            </Typography>
            <Box sx={{ my: 3 }}>
              <LinearProgress color="primary" />
            </Box>
          </>
        ) : (
          <>
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
          </>
        )}
      </DialogContent>
      <DialogActions>
        {!extendedSuccess && !isRefreshing && (
          <>
            <Button onClick={handleLogout} color="inherit">
              {t('auth.logout')}
            </Button>
            <Button onClick={handleExtendSession} variant="contained" color="primary" autoFocus>
              {t('session.continueSession')}
            </Button>
          </>
        )}
      </DialogActions>
    </Dialog>
  );
};

export default SessionTimeoutWarning;
