import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  StyleSheet,
  FlatList,
  RefreshControl,
} from 'react-native';
import {
  Text,
  Card,
  FAB,
  useTheme,
  ActivityIndicator,
  Chip,
} from 'react-native-paper';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {useVehicleSelection} from '../contexts/VehicleSelectionContext';
import {formatCurrency, formatDate, formatMileage} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

interface FuelRecord {
  id: number;
  vehicleId: number;
  date: string;
  mileage: number | null;
  litres: string | number | null;
  cost: string | number | null;
  station: string | null;
  fuelType: string | null;
  notes: string | null;
  receiptAttachmentId: number | null;
  createdAt: string;
}

interface Vehicle {
  id: number;
  registration: string;
  name: string | null;
}

const FuelRecordsScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {isOnline} = useSync();
  const {preferences} = useUserPreferences();
  const {globalVehicleId, setGlobalVehicleId} = useVehicleSelection();
  
  const [records, setRecords] = useState<FuelRecord[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const selectedVehicleId = globalVehicleId;
  const setSelectedVehicleId = setGlobalVehicleId;
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      // Try to load from cache first for instant display
      const cacheKey = `cache_fuel_${selectedVehicleId}`;
      try {
        const cached = await AsyncStorage.getItem(cacheKey);
        if (cached) {
          const parsed = JSON.parse(cached);
          setRecords(parsed.records || []);
          setVehicles(parsed.vehicles || []);
        }
      } catch (e) { /* cache miss is fine */ }

      // Then fetch from network
      const [vehiclesRes, recordsRes] = await Promise.all([
        api.get('/vehicles'),
        api.get('/fuel-records', {
          params: selectedVehicleId !== 'all' ? {vehicleId: selectedVehicleId} : {},
        }),
      ]);
      
      const newVehicles = vehiclesRes.data || [];
      const newRecords = recordsRes.data || [];
      setVehicles(newVehicles);
      setRecords(newRecords);

      // Cache the fresh data
      await AsyncStorage.setItem(cacheKey, JSON.stringify({vehicles: newVehicles, records: newRecords})).catch(() => {});
    } catch (error) {
      console.error('Error loading fuel records:', error);
    } finally {
      setLoading(false);
    }
  }, [api, selectedVehicleId]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadData();
    setRefreshing(false);
  }, [loadData]);

  const getVehicleLabel = (vehicleId: number) => {
    const vehicle = vehicles.find(v => v.id === vehicleId);
    return vehicle?.name || vehicle?.registration || 'Unknown';
  };

  const userUnit = preferences.distanceUnit === 'km' ? 'km' : 'mi' as const;

  const renderRecord = ({item}: {item: FuelRecord}) => (
    <Card
      style={styles.recordCard}
      onPress={() => navigation.navigate('FuelRecordForm', {recordId: item.id, vehicleId: item.vehicleId})}>
      <Card.Content>
        <View style={styles.recordHeader}>
          <View>
            <Text variant="titleMedium">{formatDate(item.date)}</Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
              {getVehicleLabel(item.vehicleId)}
            </Text>
          </View>
          <Text variant="titleLarge" style={{color: theme.colors.primary}}>
            {formatCurrency(item.cost, preferences.currency)}
          </Text>
        </View>
        
        <View style={styles.recordDetails}>
          {item.litres && (
            <Chip icon="water" compact style={styles.chip}>
              {Number(item.litres).toFixed(2)} L
            </Chip>
          )}
          {item.mileage && (
            <Chip icon="speedometer" compact style={styles.chip}>
              {formatMileage(item.mileage, userUnit)}
            </Chip>
          )}
          {item.fuelType && (
            <Chip icon="fuel" compact style={styles.chip}>
              {item.fuelType}
            </Chip>
          )}
          {item.station && (
            <Chip icon="gas-station" compact style={styles.chip}>
              {item.station}
            </Chip>
          )}
        </View>
      </Card.Content>
    </Card>
  );

  if (loading) {
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  return (
    <View style={[styles.container, {backgroundColor: theme.colors.background}]}>
      {!isOnline && (
        <View style={{backgroundColor: theme.colors.errorContainer, padding: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'center'}}>
          <Icon name="cloud-off-outline" size={16} color={theme.colors.onErrorContainer} />
          <Text style={{color: theme.colors.onErrorContainer, marginLeft: 6, fontSize: 13}}>Offline — showing cached data</Text>
        </View>
      )}
      <VehicleSelector
        vehicles={vehicles}
        selectedVehicleId={selectedVehicleId}
        onSelect={setSelectedVehicleId}
        includeAll
      />

      <FlatList
        data={records}
        renderItem={renderRecord}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Icon name="gas-station-off" size={64} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodyLarge" style={[styles.emptyText, {color: theme.colors.onSurfaceVariant}]}>
              No fuel records found
            </Text>
          </View>
        }
      />

      <FAB
        icon="plus"
        style={[styles.fab, {backgroundColor: theme.colors.primary}]}
        onPress={() => navigation.navigate('FuelRecordForm', {
          vehicleId: selectedVehicleId !== 'all' ? selectedVehicleId : vehicles[0]?.id,
        })}
        color={theme.colors.onPrimary}
      />
    </View>
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
  listContent: {
    padding: 16,
  },
  recordCard: {
    marginBottom: 12,
  },
  recordHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  recordDetails: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  chip: {
    marginRight: 4,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 64,
  },
  emptyText: {
    marginTop: 16,
  },
  fab: {
    position: 'absolute',
    right: 16,
    bottom: 16,
  },
});

export default FuelRecordsScreen;
