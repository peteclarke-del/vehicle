import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useTheme} from 'react-native-paper';

// Screens
import DashboardScreen from '../screens/DashboardScreen';
import VehiclesScreen from '../screens/VehiclesScreen';
import VehicleDetailScreen from '../screens/VehicleDetailScreen';
import VehicleFormScreen from '../screens/VehicleFormScreen';
import FuelRecordsScreen from '../screens/FuelRecordsScreen';
import FuelRecordFormScreen from '../screens/FuelRecordFormScreen';
import ServiceRecordsScreen from '../screens/ServiceRecordsScreen';
import ServiceRecordFormScreen from '../screens/ServiceRecordFormScreen';
import PartsScreen from '../screens/PartsScreen';
import PartFormScreen from '../screens/PartFormScreen';
import ConsumablesScreen from '../screens/ConsumablesScreen';
import ConsumableFormScreen from '../screens/ConsumableFormScreen';
import MotRecordsScreen from '../screens/MotRecordsScreen';
import MotRecordDetailScreen from '../screens/MotRecordDetailScreen';
import MotRecordFormScreen from '../screens/MotRecordFormScreen';
import QuickFuelScreen from '../screens/QuickFuelScreen';
import VehicleLookupScreen from '../screens/VehicleLookupScreen';
import MoreScreen from '../screens/MoreScreen';
import SettingsScreen from '../screens/SettingsScreen';
import CameraScreen from '../screens/CameraScreen';
import AttachmentViewerScreen from '../screens/AttachmentViewerScreen';

export type MainTabParamList = {
  DashboardTab: undefined;
  VehiclesTab: undefined;
  FuelTab: undefined;
  ServiceTab: undefined;
  MoreTab: undefined;
};

export type MainStackParamList = {
  MainTabs: undefined;
  VehicleDetail: {vehicleId: number};
  VehicleForm: {vehicleId?: number};
  FuelRecordForm: {recordId?: number; vehicleId?: number};
  ServiceRecordForm: {recordId?: number; vehicleId?: number};
  PartForm: {partId?: number; vehicleId?: number};
  ConsumableForm: {consumableId?: number; vehicleId?: number};
  PartsList: undefined;
  ConsumablesList: undefined;
  MotRecordsList: undefined;
  MotRecordDetail: {recordId: number; vehicleId: number};
  MotRecordForm: {recordId?: number; vehicleId?: number};
  QuickFuel: undefined;
  VehicleLookup: undefined;
  Settings: undefined;
  PartsTab: undefined;
  Camera: {
    vehicleId?: number;
    attachmentType?: 'receipt' | 'vehicle' | 'general';
    returnTo?: string;
  };
  AttachmentViewer: {
    attachmentId: number;
    uri?: string;
    mimeType?: string;
  };
};

const Tab = createBottomTabNavigator<MainTabParamList>();
const Stack = createNativeStackNavigator<MainStackParamList>();

