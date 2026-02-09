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

interface MotRecord {
  id: number;
  vehicleId: number;
  testDate: string;
  result: string | null;
  expiryDate: string | null;
  motTestNumber: string | null;
  testerName: string | null;
  isRetest: boolean;
  testCost: string | null;
  repairCost: string | null;
  totalCost: string | null;
  mileage: number | null;
  testCenter: string | null;
  advisories: string | null;
  failures: string | null;
  advisoryItems: Array<{text: string; type: string; dangerous: boolean}> | null;
  failureItems: Array<{text: string; type: string; dangerous: boolean}> | null;
  repairDetails: string | null;
  notes: string | null;
  receiptAttachmentId: number | null;
  testCostBundledInService: boolean;
  createdAt: string;
}

interface Vehicle {
  id: number;
  registration: string;
  name: string | null;
}

const MotRecordsScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {isOnline} = useSync();
  const {preferences} = useUserPreferences();
  const {globalVehicleId, setGlobalVehicleId} = useVehicleSelection();

  const [records, setRecords] = useState<MotRecord[]>([]);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const selectedVehicleId = globalVehicleId;
  const setSelectedVehicleId = setGlobalVehicleId;
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      const cacheKey = `cache_mot_${selectedVehicleId}`;
      try {
        const cached = await AsyncStorage.getItem(cacheKey);
        if (cached) {
          const parsed = JSON.parse(cached);
          setVehicles(parsed.vehicles || []);
          setRecords(parsed.records || []);
        }
      } catch (e) { /* cache miss */ }

      const [vehiclesRes, recordsRes] = await Promise.all([
        api.get('/vehicles'),
        api.get('/mot-records', {
          params: selectedVehicleId !== 'all' ? {vehicleId: selectedVehicleId} : {},
        }),
      ]);

      const newVehicles = Array.isArray(vehiclesRes.data) ? vehiclesRes.data : [];
      const newRecords = Array.isArray(recordsRes.data) ? recordsRes.data : [];
      setVehicles(newVehicles);
      setRecords(newRecords);

      await AsyncStorage.setItem(cacheKey, JSON.stringify({vehicles: newVehicles, records: newRecords})).catch(() => {});
    } catch (error) {
      console.error('Error loading MOT records:', error);
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

  const getResultColor = (result: string | null) => {
    switch (result?.toLowerCase()) {
      case 'pass':
        return {bg: '#DCFCE7', text: '#166534', icon: '#22C55E'};
      case 'fail':
        return {bg: theme.colors.errorContainer, text: theme.colors.onErrorContainer, icon: theme.colors.error};
      default:
        return {bg: theme.colors.surfaceVariant, text: theme.colors.onSurfaceVariant, icon: theme.colors.onSurfaceVariant};
    }
  };

  const renderRecord = ({item}: {item: MotRecord}) => {
    const resultColors = getResultColor(item.result);
    const advisoryCount = item.advisoryItems?.length || 0;
    const failureCount = item.failureItems?.length || 0;

    return (
      <Card
        style={styles.recordCard}
        onPress={() => navigation.navigate('MotRecordDetail', {recordId: item.id, vehicleId: item.vehicleId})}>
        <Card.Content>
          <View style={styles.recordHeader}>
            <View style={styles.headerLeft}>
              <View style={[styles.resultBadge, {backgroundColor: resultColors.bg}]}>
                <Icon
                  name={item.result?.toLowerCase() === 'pass' ? 'check-circle' : item.result?.toLowerCase() === 'fail' ? 'close-circle' : 'help-circle'}
                  size={24}
                  color={resultColors.icon}
                />
                <Text style={[styles.resultText, {color: resultColors.text}]}>
                  {item.result || 'Unknown'}
                </Text>
              </View>
              <View style={{marginLeft: 12, flex: 1}}>
                <Text variant="titleMedium">
                  {getVehicleLabel(item.vehicleId)}
                </Text>
                <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                  {formatDate(item.testDate)}
                  {item.isRetest && ' (Retest)'}
                </Text>
              </View>
            </View>
            <Text variant="titleMedium" style={{color: theme.colors.primary}}>
              {formatCurrency(item.totalCost, preferences.currency)}
            </Text>
          </View>

          <View style={styles.recordDetails}>
            {item.mileage != null && (
              <Chip icon="speedometer" compact style={styles.chip}>
                {formatMileage(item.mileage, userUnit)}
              </Chip>
            )}
            {item.testCenter && (
              <Chip icon="map-marker" compact style={styles.chip}>
                {item.testCenter}
              </Chip>
            )}
            {item.expiryDate && (
              <Chip icon="calendar-clock" compact style={styles.chip}>
                Exp: {formatDate(item.expiryDate)}
              </Chip>
            )}
            {item.motTestNumber && (
              <Chip icon="pound" compact style={styles.chip}>
                {item.motTestNumber}
              </Chip>
            )}
          </View>

          {(advisoryCount > 0 || failureCount > 0) && (
            <View style={styles.defectsRow}>
              {failureCount > 0 && (
                <View style={[styles.defectBadge, {backgroundColor: theme.colors.errorContainer}]}>
                  <Icon name="close-circle" size={14} color={theme.colors.error} />
                  <Text variant="labelSmall" style={{color: theme.colors.onErrorContainer, marginLeft: 4}}>
                    {failureCount} failure{failureCount !== 1 ? 's' : ''}
                  </Text>
                </View>
              )}
              {advisoryCount > 0 && (
                <View style={[styles.defectBadge, {backgroundColor: '#FEF3C7'}]}>
                  <Icon name="alert" size={14} color="#F59E0B" />
                  <Text variant="labelSmall" style={{color: '#92400E', marginLeft: 4}}>
                    {advisoryCount} advisor{advisoryCount !== 1 ? 'ies' : 'y'}
                  </Text>
                </View>
              )}
            </View>
          )}
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
            <Icon name="file-document-outline" size={64} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodyLarge" style={[styles.emptyText, {color: theme.colors.onSurfaceVariant}]}>
              No MOT records found
            </Text>
          </View>
        }
      />

      <FAB
        icon="plus"
        style={[styles.fab, {backgroundColor: theme.colors.primary}]}
        onPress={() => navigation.navigate('MotRecordForm', {
          vehicleId: selectedVehicleId !== 'all' ? selectedVehicleId : vehicles[0]?.id,
        })}
        color={theme.colors.onPrimary}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1},
  loadingContainer: {flex: 1, justifyContent: 'center', alignItems: 'center'},
  listContent: {padding: 16},
  recordCard: {marginBottom: 12},
  recordHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  headerLeft: {flexDirection: 'row', alignItems: 'flex-start', flex: 1},
  resultBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  resultText: {fontWeight: 'bold', marginLeft: 4, fontSize: 13},
  recordDetails: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 4,
  },
  chip: {marginRight: 4},
  defectsRow: {flexDirection: 'row', gap: 8, marginTop: 8},
  defectBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  emptyContainer: {alignItems: 'center', justifyContent: 'center', paddingVertical: 64},
  emptyText: {marginTop: 16},
  fab: {position: 'absolute', right: 16, bottom: 16},
});

export default MotRecordsScreen;
