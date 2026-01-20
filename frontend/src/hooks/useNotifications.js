import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useApiData } from './useApiData';
import { useAuth } from '../contexts/AuthContext';

export const useNotifications = () => {
  const { t } = useTranslation();
  const { data: vehicles } = useApiData('/vehicles');
  const { user } = useAuth();
  const [notifications, setNotifications] = useState([]);
  const [dismissedNotifications, setDismissedNotifications] = useState(() => {
    const saved = localStorage.getItem('dismissedNotifications');
    return saved ? JSON.parse(saved) : {};
  });
  const [snoozedNotifications, setSnoozedNotifications] = useState(() => {
    const saved = localStorage.getItem('snoozedNotifications');
    return saved ? JSON.parse(saved) : {};
  });

  useEffect(() => {
    if (!vehicles || vehicles.length === 0) return;

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const thirtyDaysFromNow = new Date(today);
    thirtyDaysFromNow.setDate(thirtyDaysFromNow.getDate() + 30);

    const newNotifications = [];

    const userCountry = user?.country || null;

    vehicles.forEach((vehicle) => {
      const vehicleKey = `vehicle-${vehicle.id}`;

      // Check MOT expiry — treat missing expiry as expired (no records yet)
      if (userCountry !== 'GB' || vehicle.isMotExempt) {
        // MOT not applicable or vehicle exempt
      } else {
        const notificationKey = `${vehicleKey}-mot`;
        if (!dismissedNotifications[notificationKey] && !isSnoozed(notificationKey)) {
          if (!vehicle.motExpiryDate) {
            newNotifications.push({
              id: notificationKey,
              vehicleId: vehicle.id,
              vehicleName: vehicle.name,
              type: 'mot',
              severity: 'error',
              title: t('notifications.motExpired'),
              message: t('notifications.motExpiredMessage', { date: t('common.unknown') }),
              date: null,
            });
          } else {
            const motDate = new Date(vehicle.motExpiryDate);
            motDate.setHours(0, 0, 0, 0);
            if (motDate < today) {
              newNotifications.push({
                id: notificationKey,
                vehicleId: vehicle.id,
                vehicleName: vehicle.name,
                type: 'mot',
                severity: 'error',
                title: t('notifications.motExpired'),
                message: t('notifications.motExpiredMessage', { date: motDate.toLocaleDateString() }),
                date: vehicle.motExpiryDate,
              });
            } else if (motDate <= thirtyDaysFromNow) {
              const daysUntil = Math.ceil((motDate - today) / (1000 * 60 * 60 * 24));
              newNotifications.push({
                id: notificationKey,
                vehicleId: vehicle.id,
                vehicleName: vehicle.name,
                type: 'mot',
                severity: 'warning',
                title: t('notifications.motExpiringSoon'),
                message: t('notifications.motExpiringMessage', { days: daysUntil, plural: daysUntil !== 1 ? 's' : '' }),
                date: vehicle.motExpiryDate,
              });
            }
          }
        }
      }

      // Check Road Tax expiry — treat missing expiry as expired
      if (userCountry !== 'GB' || vehicle.isRoadTaxExempt) {
        // Road tax not applicable or vehicle exempt
      } else {
        const notificationKey = `${vehicleKey}-tax`;
        if (!dismissedNotifications[notificationKey] && !isSnoozed(notificationKey)) {
          if (!vehicle.roadTaxExpiryDate) {
            newNotifications.push({
              id: notificationKey,
              vehicleId: vehicle.id,
              vehicleName: vehicle.name,
              type: 'tax',
              severity: 'error',
              title: t('notifications.roadTaxExpired'),
              message: t('notifications.roadTaxExpiredMessage', { date: t('common.unknown') }),
              date: null,
            });
          } else {
            const taxDate = new Date(vehicle.roadTaxExpiryDate);
            taxDate.setHours(0, 0, 0, 0);
            if (taxDate < today) {
              newNotifications.push({
                id: notificationKey,
                vehicleId: vehicle.id,
                vehicleName: vehicle.name,
                type: 'tax',
                severity: 'error',
                title: t('notifications.roadTaxExpired'),
                message: t('notifications.roadTaxExpiredMessage', { date: taxDate.toLocaleDateString() }),
                date: vehicle.roadTaxExpiryDate,
              });
            } else if (taxDate <= thirtyDaysFromNow) {
              const daysUntil = Math.ceil((taxDate - today) / (1000 * 60 * 60 * 24));
              newNotifications.push({
                id: notificationKey,
                vehicleId: vehicle.id,
                vehicleName: vehicle.name,
                type: 'tax',
                severity: 'warning',
                title: t('notifications.roadTaxExpiringSoon'),
                message: t('notifications.roadTaxExpiringMessage', { days: daysUntil, plural: daysUntil !== 1 ? 's' : '' }),
                date: vehicle.roadTaxExpiryDate,
              });
            }
          }
        }
      }

      // Check Insurance (treat missing expiry as expired)
      {
        const notificationKey = `${vehicleKey}-insurance`;
        if (!dismissedNotifications[notificationKey] && !isSnoozed(notificationKey)) {
          if (!vehicle.insuranceExpiryDate) {
            newNotifications.push({
              id: notificationKey,
              vehicleId: vehicle.id,
              vehicleName: vehicle.name,
              type: 'insurance',
              severity: 'error',
              title: t('notifications.insuranceExpired'),
              message: t('notifications.insuranceExpiredMessage', { date: t('common.unknown') }),
              date: null,
            });
          } else {
            const insDate = new Date(vehicle.insuranceExpiryDate);
            insDate.setHours(0, 0, 0, 0);
            if (insDate < today) {
              newNotifications.push({
                id: notificationKey,
                vehicleId: vehicle.id,
                vehicleName: vehicle.name,
                type: 'insurance',
                severity: 'error',
                title: t('notifications.insuranceExpired'),
                message: t('notifications.insuranceExpiredMessage', { date: insDate.toLocaleDateString() }),
                date: vehicle.insuranceExpiryDate,
              });
            } else if (insDate <= thirtyDaysFromNow) {
              const daysUntil = Math.ceil((insDate - today) / (1000 * 60 * 60 * 24));
              newNotifications.push({
                id: notificationKey,
                vehicleId: vehicle.id,
                vehicleName: vehicle.name,
                type: 'insurance',
                severity: 'warning',
                title: t('notifications.insuranceExpiringSoon'),
                message: t('notifications.insuranceExpiringMessage', { days: daysUntil, plural: daysUntil !== 1 ? 's' : '' }),
                date: vehicle.insuranceExpiryDate,
              });
            }
          }
        }
      }

      // Check Service Due
      if (vehicle.lastServiceDate && vehicle.serviceIntervalMonths) {
        const lastService = new Date(vehicle.lastServiceDate);
        const monthsDiff = (today - lastService) / (1000 * 60 * 60 * 24 * 30.44);
        const notificationKey = `${vehicleKey}-service`;
        
        if (!dismissedNotifications[notificationKey] && !isSnoozed(notificationKey)) {
          if (monthsDiff > vehicle.serviceIntervalMonths) {
            newNotifications.push({
              id: notificationKey,
              vehicleId: vehicle.id,
              vehicleName: vehicle.name,
              type: 'service',
              severity: 'warning',
              title: t('notifications.serviceOverdue'),
              message: t('notifications.serviceOverdueMessage', { months: Math.floor(monthsDiff - vehicle.serviceIntervalMonths) }),
              date: vehicle.lastServiceDate,
            });
          }
        }
      }
    });

    setNotifications(newNotifications);
  }, [vehicles, dismissedNotifications, snoozedNotifications]);

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
    localStorage.setItem('dismissedNotifications', JSON.stringify(updated));
  };

  const snoozeNotification = (notificationId, days = 7) => {
    const snoozedUntil = new Date();
    snoozedUntil.setDate(snoozedUntil.getDate() + days);
    
    const updated = { ...snoozedNotifications, [notificationId]: snoozedUntil.toISOString() };
    setSnoozedNotifications(updated);
    localStorage.setItem('snoozedNotifications', JSON.stringify(updated));
  };

  const clearAllNotifications = () => {
    const updated = {};
    notifications.forEach((notif) => {
      updated[notif.id] = true;
    });
    setDismissedNotifications({ ...dismissedNotifications, ...updated });
    localStorage.setItem('dismissedNotifications', JSON.stringify({ ...dismissedNotifications, ...updated }));
  };

  return {
    notifications,
    dismissNotification,
    snoozeNotification,
    clearAllNotifications,
  };
};
