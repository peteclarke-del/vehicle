/**
 * Notification Service
 * Handles local Android notifications for vehicle-related reminders
 */

import {Platform, PermissionsAndroid} from 'react-native';

export interface VehicleNotification {
  vehicleId: number;
  vehicleName: string;
  type: 'mot' | 'insurance' | 'roadTax' | 'service';
  severity: 'danger' | 'warning' | 'info';
  title: string;
  message: string;
  dueDate?: string;
  daysUntilDue: number;
  icon: string;
}

interface VehicleData {
  id: number;
  name: string | null;
  registration: string;
  status: string;
  motExpiryDate: string | null;
  insuranceExpiryDate: string | null;
  roadTaxExpiryDate: string | null;
  lastServiceDate: string | null;
  serviceIntervalMonths: number | null;
  isMotExempt: boolean;
  isRoadTaxExempt: boolean;
}

let notifee: any = null;
let AndroidImportance: any = null;

// Lazy-load notifee to handle case where it's not available
const loadNotifee = async () => {
  if (notifee !== null) return notifee;
  try {
    const mod = require('@notifee/react-native');
    notifee = mod.default;
    AndroidImportance = mod.AndroidImportance;
    return notifee;
  } catch (e) {
    console.warn('Notifee not available, system notifications disabled');
    return null;
  }
};

/**
 * Request notification permissions (Android 13+)
 */
export const requestNotificationPermission = async (): Promise<boolean> => {
  try {
    if (Platform.OS === 'android' && Platform.Version >= 33) {
      const granted = await PermissionsAndroid.request(
        'android.permission.POST_NOTIFICATIONS' as any,
        {
          title: 'Notification Permission',
          message: 'Vehicle Manager needs permission to send you reminders about MOT, insurance and service dates.',
          buttonNeutral: 'Ask Me Later',
          buttonNegative: 'Cancel',
          buttonPositive: 'OK',
        },
      );
      return granted === PermissionsAndroid.RESULTS.GRANTED;
    }
    return true; // Android < 13 doesn't need runtime permission
  } catch (err) {
    console.warn('Failed to request notification permission:', err);
    return false;
  }
};

/**
 * Initialize notification channels
 */
export const initializeNotifications = async (): Promise<void> => {
  const nf = await loadNotifee();
  if (!nf) return;

  try {
    await nf.createChannel({
      id: 'vehicle-reminders',
      name: 'Vehicle Reminders',
      description: 'MOT, insurance, road tax and service reminders',
      importance: AndroidImportance?.HIGH || 4,
      sound: 'default',
    });

    await nf.createChannel({
      id: 'vehicle-urgent',
      name: 'Urgent Vehicle Alerts',
      description: 'Expired MOT, insurance or road tax alerts',
      importance: AndroidImportance?.HIGH || 4,
      sound: 'default',
    });
  } catch (e) {
    console.warn('Failed to create notification channels:', e);
  }
};

/**
 * Display a system notification
 */
export const showNotification = async (
  title: string,
  body: string,
  channelId: string = 'vehicle-reminders',
): Promise<void> => {
  const nf = await loadNotifee();
  if (!nf) return;

  try {
    await nf.displayNotification({
      title,
      body,
      android: {
        channelId,
        smallIcon: 'ic_launcher',
        pressAction: {
          id: 'default',
        },
      },
    });
  } catch (e) {
    console.warn('Failed to show notification:', e);
  }
};

/**
 * Calculate days between two dates
 */
const daysBetween = (dateStr: string): number => {
  const target = new Date(dateStr);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  target.setHours(0, 0, 0, 0);
  return Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
};

/**
 * Calculate all vehicle notifications from vehicle data
 */
