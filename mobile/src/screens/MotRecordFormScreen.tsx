import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  StyleSheet,
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
  Chip,
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
type RouteProps = RouteProp<MainStackParamList, 'MotRecordForm'>;

interface FormData {
  vehicleId: number | null;
  testDate: string;
  result: string;
  expiryDate: string;
  motTestNumber: string;
  testerName: string;
  testCost: string;
  repairCost: string;
  mileage: string;
  testCenter: string;
  isRetest: boolean;
  advisories: string;
  failures: string;
  repairDetails: string;
  notes: string;
  testCostBundledInService: boolean;
}

const initialFormData: FormData = {
  vehicleId: null,
  testDate: new Date().toISOString().split('T')[0],
  result: 'Pass',
  expiryDate: '',
  motTestNumber: '',
  testerName: '',
  testCost: '',
  repairCost: '',
  mileage: '',
  testCenter: '',
  isRetest: false,
  advisories: '',
  failures: '',
  repairDetails: '',
  notes: '',
  testCostBundledInService: false,
};

const MotRecordFormScreen: React.FC = () => {
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
      if (ocrData.date) updates.testDate = ocrData.date;
      if (ocrData.result) updates.result = ocrData.result;
      if (ocrData.totalCost) updates.testCost = ocrData.totalCost.toString();
      if (ocrData.mileage) updates.mileage = ocrData.mileage.toString();
      if (ocrData.testCenter || ocrData.serviceProvider) {
        updates.testCenter = ocrData.testCenter || ocrData.serviceProvider;
      }
      if (ocrData.expiryDate) updates.expiryDate = ocrData.expiryDate;
      if (ocrData.motTestNumber) updates.motTestNumber = ocrData.motTestNumber;
      if (ocrData.advisories) updates.advisories = ocrData.advisories;
      if (ocrData.failures) updates.failures = ocrData.failures;
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
    entityType: 'mot',
    onOcrComplete: handleOcrComplete,
  });

  useEffect(() => {
    loadData();
  }, [recordId]);

  const loadData = async () => {
    try {
      const vehiclesRes = await api.get('/vehicles');
      setVehicles(Array.isArray(vehiclesRes.data) ? vehiclesRes.data : []);

      if (isEditing) {
        const res = await api.get(`/mot-records/${recordId}`);
        const r = res.data;
        setFormData({
          vehicleId: r.vehicleId,
          testDate: r.testDate || '',
          result: r.result || 'Pass',
          expiryDate: r.expiryDate || '',
          motTestNumber: r.motTestNumber || '',
          testerName: r.testerName || '',
          testCost: r.testCost?.toString() || '',
          repairCost: r.repairCost?.toString() || '',
          mileage: r.mileage?.toString() || '',
          testCenter: r.testCenter || '',
          isRetest: r.isRetest || false,
          advisories: r.advisories || '',
          failures: r.failures || '',
          repairDetails: r.repairDetails || '',
          notes: r.notes || '',
          testCostBundledInService: r.testCostBundledInService || false,
        });
        if (r.receiptAttachmentId) {
          setExistingReceipt(r.receiptAttachmentId);
        }
      }
    } catch (error) {
      console.error('Error loading MOT form data:', error);
    } finally {
      setLoading(false);
    }
  };

  const updateField = (field: keyof FormData, value: any) => {
    setFormData(prev => ({...prev, [field]: value}));
  };

  const handleSave = async () => {
    if (!formData.vehicleId) {
      Alert.alert('Error', 'Please select a vehicle');
      return;
    }
    if (!formData.testDate) {
      Alert.alert('Error', 'Please enter a test date');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        vehicleId: formData.vehicleId,
        testDate: formData.testDate,
        result: formData.result,
        expiryDate: formData.expiryDate || null,
        motTestNumber: formData.motTestNumber || null,
        testerName: formData.testerName || null,
        testCost: formData.testCost ? parseFloat(formData.testCost) : null,
        repairCost: formData.repairCost ? parseFloat(formData.repairCost) : null,
        mileage: formData.mileage ? parseInt(formData.mileage, 10) : null,
        testCenter: formData.testCenter || null,
        isRetest: formData.isRetest,
        advisories: formData.advisories || null,
        failures: formData.failures || null,
        repairDetails: formData.repairDetails || null,
        notes: formData.notes || null,
        testCostBundledInService: formData.testCostBundledInService,
        receiptAttachmentId: receiptAttachmentId,
      };

      if (isOnline) {
        if (isEditing) {
          await api.put(`/mot-records/${recordId}`, payload);
        } else {
          await api.post('/mot-records', payload);
        }
      } else {
        await addPendingChange({
          type: isEditing ? 'update' : 'create',
          entityType: 'motRecord',
          entityId: isEditing ? recordId : undefined,
          data: payload,
        });
        Alert.alert('Saved Offline', 'MOT record will sync when back online.');
      }
      navigation.goBack();
    } catch (error) {
      console.error('Error saving MOT record:', error);
      Alert.alert('Error', 'Failed to save MOT record');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = () => {
    if (!isEditing) return;
    Alert.alert('Delete MOT Record', 'Are you sure?', [
      {text: 'Cancel', style: 'cancel'},
      {
        text: 'Delete',
        style: 'destructive',
        onPress: async () => {
          try {
            if (isOnline) {
              await api.delete(`/mot-records/${recordId}`);
            } else {
              await addPendingChange({
                type: 'delete',
                entityType: 'motRecord',
                entityId: recordId,
                data: null,
              });
            }
            navigation.goBack();
          } catch (error) {
            Alert.alert('Error', 'Failed to delete MOT record');
          }
        },
      },
    ]);
  };

  if (loading) {
    return <LoadingScreen />;
  }

  const resultOptions = ['Pass', 'Fail', 'Advisory'];

  return (
    <KeyboardAvoidingView
      style={{flex: 1}}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView style={[styles.container, {backgroundColor: theme.colors.background}]}>
        <View style={styles.form}>
          <VehicleSelector
            vehicles={vehicles}
            selectedVehicleId={formData.vehicleId || 'all'}
            onSelect={(id) => updateField('vehicleId', id === 'all' ? null : id)}
          />

          <Text variant="labelLarge" style={styles.label}>Result</Text>
          <View style={styles.chipRow}>
            {resultOptions.map(opt => (
              <Chip
                key={opt}
                selected={formData.result === opt}
                onPress={() => updateField('result', opt)}
                mode={formData.result === opt ? 'flat' : 'outlined'}
                style={styles.chip}>
                {opt}
              </Chip>
            ))}
          </View>

          <TextInput
            label="Test Date (YYYY-MM-DD)"
            value={formData.testDate}
            onChangeText={v => updateField('testDate', v)}
            mode="outlined"
            style={formStyles.input}
          />

          <TextInput
            label="Expiry Date (YYYY-MM-DD)"
            value={formData.expiryDate}
            onChangeText={v => updateField('expiryDate', v)}
            mode="outlined"
            style={formStyles.input}
          />

          <TextInput
            label="Mileage"
            value={formData.mileage}
            onChangeText={v => updateField('mileage', v)}
            keyboardType="numeric"
            mode="outlined"
            style={formStyles.input}
          />

          <TextInput
            label="Test Cost"
            value={formData.testCost}
            onChangeText={v => updateField('testCost', v)}
            keyboardType="decimal-pad"
            mode="outlined"
            left={<TextInput.Affix text="£" />}
            style={formStyles.input}
          />

          <TextInput
            label="Repair Cost"
            value={formData.repairCost}
            onChangeText={v => updateField('repairCost', v)}
            keyboardType="decimal-pad"
            mode="outlined"
            left={<TextInput.Affix text="£" />}
            style={formStyles.input}
          />

          <TextInput
            label="Test Centre"
            value={formData.testCenter}
            onChangeText={v => updateField('testCenter', v)}
            mode="outlined"
            style={formStyles.input}
          />

          <TextInput
            label="MOT Test Number"
            value={formData.motTestNumber}
            onChangeText={v => updateField('motTestNumber', v)}
            mode="outlined"
            style={formStyles.input}
          />

          <TextInput
            label="Tester Name"
            value={formData.testerName}
            onChangeText={v => updateField('testerName', v)}
            mode="outlined"
            style={formStyles.input}
          />

          <View style={formStyles.switchRow}>
            <Text variant="bodyLarge">Retest</Text>
            <Switch value={formData.isRetest} onValueChange={v => updateField('isRetest', v)} />
          </View>

          <View style={formStyles.switchRow}>
            <Text variant="bodyLarge">Cost included in service</Text>
            <Switch value={formData.testCostBundledInService} onValueChange={v => updateField('testCostBundledInService', v)} />
          </View>

          <TextInput
            label="Advisories"
            value={formData.advisories}
            onChangeText={v => updateField('advisories', v)}
            multiline
            numberOfLines={3}
            mode="outlined"
            style={formStyles.input}
          />

          <TextInput
            label="Failures"
            value={formData.failures}
            onChangeText={v => updateField('failures', v)}
            multiline
            numberOfLines={3}
            mode="outlined"
            style={formStyles.input}
          />

          <TextInput
            label="Repair Details"
            value={formData.repairDetails}
            onChangeText={v => updateField('repairDetails', v)}
            multiline
            numberOfLines={3}
            mode="outlined"
            style={formStyles.input}
          />

          <TextInput
            label="Notes"
            value={formData.notes}
            onChangeText={v => updateField('notes', v)}
            multiline
            numberOfLines={3}
            mode="outlined"
            style={formStyles.input}
          />

          {/* Receipt / MOT Certificate with OCR */}
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
            {isEditing ? 'Update MOT Record' : 'Save MOT Record'}
          </Button>

          {isEditing && (
            <Button
              mode="outlined"
              onPress={handleDelete}
              textColor={theme.colors.error}
              style={formStyles.deleteButton}>
              Delete
            </Button>
          )}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1},
  form: {padding: 16},
  label: {marginTop: 12, marginBottom: 4},
  chipRow: {flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12},
  chip: {marginRight: 4},
});

export default MotRecordFormScreen;
