import React, {useEffect} from 'react';
import {NavigationContainer, DefaultTheme, DarkTheme} from '@react-navigation/native';
import {Provider as PaperProvider} from 'react-native-paper';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {GestureHandlerRootView} from 'react-native-gesture-handler';
import {StyleSheet, useColorScheme, View, ActivityIndicator} from 'react-native';
import {useTranslation} from 'react-i18next';

import './src/i18n';
import {ServerConfigProvider, useServerConfig} from './src/contexts/ServerConfigContext';
import ServerConfigScreen from './src/screens/ServerConfigScreen';
import {AuthProvider} from './src/contexts/AuthContext';
import {UserPreferencesProvider, useUserPreferences} from './src/contexts/UserPreferencesContext';
import {SyncProvider} from './src/contexts/SyncContext';
import {VehicleSelectionProvider} from './src/contexts/VehicleSelectionContext';
import {lightTheme, darkTheme} from './src/theme';
import RootNavigator from './src/navigation/RootNavigator';
import {initializeNotifications, requestNotificationPermission} from './src/services/NotificationService';

// Inner component that has access to preferences
const AppContent = () => {
  const {preferences} = useUserPreferences();
  const systemColorScheme = useColorScheme();
  const {i18n} = useTranslation();
  
  // Sync i18n language with user preferences
  useEffect(() => {
    if (preferences.preferredLanguage && i18n.language !== preferences.preferredLanguage) {
      i18n.changeLanguage(preferences.preferredLanguage);
    }
  }, [preferences.preferredLanguage, i18n]);

  // Determine which theme to use
  const isDark = 
    preferences.theme === 'dark' || 
    (preferences.theme === 'system' && systemColorScheme === 'dark');
  
  const paperTheme = isDark ? darkTheme : lightTheme;
  const navigationTheme = isDark ? DarkTheme : DefaultTheme;

  return (
    <PaperProvider theme={paperTheme}>
      <SyncProvider>
        <VehicleSelectionProvider>
          <NavigationContainer theme={navigationTheme}>
            <RootNavigator />
          </NavigationContainer>
        </VehicleSelectionProvider>
      </SyncProvider>
    </PaperProvider>
  );
};

// Decides whether to show server config screen or the main app
const AppWithConfig = () => {
  const {isConfigured, loading: configLoading} = useServerConfig();

  if (configLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  if (!isConfigured) {
    return (
      <PaperProvider theme={lightTheme}>
        <ServerConfigScreen />
      </PaperProvider>
    );
  }

  return (
    <AuthProvider>
      <UserPreferencesProvider>
        <AppContent />
      </UserPreferencesProvider>
    </AuthProvider>
  );
};

const App = () => {
  useEffect(() => {
    const setupNotifications = async () => {
      await initializeNotifications();
      await requestNotificationPermission();
    };
    setupNotifications();
  }, []);

  return (
    <GestureHandlerRootView style={styles.container}>
      <SafeAreaProvider>
        <ServerConfigProvider>
          <AppWithConfig />
        </ServerConfigProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
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
});

export default App;
