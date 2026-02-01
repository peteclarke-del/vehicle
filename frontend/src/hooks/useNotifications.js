import { useState, useEffect, useMemo } from 'react';
import { useAuth } from '../contexts/AuthContext';
import SafeStorage from '../utils/SafeStorage';

export const useNotifications = () => {
  const { user, api } = useAuth();
  const [rawNotifications, setRawNotifications] = useState([]);
  const [dismissedNotifications, setDismissedNotifications] = useState(() => {
    return SafeStorage.get('dismissedNotifications', {});
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

    const openStream = () => {
      if (stopped) return;
      eventSource = new EventSource(streamUrl);

      eventSource.addEventListener('notifications', (event) => {
        try {
          const payload = JSON.parse(event.data);
          setRawNotifications(Array.isArray(payload) ? payload : []);
        } catch (e) {
          // ignore malformed payload
        }
      });

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
        // retry in 5s
        setTimeout(() => {
          if (!stopped) openStream();
        }, 5000);
      };
    };

    openStream();

    return () => {
      stopped = true;
      eventSource?.close();
    };
  }, [api, user?.id]);

  const isSnoozed = (notificationKey) => {
    const snoozedUntil = snoozedNotifications[notificationKey];
    if (!snoozedUntil) return false;
    
    const now = new Date();
    const snoozedDate = new Date(snoozedUntil);
    return now < snoozedDate;
  };

  const dismissNotification = (notificationId) => {
    const updated = { ...dismissedNotifications, [notificationId]: true };
    setDismissedNotifications(updated);
    SafeStorage.set('dismissedNotifications', updated);
  };

  const snoozeNotification = (notificationId, days = 7) => {
    const snoozedUntil = new Date();
    snoozedUntil.setDate(snoozedUntil.getDate() + days);
    
    const updated = { ...snoozedNotifications, [notificationId]: snoozedUntil.toISOString() };
    setSnoozedNotifications(updated);
    SafeStorage.set('snoozedNotifications', updated);
  };

  const clearAllNotifications = () => {
    const updated = {};
    notifications.forEach((notif) => {
      updated[notif.id] = true;
    });
    setDismissedNotifications({ ...dismissedNotifications, ...updated });
    SafeStorage.set('dismissedNotifications', { ...dismissedNotifications, ...updated });
  };

  const notifications = useMemo(() => {
    const filtered = (Array.isArray(rawNotifications) ? rawNotifications : []).filter((n) => {
      if (!n || !n.id) return false;
      if (dismissedNotifications[n.id]) return false;
      if (isSnoozed(n.id)) return false;
      return true;
    });
    return filtered;
  }, [rawNotifications, dismissedNotifications, snoozedNotifications]);

  return {
    notifications,
    dismissNotification,
    snoozeNotification,
    clearAllNotifications,
  };
};
