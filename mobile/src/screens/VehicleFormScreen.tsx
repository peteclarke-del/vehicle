import React, {useState, useEffect} from 'react';
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
  SegmentedButtons,
  Text,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {MainStackParamList} from '../navigation/MainNavigator';
import LoadingScreen from '../components/LoadingScreen';
import {formStyles} from '../theme/sharedStyles';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'VehicleForm'>;

interface VehicleFormData {
  registration: string;
  name: string;
  make: string;
  model: string;
  variant: string;
  year: string;
  colour: string;
  vin: string;
  engineSize: string;
  transmission: string;
  fuelType: string;
  currentMileage: string;
  purchaseDate: string;
  purchaseCost: string;
  purchaseMileage: string;
  notes: string;
  status: string;
}

const initialFormData: VehicleFormData = {
  registration: '',
  name: '',
  make: '',
  model: '',
  variant: '',
  year: '',
  colour: '',
  vin: '',
  engineSize: '',
  transmission: '',
  fuelType: '',
  currentMileage: '',
  purchaseDate: '',
  purchaseCost: '',
  purchaseMileage: '',
  notes: '',
  status: 'Live',
};

const VehicleFormScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api} = useAuth();
  const {isOnline, addPendingChange} = useSync();
  
  const vehicleId = route.params?.vehicleId;
  const isEditing = !!vehicleId;
  
  const [formData, setFormData] = useState<VehicleFormData>(initialFormData);
  const [loading, setLoading] = useState(isEditing);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (isEditing) {
      loadVehicle();
    }
  }, [vehicleId]);

  const loadVehicle = async () => {
    try {
      const response = await api.get(`/vehicles/${vehicleId}`);
      const vehicle = response.data;
      setFormData({
        registration: vehicle.registration || '',
        name: vehicle.name || '',
        make: vehicle.make || '',
        model: vehicle.model || '',
        variant: vehicle.variant || '',
        year: vehicle.year?.toString() || '',
        colour: vehicle.colour || '',
        vin: vehicle.vin || '',
        engineSize: vehicle.engineSize || '',
        transmission: vehicle.transmission || '',
        fuelType: vehicle.fuelType || '',
        currentMileage: vehicle.currentMileage?.toString() || '',
        purchaseDate: vehicle.purchaseDate || '',
        purchaseCost: vehicle.purchaseCost?.toString() || '',
        purchaseMileage: vehicle.purchaseMileage?.toString() || '',
        notes: vehicle.notes || '',
        status: vehicle.status || 'Live',
      });
    } catch (error) {
      console.error('Error loading vehicle:', error);
      Alert.alert('Error', 'Failed to load vehicle');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.registration.trim()) {
      Alert.alert('Validation Error', 'Registration number is required');
      return;
    }

    setSaving(true);

    const payload = {
      registration: formData.registration.trim(),
      name: formData.name.trim() || null,
      make: formData.make.trim() || null,
      model: formData.model.trim() || null,
      variant: formData.variant.trim() || null,
      year: formData.year ? parseInt(formData.year, 10) : null,
      colour: formData.colour.trim() || null,
      vin: formData.vin.trim() || null,
      engineSize: formData.engineSize.trim() || null,
      transmission: formData.transmission.trim() || null,
      fuelType: formData.fuelType.trim() || null,
      currentMileage: formData.currentMileage ? parseInt(formData.currentMileage, 10) : null,
      purchaseDate: formData.purchaseDate || null,
      purchaseCost: formData.purchaseCost ? parseFloat(formData.purchaseCost) : null,
      purchaseMileage: formData.purchaseMileage ? parseInt(formData.purchaseMileage, 10) : null,
      notes: formData.notes.trim() || null,
      status: formData.status,
    };

    try {
      if (isOnline) {
        if (isEditing) {
          await api.put(`/vehicles/${vehicleId}`, payload);
        } else {
          await api.post('/vehicles', payload);
        }
      } else {
        // Queue for sync when offline
        await addPendingChange({
          type: isEditing ? 'update' : 'create',
          entityType: 'vehicle',
          entityId: isEditing ? vehicleId : undefined,
          data: payload,
        });
      }
      navigation.goBack();
    } catch (error: any) {
      const message = error.response?.data?.error || 'Failed to save vehicle';
      Alert.alert('Error', message);
    } finally {
      setSaving(false);
    }
  };

  const updateField = (field: keyof VehicleFormData, value: string) => {
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
        
        <Text variant="titleMedium" style={formStyles.sectionTitle}>Basic Information</Text>
        
        <TextInput
          label="Registration *"
          value={formData.registration}
          onChangeText={v => updateField('registration', v.toUpperCase())}
          mode="outlined"
          autoCapitalize="characters"
          style={formStyles.input}
        />

        <TextInput
          label="Nickname"
          value={formData.name}
          onChangeText={v => updateField('name', v)}
          mode="outlined"
          style={formStyles.input}
        />

        <View style={formStyles.row}>
          <TextInput
            label="Make"
            value={formData.make}
            onChangeText={v => updateField('make', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Model"
            value={formData.model}
            onChangeText={v => updateField('model', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <View style={formStyles.row}>
          <TextInput
            label="Variant"
            value={formData.variant}
            onChangeText={v => updateField('variant', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Year"
            value={formData.year}
            onChangeText={v => updateField('year', v)}
            mode="outlined"
            keyboardType="numeric"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Status</Text>
        
        <SegmentedButtons
          value={formData.status}
          onValueChange={v => updateField('status', v)}
          buttons={[
            {value: 'Live', label: 'Live'},
            {value: 'Sold', label: 'Sold'},
            {value: 'Scrapped', label: 'Scrapped'},
          ]}
          style={styles.segmented}
        />

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Specifications</Text>

        <View style={formStyles.row}>
          <TextInput
            label="Colour"
            value={formData.colour}
            onChangeText={v => updateField('colour', v)}
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

        <View style={formStyles.row}>
          <TextInput
            label="Engine Size"
            value={formData.engineSize}
            onChangeText={v => updateField('engineSize', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Transmission"
            value={formData.transmission}
            onChangeText={v => updateField('transmission', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <TextInput
          label="VIN"
          value={formData.vin}
          onChangeText={v => updateField('vin', v.toUpperCase())}
          mode="outlined"
          autoCapitalize="characters"
          style={formStyles.input}
        />

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Mileage</Text>

        <View style={formStyles.row}>
          <TextInput
            label="Current Mileage"
            value={formData.currentMileage}
            onChangeText={v => updateField('currentMileage', v)}
            mode="outlined"
            keyboardType="numeric"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Purchase Mileage"
            value={formData.purchaseMileage}
            onChangeText={v => updateField('purchaseMileage', v)}
            mode="outlined"
            keyboardType="numeric"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Purchase Details</Text>

        <View style={formStyles.row}>
          <TextInput
            label="Purchase Date"
            value={formData.purchaseDate}
            onChangeText={v => updateField('purchaseDate', v)}
            mode="outlined"
            placeholder="YYYY-MM-DD"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Purchase Price"
            value={formData.purchaseCost}
            onChangeText={v => updateField('purchaseCost', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Notes</Text>

        <TextInput
          label="Notes"
          value={formData.notes}
          onChangeText={v => updateField('notes', v)}
          mode="outlined"
          multiline
          numberOfLines={4}
          style={formStyles.input}
        />

        <Button
          mode="contained"
          onPress={handleSave}
          loading={saving}
          disabled={saving}
          style={formStyles.saveButton}>
          {isEditing ? 'Update Vehicle' : 'Add Vehicle'}
        </Button>

        <View style={formStyles.bottomPadding} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  segmented: {
    marginBottom: 12,
  },
});

export default VehicleFormScreen;
