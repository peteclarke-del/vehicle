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
type RouteProps = RouteProp<MainStackParamList, 'ServiceRecordForm'>;

interface FormData {
  vehicleId: number | null;
  serviceDate: string;
  mileage: string;
  serviceType: string;
  workPerformed: string;
  laborCost: string;
  serviceProvider: string;
  nextServiceDate: string;
  nextServiceMileage: string;
  notes: string;
}

const initialFormData: FormData = {
  vehicleId: null,
  serviceDate: new Date().toISOString().split('T')[0],
  mileage: '',
  serviceType: '',
  workPerformed: '',
  laborCost: '',
  serviceProvider: '',
  nextServiceDate: '',
  nextServiceMileage: '',
  notes: '',
};

const SERVICE_TYPES = [
  'Full Service',
  'Interim Service',
  'Oil Change',
  'MOT',
  'Tyres',
  'Brakes',
  'Battery',
  'Air Filter',
  'Spark Plugs',
  'Other',
];

const ServiceRecordFormScreen: React.FC = () => {
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
      console.log('[OCR] Service auto-fill data:', ocrData);
      const updates: Partial<FormData> = {};
      if (ocrData.date && !formData.serviceDate) updates.serviceDate = ocrData.date;
      if (ocrData.serviceType) updates.serviceType = ocrData.serviceType;
      if (ocrData.serviceProvider) updates.serviceProvider = ocrData.serviceProvider;
      if (ocrData.mileage) updates.mileage = ocrData.mileage.toString();
      if (ocrData.totalCost) {
        updates.laborCost = ocrData.totalCost.toString();
      } else if (ocrData.laborCost) {
        updates.laborCost = ocrData.laborCost.toString();
      }
      if (ocrData.workPerformed) updates.workPerformed = ocrData.workPerformed;
      if (Object.keys(updates).length > 0) {
        setFormData(prev => ({...prev, ...updates}));
      }
    },
    [formData.serviceDate],
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
    entityType: 'service',
    entityId: recordId || null,
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
        const recordRes = await api.get(`/service-records/${recordId}`);
        const record = recordRes.data;
        setFormData({
          vehicleId: record.vehicleId,
          serviceDate: record.serviceDate || '',
          mileage: record.mileage?.toString() || '',
          serviceType: record.serviceType || '',
          workPerformed: record.workPerformed || '',
          laborCost: record.laborCost?.toString() || record.totalCost?.toString() || '',
          serviceProvider: record.serviceProvider || '',
          nextServiceDate: record.nextServiceDate || '',
          nextServiceMileage: record.nextServiceMileage?.toString() || '',
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

    if (!formData.serviceDate) {
      Alert.alert('Validation Error', 'Date is required');
      return;
    }

    setSaving(true);

    const payload = {
      vehicleId: formData.vehicleId,
      serviceDate: formData.serviceDate,
      mileage: formData.mileage ? parseInt(formData.mileage, 10) : null,
      serviceType: formData.serviceType.trim() || null,
      workPerformed: formData.workPerformed.trim() || null,
      laborCost: formData.laborCost ? parseFloat(formData.laborCost) : null,
      serviceProvider: formData.serviceProvider.trim() || null,
      nextServiceDate: formData.nextServiceDate || null,
      nextServiceMileage: formData.nextServiceMileage ? parseInt(formData.nextServiceMileage, 10) : null,
      notes: formData.notes.trim() || null,
      receiptAttachmentId: receiptAttachmentId,
    };

    try {
      if (isOnline) {
        if (isEditing) {
          await api.put(`/service-records/${recordId}`, payload);
        } else {
          await api.post('/service-records', payload);
        }
      } else {
        await addPendingChange({
          type: isEditing ? 'update' : 'create',
          entityType: 'serviceRecord',
          entityId: isEditing ? recordId : undefined,
          data: payload,
        });
      }
      navigation.goBack();
    } catch (error: any) {
      const message = error.response?.data?.error || 'Failed to save service record';
      Alert.alert('Error', message);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = () => {
    Alert.alert(
      'Delete Record',
      'Are you sure you want to delete this service record?',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.delete(`/service-records/${recordId}`);
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
          value={formData.serviceDate}
          onChangeText={v => updateField('serviceDate', v)}
          mode="outlined"
          placeholder="YYYY-MM-DD"
          style={formStyles.input}
        />

        <TextInput
          label="Service Type"
          value={formData.serviceType}
          onChangeText={v => updateField('serviceType', v)}
          mode="outlined"
          style={formStyles.input}
        />

        <View style={styles.serviceTypeChips}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            {SERVICE_TYPES.map(type => (
              <Button
                key={type}
                mode={formData.serviceType === type ? 'contained' : 'outlined'}
                onPress={() => updateField('serviceType', type)}
                compact
                style={styles.serviceTypeButton}>
                {type}
              </Button>
            ))}
          </ScrollView>
        </View>

        <TextInput
          label="Description"
          value={formData.workPerformed}
          onChangeText={v => updateField('workPerformed', v)}
          mode="outlined"
          multiline
          numberOfLines={2}
          style={formStyles.input}
        />

        <View style={formStyles.row}>
          <TextInput
            label="Cost (£)"
            value={formData.laborCost}
            onChangeText={v => updateField('laborCost', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Mileage"
            value={formData.mileage}
            onChangeText={v => updateField('mileage', v)}
            mode="outlined"
            keyboardType="numeric"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <TextInput
          label="Garage / Provider"
          value={formData.serviceProvider}
          onChangeText={v => updateField('serviceProvider', v)}
          mode="outlined"
          style={formStyles.input}
        />

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Next Service</Text>

        <View style={formStyles.row}>
          <TextInput
            label="Next Service Date"
            value={formData.nextServiceDate}
            onChangeText={v => updateField('nextServiceDate', v)}
            mode="outlined"
            placeholder="YYYY-MM-DD"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Next Service Mileage"
            value={formData.nextServiceMileage}
            onChangeText={v => updateField('nextServiceMileage', v)}
            mode="outlined"
            keyboardType="numeric"
            style={[formStyles.input, formStyles.halfInput]}
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

import {StyleSheet} from 'react-native';

const styles = StyleSheet.create({
  serviceTypeChips: {
    marginBottom: 16,
    marginTop: -4,
  },
  serviceTypeButton: {
    marginRight: 8,
  },
});

export default ServiceRecordFormScreen;