export const calculateVehicleNotifications = (
  vehicles: VehicleData[],
): VehicleNotification[] => {
  const notifications: VehicleNotification[] = [];

  for (const vehicle of vehicles) {
    // Only check live vehicles
    if (vehicle.status !== 'Live') continue;

    const name = vehicle.name || vehicle.registration;

    // MOT Check
    if (vehicle.motExpiryDate && !vehicle.isMotExempt) {
      const days = daysBetween(vehicle.motExpiryDate);
      if (days < 0) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'mot',
          severity: 'danger',
          title: 'MOT Expired',
          message: `${name} — MOT expired ${Math.abs(days)} days ago`,
          dueDate: vehicle.motExpiryDate,
          daysUntilDue: days,
          icon: 'file-document-alert',
        });
      } else if (days <= 30) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'mot',
          severity: 'warning',
          title: 'MOT Due Soon',
          message: `${name} — MOT expires in ${days} day${days !== 1 ? 's' : ''}`,
          dueDate: vehicle.motExpiryDate,
          daysUntilDue: days,
          icon: 'file-document-alert',
        });
      } else if (days <= 60) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'mot',
          severity: 'info',
          title: 'MOT Coming Up',
          message: `${name} — MOT expires in ${days} days`,
          dueDate: vehicle.motExpiryDate,
          daysUntilDue: days,
          icon: 'file-document',
        });
      }
    }

    // Insurance Check
    if (vehicle.insuranceExpiryDate) {
      const days = daysBetween(vehicle.insuranceExpiryDate);
      if (days < 0) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'insurance',
          severity: 'danger',
          title: 'Insurance Expired',
          message: `${name} — Insurance expired ${Math.abs(days)} days ago`,
          dueDate: vehicle.insuranceExpiryDate,
          daysUntilDue: days,
          icon: 'shield-alert',
        });
      } else if (days <= 30) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'insurance',
          severity: 'warning',
          title: 'Insurance Due Soon',
          message: `${name} — Insurance expires in ${days} day${days !== 1 ? 's' : ''}`,
          dueDate: vehicle.insuranceExpiryDate,
          daysUntilDue: days,
          icon: 'shield-alert',
        });
      } else if (days <= 60) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'insurance',
          severity: 'info',
          title: 'Insurance Coming Up',
          message: `${name} — Insurance expires in ${days} days`,
          dueDate: vehicle.insuranceExpiryDate,
          daysUntilDue: days,
          icon: 'shield-car',
        });
      }
    }

    // Road Tax Check
    if (vehicle.roadTaxExpiryDate && !vehicle.isRoadTaxExempt) {
      const days = daysBetween(vehicle.roadTaxExpiryDate);
      if (days < 0) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'roadTax',
          severity: 'danger',
          title: 'Road Tax Expired',
          message: `${name} — Road tax expired ${Math.abs(days)} days ago`,
          dueDate: vehicle.roadTaxExpiryDate,
          daysUntilDue: days,
          icon: 'cash-remove',
        });
      } else if (days <= 30) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'roadTax',
          severity: 'warning',
          title: 'Road Tax Due Soon',
          message: `${name} — Road tax expires in ${days} day${days !== 1 ? 's' : ''}`,
          dueDate: vehicle.roadTaxExpiryDate,
          daysUntilDue: days,
          icon: 'cash-remove',
        });
      } else if (days <= 60) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'roadTax',
          severity: 'info',
          title: 'Road Tax Coming Up',
          message: `${name} — Road tax expires in ${days} days`,
          dueDate: vehicle.roadTaxExpiryDate,
          daysUntilDue: days,
          icon: 'cash',
        });
      }
    }

    // Service Due Check
    if (vehicle.lastServiceDate && vehicle.serviceIntervalMonths) {
      const lastService = new Date(vehicle.lastServiceDate);
      const nextServiceDate = new Date(lastService);
      nextServiceDate.setMonth(nextServiceDate.getMonth() + vehicle.serviceIntervalMonths);
      const nextServiceStr = nextServiceDate.toISOString().split('T')[0];
      const days = daysBetween(nextServiceStr);

      if (days < 0) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'service',
          severity: 'danger',
          title: 'Service Overdue',
          message: `${name} — Service overdue by ${Math.abs(days)} days`,
          dueDate: nextServiceStr,
          daysUntilDue: days,
          icon: 'wrench-clock',
        });
      } else if (days <= 30) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'service',
          severity: 'warning',
          title: 'Service Due Soon',
          message: `${name} — Service due in ${days} day${days !== 1 ? 's' : ''}`,
          dueDate: nextServiceStr,
          daysUntilDue: days,
          icon: 'wrench-clock',
        });
      } else if (days <= 60) {
        notifications.push({
          vehicleId: vehicle.id,
          vehicleName: name,
          type: 'service',
          severity: 'info',
          title: 'Service Coming Up',
          message: `${name} — Service due in ${days} days`,
          dueDate: nextServiceStr,
          daysUntilDue: days,
          icon: 'wrench',
        });
      }
    }
  }

  // Sort by severity (danger first) then by days until due
  const severityOrder: Record<string, number> = {danger: 0, warning: 1, info: 2};
  return notifications.sort((a, b) => {
    const sevDiff = severityOrder[a.severity] - severityOrder[b.severity];
    if (sevDiff !== 0) return sevDiff;
    return a.daysUntilDue - b.daysUntilDue;
  });
};

/**
 * Fire Android system notifications for critical items (danger + warning)
 */
export const fireSystemNotifications = async (
  notifications: VehicleNotification[],
): Promise<void> => {
  const critical = notifications.filter(n => n.severity === 'danger' || n.severity === 'warning');
  
  // Only fire the top 5 most critical to avoid notification spam
  for (const notif of critical.slice(0, 5)) {
    const channelId = notif.severity === 'danger' ? 'vehicle-urgent' : 'vehicle-reminders';
    await showNotification(notif.title, notif.message, channelId);
  }
};
