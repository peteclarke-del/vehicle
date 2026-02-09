import React, {useState, useCallback} from 'react';
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
  TouchableRipple,
} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useTranslation} from 'react-i18next';
import {useAuth} from '../contexts/AuthContext';
import {useServerConfig} from '../contexts/ServerConfigContext';
import {useUserPreferences} from '../contexts/UserPreferencesContext';
import {useSync} from '../contexts/SyncContext';
import {LANGUAGES, getLanguageByCode} from '../i18n/languages';

const CURRENCIES = [
  {value: 'GBP', label: '£ GBP', fullLabel: '£ - British Pound'},
  {value: 'USD', label: '$ USD', fullLabel: '$ - US Dollar'},
  {value: 'EUR', label: '€ EUR', fullLabel: '€ - Euro'},
  {value: 'AUD', label: 'A$ AUD', fullLabel: 'A$ - Australian Dollar'},
  {value: 'CAD', label: 'C$ CAD', fullLabel: 'C$ - Canadian Dollar'},
  {value: 'JPY', label: '¥ JPY', fullLabel: '¥ - Japanese Yen'},
  {value: 'CNY', label: '¥ CNY', fullLabel: '¥ - Chinese Yuan'},
  {value: 'KRW', label: '₩ KRW', fullLabel: '₩ - South Korean Won'},
  {value: 'INR', label: '₹ INR', fullLabel: '₹ - Indian Rupee'},
  {value: 'RUB', label: '₽ RUB', fullLabel: '₽ - Russian Ruble'},
  {value: 'PLN', label: 'zł PLN', fullLabel: 'zł - Polish Zloty'},
  {value: 'CZK', label: 'Kč CZK', fullLabel: 'Kč - Czech Koruna'},
  {value: 'SEK', label: 'kr SEK', fullLabel: 'kr - Swedish Krona'},
  {value: 'NOK', label: 'kr NOK', fullLabel: 'kr - Norwegian Krone'},
  {value: 'DKK', label: 'kr DKK', fullLabel: 'kr - Danish Krone'},
  {value: 'TRY', label: '₺ TRY', fullLabel: '₺ - Turkish Lira'},
  {value: 'SAR', label: '﷼ SAR', fullLabel: '﷼ - Saudi Riyal'},
];

