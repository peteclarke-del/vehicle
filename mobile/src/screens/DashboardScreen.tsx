import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  RefreshControl,
} from 'react-native';
import {
  Text,
  Card,
  useTheme,
  ActivityIndicator,
  Surface,
  Chip,
} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {formatCurrency} from '../utils/formatters';

interface DashboardStats {
  totalVehicles: number;
  totalFuelCost: number;
  totalServiceCost: number;
  totalMileage: number;
  upcomingMots: number;
  upcomingInsurance: number;
}

const DashboardScreen: React.FC = () => {
  const theme = useTheme();
  const {api} = useAuth();
  const {isOnline, pendingChanges, lastSyncTime} = useSync();
  const {preferences} = useUserPreferences();
  
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [vehicles, setVehicles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadDashboardData = useCallback(async () => {
    try {
      const [vehiclesRes, totalsRes] = await Promise.all([
        api.get('/vehicles'),
        api.get('/vehicles/totals').catch(() => ({data: null})),
      ]);

      setVehicles(vehiclesRes.data || []);
      
      if (totalsRes.data) {
        setStats({
          totalVehicles: totalsRes.data.totalVehicles || 0,
          totalFuelCost: totalsRes.data.totalFuelCost || 0,
          totalServiceCost: totalsRes.data.totalServiceCost || 0,
          totalMileage: totalsRes.data.totalMileage || 0,
          upcomingMots: totalsRes.data.upcomingMots || 0,
          upcomingInsurance: totalsRes.data.upcomingInsurance || 0,
        });
      } else {
        // Calculate basic stats from vehicles
        setStats({
          totalVehicles: vehiclesRes.data?.length || 0,
          totalFuelCost: 0,
          totalServiceCost: 0,
          totalMileage: vehiclesRes.data?.reduce((sum: number, v: any) => sum + (v.currentMileage || 0), 0) || 0,
          upcomingMots: 0,
          upcomingInsurance: 0,
        });
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

  const distanceUnit = preferences.distanceUnit === 'km' ? 'km' : 'miles';

  if (loading) {
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

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
              {formatCurrency(stats?.totalFuelCost || 0, preferences.currency)}
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
              {formatCurrency(stats?.totalServiceCost || 0, preferences.currency)}
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
              {stats?.totalMileage?.toLocaleString() || 0}
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
              Total {distanceUnit}
            </Text>
          </Card.Content>
        </Card>
      </View>

      {/* Alerts Section */}
      {(stats?.upcomingMots || 0) > 0 || (stats?.upcomingInsurance || 0) > 0 ? (
        <Card style={styles.alertCard}>
          <Card.Title title="Upcoming Renewals" />
          <Card.Content>
            <View style={styles.alertsRow}>
              {(stats?.upcomingMots || 0) > 0 && (
                <Chip icon="file-document" style={styles.alertChip}>
                  {stats?.upcomingMots} MOT{stats?.upcomingMots !== 1 ? 's' : ''} due
                </Chip>
              )}
              {(stats?.upcomingInsurance || 0) > 0 && (
                <Chip icon="shield-car" style={styles.alertChip}>
                  {stats?.upcomingInsurance} Insurance due
                </Chip>
              )}
            </View>
          </Card.Content>
        </Card>
      ) : null}

      {/* Recent Vehicles */}
      <Text variant="titleMedium" style={styles.sectionTitle}>
        Your Vehicles
      </Text>
      {vehicles.slice(0, 5).map(vehicle => (
        <Card key={vehicle.id} style={styles.vehicleCard}>
          <Card.Title
            title={vehicle.name || vehicle.registration}
            subtitle={`${vehicle.make || ''} ${vehicle.model || ''} ${vehicle.year || ''}`.trim()}
            left={props => <Icon {...props} name="car" size={24} color={theme.colors.primary} />}
            right={props => (
              <Text {...props} style={styles.mileageText}>
                {vehicle.currentMileage?.toLocaleString() || 0} {distanceUnit}
              </Text>
            )}
          />
        </Card>
      ))}

      {vehicles.length === 0 && (
        <Card style={styles.emptyCard}>
          <Card.Content style={styles.emptyContent}>
            <Icon name="car-off" size={48} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodyLarge" style={{color: theme.colors.onSurfaceVariant, marginTop: 16}}>
              No vehicles yet
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
              Add your first vehicle to get started
            </Text>
          </Card.Content>
        </Card>
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
  alertCard: {
    margin: 16,
    marginTop: 8,
  },
  alertsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  alertChip: {
    marginRight: 8,
  },
  sectionTitle: {
    marginHorizontal: 16,
    marginTop: 16,
    marginBottom: 8,
  },
  vehicleCard: {
    marginHorizontal: 16,
    marginBottom: 8,
  },
  mileageText: {
    marginRight: 16,
    fontSize: 12,
  },
  emptyCard: {
    margin: 16,
  },
  emptyContent: {
    alignItems: 'center',
    paddingVertical: 32,
  },
  lastSync: {
    textAlign: 'center',
    padding: 16,
    opacity: 0.6,
  },
});

export default DashboardScreen;
