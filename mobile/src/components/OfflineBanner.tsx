import React from 'react';
import {View, StyleSheet} from 'react-native';
import {Text, useTheme} from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

interface OfflineBannerProps {
  message?: string;
}

const OfflineBanner: React.FC<OfflineBannerProps> = ({
  message = 'Offline — showing cached data',
}) => {
  const theme = useTheme();

  return (
    <View
      style={[
        styles.container,
        {backgroundColor: theme.colors.errorContainer},
      ]}>
      <Icon
        name="cloud-off-outline"
        size={16}
        color={theme.colors.onErrorContainer}
      />
      <Text style={[styles.text, {color: theme.colors.onErrorContainer}]}>
        {message}
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    padding: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  text: {
    marginLeft: 6,
    fontSize: 13,
  },
});

export default OfflineBanner;
