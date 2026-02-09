import React, {useEffect} from 'react';
import {NavigationContainer, DefaultTheme, DarkTheme} from '@react-navigation/native';
import {Provider as PaperProvider} from 'react-native-paper';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {GestureHandlerRootView} from 'react-native-gesture-handler';
import {StyleSheet, useColorScheme} from 'react-native';

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
        <AuthProvider>
          <UserPreferencesProvider>
            <AppContent />
          </UserPreferencesProvider>
        </AuthProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
});

export default App;
