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
import {useAuth} from '../contexts/AuthContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {formatCurrency, formatDate} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

interface FuelRecord {
  id: number;
  vehicleId: number;
  date: string;
  mileage: number | null;
  litres: number | null;
  cost: number | null;
  station: string | null;
  fuelType: string | null;
  fullTank: boolean;
  mpg: number | null;
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
  const {preferences} = useUserPreferences();
  
  const [records, setRecords] = useState<FuelRecord[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [selectedVehicleId, setSelectedVehicleId] = useState<number | 'all'>('all');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      const [vehiclesRes, recordsRes] = await Promise.all([
        api.get('/vehicles'),
        api.get('/fuel-records', {
          params: selectedVehicleId !== 'all' ? {vehicleId: selectedVehicleId} : {},
        }),
      ]);
      
      setVehicles(vehiclesRes.data || []);
      setRecords(recordsRes.data || []);
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

  const distanceUnit = preferences.distanceUnit === 'km' ? 'km' : 'mi';

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
            {formatCurrency(item.cost || 0, preferences.currency)}
          </Text>
        </View>
        
        <View style={styles.recordDetails}>
          {item.litres && (
            <Chip icon="water" compact style={styles.chip}>
              {item.litres.toFixed(2)} L
            </Chip>
          )}
          {item.mileage && (
            <Chip icon="speedometer" compact style={styles.chip}>
              {item.mileage.toLocaleString()} {distanceUnit}
            </Chip>
          )}
          {item.mpg && (
            <Chip icon="gauge" compact style={styles.chip}>
              {item.mpg.toFixed(1)} mpg
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
