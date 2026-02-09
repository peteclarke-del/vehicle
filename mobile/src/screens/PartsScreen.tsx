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
} from 'react-native-paper';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {useVehicleSelection} from '../contexts/VehicleSelectionContext';
import {formatCurrency} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

interface Part {
  id: number;
  vehicleId: number | null;
  description: string;
  partNumber: string | null;
  manufacturer: string | null;
  partCategory: {id: number; name: string} | null;
  quantity: number;
  cost: string | null;
  price: string | null;
  supplier: string | null;
  purchaseDate: string | null;
  installationDate: string | null;
  mileageAtInstallation: number | null;
  notes: string | null;
  productUrl: string | null;
}

interface Vehicle {
  id: number;
  registration: string;
  name: string | null;
}

const PartsScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {isOnline} = useSync();
  const {preferences} = useUserPreferences();
  const {globalVehicleId, setGlobalVehicleId} = useVehicleSelection();
  
  const [parts, setParts] = useState<Part[]>([]);
  const [filteredParts, setFilteredParts] = useState<Part[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const selectedVehicleId = globalVehicleId;
  const setSelectedVehicleId = setGlobalVehicleId;
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      // Load from cache first
      const cacheKey = `cache_parts_${selectedVehicleId}`;
      try {
        const cached = await AsyncStorage.getItem(cacheKey);
        if (cached) {
          const parsed = JSON.parse(cached);
          setVehicles(parsed.vehicles || []);
          setParts(parsed.parts || []);
        }
      } catch (e) { /* cache miss is fine */ }

      const [vehiclesRes, partsRes] = await Promise.all([
        api.get('/vehicles'),
        api.get('/parts', {
          params: selectedVehicleId !== 'all' ? {vehicleId: selectedVehicleId} : {},
        }),
      ]);
      
      const newVehicles = Array.isArray(vehiclesRes.data) ? vehiclesRes.data : [];
      const newParts = Array.isArray(partsRes.data) ? partsRes.data : [];
      setVehicles(newVehicles);
      setParts(newParts);

      // Cache the fresh data
      await AsyncStorage.setItem(cacheKey, JSON.stringify({vehicles: newVehicles, parts: newParts})).catch(() => {});
    } catch (error) {
      console.error('Error loading parts:', error);
    } finally {
      setLoading(false);
    }
  }, [api, selectedVehicleId]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  useEffect(() => {
    let filtered = parts;
    
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(part =>
        part.description?.toLowerCase().includes(query) ||
        part.partNumber?.toLowerCase().includes(query) ||
        part.manufacturer?.toLowerCase().includes(query) ||
        part.partCategory?.name?.toLowerCase().includes(query)
      );
    }
    
    setFilteredParts(filtered);
  }, [parts, searchQuery]);

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

  const getCategoryIcon = (category: string | null | undefined) => {
    const cat = (category || '').toLowerCase();
    if (cat.includes('oil') || cat.includes('fluid')) return 'oil';
    if (cat.includes('filter')) return 'air-filter';
    if (cat.includes('brake')) return 'car-brake-abs';
    if (cat.includes('tyre') || cat.includes('tire')) return 'tire';
    if (cat.includes('light') || cat.includes('bulb')) return 'lightbulb';
    if (cat.includes('battery')) return 'car-battery';
    if (cat.includes('wiper')) return 'wiper';
    return 'cog';
  };

  const renderPart = ({item}: {item: Part}) => (
    <Card
      style={styles.partCard}
      onPress={() => navigation.navigate('PartForm', {partId: item.id, vehicleId: item.vehicleId || undefined})}>
      <Card.Content>
        <View style={styles.partHeader}>
          <View style={styles.headerLeft}>
            <View style={[styles.iconContainer, {backgroundColor: theme.colors.primaryContainer}]}>
              <Icon
                name={getCategoryIcon(item.partCategory?.name)}
                size={24}
                color={theme.colors.primary}
              />
            </View>
            <View style={styles.partInfo}>
              <Text variant="titleMedium" numberOfLines={2}>{item.description || 'Unnamed Part'}</Text>
              {item.partNumber && (
                <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                  #{item.partNumber}
                </Text>
              )}
            </View>
          </View>
          <View style={styles.headerRight}>
            <Text variant="titleMedium" style={{color: theme.colors.primary}}>
              {formatCurrency(item.cost || item.price || 0, preferences.currency)}
            </Text>
            {item.quantity > 1 && (
              <View style={[styles.quantityBadge, {backgroundColor: theme.colors.secondaryContainer}]}>
                <Text variant="labelMedium" style={{color: theme.colors.onSecondaryContainer}}>
                  x{item.quantity}
                </Text>
              </View>
            )}
          </View>
        </View>
        
        <View style={styles.partDetails}>
          {item.manufacturer && (
            <Chip icon="factory" compact style={styles.chip}>
              {item.manufacturer}
            </Chip>
          )}
          {item.partCategory && (
            <Chip icon="tag" compact style={styles.chip}>
              {item.partCategory.name}
            </Chip>
          )}
          {item.vehicleId && (
            <Chip icon="car" compact style={styles.chip}>
              {getVehicleLabel(item.vehicleId)}
            </Chip>
          )}
          {item.supplier && (
            <Chip icon="store" compact style={styles.chip}>
              {item.supplier}
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
      <Searchbar
        placeholder="Search parts..."
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
        data={filteredParts}
        renderItem={renderPart}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Icon name="package-variant" size={64} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodyLarge" style={[styles.emptyText, {color: theme.colors.onSurfaceVariant}]}>
              No parts found
            </Text>
          </View>
        }
      />

      <FAB
        icon="plus"
        style={[styles.fab, {backgroundColor: theme.colors.primary}]}
        onPress={() => navigation.navigate('PartForm', {
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
  partCard: {
    marginBottom: 12,
  },
  partHeader: {
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
  partInfo: {
    flex: 1,
  },
  headerRight: {
    alignItems: 'flex-end',
  },
  quantityBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 12,
    marginTop: 4,
  },
  partDetails: {
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

export default PartsScreen;
