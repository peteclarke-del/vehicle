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
import {formatCurrency, formatDate, formatMileage, convertDistance} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'VehicleDetail'>;

interface Vehicle {
  id: number;
  registration: string;
  registrationNumber: string;
  name: string | null;
  make: string | null;
  model: string | null;
  year: number | null;
  colour: string | null;
  vehicleColor: string | null;
  vin: string | null;
  engineNumber: string | null;
  currentMileage: number | null;
  purchaseDate: string | null;
  purchaseCost: string | null;
  purchaseMileage: number | null;
  lastServiceDate: string | null;
  motExpiryDate: string | null;
  roadTaxExpiryDate: string | null;
  insuranceExpiryDate: string | null;
  isRoadTaxExempt: boolean;
  isMotExempt: boolean;
  roadTaxAnnualCost: string | null;
  serviceIntervalMonths: number | null;
  serviceIntervalMiles: number | null;
  securityFeatures: string | null;
  vehicleType: {id: number; name: string} | null;
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

  const userUnit = preferences.distanceUnit === 'km' ? 'km' : 'mi' as const;
  const distanceLabel = userUnit === 'km' ? 'km' : 'miles';

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

      const v = vehicleRes.data;
      setVehicle(v);

      if (costsRes.data) {
        // API returns: { totalCosts: N, breakdown: { purchaseCost, totalFuelCost, totalPartsCost, totalServiceCost, totalConsumablesCost, totalRunningCost } }
        const breakdown = costsRes.data.breakdown || costsRes.data || {};
        const totalCostToDate = Number(costsRes.data.totalCosts) || 0;
        const totalRunning = Number(breakdown.totalRunningCost) || 0;

        // Calculate cost per unit distance if we have mileage data
        let costPerMile: number | null = null;
        if (v && v.currentMileage && v.purchaseMileage && v.currentMileage > v.purchaseMileage) {
          const distanceDriven = convertDistance(v.currentMileage - v.purchaseMileage, userUnit) || 0;
          if (distanceDriven > 0 && totalRunning > 0) {
            costPerMile = totalRunning / distanceDriven;
          }
        }

        setCosts({
          purchaseCost: Number(breakdown.purchaseCost) || 0,
          fuelCost: Number(breakdown.totalFuelCost) || 0,
          serviceCost: Number(breakdown.totalServiceCost) || 0,
          partsCost: Number(breakdown.totalPartsCost) || 0,
          consumablesCost: Number(breakdown.totalConsumablesCost) || 0,
          totalRunningCost: totalRunning,
          totalCostToDate,
          costPerMile,
        });
      }
    } catch (error) {
      console.error('Error loading vehicle:', error);
      Alert.alert('Error', 'Failed to load vehicle details');
    } finally {
      setLoading(false);
    }
  }, [api, vehicleId, userUnit]);

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

  /**
   * Helper to check if a date is soon/overdue and return a status indicator
   */
  const getDateStatus = (dateStr: string | null): {color: string; label: string} | null => {
    if (!dateStr) return null;
    const target = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    target.setHours(0, 0, 0, 0);
    const days = Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

    if (days < 0) return {color: theme.colors.error, label: `Expired ${Math.abs(days)}d ago`};
    if (days <= 30) return {color: '#F59E0B', label: `${days}d remaining`};
    if (days <= 60) return {color: theme.colors.primary, label: `${days}d remaining`};
    return null;
  };

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

  const motStatus = getDateStatus(vehicle.motExpiryDate);
  const insuranceStatus = getDateStatus(vehicle.insuranceExpiryDate);
  const taxStatus = getDateStatus(vehicle.roadTaxExpiryDate);

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
                {`${vehicle.make || ''} ${vehicle.model || ''}`.trim() || 'Unknown'}
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
            {vehicle.vehicleType && <Chip icon="shape">{vehicle.vehicleType.name}</Chip>}
            {vehicle.status && <Chip>{vehicle.status}</Chip>}
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
                <Text variant="labelSmall" style={{color: theme.colors.onSurfaceVariant}}>Fuel</Text>
                <Text variant="bodyLarge">{formatCurrency(costs.fuelCost, preferences.currency)}</Text>
              </View>
              <View style={styles.costItem}>
                <Text variant="labelSmall" style={{color: theme.colors.onSurfaceVariant}}>Service</Text>
                <Text variant="bodyLarge">{formatCurrency(costs.serviceCost, preferences.currency)}</Text>
              </View>
              <View style={styles.costItem}>
                <Text variant="labelSmall" style={{color: theme.colors.onSurfaceVariant}}>Parts</Text>
                <Text variant="bodyLarge">{formatCurrency(costs.partsCost, preferences.currency)}</Text>
              </View>
              <View style={styles.costItem}>
                <Text variant="labelSmall" style={{color: theme.colors.onSurfaceVariant}}>Consumables</Text>
                <Text variant="bodyLarge">{formatCurrency(costs.consumablesCost, preferences.currency)}</Text>
              </View>
            </View>
            <Divider style={{marginVertical: 12}} />
            <View style={styles.costTotalsRow}>
              <View style={styles.costTotalItem}>
                <Text variant="labelSmall" style={{color: theme.colors.onSurfaceVariant}}>Running Costs</Text>
                <Text variant="titleMedium" style={{color: theme.colors.primary}}>
                  {formatCurrency(costs.totalRunningCost, preferences.currency)}
                </Text>
              </View>
              <View style={styles.costTotalItem}>
                <Text variant="labelSmall" style={{color: theme.colors.onSurfaceVariant}}>Total (inc. purchase)</Text>
                <Text variant="titleMedium" style={{fontWeight: 'bold'}}>
                  {formatCurrency(costs.totalCostToDate, preferences.currency)}
                </Text>
              </View>
            </View>
            {costs.costPerMile !== null && (
              <Text variant="bodySmall" style={styles.costPerMile}>
                Cost per {userUnit === 'km' ? 'km' : 'mile'}: {formatCurrency(costs.costPerMile, preferences.currency)}
              </Text>
            )}
          </Card.Content>
        </Card>
      )}

      {/* Vehicle Details */}
      <Card style={styles.card}>
        <Card.Title title="Vehicle Details" />
        <Card.Content>
          {vehicle.vehicleType && (
            <>
              <List.Item
                title="Vehicle Type"
                description={vehicle.vehicleType.name}
                left={props => <List.Icon {...props} icon="shape" />}
              />
              <Divider />
            </>
          )}
          <List.Item
            title="Current Mileage"
            description={vehicle.currentMileage ? formatMileage(vehicle.currentMileage, userUnit) : 'Not set'}
            left={props => <List.Icon {...props} icon="speedometer" />}
          />
          <Divider />
          <List.Item
            title="Colour"
            description={vehicle.colour || vehicle.vehicleColor || 'Not set'}
            left={props => <List.Icon {...props} icon="palette" />}
          />
          {vehicle.engineNumber && (
            <>
              <Divider />
              <List.Item
                title="Engine Number"
                description={vehicle.engineNumber}
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
          {vehicle.securityFeatures && (
            <>
              <Divider />
              <List.Item
                title="Security Features"
                description={vehicle.securityFeatures}
                left={props => <List.Icon {...props} icon="shield-lock" />}
              />
            </>
          )}
        </Card.Content>
      </Card>

      {/* Important Dates */}
      <Card style={styles.card}>
        <Card.Title title="Important Dates" />
        <Card.Content>
          <List.Item
            title="MOT Expiry"
            description={
              vehicle.isMotExempt
                ? 'Exempt'
                : vehicle.motExpiryDate
                  ? `${formatDate(vehicle.motExpiryDate)}${motStatus ? ` — ${motStatus.label}` : ''}`
                  : 'Not set'
            }
            left={props => <List.Icon {...props} icon="file-document" />}
            right={() =>
              motStatus ? (
                <View style={[styles.statusDot, {backgroundColor: motStatus.color}]} />
              ) : null
            }
          />
          <Divider />
          <List.Item
            title="Insurance Expiry"
            description={
              vehicle.insuranceExpiryDate
                ? `${formatDate(vehicle.insuranceExpiryDate)}${insuranceStatus ? ` — ${insuranceStatus.label}` : ''}`
                : 'Not set'
            }
            left={props => <List.Icon {...props} icon="shield-car" />}
            right={() =>
              insuranceStatus ? (
                <View style={[styles.statusDot, {backgroundColor: insuranceStatus.color}]} />
              ) : null
            }
          />
          <Divider />
          <List.Item
            title="Road Tax Expiry"
            description={
              vehicle.isRoadTaxExempt
                ? 'Exempt'
                : vehicle.roadTaxExpiryDate
                  ? `${formatDate(vehicle.roadTaxExpiryDate)}${taxStatus ? ` — ${taxStatus.label}` : ''}`
                  : 'Not set'
            }
            left={props => <List.Icon {...props} icon="cash" />}
            right={() =>
              taxStatus ? (
                <View style={[styles.statusDot, {backgroundColor: taxStatus.color}]} />
              ) : null
            }
          />
          <Divider />
          <List.Item
            title="Last Service"
            description={vehicle.lastServiceDate ? formatDate(vehicle.lastServiceDate) : 'Not set'}
            left={props => <List.Icon {...props} icon="wrench" />}
          />
          {vehicle.serviceIntervalMonths && (
            <>
              <Divider />
              <List.Item
                title="Service Interval"
                description={`Every ${vehicle.serviceIntervalMonths} months / ${formatMileage(vehicle.serviceIntervalMiles, userUnit)}`}
                left={props => <List.Icon {...props} icon="calendar-clock" />}
              />
            </>
          )}
          {vehicle.roadTaxAnnualCost && Number(vehicle.roadTaxAnnualCost) > 0 && (
            <>
              <Divider />
              <List.Item
                title="Road Tax (Annual)"
                description={formatCurrency(vehicle.roadTaxAnnualCost, preferences.currency)}
                left={props => <List.Icon {...props} icon="currency-gbp" />}
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
                description={formatMileage(vehicle.purchaseMileage, userUnit)}
                left={props => <List.Icon {...props} icon="counter" />}
              />
            )}
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
  costTotalsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  costTotalItem: {
    flex: 1,
  },
  costPerMile: {
    marginTop: 12,
    textAlign: 'center',
    opacity: 0.7,
  },
  statusDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    alignSelf: 'center',
    marginRight: 16,
  },
  bottomPadding: {
    height: 24,
  },
});

export default VehicleDetailScreen;
