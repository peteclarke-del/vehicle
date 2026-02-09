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
  Chip,
} from 'react-native-paper';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {useVehicleSelection} from '../contexts/VehicleSelectionContext';
import {formatCurrency, formatDate, formatMileage, getVehicleLabel} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';
import OfflineBanner from '../components/OfflineBanner';
import LoadingScreen from '../components/LoadingScreen';
import EmptyState from '../components/EmptyState';
import {listStyles} from '../theme/sharedStyles';

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
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      // Try to load from cache first for instant display
      const cacheKey = `cache_fuel_${globalVehicleId}`;
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
          params: globalVehicleId !== 'all' ? {vehicleId: globalVehicleId} : {},
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
  }, [api, globalVehicleId]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadData();
    setRefreshing(false);
  }, [loadData]);

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
              {getVehicleLabel(item.vehicleId, vehicles)}
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
    return <LoadingScreen />;
  }

  return (
    <View style={[listStyles.container, {backgroundColor: theme.colors.background}]}>
      {!isOnline && <OfflineBanner />}
      <VehicleSelector
        vehicles={vehicles}
        selectedVehicleId={globalVehicleId}
        onSelect={setGlobalVehicleId}
        includeAll
      />

      <FlatList
        data={records}
        renderItem={renderRecord}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={listStyles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <EmptyState icon="gas-station-off" message="No fuel records found" />
        }
      />

      <FAB
        icon="plus"
        style={[listStyles.fab, {backgroundColor: theme.colors.primary}]}
        onPress={() => navigation.navigate('FuelRecordForm', {
          vehicleId: globalVehicleId !== 'all' ? globalVehicleId : vehicles[0]?.id,
        })}
        color={theme.colors.onPrimary}
      />
    </View>
  );
};

const styles = StyleSheet.create({
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
});

export default FuelRecordsScreen;
