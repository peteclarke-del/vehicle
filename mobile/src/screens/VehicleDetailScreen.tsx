import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  RefreshControl,
  Alert,
} from 'react-native';
import {
  Text,
  Card,
  Button,
  useTheme,
  ActivityIndicator,
  Chip,
  Divider,
  List,
  Menu,
  IconButton,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useAuth} from '../contexts/AuthContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {formatCurrency, formatDate} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'VehicleDetail'>;

interface Vehicle {
  id: number;
  registration: string;
  name: string | null;
  make: string | null;
  model: string | null;
  variant: string | null;
  year: number | null;
  colour: string | null;
  vin: string | null;
  engineSize: string | null;
  transmission: string | null;
  doors: number | null;
  bodyType: string | null;
  fuelType: string | null;
  currentMileage: number | null;
  purchaseDate: string | null;
  purchaseCost: number | null;
  purchaseMileage: number | null;
  sornStatus: boolean;
  roadTaxExempt: boolean;
  motExempt: boolean;
  notes: string | null;
  status: string;
}

interface VehicleCosts {
  purchaseCost: number;
  fuelCost: number;
  serviceCost: number;
  partsCost: number;
  consumablesCost: number;
  totalRunningCost: number;
  totalCostToDate: number;
  costPerMile: number | null;
}

const VehicleDetailScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api} = useAuth();
  const {preferences} = useUserPreferences();
  
  const {vehicleId} = route.params;
  
  const [vehicle, setVehicle] = useState<Vehicle | null>(null);
  const [costs, setCosts] = useState<VehicleCosts | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [menuVisible, setMenuVisible] = useState(false);

  const loadVehicle = useCallback(async () => {
    try {
      const [vehicleRes, costsRes] = await Promise.all([
        api.get(`/vehicles/${vehicleId}`),
        api.get(`/vehicles/${vehicleId}/costs`).catch(() => ({data: null})),
      ]);
      
      setVehicle(vehicleRes.data);
      if (costsRes.data) {
        setCosts(costsRes.data);
      }
    } catch (error) {
      console.error('Error loading vehicle:', error);
      Alert.alert('Error', 'Failed to load vehicle details');
    } finally {
      setLoading(false);
    }
  }, [api, vehicleId]);

  useEffect(() => {
    loadVehicle();
  }, [loadVehicle]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadVehicle();
    setRefreshing(false);
  }, [loadVehicle]);

  const handleDelete = () => {
    Alert.alert(
      'Delete Vehicle',
      'Are you sure you want to delete this vehicle? This will also delete all associated records.',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.delete(`/vehicles/${vehicleId}`);
              navigation.goBack();
            } catch (error) {
              Alert.alert('Error', 'Failed to delete vehicle');
            }
          },
        },
      ],
    );
  };

  const distanceUnit = preferences.distanceUnit === 'km' ? 'km' : 'miles';

  if (loading) {
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  if (!vehicle) {
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <Text>Vehicle not found</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={[styles.container, {backgroundColor: theme.colors.background}]}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Header Card */}
      <Card style={styles.headerCard}>
        <Card.Content>
          <View style={styles.headerRow}>
            <View style={styles.headerInfo}>
              <Text variant="headlineSmall">{vehicle.name || vehicle.registration}</Text>
              <Text variant="titleMedium" style={{color: theme.colors.onSurfaceVariant}}>
                {`${vehicle.make || ''} ${vehicle.model || ''} ${vehicle.variant || ''}`.trim() || 'Unknown'}
              </Text>
              {vehicle.year && (
                <Text variant="bodyMedium" style={{color: theme.colors.onSurfaceVariant}}>
                  {vehicle.year}
                </Text>
              )}
            </View>
            <Menu
              visible={menuVisible}
              onDismiss={() => setMenuVisible(false)}
              anchor={
                <IconButton
                  icon="dots-vertical"
                  onPress={() => setMenuVisible(true)}
                />
              }>
              <Menu.Item
                onPress={() => {
                  setMenuVisible(false);
                  navigation.navigate('VehicleForm', {vehicleId: vehicle.id});
                }}
                title="Edit"
                leadingIcon="pencil"
              />
              <Divider />
              <Menu.Item
                onPress={() => {
                  setMenuVisible(false);
                  handleDelete();
                }}
                title="Delete"
                leadingIcon="delete"
              />
            </Menu>
          </View>

          <View style={styles.chipsRow}>
            <Chip icon="card-account-details">{vehicle.registration}</Chip>
            {vehicle.status && <Chip>{vehicle.status}</Chip>}
            {vehicle.sornStatus && <Chip icon="alert">SORN</Chip>}
          </View>
        </Card.Content>
      </Card>

      {/* Quick Actions */}
      <View style={styles.actionsRow}>
        <Button
          mode="contained-tonal"
          icon="gas-station"
          onPress={() => navigation.navigate('FuelRecordForm', {vehicleId: vehicle.id})}
          style={styles.actionButton}>
          Add Fuel
        </Button>
        <Button
          mode="contained-tonal"
          icon="wrench"
          onPress={() => navigation.navigate('ServiceRecordForm', {vehicleId: vehicle.id})}
          style={styles.actionButton}>
          Add Service
        </Button>
      </View>

      {/* Costs Summary */}
      {costs && (
        <Card style={styles.card}>
          <Card.Title title="Costs Summary" />
          <Card.Content>
            <View style={styles.costsGrid}>
              <View style={styles.costItem}>
                <Text variant="labelSmall">Fuel</Text>
                <Text variant="bodyLarge">{formatCurrency(costs.fuelCost, preferences.currency)}</Text>
              </View>
              <View style={styles.costItem}>
                <Text variant="labelSmall">Service</Text>
                <Text variant="bodyLarge">{formatCurrency(costs.serviceCost, preferences.currency)}</Text>
              </View>
              <View style={styles.costItem}>
                <Text variant="labelSmall">Parts</Text>
                <Text variant="bodyLarge">{formatCurrency(costs.partsCost, preferences.currency)}</Text>
              </View>
              <View style={styles.costItem}>
                <Text variant="labelSmall">Total Running</Text>
                <Text variant="titleMedium" style={{color: theme.colors.primary}}>
                  {formatCurrency(costs.totalRunningCost, preferences.currency)}
                </Text>
              </View>
            </View>
            {costs.costPerMile && (
              <Text variant="bodySmall" style={styles.costPerMile}>
                Cost per {distanceUnit}: {formatCurrency(costs.costPerMile, preferences.currency)}
              </Text>
            )}
          </Card.Content>
        </Card>
      )}

      {/* Vehicle Details */}
      <Card style={styles.card}>
        <Card.Title title="Vehicle Details" />
        <Card.Content>
          <List.Item
            title="Current Mileage"
            description={vehicle.currentMileage ? `${vehicle.currentMileage.toLocaleString()} ${distanceUnit}` : 'Not set'}
            left={props => <List.Icon {...props} icon="speedometer" />}
          />
          <Divider />
          <List.Item
            title="Fuel Type"
            description={vehicle.fuelType || 'Not set'}
            left={props => <List.Icon {...props} icon="gas-station" />}
          />
          <Divider />
          <List.Item
            title="Transmission"
            description={vehicle.transmission || 'Not set'}
            left={props => <List.Icon {...props} icon="cog" />}
          />
          <Divider />
          <List.Item
            title="Colour"
            description={vehicle.colour || 'Not set'}
            left={props => <List.Icon {...props} icon="palette" />}
          />
          {vehicle.engineSize && (
            <>
              <Divider />
              <List.Item
                title="Engine Size"
                description={vehicle.engineSize}
                left={props => <List.Icon {...props} icon="engine" />}
              />
            </>
          )}
          {vehicle.vin && (
            <>
              <Divider />
              <List.Item
                title="VIN"
                description={vehicle.vin}
                left={props => <List.Icon {...props} icon="barcode" />}
              />
            </>
          )}
        </Card.Content>
      </Card>

      {/* Purchase Info */}
      {(vehicle.purchaseDate || vehicle.purchaseCost) && (
        <Card style={styles.card}>
          <Card.Title title="Purchase Information" />
          <Card.Content>
            {vehicle.purchaseDate && (
              <>
                <List.Item
                  title="Purchase Date"
                  description={formatDate(vehicle.purchaseDate)}
                  left={props => <List.Icon {...props} icon="calendar" />}
                />
                <Divider />
              </>
            )}
            {vehicle.purchaseCost && (
              <>
                <List.Item
                  title="Purchase Price"
                  description={formatCurrency(vehicle.purchaseCost, preferences.currency)}
                  left={props => <List.Icon {...props} icon="currency-gbp" />}
                />
                <Divider />
              </>
            )}
            {vehicle.purchaseMileage && (
              <List.Item
                title="Mileage at Purchase"
                description={`${vehicle.purchaseMileage.toLocaleString()} ${distanceUnit}`}
                left={props => <List.Icon {...props} icon="counter" />}
              />
            )}
          </Card.Content>
        </Card>
      )}

      {/* Notes */}
      {vehicle.notes && (
        <Card style={styles.card}>
          <Card.Title title="Notes" />
          <Card.Content>
            <Text>{vehicle.notes}</Text>
          </Card.Content>
        </Card>
      )}

      <View style={styles.bottomPadding} />
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
  headerCard: {
    margin: 16,
    marginBottom: 8,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  headerInfo: {
    flex: 1,
  },
  chipsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 16,
  },
  actionsRow: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    gap: 8,
    marginBottom: 8,
  },
  actionButton: {
    flex: 1,
  },
  card: {
    margin: 16,
    marginTop: 8,
  },
  costsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 16,
  },
  costItem: {
    minWidth: '40%',
    flex: 1,
  },
  costPerMile: {
    marginTop: 16,
    textAlign: 'center',
    opacity: 0.7,
  },
  bottomPadding: {
    height: 24,
  },
});

export default VehicleDetailScreen;
