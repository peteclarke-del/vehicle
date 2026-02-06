import { useState, useEffect, useMemo, useCallback } from 'react';
import { useAuth } from '../contexts/AuthContext';
import SafeStorage from '../utils/SafeStorage';

// Clean up old dismissed/snoozed notifications (older than 90 days)
const cleanupOldNotifications = () => {
  const now = Date.now();
  const maxAge = 90 * 24 * 60 * 60 * 1000; // 90 days in ms

  // Clean dismissed notifications (keep only recent ones)
  const dismissed = SafeStorage.get('dismissedNotifications', {});
  const dismissedTimestamps = SafeStorage.get('dismissedNotificationsTimestamps', {});
  const newDismissed = {};
  const newDismissedTimestamps = {};
  
  Object.keys(dismissed).forEach((key) => {
    const timestamp = dismissedTimestamps[key] || now;
    if (now - timestamp < maxAge) {
      newDismissed[key] = dismissed[key];
      newDismissedTimestamps[key] = timestamp;
    }
  });
  
  SafeStorage.set('dismissedNotifications', newDismissed);
  SafeStorage.set('dismissedNotificationsTimestamps', newDismissedTimestamps);

  // Clean expired snoozed notifications
  const snoozed = SafeStorage.get('snoozedNotifications', {});
  const newSnoozed = {};
  
  Object.keys(snoozed).forEach((key) => {
    const snoozedUntil = new Date(snoozed[key]).getTime();
    if (snoozedUntil > now) {
      newSnoozed[key] = snoozed[key];
    }
  });
  
  SafeStorage.set('snoozedNotifications', newSnoozed);

  return { dismissed: newDismissed, snoozed: newSnoozed, dismissedTimestamps: newDismissedTimestamps };
};

export const useNotifications = () => {
  const { user, api } = useAuth();
  const [rawNotifications, setRawNotifications] = useState([]);
  const [dismissedNotifications, setDismissedNotifications] = useState(() => {
    const cleaned = cleanupOldNotifications();
    return cleaned.dismissed;
  });
  const [dismissedTimestamps, setDismissedTimestamps] = useState(() => {
    return SafeStorage.get('dismissedNotificationsTimestamps', {});
  });
  const [snoozedNotifications, setSnoozedNotifications] = useState(() => {
    return SafeStorage.get('snoozedNotifications', {});
  });

  useEffect(() => {
    if (!api || !user?.id) return;
    const token = SafeStorage.get('token');
    const baseUrl = process.env.REACT_APP_API_URL || 'http://localhost:8081/api';
    const streamUrl = `${baseUrl}/notifications/stream?token=${encodeURIComponent(token || '')}`;
    let eventSource;
    let stopped = false;
    let retryCount = 0;
    const maxRetryDelay = 60000; // Max 60 seconds

    const openStream = () => {
      if (stopped) return;
      eventSource = new EventSource(streamUrl);

      eventSource.addEventListener('notifications', (event) => {
        try {
          const payload = JSON.parse(event.data);
          setRawNotifications(Array.isArray(payload) ? payload : []);
          retryCount = 0; // Reset on successful message
        } catch (e) {
          // ignore malformed payload
        }
      });

      eventSource.onopen = () => {
        retryCount = 0; // Reset on successful connection
      };

      eventSource.onerror = () => {
        eventSource?.close();
        // fallback to one-off fetch
        (async () => {
          try {
            const resp = await api.get('/notifications');
            const data = Array.isArray(resp.data) ? resp.data : [];
            setRawNotifications(data);
          } catch (e) {
            // ignore
          }
        })();
        // Exponential backoff: 1s, 2s, 4s, 8s... up to maxRetryDelay
        const delay = Math.min(1000 * Math.pow(2, retryCount), maxRetryDelay);
        retryCount++;
        setTimeout(() => {
          if (!stopped) openStream();
        }, delay);
      };
    };

    openStream();

    return () => {
      stopped = true;
      eventSource?.close();
    };
  }, [api, user?.id]);

  const isSnoozed = useCallback((notificationKey) => {
    const snoozedUntil = snoozedNotifications[notificationKey];
    if (!snoozedUntil) return false;
    
    const now = new Date();
    const snoozedDate = new Date(snoozedUntil);
    return now < snoozedDate;
  }, [snoozedNotifications]);

  const dismissNotification = useCallback((notificationId) => {
    const now = Date.now();
    const updatedDismissed = { ...dismissedNotifications, [notificationId]: true };
    const updatedTimestamps = { ...dismissedTimestamps, [notificationId]: now };
    
    setDismissedNotifications(updatedDismissed);
    setDismissedTimestamps(updatedTimestamps);
    SafeStorage.set('dismissedNotifications', updatedDismissed);
    SafeStorage.set('dismissedNotificationsTimestamps', updatedTimestamps);
  }, [dismissedNotifications, dismissedTimestamps]);

  const snoozeNotification = useCallback((notificationId, days = 7) => {
    const snoozedUntil = new Date();
    snoozedUntil.setDate(snoozedUntil.getDate() + days);
    
    const updated = { ...snoozedNotifications, [notificationId]: snoozedUntil.toISOString() };
    setSnoozedNotifications(updated);
    SafeStorage.set('snoozedNotifications', updated);
  }, [snoozedNotifications]);

  const notifications = useMemo(() => {
    const filtered = (Array.isArray(rawNotifications) ? rawNotifications : []).filter((n) => {
      if (!n || !n.id) return false;
      if (dismissedNotifications[n.id]) return false;
      if (isSnoozed(n.id)) return false;
      return true;
    });
    return filtered;
  }, [rawNotifications, dismissedNotifications, isSnoozed]);

  const clearAllNotifications = useCallback(() => {
    const now = Date.now();
    const updatedDismissed = { ...dismissedNotifications };
    const updatedTimestamps = { ...dismissedTimestamps };
    
    notifications.forEach((notif) => {
      updatedDismissed[notif.id] = true;
      updatedTimestamps[notif.id] = now;
    });
    
    setDismissedNotifications(updatedDismissed);
    setDismissedTimestamps(updatedTimestamps);
    SafeStorage.set('dismissedNotifications', updatedDismissed);
    SafeStorage.set('dismissedNotificationsTimestamps', updatedTimestamps);
  }, [notifications, dismissedNotifications, dismissedTimestamps]);

  return {
    notifications,
    dismissNotification,
    snoozeNotification,
    clearAllNotifications,
  };
};
