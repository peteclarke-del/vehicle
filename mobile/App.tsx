import React, {useCallback, useEffect, useState} from 'react';
import {NavigationContainer, DefaultTheme, DarkTheme} from '@react-navigation/native';
import {Provider as PaperProvider} from 'react-native-paper';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {GestureHandlerRootView} from 'react-native-gesture-handler';
import {StyleSheet, useColorScheme, View, ActivityIndicator} from 'react-native';
import {useTranslation} from 'react-i18next';
import axios from 'axios';

import './src/i18n';
import Config from './src/config';
import {ServerConfigProvider, useServerConfig} from './src/contexts/ServerConfigContext';
import ServerConfigScreen from './src/screens/ServerConfigScreen';
import {AuthProvider} from './src/contexts/AuthContext';
import {UserPreferencesProvider, useUserPreferences} from './src/contexts/UserPreferencesContext';
import {SyncProvider} from './src/contexts/SyncContext';
import {VehicleSelectionProvider} from './src/contexts/VehicleSelectionContext';
import {PermissionsProvider} from './src/contexts/PermissionsContext';
import {lightTheme, darkTheme} from './src/theme';
import RootNavigator from './src/navigation/RootNavigator';
import {initializeNotifications, requestNotificationPermission} from './src/services/NotificationService';
import UpdateRequiredScreen from './src/components/UpdateRequiredScreen';
import {
  AppCompatibilityEvaluation,
  AppCompatibilityPayload,
  evaluateAppCompatibility,
} from './src/services/appCompatibility';

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
        <PermissionsProvider>
          <VehicleSelectionProvider>
            <NavigationContainer theme={navigationTheme}>
              <RootNavigator />
            </NavigationContainer>
          </VehicleSelectionProvider>
        </PermissionsProvider>
      </SyncProvider>
    </PaperProvider>
  );
};

// Decides whether to show server config screen or the main app
const AppWithConfig = () => {
  const {isConfigured, loading: configLoading, mode, serverUrl, resetConfig} = useServerConfig();
  const [compatibilityLoading, setCompatibilityLoading] = useState(false);
  const [compatibilityPayload, setCompatibilityPayload] = useState<AppCompatibilityPayload | null>(null);
  const [compatibilityEvaluation, setCompatibilityEvaluation] = useState<AppCompatibilityEvaluation | null>(null);

  const checkCompatibility = useCallback(async () => {
    if (!isConfigured || mode !== 'web') {
      setCompatibilityPayload(null);
      setCompatibilityEvaluation(null);
      setCompatibilityLoading(false);
      return;
    }

    setCompatibilityLoading(true);

    try {
      const baseUrl = serverUrl || Config.API_URL || 'http://10.0.2.2:8081/api';
      const response = await axios.get(`${baseUrl}/app-compatibility`, {timeout: 15000});
      const payload = response.data as AppCompatibilityPayload;
      setCompatibilityPayload(payload);
      setCompatibilityEvaluation(
        evaluateAppCompatibility(
          payload,
          Config.APP_VERSION,
          Config.SUPPORTED_SERVER_API_COMPATIBILITY_VERSIONS,
        ),
      );
    } catch (error: any) {
      if (error?.response?.status === 404) {
        const fallbackPayload: AppCompatibilityPayload = {
          server: {
            releaseVersion: 'unknown',
            internalVersion: 'unknown',
          },
        };
        setCompatibilityPayload(fallbackPayload);
        setCompatibilityEvaluation({
          isCompatible: false,
          requiresAppUpdate: false,
          requiresServerUpdate: true,
          reasons: [
            'This server does not expose compatibility metadata. Update the server to at least release 0.96.0 (baseline commit 8d148cf).',
          ],
        });
      } else {
        setCompatibilityPayload(null);
        setCompatibilityEvaluation(null);
      }
    } finally {
      setCompatibilityLoading(false);
    }
  }, [isConfigured, mode, serverUrl]);

  useEffect(() => {
    checkCompatibility();
  }, [checkCompatibility]);

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

  if (mode === 'web' && compatibilityLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  if (mode === 'web' && compatibilityEvaluation && !compatibilityEvaluation.isCompatible) {
    return (
      <PaperProvider theme={lightTheme}>
        <UpdateRequiredScreen
          evaluation={compatibilityEvaluation}
          payload={compatibilityPayload}
          appVersion={Config.APP_VERSION}
          onRetry={checkCompatibility}
          onChangeServer={resetConfig}
        />
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
