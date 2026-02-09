import React from 'react';
import {View, StyleSheet} from 'react-native';
import {ActivityIndicator, useTheme} from 'react-native-paper';

const LoadingScreen: React.FC = () => {
  const theme = useTheme();

  return (
    <View
      style={[
        styles.container,
        {backgroundColor: theme.colors.background},
      ]}>
      <ActivityIndicator size="large" color={theme.colors.primary} />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
});

export default LoadingScreen;
