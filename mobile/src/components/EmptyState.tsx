import React from 'react';
import {View, StyleSheet} from 'react-native';
import {Text, useTheme} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

interface EmptyStateProps {
  icon: string;
  message: string;
  subtitle?: string;
}

const EmptyState: React.FC<EmptyStateProps> = ({icon, message, subtitle}) => {
  const theme = useTheme();

  return (
    <View style={styles.container}>
      <Icon name={icon} size={64} color={theme.colors.onSurfaceVariant} />
      <Text
        variant="bodyLarge"
        style={[styles.message, {color: theme.colors.onSurfaceVariant}]}>
        {message}
      </Text>
      {subtitle && (
        <Text
          variant="bodySmall"
          style={{color: theme.colors.onSurfaceVariant, marginTop: 4}}>
          {subtitle}
        </Text>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 64,
  },
  message: {
    marginTop: 16,
  },
});

export default EmptyState;
