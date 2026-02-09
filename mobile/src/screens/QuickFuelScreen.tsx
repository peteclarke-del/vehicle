import React, {useState, useEffect} from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
  Image,
} from 'react-native';
import {
  TextInput,
  Button,
  useTheme,
  Text,
  Switch,
  Card,
  IconButton,
} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {useVehicleSelection} from '../contexts/VehicleSelectionContext';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';
import LoadingScreen from '../components/LoadingScreen';
import {useReceiptPhoto} from '../hooks/useReceiptPhoto';
import {formStyles, receiptStyles} from '../theme/sharedStyles';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

const QuickFuelScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {api} = useAuth();
  const {isOnline, addPendingChange} = useSync();
  const {globalVehicleId, setGlobalVehicleId} = useVehicleSelection();

  const [vehicles, setVehicles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [vehicleId, setVehicleId] = useState<number | null>(
    globalVehicleId !== 'all' ? globalVehicleId : null,
  );
  const [cost, setCost] = useState('');
  const [litres, setLitres] = useState('');
  const [mileage, setMileage] = useState('');
  const [station, setStation] = useState('');
  const [fullTank, setFullTank] = useState(true);

  const {
    receiptUri,
    receiptAttachmentId,
    handleTakePhoto,
    handleChooseFromGallery,
    clearReceipt,
  } = useReceiptPhoto({api, isOnline, vehicleId});

  useEffect(() => {
    loadVehicles();
  }, []);

  const loadVehicles = async () => {
    try {
      const res = await api.get('/vehicles');
      const list = Array.isArray(res.data) ? res.data : [];
      setVehicles(list);
      // If global vehicle is set, pre-populate mileage
      if (globalVehicleId !== 'all') {
        const v = list.find((veh: any) => veh.id === globalVehicleId);
        if (v?.currentMileage) {
          setMileage(v.currentMileage.toString());
        }
      }
    } catch (e) {
      console.error('Error loading vehicles:', e);
    } finally {
      setLoading(false);
    }
  };

  const handleVehicleSelect = (id: number | 'all') => {
    if (id === 'all') {
      setVehicleId(null);
    } else {
      setVehicleId(id);
      setGlobalVehicleId(id);
      const v = vehicles.find(veh => veh.id === id);
      if (v?.currentMileage) {
        setMileage(v.currentMileage.toString());
      }
    }
  };

  const handleSave = async () => {
    if (!vehicleId) {
      Alert.alert('Error', 'Please select a vehicle');
      return;
    }
    if (!cost.trim()) {
      Alert.alert('Error', 'Please enter the fuel cost');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        vehicleId,
        date: new Date().toISOString().split('T')[0],
        cost: parseFloat(cost),
        litres: litres ? parseFloat(litres) : null,
        mileage: mileage ? parseInt(mileage, 10) : null,
        station: station || null,
        fullTank,
        fuelType: vehicles.find(v => v.id === vehicleId)?.fuelType || null,
        notes: null,
        receiptAttachmentId: receiptAttachmentId,
      };

      if (isOnline) {
        await api.post('/fuel-records', payload);
      } else {
        await addPendingChange({
          type: 'create',
          entityType: 'fuelRecord',
          data: payload,
        });
        Alert.alert('Saved Offline', 'Fuel record will sync when back online.');
      }
      navigation.goBack();
    } catch (error) {
      console.error('Error saving fuel record:', error);
      Alert.alert('Error', 'Failed to save fuel record');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <LoadingScreen />;
  }

  return (
    <KeyboardAvoidingView
      style={{flex: 1}}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView style={[styles.container, {backgroundColor: theme.colors.background}]}>
        <Card style={styles.headerCard}>
          <Card.Content style={styles.headerContent}>
            <Icon name="gas-station" size={40} color={theme.colors.primary} />
            <Text variant="titleLarge" style={{fontWeight: 'bold', marginTop: 8}}>
              Quick Fuel Up
            </Text>
            <Text variant="bodyMedium" style={{color: theme.colors.onSurfaceVariant, textAlign: 'center'}}>
              Quickly log a fuel stop with just the essentials
            </Text>
          </Card.Content>
        </Card>

        <View style={styles.form}>
          <VehicleSelector
            vehicles={vehicles}
            selectedVehicleId={vehicleId || 'all'}
            onSelect={handleVehicleSelect}
          />

          <TextInput
            label="Total Cost *"
            value={cost}
            onChangeText={setCost}
            keyboardType="decimal-pad"
            mode="outlined"
            left={<TextInput.Affix text="£" />}
            style={formStyles.input}
            autoFocus
          />

          <TextInput
            label="Litres"
            value={litres}
            onChangeText={setLitres}
            keyboardType="decimal-pad"
            mode="outlined"
            left={<TextInput.Icon icon="water" />}
            style={formStyles.input}
          />

          <TextInput
            label="Mileage"
            value={mileage}
            onChangeText={setMileage}
            keyboardType="numeric"
            mode="outlined"
            left={<TextInput.Icon icon="speedometer" />}
            style={formStyles.input}
          />

          <TextInput
            label="Station"
            value={station}
            onChangeText={setStation}
            mode="outlined"
            left={<TextInput.Icon icon="map-marker" />}
            style={formStyles.input}
          />

          <View style={styles.switchRow}>
            <Text variant="bodyLarge">Full Tank</Text>
            <Switch value={fullTank} onValueChange={setFullTank} />
          </View>

          {/* Receipt Photo */}
          <Text variant="titleSmall" style={{marginBottom: 8, color: theme.colors.onSurfaceVariant}}>
            Attach Receipt
          </Text>
          <View style={receiptStyles.receiptButtons}>
            <Button
              mode="outlined"
              icon="camera"
              onPress={handleTakePhoto}
              style={receiptStyles.receiptButton}
              compact>
              Camera
            </Button>
            <Button
              mode="outlined"
              icon="image"
              onPress={handleChooseFromGallery}
              style={receiptStyles.receiptButton}
              compact>
              Gallery
            </Button>
          </View>

          {receiptUri && (
            <Card style={receiptStyles.receiptPreview}>
              <Card.Content>
                <View style={receiptStyles.receiptImageContainer}>
                  <Image
                    source={{uri: receiptUri}}
                    style={receiptStyles.receiptImage}
                    resizeMode="contain"
                  />
                  <IconButton
                    icon="close"
                    style={receiptStyles.removeReceiptButton}
                    onPress={clearReceipt}
                  />
                </View>
              </Card.Content>
            </Card>
          )}

          <Button
            mode="contained"
            onPress={handleSave}
            loading={saving}
            disabled={saving}
            icon="check"
            style={styles.saveButton}
            contentStyle={{paddingVertical: 6}}>
            Save Fuel Record
          </Button>

          <Button
            mode="text"
            onPress={() => navigation.goBack()}
            style={{marginTop: 8}}>
            Cancel
          </Button>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1},
  headerCard: {margin: 16, marginBottom: 0},
  headerContent: {alignItems: 'center', paddingVertical: 16},
  form: {padding: 16},
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    marginBottom: 8,
  },
  saveButton: {marginTop: 8},
});

export default QuickFuelScreen;
