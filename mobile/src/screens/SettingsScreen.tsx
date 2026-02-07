import React, {useState} from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  Alert,
} from 'react-native';
import {
  Text,
  List,
  Switch,
  Divider,
  useTheme,
  Button,
  Card,
  SegmentedButtons,
  Portal,
  Dialog,
  RadioButton,
} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useAuth} from '../contexts/AuthContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {useSync} from '../contexts/SyncContext';

const CURRENCIES = [
  {value: 'GBP', label: '£ - British Pound'},
  {value: 'USD', label: '$ - US Dollar'},
  {value: 'EUR', label: '€ - Euro'},
  {value: 'AUD', label: 'A$ - Australian Dollar'},
  {value: 'CAD', label: 'C$ - Canadian Dollar'},
];

const SettingsScreen: React.FC = () => {
  const theme = useTheme();
  const {user, logout} = useAuth();
  const {preferences, updatePreferences} = useUserPreferences();
  const {isOnline, pendingChanges, syncNow, isSyncing} = useSync();
  
  const [currencyDialogVisible, setCurrencyDialogVisible] = useState(false);

  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Logout',
          onPress: async () => {
            try {
              await logout();
            } catch (error) {
              console.error('Logout error:', error);
            }
          },
        },
      ],
    );
  };

  const handleSyncNow = async () => {
    if (!isOnline) {
      Alert.alert('Offline', 'You need to be online to sync data.');
      return;
    }

    try {
      await syncNow();
      Alert.alert('Success', 'Data synced successfully!');
    } catch (error) {
      Alert.alert('Error', 'Failed to sync data. Please try again.');
    }
  };

  return (
    <ScrollView
      style={[styles.container, {backgroundColor: theme.colors.background}]}
      contentContainerStyle={styles.content}>
      
      {/* Sync Status Card */}
      <Card style={styles.syncCard}>
        <Card.Content>
          <View style={styles.syncHeader}>
            <Icon
              name={isOnline ? 'cloud-check' : 'cloud-off-outline'}
              size={32}
              color={isOnline ? theme.colors.primary : theme.colors.error}
            />
            <View style={styles.syncInfo}>
              <Text variant="titleMedium">
                {isOnline ? 'Online' : 'Offline'}
              </Text>
              <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                {pendingChanges.length > 0
                  ? `${pendingChanges.length} pending changes`
                  : 'All changes synced'}
              </Text>
            </View>
          </View>
          {pendingChanges.length > 0 && (
            <Button
              mode="contained"
              onPress={handleSyncNow}
              loading={isSyncing}
              disabled={!isOnline || isSyncing}
              style={styles.syncButton}>
              Sync Now
            </Button>
          )}
        </Card.Content>
      </Card>

      {/* User Section */}
      <Text variant="titleSmall" style={styles.sectionHeader}>Account</Text>
      <List.Section style={styles.section}>
        <List.Item
          title={user?.email || 'Not logged in'}
          description="Logged in as"
          left={props => <List.Icon {...props} icon="account" />}
        />
      </List.Section>

      <Divider />

      {/* Display Preferences */}
      <Text variant="titleSmall" style={styles.sectionHeader}>Display Preferences</Text>
      <List.Section style={styles.section}>
        <List.Item
          title="Theme"
          description="Choose light or dark mode"
          left={props => <List.Icon {...props} icon="theme-light-dark" />}
          right={() => (
            <SegmentedButtons
              value={preferences.theme}
              onValueChange={(value) => updatePreferences({theme: value as 'light' | 'dark' | 'system'})}
              buttons={[
                {value: 'light', icon: 'white-balance-sunny'},
                {value: 'system', icon: 'cellphone'},
                {value: 'dark', icon: 'weather-night'},
              ]}
              style={styles.segmentedButtons}
            />
          )}
        />
        
        <List.Item
          title="Currency"
          description={CURRENCIES.find(c => c.value === preferences.currency)?.label || 'GBP'}
          left={props => <List.Icon {...props} icon="currency-gbp" />}
          onPress={() => setCurrencyDialogVisible(true)}
        />
        
        <List.Item
          title="Distance Unit"
          description={preferences.distanceUnit === 'km' ? 'Kilometres' : 'Miles'}
          left={props => <List.Icon {...props} icon="map-marker-distance" />}
          right={() => (
            <SegmentedButtons
              value={preferences.distanceUnit}
              onValueChange={(value) => updatePreferences({distanceUnit: value as 'mi' | 'km'})}
              buttons={[
                {value: 'mi', label: 'Miles'},
                {value: 'km', label: 'km'},
              ]}
              style={styles.segmentedButtons}
            />
          )}
        />

        <List.Item
          title="Volume Unit"
          description={preferences.volumeUnit === 'l' ? 'Litres' : 'Gallons'}
          left={props => <List.Icon {...props} icon="gas-station" />}
          right={() => (
            <SegmentedButtons
              value={preferences.volumeUnit}
              onValueChange={(value) => updatePreferences({volumeUnit: value as 'l' | 'gal'})}
              buttons={[
                {value: 'l', label: 'Litres'},
                {value: 'gal', label: 'Gal'},
              ]}
              style={styles.segmentedButtons}
            />
          )}
        />
      </List.Section>

      <Divider />

      {/* Notification Preferences */}
      <Text variant="titleSmall" style={styles.sectionHeader}>Notifications</Text>
      <List.Section style={styles.section}>
        <List.Item
          title="Service Reminders"
          description="Get notified about upcoming services"
          left={props => <List.Icon {...props} icon="bell" />}
          right={() => (
            <Switch
              value={preferences.serviceReminders ?? true}
              onValueChange={(value) => updatePreferences({serviceReminders: value})}
            />
          )}
        />
        
        <List.Item
          title="MOT Reminders"
          description="Get reminded before MOT expires"
          left={props => <List.Icon {...props} icon="certificate" />}
          right={() => (
            <Switch
              value={preferences.motReminders ?? true}
              onValueChange={(value) => updatePreferences({motReminders: value})}
            />
          )}
        />

        <List.Item
          title="Insurance Reminders"
          description="Get reminded before insurance expires"
          left={props => <List.Icon {...props} icon="shield-car" />}
          right={() => (
            <Switch
              value={preferences.insuranceReminders ?? true}
              onValueChange={(value) => updatePreferences({insuranceReminders: value})}
            />
          )}
        />
      </List.Section>

      <Divider />

      {/* About Section */}
      <Text variant="titleSmall" style={styles.sectionHeader}>About</Text>
      <List.Section style={styles.section}>
        <List.Item
          title="Version"
          description="1.0.0"
          left={props => <List.Icon {...props} icon="information" />}
        />
      </List.Section>

      <Divider />

      {/* Logout */}
      <View style={styles.logoutContainer}>
        <Button
          mode="outlined"
          onPress={handleLogout}
          icon="logout"
          textColor={theme.colors.error}
          style={styles.logoutButton}>
          Logout
        </Button>
      </View>

      {/* Currency Dialog */}
      <Portal>
        <Dialog visible={currencyDialogVisible} onDismiss={() => setCurrencyDialogVisible(false)}>
          <Dialog.Title>Select Currency</Dialog.Title>
          <Dialog.Content>
            <RadioButton.Group
              onValueChange={(value) => {
                updatePreferences({currency: value});
                setCurrencyDialogVisible(false);
              }}
              value={preferences.currency}>
              {CURRENCIES.map(currency => (
                <RadioButton.Item
                  key={currency.value}
                  label={currency.label}
                  value={currency.value}
                />
              ))}
            </RadioButton.Group>
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setCurrencyDialogVisible(false)}>Cancel</Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>

      <View style={styles.bottomPadding} />
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  content: {
    paddingBottom: 32,
  },
  syncCard: {
    margin: 16,
  },
  syncHeader: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  syncInfo: {
    marginLeft: 16,
    flex: 1,
  },
  syncButton: {
    marginTop: 16,
  },
  sectionHeader: {
    paddingHorizontal: 16,
    paddingTop: 24,
    paddingBottom: 8,
    opacity: 0.7,
  },
  section: {
    marginBottom: 8,
  },
  segmentedButtons: {
    marginRight: -8,
  },
  logoutContainer: {
    padding: 16,
    paddingTop: 24,
  },
  logoutButton: {
    borderColor: 'transparent',
  },
  bottomPadding: {
    height: 24,
  },
});

export default SettingsScreen;
