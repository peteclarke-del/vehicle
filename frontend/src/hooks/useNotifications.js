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

    // Poll interval: 5 minutes. SSE was avoided because it holds a
    // PHP-FPM worker open indefinitely (while(true)+sleep in PHP), which
    // starves the worker pool. Polling is sufficient for notification data.
    const POLL_INTERVAL_MS = 5 * 60 * 1000;
    let timer = null;
    let cancelled = false;

    const fetchNotifications = async () => {
      try {
        const resp = await api.get('/notifications');
        if (!cancelled) {
          setRawNotifications(Array.isArray(resp.data) ? resp.data : []);
        }
      } catch (e) {
        // ignore — stale data is fine for notifications
      }
    };

    const scheduleNext = () => {
      if (!cancelled) {
        timer = setTimeout(async () => {
          await fetchNotifications();
          scheduleNext();
        }, POLL_INTERVAL_MS);
      }
    };

    // Refresh immediately when the tab becomes visible again
    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        fetchNotifications();
      }
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Initial fetch
    fetchNotifications().then(scheduleNext);

    return () => {
      cancelled = true;
      clearTimeout(timer);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
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
