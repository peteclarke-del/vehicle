import React, { useState } from 'react';
import {
  IconButton,
  Badge,
  Menu,
  MenuItem,
  Box,
  Typography,
  Divider,
  Button,
  Chip,
  ListItemIcon,
  ListItemText,
  Tooltip,
} from '@mui/material';
import {
  Notifications as NotificationsIcon,
  Warning as WarningIcon,
  Error as ErrorIcon,
  CheckCircle as CheckCircleIcon,
  Snooze as SnoozeIcon,
  Close as CloseIcon,
} from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

const NotificationMenu = ({ notifications, dismissNotification, snoozeNotification, clearAllNotifications }) => {
  const [anchorEl, setAnchorEl] = useState(null);
  const navigate = useNavigate();
  const items = Array.isArray(notifications) ? notifications : [];

  const handleOpen = (event) => {
    setAnchorEl(event.currentTarget);
  };

  const { t } = useTranslation();

  const handleClose = () => {
    setAnchorEl(null);
  };

  const handleSnooze = (notificationId, e) => {
    e.stopPropagation();
    snoozeNotification(notificationId, 7);
  };

  const handleDismiss = (notificationId, e) => {
    e.stopPropagation();
    dismissNotification(notificationId);
  };

  const handleNotificationClick = (notification) => {
    handleClose();
    if (notification.route) {
      navigate(notification.route);
    } else if (notification.vehicleId) {
      navigate(`/vehicles/${notification.vehicleId}`);
    }
  };

  const getSeverityIcon = (severity) => {
    switch (severity) {
      case 'error':
        return <ErrorIcon color="error" fontSize="small" />;
      case 'warning':
        return <WarningIcon color="warning" fontSize="small" />;
      default:
        return <CheckCircleIcon color="success" fontSize="small" />;
    }
  };

  return (
    <>
      <Tooltip title={t('notifications.title') || 'Notifications'}>
        <IconButton color="inherit" onClick={handleOpen}>
          <Badge badgeContent={items.length} color="error">
            <NotificationsIcon />
          </Badge>
        </IconButton>
      </Tooltip>
      <Menu
        anchorEl={anchorEl}
        open={Boolean(anchorEl)}
        onClose={handleClose}
        PaperProps={{
          sx: {
            maxHeight: 500,
            width: 380,
          },
        }}
      >
        <Box sx={{ px: 2, py: 1, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <Typography variant="h6">{t('notifications.title')}</Typography>
          {items.length > 0 && (
            <Button size="small" onClick={clearAllNotifications}>
              {t('notifications.clearAll')}
            </Button>
          )}
        </Box>
        <Divider />
        
        {items.length === 0 ? (
          <Box sx={{ p: 3, textAlign: 'center' }}>
            <CheckCircleIcon sx={{ fontSize: 48, color: 'success.main', mb: 1 }} />
            <Typography color="text.secondary">
              {t('notifications.noNotifications')}
            </Typography>
          </Box>
        ) : (
          items.map((notification) => {
            const title = notification.titleKey
              ? t(notification.titleKey, notification.params || {})
              : (notification.title || t('notifications.notification'));
            const message = notification.messageKey
              ? t(notification.messageKey, notification.params || {})
              : (notification.message || '');

            return (
            <MenuItem
              key={notification.id}
              onClick={() => handleNotificationClick(notification)}
              sx={{
                flexDirection: 'column',
                alignItems: 'flex-start',
                py: 0.75,
                borderBottom: '1px solid',
                borderColor: 'divider',
                overflow: 'hidden',
                maxHeight: 64,
                transition: 'max-height 180ms ease',
                '&:last-child': { borderBottom: 'none' },
                '&:hover': {
                  maxHeight: 180,
                },
                '&:hover .notification-extra': {
                  opacity: 1,
                  maxHeight: 120,
                },
              }}
            >
              <Box sx={{ display: 'flex', alignItems: 'flex-start', width: '100%', mb: 0.5 }}>
                <Box sx={{ mr: 1, mt: 0.5 }}>
                  {getSeverityIcon(notification.severity)}
                </Box>
                <Box sx={{ flex: 1 }}>
                  <Typography variant="subtitle2" sx={{ fontWeight: 600 }} noWrap>
                    {notification.vehicleName}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" noWrap>
                    {title}
                  </Typography>
                  <Typography
                    variant="caption"
                    color="text.secondary"
                    className="notification-extra"
                    sx={{
                      display: 'block',
                      opacity: 0,
                      maxHeight: 0,
                      overflow: 'hidden',
                      transition: 'opacity 150ms ease, max-height 150ms ease',
                    }}
                  >
                    {message}
                  </Typography>
                </Box>
              </Box>
              <Box
                className="notification-extra"
                sx={{
                  display: 'flex',
                  gap: 1,
                  mt: 1,
                  width: '100%',
                  justifyContent: 'flex-end',
                  opacity: 0,
                  maxHeight: 0,
                  overflow: 'hidden',
                  transition: 'opacity 150ms ease, max-height 150ms ease',
                }}
              >
                <Chip
                  size="small"
                  icon={<SnoozeIcon />}
                  label={t('common.snooze7d')}
                  onClick={(e) => handleSnooze(notification.id, e)}
                  sx={{ cursor: 'pointer' }}
                />
                <Chip
                  size="small"
                  icon={<CloseIcon />}
                  label={t('common.dismiss')}
                  onClick={(e) => handleDismiss(notification.id, e)}
                  sx={{ cursor: 'pointer' }}
                />
              </Box>
            </MenuItem>
            );
          })
        )}
      </Menu>
    </>
  );
};

export default NotificationMenu;
