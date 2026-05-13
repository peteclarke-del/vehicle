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
  Searchbar,
  Switch,
} from 'react-native-paper';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {formatCurrency, formatDate} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';
import OfflineBanner from '../components/OfflineBanner';
import LoadingScreen from '../components/LoadingScreen';
import EmptyState from '../components/EmptyState';
import {listStyles} from '../theme/sharedStyles';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

type StockItemType = 'part' | 'consumable';

type TypeFilter = 'all' | StockItemType;

interface StockItem {
  id: number;
  vehicleTypeId: number | null;
  vehicleType: string | null;
  itemType: StockItemType;
  category: string;
  quantity: number | string;
  supplier: string | null;
  description: string | null;
  price: string | null;
  notes?: string | null;
  purchaseDate: string | null;
  partNumber: string | null;
  manufacturer: string | null;
  warranty?: string | null;
  updatedAt?: string | null;
}

const StockItemsScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {isOnline} = useSync();
  const {preferences} = useUserPreferences();

  const [items, setItems] = useState<StockItem[]>([]);
  const [filteredItems, setFilteredItems] = useState<StockItem[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [typeFilter, setTypeFilter] = useState<TypeFilter>('all');
  const [inStockOnly, setInStockOnly] = useState(true);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      const cacheKey = `cache_stock_items_${typeFilter}_${inStockOnly ? 'in' : 'all'}`;
      try {
        const cached = await AsyncStorage.getItem(cacheKey);
        if (cached) {
          const parsed = JSON.parse(cached);
          setItems(Array.isArray(parsed.items) ? parsed.items : []);
        }
      } catch {
        // Ignore cache read failures.
      }

      const params: Record<string, string> = {};
      if (typeFilter !== 'all') {
        params.itemType = typeFilter;
      }
      if (inStockOnly) {
        params.inStock = 'true';
      }

      const response = await api.get('/stock-items', {params});
      const newItems = Array.isArray(response.data) ? response.data : [];
      setItems(newItems);

      await AsyncStorage.setItem(cacheKey, JSON.stringify({items: newItems})).catch(() => {});
    } catch (error) {
      console.error('Error loading stock items:', error);
    } finally {
      setLoading(false);
    }
  }, [api, typeFilter, inStockOnly]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  useEffect(() => {
    let filtered = items;

    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(item =>
        item.category?.toLowerCase().includes(query) ||
        item.description?.toLowerCase().includes(query) ||
        item.supplier?.toLowerCase().includes(query) ||
        item.partNumber?.toLowerCase().includes(query) ||
        item.manufacturer?.toLowerCase().includes(query) ||
        item.vehicleType?.toLowerCase().includes(query),
      );
    }

    setFilteredItems(filtered);
  }, [items, searchQuery]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadData();
    setRefreshing(false);
  }, [loadData]);

  const getItemIcon = (itemType: StockItemType) => {
    return itemType === 'part' ? 'package-variant' : 'oil';
  };

  const renderItem = ({item}: {item: StockItem}) => {
    const quantity = Number(item.quantity) || 0;

    return (
      <Card
        style={styles.itemCard}
        onPress={() => navigation.navigate('StockItemForm', {item})}>
        <Card.Content>
          <View style={styles.itemHeader}>
            <View style={styles.headerLeft}>
              <View style={[styles.iconContainer, {backgroundColor: theme.colors.primaryContainer}]}>
                <Icon
                  name={getItemIcon(item.itemType)}
                  size={22}
                  color={theme.colors.primary}
                />
              </View>
              <View style={styles.itemInfo}>
                <Text variant="titleMedium" numberOfLines={2}>
                  {item.description || item.category}
                </Text>
                <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                  {item.itemType === 'part' ? 'Part' : 'Consumable'} • {item.category || 'Uncategorised'}
                </Text>
              </View>
            </View>
            <View style={styles.headerRight}>
              <Text variant="titleMedium" style={{color: theme.colors.primary}}>
                {formatCurrency(item.price || 0, preferences.currency)}
              </Text>
              <View
                style={[
                  styles.quantityBadge,
                  {
                    backgroundColor:
                      quantity > 0 ? theme.colors.secondaryContainer : theme.colors.errorContainer,
                  },
                ]}>
                <Text
                  variant="labelMedium"
                  style={{
                    color: quantity > 0 ? theme.colors.onSecondaryContainer : theme.colors.onErrorContainer,
                  }}>
                  Qty {quantity.toFixed(2)}
                </Text>
              </View>
            </View>
          </View>

          <View style={styles.itemDetails}>
            {item.partNumber && (
              <Chip icon="pound" compact style={styles.chip}>
                {item.partNumber}
              </Chip>
            )}
            {item.manufacturer && (
              <Chip icon="factory" compact style={styles.chip}>
                {item.manufacturer}
              </Chip>
            )}
            {item.supplier && (
              <Chip icon="store" compact style={styles.chip}>
                {item.supplier}
              </Chip>
            )}
            {item.vehicleType && (
              <Chip icon="car-multiple" compact style={styles.chip}>
                {item.vehicleType}
              </Chip>
            )}
            {item.purchaseDate && (
              <Chip icon="calendar" compact style={styles.chip}>
                {formatDate(item.purchaseDate)}
              </Chip>
            )}
          </View>
        </Card.Content>
      </Card>
    );
  };

  if (loading) {
    return <LoadingScreen />;
  }

  return (
    <View style={[listStyles.container, {backgroundColor: theme.colors.background}]}> 
      {!isOnline && <OfflineBanner message="Offline - showing cached stock items" />}

      <Searchbar
        placeholder="Search stock items..."
        onChangeText={setSearchQuery}
        value={searchQuery}
        style={listStyles.searchbar}
      />

      <View style={styles.filtersRow}>
        <View style={styles.typeFilterWrap}>
          <Chip
            selected={typeFilter === 'all'}
            onPress={() => setTypeFilter('all')}
            style={styles.filterChip}>
            All
          </Chip>
          <Chip
            selected={typeFilter === 'part'}
            onPress={() => setTypeFilter('part')}
            style={styles.filterChip}>
            Parts
          </Chip>
          <Chip
            selected={typeFilter === 'consumable'}
            onPress={() => setTypeFilter('consumable')}
            style={styles.filterChip}>
            Consumables
          </Chip>
        </View>
        <View style={styles.inStockToggle}>
          <Text variant="bodySmall">In stock</Text>
          <Switch value={inStockOnly} onValueChange={setInStockOnly} />
        </View>
      </View>

      <FlatList
        data={filteredItems}
        renderItem={renderItem}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContentPadded}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <EmptyState icon="archive" message="No stock items found" />
        }
      />

      <FAB
        icon="plus"
        style={[listStyles.fab, {backgroundColor: theme.colors.primary}]}
        onPress={() => navigation.navigate('StockItemForm')}
        color={theme.colors.onPrimary}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  filtersRow: {
    marginHorizontal: 16,
    marginBottom: 10,
    gap: 8,
  },
  typeFilterWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  filterChip: {
    marginRight: 4,
  },
  inStockToggle: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  listContentPadded: {
    padding: 16,
    paddingTop: 8,
  },
  itemCard: {
    marginBottom: 12,
  },
  itemHeader: {
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
    width: 44,
    height: 44,
    borderRadius: 22,
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
  quantityBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 12,
    marginTop: 4,
  },
  itemDetails: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  chip: {
    marginRight: 4,
  },
});

export default StockItemsScreen;
