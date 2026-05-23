import React from 'react';
import {ScrollView, StyleSheet, View} from 'react-native';
import {Button, Card, List, Text, useTheme} from 'react-native-paper';
import {SafeAreaView} from 'react-native-safe-area-context';
import {AppCompatibilityEvaluation, AppCompatibilityPayload} from '../services/appCompatibility';

interface UpdateRequiredScreenProps {
  evaluation: AppCompatibilityEvaluation;
  payload: AppCompatibilityPayload | null;
  appVersion: string;
  onRetry: () => void;
  onChangeServer: () => void;
}

const UpdateRequiredScreen: React.FC<UpdateRequiredScreenProps> = ({
  evaluation,
  payload,
  appVersion,
  onRetry,
  onChangeServer,
}) => {
  const theme = useTheme();

  return (
    <SafeAreaView style={[styles.container, {backgroundColor: theme.colors.background}]}> 
      <ScrollView contentContainerStyle={styles.content}>
        <Card mode="outlined">
          <Card.Content>
            <Text variant="headlineSmall" style={styles.title}>
              Update Required
            </Text>
            <Text variant="bodyMedium" style={styles.subtitle}>
              This mobile app and server are not on a supported compatibility combination.
            </Text>

            <List.Section>
              {evaluation.reasons.map(reason => (
                <List.Item
                  key={reason}
                  title={reason}
                  left={props => <List.Icon {...props} icon="alert-circle-outline" />}
                />
              ))}
            </List.Section>

            <View style={styles.metaBlock}>
              <Text variant="labelLarge">Mobile app</Text>
              <Text variant="bodyMedium">Installed version: {appVersion}</Text>
              {payload?.mobile?.minimumSupportedVersion ? (
                <Text variant="bodyMedium">
                  Minimum supported: {payload.mobile.minimumSupportedVersion}
                </Text>
              ) : null}
              {payload?.mobile?.latestSupportedVersion ? (
                <Text variant="bodyMedium">
                  Latest supported: {payload.mobile.latestSupportedVersion}
                </Text>
              ) : null}
            </View>

            <View style={styles.metaBlock}>
              <Text variant="labelLarge">Server</Text>
              <Text variant="bodyMedium">
                Release version: {payload?.server?.releaseVersion || 'Unknown'}
              </Text>
              <Text variant="bodyMedium">
                Internal version: {payload?.server?.internalVersion || 'Unknown'}
              </Text>
              {payload?.compatibility?.apiCompatibilityVersion !== undefined ? (
                <Text variant="bodyMedium">
                  API compatibility version: {payload.compatibility.apiCompatibilityVersion}
                </Text>
              ) : null}
              {payload?.server?.compatibilityBaselineCommit ? (
                <Text variant="bodyMedium">
                  Baseline: {payload.server.compatibilityBaselineCommit}
                  {payload.server.compatibilityBaselineLabel ? ` (${payload.server.compatibilityBaselineLabel})` : ''}
                </Text>
              ) : null}
            </View>

            <View style={styles.buttonRow}>
              <Button mode="outlined" onPress={onChangeServer} style={styles.button}>
                Change Server
              </Button>
              <Button mode="contained" onPress={onRetry} style={styles.button}>
                Retry Check
              </Button>
            </View>
          </Card.Content>
        </Card>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  content: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 20,
  },
  title: {
    marginBottom: 8,
  },
  subtitle: {
    marginBottom: 12,
  },
  metaBlock: {
    marginTop: 16,
    gap: 4,
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 24,
  },
  button: {
    flex: 1,
  },
});

export default UpdateRequiredScreen;