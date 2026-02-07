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
  Searchbar,
  Chip,
  IconButton,
} from 'react-native-paper';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useAuth} from '../contexts/AuthContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {MainStackParamList} from '../navigation/MainNavigator';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

interface Vehicle {
  id: number;
  registration: string;
  name: string | null;
  make: string | null;
  model: string | null;
  year: number | null;
  colour: string | null;
  fuelType: string | null;
  currentMileage: number | null;
  status: string;
  primaryImage?: {
    url: string;
  };
}

const VehiclesScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {preferences} = useUserPreferences();
  
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [filteredVehicles, setFilteredVehicles] = useState<Vehicle[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('Live');

  const loadVehicles = useCallback(async () => {
    try {
      const response = await api.get('/vehicles');
      setVehicles(response.data || []);
    } catch (error) {
      console.error('Error loading vehicles:', error);
    } finally {
      setLoading(false);
    }
  }, [api]);

  useEffect(() => {
    loadVehicles();
  }, [loadVehicles]);

  useEffect(() => {
    let filtered = vehicles;
    
    // Apply status filter
    if (statusFilter && statusFilter !== 'all') {
      filtered = filtered.filter(v => v.status === statusFilter);
    }
    
    // Apply search filter
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(v => 
        v.registration?.toLowerCase().includes(query) ||
        v.name?.toLowerCase().includes(query) ||
        v.make?.toLowerCase().includes(query) ||
        v.model?.toLowerCase().includes(query)
      );
    }
    
    setFilteredVehicles(filtered);
  }, [vehicles, searchQuery, statusFilter]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadVehicles();
    setRefreshing(false);
  }, [loadVehicles]);

  const distanceUnit = preferences.distanceUnit === 'km' ? 'km' : 'mi';

  const getVehicleIcon = (fuelType: string | null) => {
    switch (fuelType?.toLowerCase()) {
      case 'electric':
        return 'car-electric';
      case 'hybrid':
        return 'car-electric-outline';
      default:
        return 'car';
    }
  };

  const renderVehicle = ({item}: {item: Vehicle}) => (
    <Card
      style={styles.vehicleCard}
      onPress={() => navigation.navigate('VehicleDetail', {vehicleId: item.id})}>
      <Card.Title
        title={item.name || item.registration}
        subtitle={`${item.make || ''} ${item.model || ''} ${item.year || ''}`.trim() || 'Unknown Vehicle'}
        left={props => (
          <Icon
            {...props}
            name={getVehicleIcon(item.fuelType)}
            size={32}
            color={theme.colors.primary}
          />
        )}
        right={props => (
          <IconButton
            {...props}
            icon="chevron-right"
            onPress={() => navigation.navigate('VehicleDetail', {vehicleId: item.id})}
          />
        )}
      />
      <Card.Content>
        <View style={styles.vehicleInfo}>
          <View style={styles.infoItem}>
            <Icon name="card-account-details" size={16} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodySmall" style={styles.infoText}>
              {item.registration}
            </Text>
          </View>
          {item.currentMileage && (
            <View style={styles.infoItem}>
              <Icon name="speedometer" size={16} color={theme.colors.onSurfaceVariant} />
              <Text variant="bodySmall" style={styles.infoText}>
                {item.currentMileage.toLocaleString()} {distanceUnit}
              </Text>
            </View>
          )}
          {item.fuelType && (
            <View style={styles.infoItem}>
              <Icon name="gas-station" size={16} color={theme.colors.onSurfaceVariant} />
              <Text variant="bodySmall" style={styles.infoText}>
                {item.fuelType}
              </Text>
            </View>
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
      <Searchbar
        placeholder="Search vehicles..."
        onChangeText={setSearchQuery}
        value={searchQuery}
        style={styles.searchbar}
      />
      
      <View style={styles.filterRow}>
        {['all', 'Live', 'Sold', 'Scrapped'].map(status => (
          <Chip
            key={status}
            selected={statusFilter === status}
            onPress={() => setStatusFilter(status)}
            style={styles.filterChip}>
            {status === 'all' ? 'All' : status}
          </Chip>
        ))}
      </View>

      <FlatList
        data={filteredVehicles}
        renderItem={renderVehicle}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Icon name="car-off" size={64} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodyLarge" style={[styles.emptyText, {color: theme.colors.onSurfaceVariant}]}>
              {searchQuery ? 'No vehicles match your search' : 'No vehicles found'}
            </Text>
          </View>
        }
      />

      <FAB
        icon="plus"
        style={[styles.fab, {backgroundColor: theme.colors.primary}]}
        onPress={() => navigation.navigate('VehicleForm', {})}
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
  searchbar: {
    margin: 16,
    marginBottom: 8,
  },
  filterRow: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingBottom: 8,
    gap: 8,
  },
  filterChip: {
    marginRight: 4,
  },
  listContent: {
    padding: 16,
    paddingTop: 8,
  },
  vehicleCard: {
    marginBottom: 12,
  },
  vehicleInfo: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 16,
  },
  infoItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  infoText: {
    marginLeft: 4,
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

export default VehiclesScreen;
