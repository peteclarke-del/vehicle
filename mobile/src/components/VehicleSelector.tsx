import React from 'react';
import {View, StyleSheet, ScrollView} from 'react-native';
import {Text, Chip, useTheme} from 'react-native-paper';

interface Vehicle {
  id: number;
  registration: string;
  name: string | null;
}

interface VehicleSelectorProps {
  vehicles: Vehicle[];
  selectedVehicleId: number | 'all';
  onSelect: (id: number | 'all') => void;
  includeAll?: boolean;
  allLabel?: string;
}

const VehicleSelector: React.FC<VehicleSelectorProps> = ({
  vehicles,
  selectedVehicleId,
  onSelect,
  includeAll = false,
  allLabel = 'All',
}) => {
  const theme = useTheme();

  const getVehicleLabel = (vehicle: Vehicle) => {
    if (vehicle.name) {
      return `${vehicle.name} (${vehicle.registration})`;
    }
    return vehicle.registration;
  };

  return (
    <View style={styles.container}>
      <Text variant="labelMedium" style={[styles.label, {color: theme.colors.onSurfaceVariant}]}>
        Select Vehicle
      </Text>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}>
        {includeAll && (
          <Chip
            selected={selectedVehicleId === 'all'}
            onPress={() => onSelect('all')}
            style={styles.chip}
            mode={selectedVehicleId === 'all' ? 'flat' : 'outlined'}>
            {allLabel}
          </Chip>
        )}
        {vehicles.map(vehicle => (
          <Chip
            key={vehicle.id}
            selected={selectedVehicleId === vehicle.id}
            onPress={() => onSelect(vehicle.id)}
            style={styles.chip}
            mode={selectedVehicleId === vehicle.id ? 'flat' : 'outlined'}>
            {getVehicleLabel(vehicle)}
          </Chip>
        ))}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginBottom: 16,
  },
  label: {
    marginBottom: 8,
    paddingHorizontal: 4,
  },
  scrollContent: {
    paddingHorizontal: 4,
  },
  chip: {
    marginRight: 8,
  },
});

export default VehicleSelector;
