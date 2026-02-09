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
  Searchbar,
  Banner,
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
import {useOfflineData} from '../hooks/useOfflineData';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

interface Consumable {
  id: number;
  vehicleId: number | null;
  description: string;
  cost: string | null;
  quantity: string | null;
  consumableType: {id: number; name: string; unit?: string} | null;
  brand: string | null;
  partNumber: string | null;
  supplier: string | null;
  lastChanged: string | null;
  mileageAtChange: number | null;
  replacementIntervalMiles: number | null;
  nextReplacementMileage: number | null;
  notes: string | null;
  productUrl: string | null;
  serviceRecordId: number | null;
  receiptAttachmentId: number | null;
  includedInServiceCost: boolean;
  createdAt: string;
}

interface Vehicle {
  id: number;
  registration: string;
  name: string | null;
}

const ConsumablesScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {isOnline} = useSync();
  const {preferences} = useUserPreferences();

  const {globalVehicleId, setGlobalVehicleId} = useVehicleSelection();

  const [consumables, setConsumables] = useState<Consumable[]>([]);
  const [filteredConsumables, setFilteredConsumables] = useState<Consumable[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const selectedVehicleId = globalVehicleId;
  const setSelectedVehicleId = setGlobalVehicleId;
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      // Load from cache first
      const cacheKey = `cache_consumables_${selectedVehicleId}`;
      try {
        const cached = await AsyncStorage.getItem(cacheKey);
        if (cached) {
          const parsed = JSON.parse(cached);
          setVehicles(parsed.vehicles || []);
          setConsumables(parsed.consumables || []);
        }
      } catch (e) { /* cache miss is fine */ }

      const [vehiclesRes, consumablesRes] = await Promise.all([
        api.get('/vehicles'),
        api.get('/consumables', {
          params: selectedVehicleId !== 'all' ? {vehicleId: selectedVehicleId} : {},
        }),
      ]);

      const newVehicles = Array.isArray(vehiclesRes.data) ? vehiclesRes.data : [];
      const newConsumables = Array.isArray(consumablesRes.data) ? consumablesRes.data : [];
      setVehicles(newVehicles);
      setConsumables(newConsumables);

      // Cache the fresh data
      await AsyncStorage.setItem(cacheKey, JSON.stringify({
        vehicles: newVehicles, consumables: newConsumables,
      })).catch(() => {});
    } catch (error) {
      console.error('Error loading consumables:', error);
    } finally {
      setLoading(false);
    }
  }, [api, selectedVehicleId]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  useEffect(() => {
    let filtered = consumables;

    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(item =>
        item.description?.toLowerCase().includes(query) ||
        item.partNumber?.toLowerCase().includes(query) ||
        item.brand?.toLowerCase().includes(query) ||
        item.consumableType?.name?.toLowerCase().includes(query) ||
        item.supplier?.toLowerCase().includes(query),
      );
    }

    setFilteredConsumables(filtered);
  }, [consumables, searchQuery]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadData();
    setRefreshing(false);
  }, [loadData]);

  const getVehicleLabel = (vehicleId: number | null) => {
    if (!vehicleId) return 'General';
    const vehicle = vehicles.find(v => v.id === vehicleId);
    return vehicle?.name || vehicle?.registration || 'Unknown';
  };

  const getTypeIcon = (typeName: string | null | undefined) => {
    const name = (typeName || '').toLowerCase();
    if (name.includes('oil')) return 'oil';
    if (name.includes('filter')) return 'air-filter';
    if (name.includes('brake')) return 'car-brake-abs';
    if (name.includes('tyre') || name.includes('tire')) return 'tire';
    if (name.includes('plug') || name.includes('spark')) return 'flash';
    if (name.includes('battery')) return 'car-battery';
    if (name.includes('coolant') || name.includes('antifreeze')) return 'snowflake-thermometer';
    if (name.includes('wiper')) return 'wiper';
    if (name.includes('chain') || name.includes('belt')) return 'link-variant';
    if (name.includes('bulb') || name.includes('light')) return 'lightbulb';
    if (name.includes('fluid')) return 'water';
    return 'package-variant';
  };

  const userUnit = preferences.distanceUnit === 'km' ? 'km' : 'mi' as const;

  const renderConsumable = ({item}: {item: Consumable}) => {
    const needsReplacement = item.nextReplacementMileage && item.mileageAtChange;
    const isOverdue = false; // Would need current vehicle mileage to determine

    return (
      <Card
        style={styles.card}
        onPress={() => navigation.navigate('ConsumableForm', {consumableId: item.id, vehicleId: item.vehicleId || undefined})}>
        <Card.Content>
          <View style={styles.cardHeader}>
            <View style={styles.headerLeft}>
              <View style={[styles.iconContainer, {backgroundColor: theme.colors.primaryContainer}]}>
                <Icon
                  name={getTypeIcon(item.consumableType?.name)}
                  size={24}
                  color={theme.colors.primary}
                />
              </View>
              <View style={styles.itemInfo}>
                <Text variant="titleMedium" numberOfLines={2}>{item.description || 'Unnamed Consumable'}</Text>
                {item.consumableType && (
                  <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                    {item.consumableType.name}
                  </Text>
                )}
              </View>
            </View>
            <View style={styles.headerRight}>
              <Text variant="titleMedium" style={{color: theme.colors.primary}}>
                {formatCurrency(item.cost || 0, preferences.currency)}
              </Text>
              {item.lastChanged && (
                <Text variant="labelSmall" style={{color: theme.colors.onSurfaceVariant}}>
                  {formatDate(item.lastChanged)}
                </Text>
              )}
            </View>
          </View>

          <View style={styles.details}>
            {item.brand && (
              <Chip icon="factory" compact style={styles.chip}>
                {item.brand}
              </Chip>
            )}
            {item.vehicleId && (
              <Chip icon="motorbike" compact style={styles.chip}>
                {getVehicleLabel(item.vehicleId)}
              </Chip>
            )}
            {item.supplier && (
              <Chip icon="store" compact style={styles.chip}>
                {item.supplier}
              </Chip>
            )}
            {item.mileageAtChange && (
              <Chip icon="speedometer" compact style={styles.chip}>
                {formatMileage(item.mileageAtChange, userUnit)}
              </Chip>
            )}
            {item.nextReplacementMileage && (
              <Chip icon="calendar-clock" compact style={styles.chip}>
                Next: {formatMileage(item.nextReplacementMileage, userUnit)}
              </Chip>
            )}
            {item.partNumber && (
              <Chip icon="barcode" compact style={styles.chip}>
                #{item.partNumber}
              </Chip>
            )}
          </View>
        </Card.Content>
      </Card>
    );
  };

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
        <Banner visible icon="cloud-off-outline" style={{backgroundColor: theme.colors.errorContainer}}>
          <Text style={{color: theme.colors.onErrorContainer}}>
            You're offline. Showing cached data. Changes will sync when reconnected.
          </Text>
        </Banner>
      )}

      <Searchbar
        placeholder="Search consumables..."
        onChangeText={setSearchQuery}
        value={searchQuery}
        style={styles.searchbar}
      />

      <VehicleSelector
        vehicles={vehicles}
        selectedVehicleId={selectedVehicleId}
        onSelect={setSelectedVehicleId}
        includeAll
        allLabel="All Vehicles"
      />

      <FlatList
        data={filteredConsumables}
        renderItem={renderConsumable}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Icon name="package-variant" size={64} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodyLarge" style={[styles.emptyText, {color: theme.colors.onSurfaceVariant}]}>
              No consumables found
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant, marginTop: 4}}>
              Track oils, filters, brake pads, spark plugs and more
            </Text>
          </View>
        }
      />

      <FAB
        icon="plus"
        style={[styles.fab, {backgroundColor: theme.colors.primary}]}
        onPress={() => navigation.navigate('ConsumableForm', {
          vehicleId: selectedVehicleId !== 'all' ? selectedVehicleId : undefined,
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
  searchbar: {
    margin: 16,
    marginBottom: 8,
  },
  listContent: {
    padding: 16,
    paddingTop: 8,
  },
  card: {
    marginBottom: 12,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  itemInfo: {
    flex: 1,
  },
  headerRight: {
    alignItems: 'flex-end',
  },
  details: {
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

export default ConsumablesScreen;
