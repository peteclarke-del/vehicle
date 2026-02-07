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

interface ServiceRecord {
  id: number;
  vehicleId: number;
  date: string;
  mileage: number | null;
  serviceType: string | null;
  description: string | null;
  cost: number | null;
  garage: string | null;
  nextServiceDate: string | null;
  nextServiceMileage: number | null;
}

interface Vehicle {
  id: number;
  registration: string;
  name: string | null;
}

const ServiceRecordsScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {preferences} = useUserPreferences();
  
  const [records, setRecords] = useState<ServiceRecord[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [selectedVehicleId, setSelectedVehicleId] = useState<number | 'all'>('all');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      const [vehiclesRes, recordsRes] = await Promise.all([
        api.get('/vehicles'),
        api.get('/service-records', {
          params: selectedVehicleId !== 'all' ? {vehicleId: selectedVehicleId} : {},
        }),
      ]);
      
      setVehicles(vehiclesRes.data || []);
      setRecords(recordsRes.data || []);
    } catch (error) {
      console.error('Error loading service records:', error);
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

  const getServiceIcon = (serviceType: string | null) => {
    const type = (serviceType || '').toLowerCase();
    if (type.includes('oil')) return 'oil';
    if (type.includes('tyre') || type.includes('tire')) return 'tire';
    if (type.includes('brake')) return 'car-brake-abs';
    if (type.includes('mot')) return 'certificate';
    if (type.includes('full')) return 'car-wrench';
    if (type.includes('interim')) return 'car-cog';
    return 'wrench';
  };

  const distanceUnit = preferences.distanceUnit === 'km' ? 'km' : 'mi';

  const renderRecord = ({item}: {item: ServiceRecord}) => (
    <Card
      style={styles.recordCard}
      onPress={() => navigation.navigate('ServiceRecordForm', {recordId: item.id, vehicleId: item.vehicleId})}>
      <Card.Content>
        <View style={styles.recordHeader}>
          <View style={styles.headerLeft}>
            <Icon
              name={getServiceIcon(item.serviceType)}
              size={24}
              color={theme.colors.primary}
              style={styles.serviceIcon}
            />
            <View>
              <Text variant="titleMedium">
                {item.serviceType || 'Service'}
              </Text>
              <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                {getVehicleLabel(item.vehicleId)} • {formatDate(item.date)}
              </Text>
            </View>
          </View>
          <Text variant="titleLarge" style={{color: theme.colors.primary}}>
            {formatCurrency(item.cost || 0, preferences.currency)}
          </Text>
        </View>

        {item.description && (
          <Text 
            variant="bodyMedium" 
            style={styles.description}
            numberOfLines={2}>
            {item.description}
          </Text>
        )}
        
        <View style={styles.recordDetails}>
          {item.mileage && (
            <Chip icon="speedometer" compact style={styles.chip}>
              {item.mileage.toLocaleString()} {distanceUnit}
            </Chip>
          )}
          {item.garage && (
            <Chip icon="garage" compact style={styles.chip}>
              {item.garage}
            </Chip>
          )}
          {item.nextServiceDate && (
            <Chip icon="calendar-clock" compact style={styles.chip}>
              Next: {formatDate(item.nextServiceDate)}
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
            <Icon name="car-wrench" size={64} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodyLarge" style={[styles.emptyText, {color: theme.colors.onSurfaceVariant}]}>
              No service records found
            </Text>
          </View>
        }
      />

      <FAB
        icon="plus"
        style={[styles.fab, {backgroundColor: theme.colors.primary}]}
        onPress={() => navigation.navigate('ServiceRecordForm', {
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
    marginBottom: 8,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    flex: 1,
  },
  serviceIcon: {
    marginRight: 12,
    marginTop: 2,
  },
  description: {
    marginBottom: 12,
    marginLeft: 36,
  },
  recordDetails: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginLeft: 36,
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

export default ServiceRecordsScreen;
