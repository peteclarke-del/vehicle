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
import { useNotifications } from '../hooks/useNotifications';
import { useTranslation } from 'react-i18next';

const NotificationMenu = () => {
  const [anchorEl, setAnchorEl] = useState(null);
  const navigate = useNavigate();
  const { notifications, dismissNotification, snoozeNotification, clearAllNotifications } = useNotifications();

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
    navigate(`/vehicles/${notification.vehicleId}`);
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
          <Badge badgeContent={notifications.length} color="error">
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
          {notifications.length > 0 && (
            <Button size="small" onClick={clearAllNotifications}>
              {t('notifications.clearAll')}
            </Button>
          )}
        </Box>
        <Divider />
        
        {notifications.length === 0 ? (
          <Box sx={{ p: 3, textAlign: 'center' }}>
            <CheckCircleIcon sx={{ fontSize: 48, color: 'success.main', mb: 1 }} />
            <Typography color="text.secondary">
              {t('notifications.noNotifications')}
            </Typography>
          </Box>
        ) : (
          notifications.map((notification) => (
            <MenuItem
              key={notification.id}
              onClick={() => handleNotificationClick(notification)}
              sx={{
                flexDirection: 'column',
                alignItems: 'flex-start',
                py: 1.5,
                borderBottom: '1px solid',
                borderColor: 'divider',
                '&:last-child': { borderBottom: 'none' },
              }}
            >
              <Box sx={{ display: 'flex', alignItems: 'flex-start', width: '100%', mb: 0.5 }}>
                <Box sx={{ mr: 1, mt: 0.5 }}>
                  {getSeverityIcon(notification.severity)}
                </Box>
                <Box sx={{ flex: 1 }}>
                  <Typography variant="subtitle2" sx={{ fontWeight: 600 }}>
                    {notification.vehicleName}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mb: 0.5 }}>
                    {notification.title}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    {notification.message}
                  </Typography>
                </Box>
              </Box>
              <Box sx={{ display: 'flex', gap: 1, mt: 1, width: '100%', justifyContent: 'flex-end' }}>
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
          ))
        )}
      </Menu>
    </>
  );
};

export default NotificationMenu;
