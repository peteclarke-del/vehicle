import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  RefreshControl,
  TouchableOpacity,
} from 'react-native';
import {
  Text,
  Card,
  useTheme,
  ActivityIndicator,
  Surface,
  IconButton,
  Button,
} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {formatCurrency, formatDate, convertDistance} from '../utils/formatters';
import {
  VehicleNotification,
  calculateVehicleNotifications,
  fireSystemNotifications,
} from '../services/NotificationService';
import {MainStackParamList} from '../navigation/MainNavigator';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

interface DashboardStats {
  totalVehicles: number;
  totalFuelCost: number;
  totalServiceCost: number;
  totalMileage: number;
}

const DashboardScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {isOnline, pendingChanges, lastSyncTime} = useSync();
  const {preferences} = useUserPreferences();

  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [notifications, setNotifications] = useState<VehicleNotification[]>([]);
  const [dismissedKeys, setDismissedKeys] = useState<Set<string>>(new Set());
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  // Load dismissed notification keys from storage
  useEffect(() => {
    AsyncStorage.getItem('dismissed_notifications')
      .then(val => {
        if (val) setDismissedKeys(new Set(JSON.parse(val)));
      })
      .catch(() => {});
  }, []);

  const dismissNotification = useCallback((notif: VehicleNotification) => {
    const key = `${notif.vehicleId}-${notif.type}`;
    setDismissedKeys(prev => {
      const next = new Set(prev);
      next.add(key);
      AsyncStorage.setItem('dismissed_notifications', JSON.stringify([...next])).catch(() => {});
      return next;
    });
  }, []);

  const clearAllNotifications = useCallback(() => {
    const allKeys = notifications.map(n => `${n.vehicleId}-${n.type}`);
    setDismissedKeys(new Set(allKeys));
    AsyncStorage.setItem('dismissed_notifications', JSON.stringify(allKeys)).catch(() => {});
  }, [notifications]);

  const resetDismissed = useCallback(() => {
    setDismissedKeys(new Set());
    AsyncStorage.removeItem('dismissed_notifications').catch(() => {});
  }, []);

  const loadDashboardData = useCallback(async () => {
    try {
      // Load cached dashboard data first for instant display
      try {
        const cached = await AsyncStorage.getItem('cache_dashboard');
        if (cached) {
          const parsed = JSON.parse(cached);
          if (parsed.stats) setStats(parsed.stats);
          if (parsed.notifications) setNotifications(parsed.notifications);
        }
      } catch (e) { /* cache miss is fine */ }

      const [vehiclesRes, totalsRes] = await Promise.all([
        api.get('/vehicles'),
        api.get('/vehicles/totals').catch(() => ({data: null})),
      ]);

      const vehicleList = Array.isArray(vehiclesRes.data) ? vehiclesRes.data : [];

      // API returns: {periodMonths, fuel, parts, consumables, averageServiceCost}
      const totals = totalsRes.data;
      const totalMileage = vehicleList.reduce(
        (sum: number, v: any) => sum + (v.currentMileage || 0),
        0,
      );

      const newStats = {
        totalVehicles: vehicleList.length,
        totalFuelCost: Number(totals?.fuel) || 0,
        totalServiceCost: (Number(totals?.parts) || 0) + (Number(totals?.consumables) || 0),
        totalMileage,
      };
      setStats(newStats);

      // Calculate vehicle notifications from real data
      const vehicleNotifications = calculateVehicleNotifications(vehicleList);
      setNotifications(vehicleNotifications);

      // Cache the fresh data
      await AsyncStorage.setItem('cache_dashboard', JSON.stringify({
        stats: newStats,
        notifications: vehicleNotifications,
      })).catch(() => {});

      // Fire system notifications for critical items (runs once per load)
      if (vehicleNotifications.some(n => n.severity === 'danger' || n.severity === 'warning')) {
        fireSystemNotifications(vehicleNotifications).catch(() => {});
      }
    } catch (error) {
      console.error('Error loading dashboard data:', error);
    } finally {
      setLoading(false);
    }
  }, [api]);

  useEffect(() => {
    loadDashboardData();
  }, [loadDashboardData]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadDashboardData();
    setRefreshing(false);
  }, [loadDashboardData]);

  const userUnit = preferences.distanceUnit === 'km' ? 'km' : 'mi' as const;
  const distanceLabel = userUnit === 'km' ? 'km' : 'miles';

  const getSeverityColors = (severity: string) => {
    switch (severity) {
      case 'danger':
        return {
          bg: theme.colors.errorContainer,
          text: theme.colors.onErrorContainer,
          icon: theme.colors.error,
        };
      case 'warning':
        return {
          bg: '#FEF3C7',
          text: '#92400E',
          icon: '#F59E0B',
        };
      case 'info':
      default:
        return {
          bg: theme.colors.primaryContainer,
          text: theme.colors.onPrimaryContainer,
          icon: theme.colors.primary,
        };
    }
  };

  if (loading) {
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  const dangerCount = notifications.filter(n => n.severity === 'danger').length;
  const warningCount = notifications.filter(n => n.severity === 'warning').length;
  const infoCount = notifications.filter(n => n.severity === 'info').length;

  // Filter out dismissed notifications for display
  const visibleNotifications = notifications.filter(
    n => !dismissedKeys.has(`${n.vehicleId}-${n.type}`),
  );

  return (
    <ScrollView
      style={[styles.container, {backgroundColor: theme.colors.background}]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Sync Status Banner */}
      <Surface style={[styles.syncBanner, {backgroundColor: isOnline ? theme.colors.primaryContainer : theme.colors.errorContainer}]}>
        <Icon
          name={isOnline ? 'cloud-check' : 'cloud-off-outline'}
          size={20}
          color={isOnline ? theme.colors.onPrimaryContainer : theme.colors.onErrorContainer}
        />
        <Text style={{color: isOnline ? theme.colors.onPrimaryContainer : theme.colors.onErrorContainer, marginLeft: 8}}>
          {isOnline ? 'Online' : 'Offline'}
          {pendingChanges.length > 0 && ` • ${pendingChanges.length} pending`}
        </Text>
      </Surface>

      {/* Stats Cards */}
      <View style={styles.statsGrid}>
        <Card style={styles.statCard}>
          <Card.Content style={styles.statContent}>
            <Icon name="car" size={32} color={theme.colors.primary} />
            <Text variant="headlineMedium" style={styles.statValue}>
              {stats?.totalVehicles || 0}
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
              Vehicles
            </Text>
          </Card.Content>
        </Card>

        <Card style={styles.statCard}>
          <Card.Content style={styles.statContent}>
            <Icon name="gas-station" size={32} color={theme.colors.tertiary} />
            <Text variant="headlineMedium" style={styles.statValue}>
              {formatCurrency(stats?.totalFuelCost, preferences.currency)}
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
              Fuel Costs
            </Text>
          </Card.Content>
        </Card>

        <Card style={styles.statCard}>
          <Card.Content style={styles.statContent}>
            <Icon name="wrench" size={32} color={theme.colors.secondary} />
            <Text variant="headlineMedium" style={styles.statValue}>
              {formatCurrency(stats?.totalServiceCost, preferences.currency)}
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
              Service Costs
            </Text>
          </Card.Content>
        </Card>

        <Card style={styles.statCard}>
          <Card.Content style={styles.statContent}>
            <Icon name="road-variant" size={32} color={theme.colors.primary} />
            <Text variant="headlineMedium" style={styles.statValue}>
              {(convertDistance(stats?.totalMileage, userUnit) || 0).toLocaleString('en-GB')}
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
              Total {distanceLabel}
            </Text>
          </Card.Content>
        </Card>
      </View>

      {/* Quick Actions */}
      <View style={styles.quickActionsRow}>
        <TouchableOpacity
          style={[styles.quickAction, {backgroundColor: theme.colors.primaryContainer}]}
          onPress={() => navigation.navigate('QuickFuel')}
          activeOpacity={0.7}>
          <Icon name="gas-station" size={28} color={theme.colors.primary} />
          <Text variant="labelMedium" style={{color: theme.colors.onPrimaryContainer, marginTop: 4}}>
            Quick Fuel
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.quickAction, {backgroundColor: theme.colors.secondaryContainer}]}
          onPress={() => navigation.navigate('VehicleLookup')}
          activeOpacity={0.7}>
          <Icon name="car-search" size={28} color={theme.colors.secondary} />
          <Text variant="labelMedium" style={{color: theme.colors.onSecondaryContainer, marginTop: 4}}>
            Reg Lookup
          </Text>
        </TouchableOpacity>
      </View>

      {/* Notifications Section */}
      <View style={styles.notificationsHeader}>
        <Text variant="titleMedium" style={{fontWeight: 'bold'}}>
          Notifications
        </Text>
        <View style={styles.notificationActions}>
          {notifications.length > 0 && (
            <View style={styles.notificationBadges}>
              {dangerCount > 0 && (
                <View style={[styles.badge, {backgroundColor: theme.colors.error}]}>
                  <Text variant="labelSmall" style={{color: theme.colors.onError, fontWeight: 'bold'}}>
                    {dangerCount}
                  </Text>
                </View>
              )}
              {warningCount > 0 && (
                <View style={[styles.badge, {backgroundColor: '#F59E0B'}]}>
                  <Text variant="labelSmall" style={{color: '#FFFFFF', fontWeight: 'bold'}}>
                    {warningCount}
                  </Text>
                </View>
              )}
              {infoCount > 0 && (
                <View style={[styles.badge, {backgroundColor: theme.colors.primary}]}>
                  <Text variant="labelSmall" style={{color: theme.colors.onPrimary, fontWeight: 'bold'}}>
                    {infoCount}
                  </Text>
                </View>
              )}
            </View>
          )}
          {visibleNotifications.length > 0 && (
            <TouchableOpacity onPress={clearAllNotifications} style={{marginLeft: 8}}>
              <Text variant="labelSmall" style={{color: theme.colors.primary}}>Clear all</Text>
            </TouchableOpacity>
          )}
          {dismissedKeys.size > 0 && visibleNotifications.length === 0 && (
            <TouchableOpacity onPress={resetDismissed} style={{marginLeft: 8}}>
              <Text variant="labelSmall" style={{color: theme.colors.primary}}>Show all</Text>
            </TouchableOpacity>
          )}
        </View>
      </View>

      {visibleNotifications.length === 0 ? (
        <Card style={styles.emptyNotificationCard}>
          <Card.Content style={styles.emptyNotificationContent}>
            <Icon name="check-circle" size={48} color={theme.colors.primary} />
            <Text variant="bodyLarge" style={{marginTop: 12, color: theme.colors.onSurfaceVariant}}>
              All clear!
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant, textAlign: 'center'}}>
              No upcoming MOT, insurance, road tax or service reminders.
            </Text>
          </Card.Content>
        </Card>
      ) : (
        visibleNotifications.map((notif, index) => {
          const colors = getSeverityColors(notif.severity);
          return (
            <Card
              key={`${notif.vehicleId}-${notif.type}-${index}`}
              style={[styles.notificationCard, {backgroundColor: colors.bg}]}>
              <Card.Content style={styles.notificationContent}>
                <TouchableOpacity
                  style={{flexDirection: 'row', alignItems: 'center', flex: 1}}
                  onPress={() => navigation.navigate('VehicleDetail', {vehicleId: notif.vehicleId})}
                  activeOpacity={0.7}>
                  <Icon name={notif.icon} size={28} color={colors.icon} />
                  <View style={styles.notificationText}>
                    <Text variant="titleSmall" style={{color: colors.text, fontWeight: 'bold'}}>
                      {notif.title}
                    </Text>
                    <Text variant="bodySmall" style={{color: colors.text}}>
                      {notif.message}
                    </Text>
                    {notif.dueDate && (
                      <Text variant="labelSmall" style={{color: colors.text, opacity: 0.7, marginTop: 2}}>
                        {formatDate(notif.dueDate)}
                      </Text>
                    )}
                  </View>
                </TouchableOpacity>
                <IconButton
                  icon="close"
                  size={18}
                  iconColor={colors.text}
                  onPress={() => dismissNotification(notif)}
                  style={{margin: -4, opacity: 0.7}}
                />
              </Card.Content>
            </Card>
          );
        })
      )}

      {/* Last Sync Info */}
      {lastSyncTime && (
        <Text variant="bodySmall" style={styles.lastSync}>
          Last synced: {lastSyncTime.toLocaleString()}
        </Text>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  syncBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    padding: 8,
    gap: 8,
  },
  statCard: {
    width: '48%',
    flexGrow: 1,
  },
  statContent: {
    alignItems: 'center',
    paddingVertical: 16,
  },
  statValue: {
    fontWeight: 'bold',
    marginTop: 8,
  },
  notificationsHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 8,
  },
  notificationActions: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  notificationBadges: {
    flexDirection: 'row',
    gap: 6,
  },
  badge: {
    minWidth: 22,
    height: 22,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  emptyNotificationCard: {
    marginHorizontal: 16,
    marginBottom: 8,
  },
  emptyNotificationContent: {
    alignItems: 'center',
    paddingVertical: 24,
  },
  notificationCard: {
    marginHorizontal: 16,
    marginBottom: 8,
  },
  notificationContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  notificationText: {
    flex: 1,
  },
  lastSync: {
    textAlign: 'center',
    padding: 16,
    opacity: 0.6,
  },
  quickActionsRow: {
    flexDirection: 'row',
    paddingHorizontal: 12,
    paddingTop: 8,
    gap: 8,
  },
  quickAction: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 12,
  },
});

export default DashboardScreen;
