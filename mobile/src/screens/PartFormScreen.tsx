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
  ActivityIndicator,
  Text,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {MainStackParamList} from '../navigation/MainNavigator';
import VehicleSelector from '../components/VehicleSelector';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'PartForm'>;

interface FormData {
  vehicleId: number | null;
  name: string;
  partNumber: string;
  manufacturer: string;
  category: string;
  quantity: string;
  cost: string;
  supplier: string;
  purchaseDate: string;
  location: string;
  notes: string;
}

const initialFormData: FormData = {
  vehicleId: null,
  name: '',
  partNumber: '',
  manufacturer: '',
  category: '',
  quantity: '1',
  cost: '',
  supplier: '',
  purchaseDate: '',
  location: '',
  notes: '',
};

const CATEGORIES = [
  'Oils & Fluids',
  'Filters',
  'Brakes',
  'Tyres',
  'Lights & Bulbs',
  'Battery',
  'Wipers',
  'Engine',
  'Suspension',
  'Exhaust',
  'Interior',
  'Exterior',
  'Tools',
  'Other',
];

const PartFormScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api} = useAuth();
  const {isOnline, addPendingChange} = useSync();
  
  const partId = route.params?.partId;
  const initialVehicleId = route.params?.vehicleId;
  const isEditing = !!partId;
  
  const [formData, setFormData] = useState<FormData>({
    ...initialFormData,
    vehicleId: initialVehicleId || null,
  });
  const [vehicles, setVehicles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    loadData();
  }, [partId]);

  const loadData = async () => {
    try {
      const vehiclesRes = await api.get('/vehicles');
      setVehicles(vehiclesRes.data || []);

      if (isEditing) {
        const partRes = await api.get(`/parts/${partId}`);
        const part = partRes.data;
        setFormData({
          vehicleId: part.vehicleId,
          name: part.name || '',
          partNumber: part.partNumber || '',
          manufacturer: part.manufacturer || '',
          category: part.category || '',
          quantity: part.quantity?.toString() || '1',
          cost: part.cost?.toString() || '',
          supplier: part.supplier || '',
          purchaseDate: part.purchaseDate || '',
          location: part.location || '',
          notes: part.notes || '',
        });
      }
    } catch (error) {
      console.error('Error loading data:', error);
      Alert.alert('Error', 'Failed to load data');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      Alert.alert('Validation Error', 'Part name is required');
      return;
    }

    setSaving(true);

    const payload = {
      vehicleId: formData.vehicleId,
      name: formData.name.trim(),
      partNumber: formData.partNumber.trim() || null,
      manufacturer: formData.manufacturer.trim() || null,
      category: formData.category.trim() || null,
      quantity: parseInt(formData.quantity, 10) || 1,
      cost: formData.cost ? parseFloat(formData.cost) : null,
      supplier: formData.supplier.trim() || null,
      purchaseDate: formData.purchaseDate || null,
      location: formData.location.trim() || null,
      notes: formData.notes.trim() || null,
    };

    try {
      if (isOnline) {
        if (isEditing) {
          await api.put(`/parts/${partId}`, payload);
        } else {
          await api.post('/parts', payload);
        }
      } else {
        await addPendingChange({
          type: isEditing ? 'update' : 'create',
          entityType: 'part',
          entityId: isEditing ? partId : undefined,
          data: payload,
        });
      }
      navigation.goBack();
    } catch (error: any) {
      const message = error.response?.data?.error || 'Failed to save part';
      Alert.alert('Error', message);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = () => {
    Alert.alert(
      'Delete Part',
      'Are you sure you want to delete this part?',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.delete(`/parts/${partId}`);
              navigation.goBack();
            } catch (error) {
              Alert.alert('Error', 'Failed to delete part');
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
        
        <Text variant="titleMedium" style={styles.sectionTitle}>Part Details</Text>

        <TextInput
          label="Part Name *"
          value={formData.name}
          onChangeText={v => updateField('name', v)}
          mode="outlined"
          style={styles.input}
        />

        <View style={styles.row}>
          <TextInput
            label="Part Number"
            value={formData.partNumber}
            onChangeText={v => updateField('partNumber', v)}
            mode="outlined"
            style={[styles.input, styles.halfInput]}
          />
          <TextInput
            label="Manufacturer"
            value={formData.manufacturer}
            onChangeText={v => updateField('manufacturer', v)}
            mode="outlined"
            style={[styles.input, styles.halfInput]}
          />
        </View>

        <TextInput
          label="Category"
          value={formData.category}
          onChangeText={v => updateField('category', v)}
          mode="outlined"
          style={styles.input}
        />

        <View style={styles.categoryChips}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            {CATEGORIES.map(cat => (
              <Button
                key={cat}
                mode={formData.category === cat ? 'contained' : 'outlined'}
                onPress={() => updateField('category', cat)}
                compact
                style={styles.categoryButton}>
                {cat}
              </Button>
            ))}
          </ScrollView>
        </View>

        <Text variant="titleMedium" style={styles.sectionTitle}>Inventory</Text>

        <View style={styles.row}>
          <TextInput
            label="Quantity"
            value={formData.quantity}
            onChangeText={v => updateField('quantity', v)}
            mode="outlined"
            keyboardType="numeric"
            style={[styles.input, styles.halfInput]}
          />
          <TextInput
            label="Cost (£)"
            value={formData.cost}
            onChangeText={v => updateField('cost', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[styles.input, styles.halfInput]}
          />
        </View>

        <TextInput
          label="Location (e.g., Garage Shelf 1)"
          value={formData.location}
          onChangeText={v => updateField('location', v)}
          mode="outlined"
          style={styles.input}
        />

        <Text variant="titleMedium" style={styles.sectionTitle}>Purchase Info</Text>

        <TextInput
          label="Supplier"
          value={formData.supplier}
          onChangeText={v => updateField('supplier', v)}
          mode="outlined"
          style={styles.input}
        />

        <TextInput
          label="Purchase Date"
          value={formData.purchaseDate}
          onChangeText={v => updateField('purchaseDate', v)}
          mode="outlined"
          placeholder="YYYY-MM-DD"
          style={styles.input}
        />

        <Text variant="titleMedium" style={styles.sectionTitle}>Vehicle Assignment</Text>

        <VehicleSelector
          vehicles={vehicles}
          selectedVehicleId={formData.vehicleId || 'all'}
          onSelect={(id) => updateField('vehicleId', id === 'all' ? null : id)}
          includeAll
          allLabel="General (No specific vehicle)"
        />

        <Text variant="titleMedium" style={styles.sectionTitle}>Notes</Text>

        <TextInput
          label="Notes"
          value={formData.notes}
          onChangeText={v => updateField('notes', v)}
          mode="outlined"
          multiline
          numberOfLines={3}
          style={styles.input}
        />

        <Button
          mode="contained"
          onPress={handleSave}
          loading={saving}
          disabled={saving}
          style={styles.saveButton}>
          {isEditing ? 'Update Part' : 'Add Part'}
        </Button>

        {isEditing && (
          <Button
            mode="outlined"
            onPress={handleDelete}
            textColor={theme.colors.error}
            style={styles.deleteButton}>
            Delete Part
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
  sectionTitle: {
    marginTop: 16,
    marginBottom: 12,
  },
  categoryChips: {
    marginBottom: 8,
    marginTop: -4,
  },
  categoryButton: {
    marginRight: 8,
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

export default PartFormScreen;
