import React, {useMemo, useState} from 'react';
import {
  View,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
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
import OfflineBanner from '../components/OfflineBanner';
import {formStyles} from '../theme/sharedStyles';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'StockItemForm'>;

type StockItemType = 'part' | 'consumable';

interface FormData {
  itemType: StockItemType;
  category: string;
  supplier: string;
  quantity: string;
  price: string;
  description: string;
  notes: string;
  purchaseDate: string;
  partNumber: string;
  manufacturer: string;
  warranty: string;
}

interface ScrapedStockData {
  name?: string;
  supplier?: string;
  price?: string | number;
  manufacturer?: string;
}

const EMPTY_FORM: FormData = {
  itemType: 'part',
  category: '',
  supplier: '',
  quantity: '',
  price: '',
  description: '',
  notes: '',
  purchaseDate: '',
  partNumber: '',
  manufacturer: '',
  warranty: '',
};

const StockItemFormScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api} = useAuth();
  const {isOnline} = useSync();

  const item = route.params?.item;
  const isEditing = !!item?.id;

  const [saving, setSaving] = useState(false);
  const [scrapeUrl, setScrapeUrl] = useState('');
  const [scraping, setScraping] = useState(false);
  const [formData, setFormData] = useState<FormData>(() => {
    if (!item) {
      return EMPTY_FORM;
    }

    return {
      itemType: item.itemType,
      category: item.category || '',
      supplier: item.supplier || '',
      quantity: item.quantity?.toString() || '',
      price: item.price?.toString() || '',
      description: item.description || '',
      notes: item.notes || '',
      purchaseDate: item.purchaseDate || '',
      partNumber: item.partNumber || '',
      manufacturer: item.manufacturer || '',
      warranty: item.warranty || '',
    };
  });

  const saveLabel = useMemo(() => {
    return isEditing ? 'Update Stock Item' : 'Add Stock Item';
  }, [isEditing]);

  const updateField = (field: keyof FormData, value: string) => {
    setFormData(prev => ({...prev, [field]: value}));
  };

  const handleDataScraped = (scrapedData: ScrapedStockData) => {
    if (!scrapedData) {
      return;
    }

    setFormData(prev => ({
      ...prev,
      description: scrapedData.name || prev.description,
      supplier: scrapedData.supplier || prev.supplier,
      price: scrapedData.price?.toString() || prev.price,
      manufacturer: scrapedData.manufacturer || prev.manufacturer,
    }));
  };

  const handleScrape = async () => {
    if (!isOnline) {
      Alert.alert('Offline', 'URL scraping requires an online connection.');
      return;
    }

    const url = scrapeUrl.trim();
    if (!url) {
      return;
    }

    setScraping(true);
    try {
      const response = await api.post('/stock-items/scrape-url', {url});
      handleDataScraped(response.data as ScrapedStockData);
      setScrapeUrl('');
    } catch (error: any) {
      const message = error.response?.data?.error || 'Scraping failed';
      Alert.alert('Scrape Error', message);
    } finally {
      setScraping(false);
    }
  };

  const handleSave = async () => {
    if (!isOnline) {
      Alert.alert('Offline', 'Saving stock items requires an online connection.');
      return;
    }

    const quantity = parseFloat(formData.quantity || '0');
    if (!formData.category.trim()) {
      Alert.alert('Validation Error', 'Category is required');
      return;
    }

    if (!isEditing && !(quantity > 0)) {
      Alert.alert('Validation Error', 'Quantity must be greater than zero when creating a stock item');
      return;
    }

    if (isEditing && quantity < 0) {
      Alert.alert('Validation Error', 'Quantity cannot be negative');
      return;
    }

    const payload = {
      vehicleTypeId: item?.vehicleTypeId || null,
      itemType: formData.itemType,
      category: formData.category.trim(),
      supplier: formData.supplier.trim() || null,
      description: formData.description.trim() || null,
      price: formData.price.trim() || null,
      notes: formData.notes.trim() || null,
      purchaseDate: formData.purchaseDate.trim() || null,
      partNumber: formData.partNumber.trim() || null,
      manufacturer: formData.manufacturer.trim() || null,
      warranty: formData.warranty.trim() || null,
    };

    setSaving(true);
    try {
      if (isEditing && item?.id) {
        await api.put(`/stock-items/${item.id}`, {
          ...payload,
          quantity,
        });
      } else {
        await api.post('/stock-items/adjust', {
          ...payload,
          delta: quantity,
        });
      }

      navigation.goBack();
    } catch (error: any) {
      const message = error.response?.data?.error || 'Failed to save stock item';
      Alert.alert('Error', message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={formStyles.container}>
      <ScrollView
        style={[formStyles.scrollView, {backgroundColor: theme.colors.background}]}
        contentContainerStyle={formStyles.content}
        keyboardShouldPersistTaps="handled">

        {!isOnline && <OfflineBanner message="Offline - stock updates are disabled" />}

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Source</Text>

        <View style={styles.scrapeRow}>
          <TextInput
            label="Product URL"
            value={scrapeUrl}
            onChangeText={setScrapeUrl}
            mode="outlined"
            style={[formStyles.input, styles.scrapeInput]}
            autoCapitalize="none"
            keyboardType="url"
          />
          <Button
            mode="outlined"
            onPress={handleScrape}
            loading={scraping}
            disabled={scraping || !scrapeUrl.trim() || !isOnline}
            style={styles.scrapeButton}>
            Scrape
          </Button>
        </View>

        <Text variant="titleMedium" style={formStyles.sectionTitle}>Stock Item</Text>

        <View style={styles.typeButtons}>
          <Button
            mode={formData.itemType === 'part' ? 'contained' : 'outlined'}
            onPress={() => updateField('itemType', 'part')}
            style={styles.typeButton}>
            Part
          </Button>
          <Button
            mode={formData.itemType === 'consumable' ? 'contained' : 'outlined'}
            onPress={() => updateField('itemType', 'consumable')}
            style={styles.typeButton}>
            Consumable
          </Button>
        </View>

        <TextInput
          label="Category *"
          value={formData.category}
          onChangeText={v => updateField('category', v)}
          mode="outlined"
          style={formStyles.input}
        />

        <TextInput
          label="Item Description"
          value={formData.description}
          onChangeText={v => updateField('description', v)}
          mode="outlined"
          style={formStyles.input}
        />

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
            label="Quantity"
            value={formData.quantity}
            onChangeText={v => updateField('quantity', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <View style={formStyles.row}>
          <TextInput
            label="Price"
            value={formData.price}
            onChangeText={v => updateField('price', v)}
            mode="outlined"
            keyboardType="decimal-pad"
            style={[formStyles.input, formStyles.halfInput]}
          />
          <TextInput
            label="Supplier"
            value={formData.supplier}
            onChangeText={v => updateField('supplier', v)}
            mode="outlined"
            style={[formStyles.input, formStyles.halfInput]}
          />
        </View>

        <View style={formStyles.row}>
          <TextInput
            label="Warranty"
            value={formData.warranty}
            onChangeText={v => updateField('warranty', v)}
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

        <TextInput
          label={formData.itemType === 'consumable' ? 'Brand' : 'Manufacturer'}
          value={formData.manufacturer}
          onChangeText={v => updateField('manufacturer', v)}
          mode="outlined"
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

        <Button
          mode="contained"
          onPress={handleSave}
          loading={saving}
          disabled={saving || !isOnline}
          style={formStyles.saveButton}>
          {saveLabel}
        </Button>

        <View style={formStyles.bottomPadding} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  scrapeRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
  },
  scrapeInput: {
    flex: 1,
  },
  scrapeButton: {
    marginTop: 6,
  },
  typeButtons: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 12,
  },
  typeButton: {
    flex: 1,
  },
});

export default StockItemFormScreen;
