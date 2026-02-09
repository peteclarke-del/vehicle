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
  ActivityIndicator,
  Text,
  Switch,
  Card,
  IconButton,
} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {launchCamera, launchImageLibrary} from 'react-native-image-picker';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {useVehicleSelection} from '../contexts/VehicleSelectionContext';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';

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
  const [receiptUri, setReceiptUri] = useState<string | null>(null);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState<number | null>(null);

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

  const handleTakePhoto = async () => {
    try {
      const result = await launchCamera({
        mediaType: 'photo',
        quality: 0.8,
        saveToPhotos: false,
      });
      if (result.assets && result.assets[0]?.uri) {
        setReceiptUri(result.assets[0].uri);
        await uploadReceipt(result.assets[0]);
      }
    } catch (error) {
      console.error('Camera error:', error);
      Alert.alert('Error', 'Failed to take photo');
    }
  };

  const handleChooseFromGallery = async () => {
    try {
      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
      });
      if (result.assets && result.assets[0]?.uri) {
        setReceiptUri(result.assets[0].uri);
        await uploadReceipt(result.assets[0]);
      }
    } catch (error) {
      console.error('Gallery error:', error);
      Alert.alert('Error', 'Failed to select image');
    }
  };

  const uploadReceipt = async (asset: any) => {
    if (!isOnline) {
      Alert.alert('Offline', 'Receipt will be uploaded when back online');
      return;
    }
    try {
      const formDataUpload = new FormData();
      formDataUpload.append('file', {
        uri: asset.uri,
        type: asset.type || 'image/jpeg',
        name: asset.fileName || 'receipt.jpg',
      } as any);
      if (vehicleId) {
        formDataUpload.append('vehicleId', vehicleId.toString());
      }
      const response = await api.post('/attachments', formDataUpload, {
        headers: {'Content-Type': 'multipart/form-data'},
      });
      setReceiptAttachmentId(response.data.id);
    } catch (error) {
      console.error('Upload error:', error);
      Alert.alert('Error', 'Failed to upload receipt');
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
        addPendingChange({
          type: 'create',
          endpoint: '/fuel-records',
          method: 'POST',
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
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
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
            style={styles.input}
            autoFocus
          />

          <TextInput
            label="Litres"
            value={litres}
            onChangeText={setLitres}
            keyboardType="decimal-pad"
            mode="outlined"
            left={<TextInput.Icon icon="water" />}
            style={styles.input}
          />

          <TextInput
            label="Mileage"
            value={mileage}
            onChangeText={setMileage}
            keyboardType="numeric"
            mode="outlined"
            left={<TextInput.Icon icon="speedometer" />}
            style={styles.input}
          />

          <TextInput
            label="Station"
            value={station}
            onChangeText={setStation}
            mode="outlined"
            left={<TextInput.Icon icon="map-marker" />}
            style={styles.input}
          />

          <View style={styles.switchRow}>
            <Text variant="bodyLarge">Full Tank</Text>
            <Switch value={fullTank} onValueChange={setFullTank} />
          </View>

          {/* Receipt Photo */}
          <Text variant="titleSmall" style={{marginBottom: 8, color: theme.colors.onSurfaceVariant}}>
            Attach Receipt
          </Text>
          <View style={styles.receiptButtons}>
            <Button
              mode="outlined"
              icon="camera"
              onPress={handleTakePhoto}
              style={styles.receiptButton}
              compact>
              Camera
            </Button>
            <Button
              mode="outlined"
              icon="image"
              onPress={handleChooseFromGallery}
              style={styles.receiptButton}
              compact>
              Gallery
            </Button>
          </View>

          {receiptUri && (
            <Card style={styles.receiptPreview}>
              <Card.Content>
                <View style={styles.receiptImageContainer}>
                  <Image
                    source={{uri: receiptUri}}
                    style={styles.receiptImage}
                    resizeMode="contain"
                  />
                  <IconButton
                    icon="close"
                    style={styles.removeReceiptButton}
                    onPress={() => {
                      setReceiptUri(null);
                      setReceiptAttachmentId(null);
                    }}
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
  loadingContainer: {flex: 1, justifyContent: 'center', alignItems: 'center'},
  headerCard: {margin: 16, marginBottom: 0},
  headerContent: {alignItems: 'center', paddingVertical: 16},
  form: {padding: 16},
  input: {marginBottom: 12},
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    marginBottom: 8,
  },
  saveButton: {marginTop: 8},
  receiptButtons: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  receiptButton: {
    flex: 1,
  },
  receiptPreview: {
    marginBottom: 12,
  },
  receiptImageContainer: {
    position: 'relative',
  },
  receiptImage: {
    width: '100%',
    height: 180,
    borderRadius: 8,
  },
  removeReceiptButton: {
    position: 'absolute',
    top: -8,
    right: -8,
    backgroundColor: 'white',
  },
});

export default QuickFuelScreen;