const TabNavigator: React.FC = () => {
  const theme = useTheme();

  return (
    <Tab.Navigator
      screenOptions={({route}) => ({
        tabBarIcon: ({focused, color, size}) => {
          let iconName: string;

          switch (route.name) {
            case 'DashboardTab':
              iconName = focused ? 'view-dashboard' : 'view-dashboard-outline';
              break;
            case 'VehiclesTab':
              iconName = focused ? 'car' : 'car-outline';
              break;
            case 'FuelTab':
              iconName = focused ? 'gas-station' : 'gas-station-outline';
              break;
            case 'ServiceTab':
              iconName = focused ? 'wrench' : 'wrench-outline';
              break;
            case 'MoreTab':
              iconName = focused ? 'dots-horizontal-circle' : 'dots-horizontal-circle-outline';
              break;
            default:
              iconName = 'circle';
          }

          return <Icon name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.onSurfaceVariant,
        tabBarStyle: {
          backgroundColor: theme.colors.surface,
          borderTopColor: theme.colors.outlineVariant,
        },
        headerStyle: {
          backgroundColor: theme.colors.surface,
        },
        headerTintColor: theme.colors.onSurface,
      })}>
      <Tab.Screen
        name="DashboardTab"
        component={DashboardScreen}
        options={{title: 'Dashboard'}}
      />
      <Tab.Screen
        name="VehiclesTab"
        component={VehiclesScreen}
        options={{title: 'Vehicles'}}
      />
      <Tab.Screen
        name="FuelTab"
        component={FuelRecordsScreen}
        options={{title: 'Fuel'}}
      />
      <Tab.Screen
        name="ServiceTab"
        component={ServiceRecordsScreen}
        options={{title: 'Service'}}
      />
      <Tab.Screen
        name="MoreTab"
        component={MoreScreen}
        options={{title: 'More'}}
      />
    </Tab.Navigator>
  );
};

const MainNavigator: React.FC = () => {
  const theme = useTheme();

  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: theme.colors.surface,
        },
        headerTintColor: theme.colors.onSurface,
      }}>
      <Stack.Screen
        name="MainTabs"
        component={TabNavigator}
        options={{headerShown: false}}
      />
      <Stack.Screen
        name="VehicleDetail"
        component={VehicleDetailScreen}
        options={{title: 'Vehicle Details'}}
      />
      <Stack.Screen
        name="VehicleForm"
        component={VehicleFormScreen}
        options={({route}) => ({
          title: route.params?.vehicleId ? 'Edit Vehicle' : 'Add Vehicle',
        })}
      />
      <Stack.Screen
        name="FuelRecordForm"
        component={FuelRecordFormScreen}
        options={({route}) => ({
          title: route.params?.recordId ? 'Edit Fuel Record' : 'Add Fuel Record',
        })}
      />
      <Stack.Screen
        name="ServiceRecordForm"
        component={ServiceRecordFormScreen}
        options={({route}) => ({
          title: route.params?.recordId ? 'Edit Service' : 'Add Service',
        })}
      />
      <Stack.Screen
        name="PartsList"
        component={PartsScreen}
        options={{title: 'Parts'}}
      />
      <Stack.Screen
        name="PartForm"
        component={PartFormScreen}
        options={({route}) => ({
          title: route.params?.partId ? 'Edit Part' : 'Add Part',
        })}
      />
      <Stack.Screen
        name="ConsumablesList"
        component={ConsumablesScreen}
        options={{title: 'Consumables'}}
      />
      <Stack.Screen
        name="ConsumableForm"
        component={ConsumableFormScreen}
        options={({route}) => ({
          title: route.params?.consumableId ? 'Edit Consumable' : 'Add Consumable',
        })}
      />
      <Stack.Screen
        name="MotRecordsList"
        component={MotRecordsScreen}
        options={{title: 'MOT Records'}}
      />
      <Stack.Screen
        name="MotRecordDetail"
        component={MotRecordDetailScreen}
        options={{title: 'MOT Record'}}
      />
      <Stack.Screen
        name="MotRecordForm"
        component={MotRecordFormScreen}
        options={({route}) => ({
          title: route.params?.recordId ? 'Edit MOT Record' : 'Add MOT Record',
        })}
      />
      <Stack.Screen
        name="QuickFuel"
        component={QuickFuelScreen}
        options={{title: 'Quick Fuel Up'}}
      />
      <Stack.Screen
        name="VehicleLookup"
        component={VehicleLookupScreen}
        options={{title: 'Vehicle Lookup'}}
      />
      <Stack.Screen
        name="Settings"
        component={SettingsScreen}
        options={{title: 'Settings'}}
      />
      <Stack.Screen
        name="Camera"
        component={CameraScreen}
        options={{
          title: 'Take Photo',
          headerShown: false,
          presentation: 'fullScreenModal',
        }}
      />
      <Stack.Screen
        name="AttachmentViewer"
        component={AttachmentViewerScreen}
        options={{title: 'Attachment'}}
      />
    </Stack.Navigator>
  );
};

export default MainNavigator;
