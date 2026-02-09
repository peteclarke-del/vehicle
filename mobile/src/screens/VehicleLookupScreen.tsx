import React, {useState} from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  Alert,
} from 'react-native';
import {
  TextInput,
  Button,
  Text,
  Card,
  useTheme,
  ActivityIndicator,
  Chip,
  Divider,
  List,
} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useAuth} from '../contexts/AuthContext';
import {formatDate} from '../utils/formatters';

interface VehicleInfo {
  registration: string;
  make: string;
  model: string;
  firstUsedDate: string | null;
  fuelType: string | null;
  primaryColour: string | null;
  registrationDate: string | null;
  manufactureDate: string | null;
  engineSize: string | null;
  yearOfManufacture: number | null;
}

interface MotTest {
  completedDate: string;
  testResult: string;
  expiryDate: string | null;
  odometerValue: string;
  odometerUnit: string;
  motTestNumber: string;
  defects: number;
}

interface LookupResult {
  vehicle: VehicleInfo;
  motTests: MotTest[];
}

const VehicleLookupScreen: React.FC = () => {
  const theme = useTheme();
  const {api} = useAuth();

  const [registration, setRegistration] = useState('');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<LookupResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleLookup = async () => {
    const reg = registration.replace(/\s+/g, '').toUpperCase();
    if (!reg || reg.length < 2) {
      Alert.alert('Error', 'Please enter a valid registration number');
      return;
    }

    setLoading(true);
    setError(null);
    setResult(null);

    try {
      // Fetch vehicle info + MOT history from DVSA
      const [vehicleRes, historyRes] = await Promise.all([
        api.get(`/dvsa/vehicle/${reg}`).catch(() => null),
        api.get(`/dvsa/mot-history/${reg}`).catch(() => null),
      ]);

      if (historyRes?.data) {
        setResult(historyRes.data);
      } else if (vehicleRes?.data && !vehicleRes.data.error) {
        setResult({vehicle: vehicleRes.data, motTests: []});
      } else {
        setError('Vehicle not found. Please check the registration number and try again.');
      }
    } catch (e: any) {
      setError(e?.response?.data?.error || 'Failed to look up vehicle. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const getResultIcon = (testResult: string) => {
    if (testResult === 'PASSED') return {name: 'check-circle', color: '#22C55E'};
    if (testResult === 'FAILED') return {name: 'close-circle', color: theme.colors.error};
    return {name: 'help-circle', color: theme.colors.onSurfaceVariant};
  };

  const formatOdometer = (value: string, unit: string) => {
    const num = parseInt(value, 10);
    if (isNaN(num)) return value;
    return `${num.toLocaleString('en-GB')} ${unit === 'MI' ? 'miles' : 'km'}`;
  };

  const formatMotDate = (dateStr: string) => {
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
    } catch {
      return dateStr;
    }
  };

  return (
    <ScrollView style={[styles.container, {backgroundColor: theme.colors.background}]}>
      {/* Search Section */}
      <Card style={styles.searchCard}>
        <Card.Content>
          <View style={styles.searchHeader}>
            <Icon name="car-search" size={32} color={theme.colors.primary} />
            <Text variant="titleLarge" style={{marginLeft: 12, fontWeight: 'bold'}}>
              Vehicle Lookup
            </Text>
          </View>
          <Text variant="bodyMedium" style={{color: theme.colors.onSurfaceVariant, marginBottom: 16}}>
            Enter a UK registration number to get vehicle information, MOT history, and specs.
          </Text>
          <TextInput
            label="Registration Number"
            value={registration}
            onChangeText={setRegistration}
            mode="outlined"
            autoCapitalize="characters"
            placeholder="e.g. AB12 CDE"
            style={styles.regInput}
            left={<TextInput.Icon icon="card-account-details" />}
          />
          <Button
            mode="contained"
            onPress={handleLookup}
            loading={loading}
            disabled={loading || !registration.trim()}
            icon="magnify"
            style={styles.searchButton}>
            Look Up
          </Button>
        </Card.Content>
      </Card>

      {/* Error */}
      {error && (
        <Card style={[styles.card, {backgroundColor: theme.colors.errorContainer}]}>
          <Card.Content style={{flexDirection: 'row', alignItems: 'center'}}>
            <Icon name="alert-circle" size={24} color={theme.colors.error} />
            <Text style={{color: theme.colors.onErrorContainer, marginLeft: 12, flex: 1}}>
              {error}
            </Text>
          </Card.Content>
        </Card>
      )}

      {/* Loading */}
      {loading && (
        <View style={styles.loadingBox}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
          <Text variant="bodyMedium" style={{marginTop: 12, color: theme.colors.onSurfaceVariant}}>
            Looking up vehicle...
          </Text>
        </View>
      )}

      {/* Results */}
      {result && (
        <>
          {/* Vehicle Info Card */}
          <Card style={styles.card}>
            <Card.Content>
              <View style={styles.vehicleHeader}>
                <Icon name="car" size={32} color={theme.colors.primary} />
                <View style={{marginLeft: 12, flex: 1}}>
                  <Text variant="headlineSmall" style={{fontWeight: 'bold'}}>
                    {result.vehicle.make} {result.vehicle.model}
                  </Text>
                  <Text variant="titleMedium" style={{color: theme.colors.primary}}>
                    {result.vehicle.registration}
                  </Text>
                </View>
              </View>

              <Divider style={{marginVertical: 12}} />

              <View style={styles.specsGrid}>
                {result.vehicle.yearOfManufacture && (
                  <View style={styles.specItem}>
                    <Icon name="calendar" size={18} color={theme.colors.onSurfaceVariant} />
                    <Text variant="bodySmall" style={styles.specLabel}>Year</Text>
                    <Text variant="bodyLarge" style={{fontWeight: 'bold'}}>{result.vehicle.yearOfManufacture}</Text>
                  </View>
                )}
                {result.vehicle.fuelType && (
                  <View style={styles.specItem}>
                    <Icon name="gas-station" size={18} color={theme.colors.onSurfaceVariant} />
                    <Text variant="bodySmall" style={styles.specLabel}>Fuel</Text>
                    <Text variant="bodyLarge" style={{fontWeight: 'bold'}}>{result.vehicle.fuelType}</Text>
                  </View>
                )}
                {result.vehicle.engineSize && (
                  <View style={styles.specItem}>
                    <Icon name="engine" size={18} color={theme.colors.onSurfaceVariant} />
                    <Text variant="bodySmall" style={styles.specLabel}>Engine</Text>
                    <Text variant="bodyLarge" style={{fontWeight: 'bold'}}>{result.vehicle.engineSize}cc</Text>
                  </View>
                )}
                {result.vehicle.primaryColour && (
                  <View style={styles.specItem}>
                    <Icon name="palette" size={18} color={theme.colors.onSurfaceVariant} />
                    <Text variant="bodySmall" style={styles.specLabel}>Colour</Text>
                    <Text variant="bodyLarge" style={{fontWeight: 'bold'}}>{result.vehicle.primaryColour}</Text>
                  </View>
                )}
              </View>

              {result.vehicle.firstUsedDate && (
                <List.Item
                  title="First Registered"
                  description={formatDate(result.vehicle.firstUsedDate)}
                  left={props => <List.Icon {...props} icon="calendar-check" />}
                />
              )}
            </Card.Content>
          </Card>

          {/* MOT History */}
          <Card style={styles.card}>
            <Card.Content>
              <Text variant="titleMedium" style={{fontWeight: 'bold', marginBottom: 12}}>
                MOT History ({result.motTests.length} tests)
              </Text>
              {result.motTests.length === 0 ? (
                <View style={styles.emptyMot}>
                  <Icon name="file-document-outline" size={32} color={theme.colors.onSurfaceVariant} />
                  <Text variant="bodyMedium" style={{color: theme.colors.onSurfaceVariant, marginTop: 8}}>
                    No MOT history available
                  </Text>
                </View>
              ) : (
                result.motTests.map((test, idx) => {
                  const icon = getResultIcon(test.testResult);
                  return (
                    <View key={idx}>
                      {idx > 0 && <Divider style={{marginVertical: 8}} />}
                      <View style={styles.motTestItem}>
                        <Icon name={icon.name} size={28} color={icon.color} />
                        <View style={{flex: 1, marginLeft: 12}}>
                          <View style={styles.motTestHeader}>
                            <Text variant="titleSmall" style={{fontWeight: 'bold'}}>
                              {test.testResult === 'PASSED' ? 'Pass' : test.testResult === 'FAILED' ? 'Fail' : test.testResult}
                            </Text>
                            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                              {formatMotDate(test.completedDate)}
                            </Text>
                          </View>
                          <View style={styles.motTestChips}>
                            {test.odometerValue && test.odometerValue !== '0' && (
                              <Chip compact icon="speedometer" style={styles.smallChip} textStyle={styles.smallChipText}>
                                {formatOdometer(test.odometerValue, test.odometerUnit)}
                              </Chip>
                            )}
                            {test.expiryDate && (
                              <Chip compact icon="calendar-clock" style={styles.smallChip} textStyle={styles.smallChipText}>
                                Exp: {formatMotDate(test.expiryDate)}
                              </Chip>
                            )}
                          </View>
                        </View>
                      </View>
                    </View>
                  );
                })
              )}
            </Card.Content>
          </Card>

          {/* Mileage History (from MOT) */}
          {result.motTests.filter(t => t.odometerValue && t.odometerValue !== '0').length > 1 && (
            <Card style={[styles.card, {marginBottom: 32}]}>
              <Card.Content>
                <Text variant="titleMedium" style={{fontWeight: 'bold', marginBottom: 12}}>
                  Mileage History
                </Text>
                {result.motTests
                  .filter(t => t.odometerValue && t.odometerValue !== '0')
                  .map((test, idx, arr) => {
                    const miles = parseInt(test.odometerValue, 10);
                    const prevMiles = idx < arr.length - 1 ? parseInt(arr[idx + 1].odometerValue, 10) : null;
                    const diff = prevMiles != null ? miles - prevMiles : null;
                    return (
                      <View key={idx} style={styles.mileageRow}>
                        <Text variant="bodyMedium" style={{width: 100}}>
                          {formatMotDate(test.completedDate)}
                        </Text>
                        <Text variant="bodyLarge" style={{fontWeight: 'bold', flex: 1}}>
                          {miles.toLocaleString('en-GB')} mi
                        </Text>
                        {diff != null && (
                          <Chip compact style={[styles.smallChip, {backgroundColor: diff > 20000 ? '#FEF3C7' : '#DCFCE7'}]} textStyle={styles.smallChipText}>
                            +{diff.toLocaleString('en-GB')}
                          </Chip>
                        )}
                      </View>
                    );
                  })}
              </Card.Content>
            </Card>
          )}
        </>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1},
  searchCard: {margin: 16},
  searchHeader: {flexDirection: 'row', alignItems: 'center', marginBottom: 8},
  regInput: {marginBottom: 12, fontSize: 18},
  searchButton: {paddingVertical: 4},
  card: {marginHorizontal: 16, marginTop: 16},
  vehicleHeader: {flexDirection: 'row', alignItems: 'center'},
  specsGrid: {flexDirection: 'row', flexWrap: 'wrap', gap: 16},
  specItem: {alignItems: 'center', minWidth: 70},
  specLabel: {opacity: 0.7, marginTop: 2},
  emptyMot: {alignItems: 'center', paddingVertical: 16},
  motTestItem: {flexDirection: 'row', alignItems: 'flex-start'},
  motTestHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center'},
  motTestChips: {flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 4},
  smallChip: {height: 26},
  smallChipText: {fontSize: 11},
  loadingBox: {alignItems: 'center', paddingVertical: 32},
  mileageRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 6,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#E5E7EB',
  },
});

export default VehicleLookupScreen;
