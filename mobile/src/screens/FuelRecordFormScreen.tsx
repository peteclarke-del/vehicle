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
  Switch,
  Text,
  IconButton,
  Card,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {launchCamera, launchImageLibrary} from 'react-native-image-picker';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'FuelRecordForm'>;

interface FormData {
  vehicleId: number | null;
  date: string;
  mileage: string;
  litres: string;
  cost: string;
  station: string;
  fuelType: string;
  fullTank: boolean;
  notes: string;
}

const initialFormData: FormData = {
  vehicleId: null,
  date: new Date().toISOString().split('T')[0],
  mileage: '',
  litres: '',
  cost: '',
  station: '',
  fuelType: '',
  fullTank: true,
  notes: '',
};

const FuelRecordFormScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api} = useAuth();
  const {isOnline, addPendingChange} = useSync();
  
  const recordId = route.params?.recordId;
  const initialVehicleId = route.params?.vehicleId;
  const isEditing = !!recordId;
  
  const [formData, setFormData] = useState<FormData>({
    ...initialFormData,
    vehicleId: initialVehicleId || null,
  });
  const [vehicles, setVehicles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [receiptUri, setReceiptUri] = useState<string | null>(null);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState<number | null>(null);

  useEffect(() => {
    loadData();
  }, [recordId]);

  const loadData = async () => {
    try {
      const vehiclesRes = await api.get('/vehicles');
      setVehicles(vehiclesRes.data || []);

      if (isEditing) {
        const recordRes = await api.get(`/fuel-records/${recordId}`);
        const record = recordRes.data;
        setFormData({
          vehicleId: record.vehicleId,
          date: record.date || '',
          mileage: record.mileage?.toString() || '',
          litres: record.litres?.toString() || '',
          cost: record.cost?.toString() || '',
          station: record.station || '',
          fuelType: record.fuelType || '',
          fullTank: record.fullTank ?? true,
          notes: record.notes || '',
        });
        if (record.receiptAttachmentId) {
          setReceiptAttachmentId(record.receiptAttachmentId);
        }
      } else if (!formData.vehicleId && vehiclesRes.data?.length > 0) {
        setFormData(prev => ({...prev, vehicleId: vehiclesRes.data[0].id}));
      }
    } catch (error) {
      console.error('Error loading data:', error);
      Alert.alert('Error', 'Failed to load data');
    } finally {
      setLoading(false);
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
      Alert.alert('Offline', 'Receipt will be uploaded when online');
      return;
    }

    try {
      const formDataUpload = new FormData();
      formDataUpload.append('file', {
        uri: asset.uri,
        type: asset.type || 'image/jpeg',
        name: asset.fileName || 'receipt.jpg',
      } as any);

      if (formData.vehicleId) {
        formDataUpload.append('vehicleId', formData.vehicleId.toString());
      }

      const response = await api.post('/attachments', formDataUpload, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      setReceiptAttachmentId(response.data.id);
    } catch (error) {
      console.error('Upload error:', error);
      Alert.alert('Error', 'Failed to upload receipt');
    }
  };

  const handleSave = async () => {
    if (!formData.vehicleId) {
      Alert.alert('Validation Error', 'Please select a vehicle');
      return;
    }

    if (!formData.date) {
      Alert.alert('Validation Error', 'Date is required');
      return;
    }

    setSaving(true);

    const payload = {
      vehicleId: formData.vehicleId,
      date: formData.date,
      mileage: formData.mileage ? parseInt(formData.mileage, 10) : null,
      litres: formData.litres ? parseFloat(formData.litres) : null,
      cost: formData.cost ? parseFloat(formData.cost) : null,
      station: formData.station.trim() || null,
      fuelType: formData.fuelType.trim() || null,
      fullTank: formData.fullTank,
      notes: formData.notes.trim() || null,
      receiptAttachmentId: receiptAttachmentId,
    };

    try {
      if (isOnline) {
        if (isEditing) {
          await api.put(`/fuel-records/${recordId}`, payload);
        } else {
          await api.post('/fuel-records', payload);
        }
      } else {
        await addPendingChange({
          type: isEditing ? 'update' : 'create',
          entityType: 'fuelRecord',
          entityId: isEditing ? recordId : undefined,
          data: payload,
        });
      }
      navigation.goBack();
    } catch (error: any) {
      const message = error.response?.data?.error || 'Failed to save fuel record';
      Alert.alert('Error', message);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = () => {
    Alert.alert(
      'Delete Record',
      'Are you sure you want to delete this fuel record?',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.delete(`/fuel-records/${recordId}`);
              navigation.goBack();
            } catch (error) {
              Alert.alert('Error', 'Failed to delete record');
            }
          },
        },
      ],
    );
  };

  const updateField = (field: keyof FormData, value: any) => {
    setFormData(prev => ({...prev, [field]: value}));
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
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={styles.container}>
      <ScrollView
        style={[styles.scrollView, {backgroundColor: theme.colors.background}]}
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled">
        
        <VehicleSelector
          vehicles={vehicles}
          selectedVehicleId={formData.vehicleId || 'all'}
          onSelect={(id) => updateField('vehicleId', id === 'all' ? null : id)}
        />

        <TextInput
          label="Date *"
          value={formData.date}
          onChangeText={v => updateField('date', v)}
          mode="outlined"
          placeholder="YYYY-MM-DD"
          style={styles.input}
        />

        <View style={styles.row}>
          <TextInput
            label="Cost (£)"
            value={formData.cost}
            onChangeText={v => updateField('cost', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[styles.input, styles.halfInput]}
          />
          <TextInput
            label="Litres"
            value={formData.litres}
            onChangeText={v => updateField('litres', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[styles.input, styles.halfInput]}
          />
        </View>

        <TextInput
          label="Mileage"
          value={formData.mileage}
          onChangeText={v => updateField('mileage', v)}
          mode="outlined"
          keyboardType="numeric"
          style={styles.input}
        />

        <View style={styles.row}>
          <TextInput
            label="Station"
            value={formData.station}
            onChangeText={v => updateField('station', v)}
            mode="outlined"
            style={[styles.input, styles.halfInput]}
          />
          <TextInput
            label="Fuel Type"
            value={formData.fuelType}
            onChangeText={v => updateField('fuelType', v)}
            mode="outlined"
            style={[styles.input, styles.halfInput]}
          />
        </View>

        <View style={styles.switchRow}>
          <Text>Full Tank</Text>
          <Switch
            value={formData.fullTank}
            onValueChange={v => updateField('fullTank', v)}
          />
        </View>

        <TextInput
          label="Notes"
          value={formData.notes}
          onChangeText={v => updateField('notes', v)}
          mode="outlined"
          multiline
          numberOfLines={3}
          style={styles.input}
        />

        {/* Receipt Section */}
        <Text variant="titleMedium" style={styles.sectionTitle}>Receipt</Text>
        
        <View style={styles.receiptButtons}>
          <Button
            mode="outlined"
            icon="camera"
            onPress={handleTakePhoto}
            style={styles.receiptButton}>
            Take Photo
          </Button>
          <Button
            mode="outlined"
            icon="image"
            onPress={handleChooseFromGallery}
            style={styles.receiptButton}>
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
          style={styles.saveButton}>
          {isEditing ? 'Update Record' : 'Add Record'}
        </Button>

        {isEditing && (
          <Button
            mode="outlined"
            onPress={handleDelete}
            textColor={theme.colors.error}
            style={styles.deleteButton}>
            Delete Record
          </Button>
        )}

        <View style={styles.bottomPadding} />
      </ScrollView>
    </KeyboardAvoidingView>
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
  scrollView: {
    flex: 1,
  },
  content: {
    padding: 16,
  },
  input: {
    marginBottom: 12,
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  halfInput: {
    flex: 1,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
    paddingHorizontal: 4,
  },
  sectionTitle: {
    marginTop: 16,
    marginBottom: 12,
  },
  receiptButtons: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 16,
  },
  receiptButton: {
    flex: 1,
  },
  receiptPreview: {
    marginBottom: 16,
  },
  receiptImageContainer: {
    position: 'relative',
  },
  receiptImage: {
    width: '100%',
    height: 200,
    borderRadius: 8,
  },
  removeReceiptButton: {
    position: 'absolute',
    top: -8,
    right: -8,
    backgroundColor: 'white',
  },
  saveButton: {
    marginTop: 24,
    paddingVertical: 6,
  },
  deleteButton: {
    marginTop: 12,
  },
  bottomPadding: {
    height: 24,
  },
});

export default FuelRecordFormScreen;
