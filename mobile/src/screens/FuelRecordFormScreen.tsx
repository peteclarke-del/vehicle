import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import {
  TextInput,
  Button,
  useTheme,
  Switch,
  Text,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';
import LoadingScreen from '../components/LoadingScreen';
import ReceiptCapture from '../components/ReceiptCapture';
import {useReceiptOcr, OcrResult} from '../hooks/useReceiptOcr';
import {formStyles} from '../theme/sharedStyles';

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

  const handleOcrComplete = useCallback(
    (primaryId: number, ocrData: OcrResult) => {
      const updates: Partial<FormData> = {};
      if (ocrData.date && !formData.date) updates.date = ocrData.date;
      if (ocrData.totalCost) updates.cost = ocrData.totalCost.toString();
      if (ocrData.litres) updates.litres = ocrData.litres.toString();
      if (ocrData.mileage) updates.mileage = ocrData.mileage.toString();
      if (ocrData.station) updates.station = ocrData.station;
      if (ocrData.fuelType) updates.fuelType = ocrData.fuelType;
      if (Object.keys(updates).length > 0) {
        setFormData(prev => ({...prev, ...updates}));
      }
    },
    [formData.date],
  );

  const {
    attachments: receiptAttachments,
    uploading: receiptUploading,
    scanning: receiptScanning,
    scanned: receiptScanned,
    ocrResult,
    receiptAttachmentId,
    handleTakePhoto,
    handleChooseFromGallery,
    handleScanAll,
    removeAttachment,
    clearAll: clearReceipt,
    setExistingReceipt,
  } = useReceiptOcr({
    api,
    isOnline,
    vehicleId: formData.vehicleId,
    entityType: 'fuel',
    onOcrComplete: handleOcrComplete,
  });

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
          setExistingReceipt(record.receiptAttachmentId);
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
    return <LoadingScreen />;
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={formStyles.container}>
      <ScrollView
        style={[formStyles.scrollView, {backgroundColor: theme.colors.background}]}
        contentContainerStyle={formStyles.content}
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
          style={formStyles.input}
        />

        <View style={formStyles.row}>
          <TextInput
            label="Cost (£)"
            value={formData.cost}
            onChangeText={v => updateField('cost', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Litres"
            value={formData.litres}
            onChangeText={v => updateField('litres', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <TextInput
          label="Mileage"
          value={formData.mileage}
          onChangeText={v => updateField('mileage', v)}
          mode="outlined"
          keyboardType="numeric"
          style={formStyles.input}
        />

        <View style={formStyles.row}>
          <TextInput
            label="Station"
            value={formData.station}
            onChangeText={v => updateField('station', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Fuel Type"
            value={formData.fuelType}
            onChangeText={v => updateField('fuelType', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <View style={formStyles.switchRow}>
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
          style={formStyles.input}
        />

        {/* Receipt Section with OCR */}
        <ReceiptCapture
          attachments={receiptAttachments}
          uploading={receiptUploading}
          scanning={receiptScanning}
          scanned={receiptScanned}
          ocrResult={ocrResult}
          onTakePhoto={handleTakePhoto}
          onChooseGallery={handleChooseFromGallery}
          onScanAll={handleScanAll}
          onRemoveAttachment={removeAttachment}
          onClearAll={clearReceipt}
        />

        <Button
          mode="contained"
          onPress={handleSave}
          loading={saving}
          disabled={saving}
          style={formStyles.saveButton}>
          {isEditing ? 'Update Record' : 'Add Record'}
        </Button>

        {isEditing && (
          <Button
            mode="outlined"
            onPress={handleDelete}
            textColor={theme.colors.error}
            style={formStyles.deleteButton}>
            Delete Record
          </Button>
        )}

        <View style={formStyles.bottomPadding} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

export default FuelRecordFormScreen;