const SettingsScreen: React.FC = () => {
  const theme = useTheme();
  const {t, i18n} = useTranslation();
  const {user, logout, isStandalone} = useAuth();
  const {mode, serverUrl, resetConfig} = useServerConfig();
  const {preferences, updatePreferences} = useUserPreferences();
  const {isOnline, pendingChanges, syncNow, isSyncing} = useSync();

  const [currencyDialogVisible, setCurrencyDialogVisible] = useState(false);
  const [languageDialogVisible, setLanguageDialogVisible] = useState(false);

  const currentLanguage = getLanguageByCode(preferences.preferredLanguage || 'en');

  const handleLanguageChange = useCallback((langCode: string) => {
    const lang = getLanguageByCode(langCode);
    if (!lang) return;

    setLanguageDialogVisible(false);
    i18n.changeLanguage(langCode);
    updatePreferences({preferredLanguage: langCode});

    // Offer to update currency to match the new language's default
    if (lang.defaultCurrency !== preferences.currency) {
      const currencyInfo = CURRENCIES.find(c => c.value === lang.defaultCurrency);
      if (currencyInfo) {
        Alert.alert(
          t('settings.currency'),
          t('settings.languageChangeCurrency', {
            currency: currencyInfo.label,
            language: lang.nativeName,
          }),
          [
            {text: t('common.no'), style: 'cancel'},
            {
              text: t('common.yes'),
              onPress: () => {
                updatePreferences({
                  currency: lang.defaultCurrency,
                  distanceUnit: lang.defaultDistanceUnit,
                  volumeUnit: lang.defaultVolumeUnit,
                });
              },
            },
          ],
        );
      }
    }
  }, [i18n, preferences.currency, t, updatePreferences]);

  const handleLogout = () => {
    Alert.alert(
      t('auth.logout'),
      t('settings.logoutConfirm'),
      [
        {text: t('common.cancel'), style: 'cancel'},
        {
          text: t('auth.logout'),
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
      Alert.alert(t('settings.syncOffline'), t('settings.syncOfflineMessage'));
      return;
    }

    try {
      await syncNow();
      Alert.alert(t('common.success'), t('settings.syncSuccess'));
    } catch (_error) {
      Alert.alert(t('common.error'), t('settings.syncError'));
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
                {isOnline ? t('settings.syncOnline') : t('settings.syncOffline')}
              </Text>
              <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                {pendingChanges.length > 0
                  ? t('settings.pendingChanges', {count: pendingChanges.length})
                  : t('settings.allSynced')}
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
              {t('settings.syncNow')}
            </Button>
          )}
        </Card.Content>
      </Card>

      {/* User Section */}
      <Text variant="titleSmall" style={styles.sectionHeader}>{t('settings.account')}</Text>
      <List.Section style={styles.section}>
        <List.Item
          title={user?.email || t('settings.notLoggedIn')}
          description={t('settings.loggedInAs')}
          left={props => <List.Icon {...props} icon="account" />}
        />
      </List.Section>

      <Divider />

      {/* Display Preferences */}
      <Text variant="titleSmall" style={styles.sectionHeader}>{t('settings.displayPreferences')}</Text>
      <List.Section style={styles.section}>

        {/* Language Selector */}
        <List.Item
          title={t('settings.language')}
          description={currentLanguage ? `${currentLanguage.flag} ${currentLanguage.nativeName}` : 'English'}
          left={props => <List.Icon {...props} icon="translate" />}
          right={props => <List.Icon {...props} icon="chevron-right" />}
          onPress={() => setLanguageDialogVisible(true)}
        />

        {/* Theme - full width row */}
        <View style={styles.settingRow}>
          <View style={styles.settingRowHeader}>
            <Icon name="theme-light-dark" size={24} color={theme.colors.onSurfaceVariant} style={styles.settingRowIcon} />
            <Text variant="bodyLarge">{t('settings.theme')}</Text>
          </View>
          <SegmentedButtons
            value={preferences.theme}
            onValueChange={(value) => updatePreferences({theme: value as 'light' | 'dark' | 'system'})}
            buttons={[
              {value: 'light', label: t('settings.light', 'Light'), icon: 'white-balance-sunny'},
              {value: 'system', label: t('settings.system', 'System'), icon: 'cellphone'},
              {value: 'dark', label: t('settings.dark', 'Dark'), icon: 'weather-night'},
            ]}
            style={styles.fullWidthSegmented}
          />
        </View>

        {/* Currency */}
        <List.Item
          title={t('settings.currency')}
          description={CURRENCIES.find(c => c.value === preferences.currency)?.fullLabel || 'GBP'}
          left={props => <List.Icon {...props} icon="currency-gbp" />}
          right={props => <List.Icon {...props} icon="chevron-right" />}
          onPress={() => setCurrencyDialogVisible(true)}
        />

        {/* Distance Unit - full width row */}
        <View style={styles.settingRow}>
          <View style={styles.settingRowHeader}>
            <Icon name="map-marker-distance" size={24} color={theme.colors.onSurfaceVariant} style={styles.settingRowIcon} />
            <Text variant="bodyLarge">{t('settings.distanceUnit')}</Text>
          </View>
          <SegmentedButtons
            value={preferences.distanceUnit}
            onValueChange={(value) => updatePreferences({distanceUnit: value as 'mi' | 'km'})}
            buttons={[
              {value: 'mi', label: t('settings.miles')},
              {value: 'km', label: t('settings.kilometres')},
            ]}
            style={styles.fullWidthSegmented}
          />
        </View>

        {/* Volume Unit - full width row */}
        <View style={styles.settingRow}>
          <View style={styles.settingRowHeader}>
            <Icon name="gas-station" size={24} color={theme.colors.onSurfaceVariant} style={styles.settingRowIcon} />
            <Text variant="bodyLarge">{t('settings.volumeUnit')}</Text>
          </View>
          <SegmentedButtons
            value={preferences.volumeUnit}
            onValueChange={(value) => updatePreferences({volumeUnit: value as 'l' | 'gal'})}
            buttons={[
              {value: 'l', label: t('settings.litres')},
              {value: 'gal', label: t('settings.gallons')},
            ]}
            style={styles.fullWidthSegmented}
          />
        </View>
      </List.Section>

      <Divider />

      {/* Notification Preferences */}
      <Text variant="titleSmall" style={styles.sectionHeader}>{t('settings.notifications')}</Text>
      <List.Section style={styles.section}>
        <List.Item
          title={t('settings.serviceReminders')}
          description={t('settings.serviceRemindersDesc')}
          left={props => <List.Icon {...props} icon="bell" />}
          right={() => (
            <Switch
              value={preferences.serviceReminders ?? true}
              onValueChange={(value) => updatePreferences({serviceReminders: value})}
            />
          )}
        />

        <List.Item
          title={t('settings.motReminders')}
          description={t('settings.motRemindersDesc')}
          left={props => <List.Icon {...props} icon="certificate" />}
          right={() => (
            <Switch
              value={preferences.motReminders ?? true}
              onValueChange={(value) => updatePreferences({motReminders: value})}
            />
          )}
        />

        <List.Item
          title={t('settings.insuranceReminders')}
          description={t('settings.insuranceRemindersDesc')}
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
      <Text variant="titleSmall" style={styles.sectionHeader}>{t('settings.about')}</Text>
      <List.Section style={styles.section}>
        <List.Item
          title={t('settings.version')}
          description="1.0.0"
          left={props => <List.Icon {...props} icon="information" />}
        />
      </List.Section>

      <Divider />

      {/* Server Configuration */}
      <Text variant="titleSmall" style={styles.sectionHeader}>Server</Text>
      <List.Section style={styles.section}>
        <List.Item
          title="Connection Mode"
          description={isStandalone ? 'Standalone (local data only)' : `Web — ${serverUrl}`}
          left={props => <List.Icon {...props} icon={isStandalone ? 'cellphone' : 'cloud-sync'} />}
        />
        <List.Item
          title="Change Server Configuration"
          description={isStandalone ? 'Switch to web mode' : 'Change server or switch to standalone'}
          left={props => <List.Icon {...props} icon="server-network" />}
          right={props => <List.Icon {...props} icon="chevron-right" />}
          onPress={resetConfig}
        />
      </List.Section>

      <Divider />

      {/* Logout */}
      <View style={styles.logoutContainer}>
        <Button
          mode="outlined"
          onPress={handleLogout}
          icon={isStandalone ? 'swap-horizontal' : 'logout'}
          textColor={theme.colors.error}
          style={styles.logoutButton}>
          {isStandalone ? 'Switch Mode' : t('settings.logout')}
        </Button>
      </View>

      {/* Language Dialog */}
      <Portal>
        <Dialog
          visible={languageDialogVisible}
          onDismiss={() => setLanguageDialogVisible(false)}
          style={styles.dialog}>
          <Dialog.Title>{t('settings.selectLanguage')}</Dialog.Title>
          <Dialog.ScrollArea style={styles.dialogScrollArea}>
            <ScrollView>
              {LANGUAGES.map(lang => (
                <TouchableRipple
                  key={lang.code}
                  onPress={() => handleLanguageChange(lang.code)}
                  style={styles.languageRow}>
                  <View style={styles.languageRowContent}>
                    <Text style={styles.languageFlag}>{lang.flag}</Text>
                    <View style={styles.languageTextContainer}>
                      <Text variant="bodyLarge">{lang.nativeName}</Text>
                      <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
                        {lang.name}
                      </Text>
                    </View>
                    {(preferences.preferredLanguage || 'en') === lang.code && (
                      <Icon name="check" size={24} color={theme.colors.primary} />
                    )}
                  </View>
                </TouchableRipple>
              ))}
            </ScrollView>
          </Dialog.ScrollArea>
          <Dialog.Actions>
            <Button onPress={() => setLanguageDialogVisible(false)}>{t('common.cancel')}</Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>

      {/* Currency Dialog */}
      <Portal>
        <Dialog
          visible={currencyDialogVisible}
          onDismiss={() => setCurrencyDialogVisible(false)}
          style={styles.dialog}>
          <Dialog.Title>{t('settings.selectCurrency')}</Dialog.Title>
          <Dialog.ScrollArea style={styles.dialogScrollArea}>
            <ScrollView>
              <RadioButton.Group
                onValueChange={(value) => {
                  updatePreferences({currency: value});
                  setCurrencyDialogVisible(false);
                }}
                value={preferences.currency}>
                {CURRENCIES.map(currency => (
                  <RadioButton.Item
                    key={currency.value}
                    label={currency.fullLabel}
                    value={currency.value}
                  />
                ))}
              </RadioButton.Group>
            </ScrollView>
          </Dialog.ScrollArea>
          <Dialog.Actions>
            <Button onPress={() => setCurrencyDialogVisible(false)}>{t('common.cancel')}</Button>
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
  settingRow: {
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  settingRowHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  settingRowIcon: {
    marginRight: 16,
    width: 24,
  },
  fullWidthSegmented: {
    marginLeft: 40,
  },
  logoutContainer: {
    padding: 16,
    paddingTop: 24,
  },
  logoutButton: {
    borderColor: 'transparent',
  },
  dialog: {
    maxHeight: '80%',
  },
  dialogScrollArea: {
    maxHeight: 400,
    paddingHorizontal: 0,
  },
  languageRow: {
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  languageRowContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  languageFlag: {
    fontSize: 24,
    marginRight: 16,
  },
  languageTextContainer: {
    flex: 1,
  },
  bottomPadding: {
    height: 24,
  },
});

export default SettingsScreen;
