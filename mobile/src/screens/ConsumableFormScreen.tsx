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
  Switch,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';
import LoadingScreen from '../components/LoadingScreen';
import OfflineBanner from '../components/OfflineBanner';
import ReceiptCapture from '../components/ReceiptCapture';
import {useReceiptOcr, OcrResult} from '../hooks/useReceiptOcr';
import {formStyles} from '../theme/sharedStyles';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'ConsumableForm'>;

interface FormData {
  vehicleId: number | null;
  description: string;
  brand: string;
  partNumber: string;
  cost: string;
  quantity: string;
  supplier: string;
  lastChanged: string;
  mileageAtChange: string;
  replacementIntervalMiles: string;
  notes: string;
  productUrl: string;
  includedInServiceCost: boolean;
}

const initialFormData: FormData = {
  vehicleId: null,
  description: '',
  brand: '',
  partNumber: '',
  cost: '',
  quantity: '1',
  supplier: '',
  lastChanged: '',
  mileageAtChange: '',
  replacementIntervalMiles: '',
  notes: '',
  productUrl: '',
  includedInServiceCost: false,
};

const ConsumableFormScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api} = useAuth();
  const {isOnline, addPendingChange} = useSync();

  const consumableId = route.params?.consumableId;
  const initialVehicleId = route.params?.vehicleId;
  const isEditing = !!consumableId;

  const [formData, setFormData] = useState<FormData>({
    ...initialFormData,
    vehicleId: initialVehicleId || null,
  });
  const [vehicles, setVehicles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const handleOcrComplete = useCallback(
    (primaryId: number, ocrData: OcrResult) => {
      console.log('[OCR] Consumable auto-fill data:', ocrData);
      const updates: Partial<FormData> = {};
      if (ocrData.name) updates.description = ocrData.name;
      if (ocrData.partNumber) updates.partNumber = ocrData.partNumber;
      if (ocrData.price) updates.cost = ocrData.price.toString();
      if (ocrData.supplier) updates.supplier = ocrData.supplier;
      if (ocrData.quantity) updates.quantity = ocrData.quantity.toString();
      if (ocrData.manufacturer) updates.brand = ocrData.manufacturer;
      if (ocrData.date) updates.lastChanged = ocrData.date;
      if (Object.keys(updates).length > 0) {
        setFormData(prev => ({...prev, ...updates}));
      }
    },
    [],
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
    entityType: 'consumable',
    entityId: consumableId || null,
    onOcrComplete: handleOcrComplete,
  });

  useEffect(() => {
    loadData();
  }, [consumableId]);

  const loadData = async () => {
    try {
      const vehiclesRes = await api.get('/vehicles');
      setVehicles(Array.isArray(vehiclesRes.data) ? vehiclesRes.data : []);

      if (isEditing) {
        const res = await api.get(`/consumables/${consumableId}`);
        const c = res.data;
        setFormData({
          vehicleId: c.vehicleId || null,
          description: c.description || '',
          brand: c.brand || '',
          partNumber: c.partNumber || '',
          cost: c.cost?.toString() || '',
          quantity: c.quantity?.toString() || '1',
          supplier: c.supplier || '',
          lastChanged: c.lastChanged || '',
          mileageAtChange: c.mileageAtChange?.toString() || '',
          replacementIntervalMiles: c.replacementIntervalMiles?.toString() || '',
          notes: c.notes || '',
          productUrl: c.productUrl || '',
          includedInServiceCost: c.includedInServiceCost || false,
        });
        if (c.receiptAttachmentId) {
          setExistingReceipt(c.receiptAttachmentId);
        }
      }
    } catch (error) {
      console.error('Error loading data:', error);
      Alert.alert('Error', 'Failed to load data');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.description.trim()) {
      Alert.alert('Validation Error', 'Description is required');
      return;
    }

    setSaving(true);

    const payload = {
      vehicleId: formData.vehicleId,
      description: formData.description.trim(),
      brand: formData.brand.trim() || null,
      partNumber: formData.partNumber.trim() || null,
      cost: formData.cost ? parseFloat(formData.cost) : null,
      quantity: parseFloat(formData.quantity) || 1,
      supplier: formData.supplier.trim() || null,
      lastChanged: formData.lastChanged || null,
      mileageAtChange: formData.mileageAtChange ? parseInt(formData.mileageAtChange, 10) : null,
      replacementIntervalMiles: formData.replacementIntervalMiles
        ? parseInt(formData.replacementIntervalMiles, 10)
        : null,
      notes: formData.notes.trim() || null,
      productUrl: formData.productUrl.trim() || null,
      includedInServiceCost: formData.includedInServiceCost,
      receiptAttachmentId: receiptAttachmentId,
    };

    try {
      if (isOnline) {
        if (isEditing) {
          await api.put(`/consumables/${consumableId}`, payload);
        } else {
          await api.post('/consumables', payload);
        }
      } else {
        await addPendingChange({
          type: isEditing ? 'update' : 'create',
          entityType: 'consumable',
          entityId: isEditing ? consumableId : undefined,
          data: payload,
        });
        Alert.alert('Saved Offline', 'Your changes will be synced when you\'re back online.');
      }
      navigation.goBack();
    } catch (error: any) {
      // If network error, queue offline
      if (!error.response) {
        await addPendingChange({
          type: isEditing ? 'update' : 'create',
          entityType: 'consumable',
          entityId: isEditing ? consumableId : undefined,
          data: payload,
        });
        Alert.alert('Saved Offline', 'Connection lost. Your changes will be synced when you\'re back online.');
        navigation.goBack();
        return;
      }
      const message = error.response?.data?.error || 'Failed to save consumable';
      Alert.alert('Error', message);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = () => {
    Alert.alert(
      'Delete Consumable',
      'Are you sure you want to delete this consumable?',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              if (isOnline) {
                await api.delete(`/consumables/${consumableId}`);
              } else {
                await addPendingChange({
                  type: 'delete',
                  entityType: 'consumable',
                  entityId: consumableId,
                  data: null,
                });
              }
              navigation.goBack();
            } catch (error) {
              Alert.alert('Error', 'Failed to delete consumable');
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

        {!isOnline && <OfflineBanner message="Offline - changes will be synced later" />}

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Consumable Details</Text>

        <TextInput
          label="Description *"
          value={formData.description}
          onChangeText={v => updateField('description', v)}
          mode="outlined"
          style={formStyles.input}
        />

        <View style={formStyles.row}>
          <TextInput
            label="Brand"
            value={formData.brand}
            onChangeText={v => updateField('brand', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Part Number"
            value={formData.partNumber}
            onChangeText={v => updateField('partNumber', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

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
            label="Quantity"
            value={formData.quantity}
            onChangeText={v => updateField('quantity', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <TextInput
          label="Supplier"
          value={formData.supplier}
          onChangeText={v => updateField('supplier', v)}
          mode="outlined"
          style={formStyles.input}
        />

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Replacement Tracking</Text>

        <TextInput
          label="Last Changed"
          value={formData.lastChanged}
          onChangeText={v => updateField('lastChanged', v)}
          mode="outlined"
          placeholder="YYYY-MM-DD"
          style={formStyles.input}
        />

        <View style={formStyles.row}>
          <TextInput
            label="Mileage at Change"
            value={formData.mileageAtChange}
            onChangeText={v => updateField('mileageAtChange', v)}
            mode="outlined"
            keyboardType="numeric"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Replacement Interval (mi)"
            value={formData.replacementIntervalMiles}
            onChangeText={v => updateField('replacementIntervalMiles', v)}
            mode="outlined"
            keyboardType="numeric"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Vehicle Assignment</Text>

        <VehicleSelector
          vehicles={vehicles}
          selectedVehicleId={formData.vehicleId || 'all'}
          onSelect={(id) => updateField('vehicleId', id === 'all' ? null : id)}
          includeAll
          allLabel="General (No specific vehicle)"
        />

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Additional Info</Text>

        <TextInput
          label="Product URL"
          value={formData.productUrl}
          onChangeText={v => updateField('productUrl', v)}
          mode="outlined"
          keyboardType="url"
          style={formStyles.input}
        />

        <TextInput
          label="Notes"
          value={formData.notes}
          onChangeText={v => updateField('notes', v)}
          mode="outlined"
          multiline
          numberOfLines={3}
          style={formStyles.input}
        />

        <View style={formStyles.switchRow}>
          <Text variant="bodyLarge">Included in Service Cost</Text>
          <Switch
            value={formData.includedInServiceCost}
            onValueChange={v => updateField('includedInServiceCost', v)}
          />
        </View>

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
          {isEditing ? 'Update Consumable' : 'Add Consumable'}
        </Button>

        {isEditing && (
          <Button
            mode="outlined"
            onPress={handleDelete}
            textColor={theme.colors.error}
            style={formStyles.deleteButton}>
            Delete Consumable
          </Button>
        )}

        <View style={formStyles.bottomPadding} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

export default ConsumableFormScreen;
