import React, {useState, useEffect} from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
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
  IconButton,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useAuth} from '../contexts/AuthContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {formatCurrency, formatDate, formatMileage} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'MotRecordDetail'>;

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

const MotRecordDetailScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api} = useAuth();
  const {preferences} = useUserPreferences();

  const {recordId} = route.params;
  const [record, setRecord] = useState<MotRecord | null>(null);
  const [vehicleName, setVehicleName] = useState('');
  const [loading, setLoading] = useState(true);

  const userUnit = preferences.distanceUnit === 'km' ? 'km' : 'mi' as const;

  useEffect(() => {
    loadData();
  }, [recordId]);

  const loadData = async () => {
    try {
      const res = await api.get(`/mot-records/${recordId}`);
      setRecord(res.data);
      if (res.data?.vehicleId) {
        const vRes = await api.get(`/vehicles/${res.data.vehicleId}`).catch(() => null);
        if (vRes?.data) {
          setVehicleName(vRes.data.name || vRes.data.registration || '');
        }
      }
    } catch (error) {
      console.error('Error loading MOT record:', error);
      Alert.alert('Error', 'Failed to load MOT record');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = () => {
    Alert.alert('Delete MOT Record', 'Are you sure you want to delete this MOT record?', [
      {text: 'Cancel', style: 'cancel'},
      {
        text: 'Delete',
        style: 'destructive',
        onPress: async () => {
          try {
            await api.delete(`/mot-records/${recordId}`);
            navigation.goBack();
          } catch (error) {
            Alert.alert('Error', 'Failed to delete MOT record');
          }
        },
      },
    ]);
  };

  if (loading) {
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  if (!record) {
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <Text>Record not found</Text>
      </View>
    );
  }

  const isPassed = record.result?.toLowerCase() === 'pass';
  const resultColor = isPassed ? '#22C55E' : theme.colors.error;
  const resultBg = isPassed ? '#DCFCE7' : theme.colors.errorContainer;

  return (
    <ScrollView style={[styles.container, {backgroundColor: theme.colors.background}]}>
      {/* Result Banner */}
      <View style={[styles.resultBanner, {backgroundColor: resultBg}]}>
        <Icon
          name={isPassed ? 'check-circle' : 'close-circle'}
          size={48}
          color={resultColor}
        />
        <Text variant="headlineMedium" style={[styles.resultTitle, {color: isPassed ? '#166534' : theme.colors.onErrorContainer}]}>
          {record.result || 'Unknown'}
        </Text>
        <Text variant="bodyMedium" style={{color: isPassed ? '#166534' : theme.colors.onErrorContainer}}>
          {vehicleName} • {formatDate(record.testDate)}
          {record.isRetest && ' (Retest)'}
        </Text>
      </View>

      {/* Details */}
      <Card style={styles.card}>
        <Card.Content>
          <Text variant="titleMedium" style={styles.sectionTitle}>Test Details</Text>
          {record.expiryDate && (
            <List.Item
              title="Expiry Date"
              description={formatDate(record.expiryDate)}
              left={props => <List.Icon {...props} icon="calendar-clock" />}
            />
          )}
          {record.mileage != null && (
            <List.Item
              title="Mileage"
              description={formatMileage(record.mileage, userUnit)}
              left={props => <List.Icon {...props} icon="speedometer" />}
            />
          )}
          {record.testCenter && (
            <List.Item
              title="Test Centre"
              description={record.testCenter}
              left={props => <List.Icon {...props} icon="map-marker" />}
            />
          )}
          {record.motTestNumber && (
            <List.Item
              title="MOT Test Number"
              description={record.motTestNumber}
              left={props => <List.Icon {...props} icon="pound" />}
            />
          )}
          {record.testerName && (
            <List.Item
              title="Tester"
              description={record.testerName}
              left={props => <List.Icon {...props} icon="account" />}
            />
          )}
        </Card.Content>
      </Card>

      {/* Costs */}
      <Card style={styles.card}>
        <Card.Content>
          <Text variant="titleMedium" style={styles.sectionTitle}>Costs</Text>
          <View style={styles.costsRow}>
            <View style={styles.costItem}>
              <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>Test Cost</Text>
              <Text variant="titleMedium">{formatCurrency(record.testCost, preferences.currency)}</Text>
            </View>
            <View style={styles.costItem}>
              <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>Repair Cost</Text>
              <Text variant="titleMedium">{formatCurrency(record.repairCost, preferences.currency)}</Text>
            </View>
            <View style={styles.costItem}>
              <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>Total</Text>
              <Text variant="titleMedium" style={{color: theme.colors.primary, fontWeight: 'bold'}}>
                {formatCurrency(record.totalCost, preferences.currency)}
              </Text>
            </View>
          </View>
        </Card.Content>
      </Card>

      {/* Failures */}
      {record.failureItems && record.failureItems.length > 0 && (
        <Card style={styles.card}>
          <Card.Content>
            <Text variant="titleMedium" style={[styles.sectionTitle, {color: theme.colors.error}]}>
              Failures ({record.failureItems.length})
            </Text>
            {record.failureItems.map((item, idx) => (
              <View key={idx} style={styles.defectItem}>
                <Icon name="close-circle" size={18} color={theme.colors.error} />
                <Text variant="bodyMedium" style={{flex: 1, marginLeft: 8}}>
                  {item.text}
                  {item.dangerous && (
                    <Text style={{color: theme.colors.error, fontWeight: 'bold'}}> (Dangerous)</Text>
                  )}
                </Text>
              </View>
            ))}
          </Card.Content>
        </Card>
      )}

      {/* Advisories */}
      {record.advisoryItems && record.advisoryItems.length > 0 && (
        <Card style={styles.card}>
          <Card.Content>
            <Text variant="titleMedium" style={[styles.sectionTitle, {color: '#92400E'}]}>
              Advisories ({record.advisoryItems.length})
            </Text>
            {record.advisoryItems.map((item, idx) => (
              <View key={idx} style={styles.defectItem}>
                <Icon name="alert" size={18} color="#F59E0B" />
                <Text variant="bodyMedium" style={{flex: 1, marginLeft: 8}}>
                  {item.text}
                </Text>
              </View>
            ))}
          </Card.Content>
        </Card>
      )}

      {/* Notes / Repair Details */}
      {(record.notes || record.repairDetails) && (
        <Card style={styles.card}>
          <Card.Content>
            <Text variant="titleMedium" style={styles.sectionTitle}>Notes</Text>
            {record.repairDetails && (
              <Text variant="bodyMedium" style={{marginBottom: 8}}>{record.repairDetails}</Text>
            )}
            {record.notes && (
              <Text variant="bodyMedium">{record.notes}</Text>
            )}
          </Card.Content>
        </Card>
      )}

      {/* Actions */}
      <View style={styles.actions}>
        <Button
          mode="contained"
          icon="pencil"
          onPress={() => navigation.navigate('MotRecordForm', {recordId: record.id, vehicleId: record.vehicleId})}
          style={styles.actionButton}>
          Edit
        </Button>
        <Button
          mode="outlined"
          icon="delete"
          onPress={handleDelete}
          textColor={theme.colors.error}
          style={styles.actionButton}>
          Delete
        </Button>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1},
  loadingContainer: {flex: 1, justifyContent: 'center', alignItems: 'center'},
  resultBanner: {
    alignItems: 'center',
    paddingVertical: 24,
    paddingHorizontal: 16,
  },
  resultTitle: {fontWeight: 'bold', marginTop: 8, marginBottom: 4},
  card: {marginHorizontal: 16, marginTop: 16},
  sectionTitle: {fontWeight: 'bold', marginBottom: 8},
  costsRow: {flexDirection: 'row', justifyContent: 'space-around'},
  costItem: {alignItems: 'center'},
  defectItem: {flexDirection: 'row', alignItems: 'flex-start', marginBottom: 8},
  actions: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 12,
    padding: 16,
    marginBottom: 32,
  },
  actionButton: {flex: 1},
});

export default MotRecordDetailScreen;
