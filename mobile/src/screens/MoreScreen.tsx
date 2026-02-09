import React from 'react';
import {View, StyleSheet, ScrollView} from 'react-native';
import {List, useTheme, Text, Divider, Surface} from 'react-native-paper';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useSync} from '../contexts/SyncContext';
import {usePermissions} from '../contexts/PermissionsContext';
import {MainStackParamList} from '../navigation/MainNavigator';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;

const MoreScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const {isOnline, pendingChanges, isSyncing} = useSync();
  const {can} = usePermissions();

  return (
    <ScrollView style={[styles.container, {backgroundColor: theme.colors.background}]}>
      {/* Sync Status */}
      <Surface style={[styles.syncBanner, {
        backgroundColor: isOnline ? theme.colors.primaryContainer : theme.colors.errorContainer,
      }]}>
        <Icon
          name={isOnline ? 'cloud-check' : 'cloud-off-outline'}
          size={20}
          color={isOnline ? theme.colors.onPrimaryContainer : theme.colors.onErrorContainer}
        />
        <Text style={{
          color: isOnline ? theme.colors.onPrimaryContainer : theme.colors.onErrorContainer,
          marginLeft: 8,
        }}>
          {isOnline ? 'Online' : 'Offline — changes will sync when reconnected'}
          {pendingChanges.length > 0 && ` • ${pendingChanges.length} pending`}
          {isSyncing && ' • Syncing...'}
        </Text>
      </Surface>

      {/* Records Section */}
      <Text variant="titleSmall" style={styles.sectionHeader}>Records</Text>
      <List.Section style={styles.section}>
        {can('mot.view') && (
          <>
            <List.Item
              title="MOT Records"
              description="MOT test history, results, advisories"
              left={props => <List.Icon {...props} icon="file-document" />}
              right={props => <List.Icon {...props} icon="chevron-right" />}
              onPress={() => navigation.navigate('MotRecordsList')}
            />
            <Divider />
          </>
        )}
        {can('parts.view') && (
          <>
            <List.Item
              title="Parts"
              description="Vehicle parts and accessories"
              left={props => <List.Icon {...props} icon="package-variant" />}
              right={props => <List.Icon {...props} icon="chevron-right" />}
              onPress={() => navigation.navigate('PartsList')}
            />
            <Divider />
          </>
        )}
        {can('consumables.view') && (
          <List.Item
            title="Consumables"
            description="Oils, filters, brake pads, spark plugs"
            left={props => <List.Icon {...props} icon="oil" />}
            right={props => <List.Icon {...props} icon="chevron-right" />}
            onPress={() => navigation.navigate('ConsumablesList')}
          />
        )}
      </List.Section>

      <Divider />

      {/* Tools Section */}
      <Text variant="titleSmall" style={styles.sectionHeader}>Tools</Text>
      <List.Section style={styles.section}>
        {can('vehicles.view') && (
          <List.Item
            title="Vehicle Lookup"
            description="Look up any UK vehicle by registration"
            left={props => <List.Icon {...props} icon="car-search" />}
            right={props => <List.Icon {...props} icon="chevron-right" />}
            onPress={() => navigation.navigate('VehicleLookup')}
          />
        )}
      </List.Section>

      <Divider />

      {/* Settings Section */}
      <Text variant="titleSmall" style={styles.sectionHeader}>App</Text>
      <List.Section style={styles.section}>
        {can('settings.edit') && (
          <List.Item
            title="Settings"
            description="Theme, units, currency, notifications"
            left={props => <List.Icon {...props} icon="cog" />}
            right={props => <List.Icon {...props} icon="chevron-right" />}
            onPress={() => navigation.navigate('Settings')}
          />
        )}
      </List.Section>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  syncBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 16,
  },
  sectionHeader: {
    paddingHorizontal: 16,
    paddingTop: 20,
    paddingBottom: 4,
    opacity: 0.7,
  },
  section: {
    paddingHorizontal: 0,
  },
});

export default MoreScreen;
