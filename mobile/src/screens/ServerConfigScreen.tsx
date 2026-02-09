import React, {useState} from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import {
  Text,
  TextInput,
  Button,
  Card,
  useTheme,
  Divider,
  HelperText,
} from 'react-native-paper';
import {SafeAreaView} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import axios from 'axios';
import {useServerConfig} from '../contexts/ServerConfigContext';

const ServerConfigScreen: React.FC = () => {
  const theme = useTheme();
  const {setConfig} = useServerConfig();

  const [serverUrl, setServerUrl] = useState('');
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<{success: boolean; message: string} | null>(null);
  const [error, setError] = useState<string | null>(null);

  const normalizeUrl = (url: string): string => {
    let normalized = url.trim();
    if (!normalized) {
      return '';
    }
    // Add https:// if no protocol specified
    if (!normalized.startsWith('http://') && !normalized.startsWith('https://')) {
      normalized = 'https://' + normalized;
    }
    // Remove trailing slashes
    normalized = normalized.replace(/\/+$/, '');
    // Append /api if not already present
    if (!normalized.endsWith('/api')) {
      normalized += '/api';
    }
    return normalized;
  };

  const testConnection = async () => {
    const url = normalizeUrl(serverUrl);
    if (!url) {
      setError('Please enter a server URL');
      return;
    }

    setTesting(true);
    setTestResult(null);
    setError(null);

    try {
      await axios.get(url, {timeout: 10000});
      setTestResult({success: true, message: 'Connection successful!'});
    } catch (err: any) {
      // A response status means the server is reachable (even 401/403/404)
      if (err.response?.status) {
        setTestResult({
          success: true,
          message: `Server reachable (HTTP ${err.response.status})`,
        });
      } else {
        setTestResult({
          success: false,
          message: 'Could not connect to server. Check the URL and try again.',
        });
      }
    } finally {
      setTesting(false);
    }
  };

  const connectToServer = async () => {
    const url = normalizeUrl(serverUrl);
    if (!url) {
      setError('Please enter a server URL');
      return;
    }
    await setConfig('web', url);
  };

  const useStandalone = async () => {
    await setConfig('standalone');
  };

  return (
    <SafeAreaView style={[styles.container, {backgroundColor: theme.colors.background}]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.flex}>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled">
          {/* Header */}
          <View style={styles.header}>
            <Icon name="car-cog" size={64} color={theme.colors.primary} />
            <Text
              variant="headlineLarge"
              style={[styles.title, {color: theme.colors.primary}]}>
              Vehicle Manager
            </Text>
            <Text
              variant="bodyLarge"
              style={{color: theme.colors.onSurfaceVariant, textAlign: 'center'}}>
              Choose how you'd like to use the app
            </Text>
          </View>

          {/* Web Mode Card */}
          <Card style={styles.card} mode="outlined">
            <Card.Content>
              <View style={styles.cardHeader}>
                <Icon name="cloud-sync" size={28} color={theme.colors.primary} />
                <Text
                  variant="titleLarge"
                  style={[styles.cardTitle, {color: theme.colors.onSurface}]}>
                  Connect to Server
                </Text>
              </View>
              <Text
                variant="bodyMedium"
                style={{color: theme.colors.onSurfaceVariant, marginBottom: 16}}>
                Sync your data with a remote server. Your vehicles, records, and
                settings will be backed up and accessible from multiple devices.
              </Text>

              <TextInput
                label="Server URL"
                value={serverUrl}
                onChangeText={text => {
                  setServerUrl(text);
                  setError(null);
                  setTestResult(null);
                }}
                mode="outlined"
                placeholder="e.g. https://myserver.com"
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="url"
                left={<TextInput.Icon icon="server" />}
                style={styles.input}
              />

              {error && (
                <HelperText type="error" visible={!!error}>
                  {error}
                </HelperText>
              )}

              {testResult && (
                <HelperText
                  type={testResult.success ? 'info' : 'error'}
                  visible={true}
                  style={testResult.success ? {color: '#22C55E'} : undefined}>
                  {testResult.message}
                </HelperText>
              )}

              <View style={styles.buttonRow}>
                <Button
                  mode="outlined"
                  onPress={testConnection}
                  loading={testing}
                  disabled={testing || !serverUrl.trim()}
                  icon="connection"
                  style={styles.testButton}>
                  Test
                </Button>
                <Button
                  mode="contained"
                  onPress={connectToServer}
                  disabled={testing || !serverUrl.trim()}
                  icon="login"
                  style={styles.connectButton}>
                  Connect
                </Button>
              </View>
            </Card.Content>
          </Card>

          {/* Divider */}
          <View style={styles.dividerRow}>
            <Divider style={styles.divider} />
            <Text
              variant="bodyMedium"
              style={{color: theme.colors.onSurfaceVariant, marginHorizontal: 16}}>
              or
            </Text>
            <Divider style={styles.divider} />
          </View>

          {/* Standalone Mode Card */}
          <Card style={styles.card} mode="outlined">
            <Card.Content>
              <View style={styles.cardHeader}>
                <Icon name="cellphone" size={28} color={theme.colors.secondary} />
                <Text
                  variant="titleLarge"
                  style={[styles.cardTitle, {color: theme.colors.onSurface}]}>
                  Standalone Mode
                </Text>
              </View>
              <Text
                variant="bodyMedium"
                style={{color: theme.colors.onSurfaceVariant, marginBottom: 16}}>
                Use the app entirely on this device. All data is stored locally —
                no server or account required. You can switch to server mode later.
              </Text>

              <Button
                mode="contained-tonal"
                onPress={useStandalone}
                icon="cellphone-check">
                Use Standalone Mode
              </Button>
            </Card.Content>
          </Card>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  flex: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    padding: 24,
    justifyContent: 'center',
  },
  header: {
    alignItems: 'center',
    marginBottom: 32,
  },
  title: {
    fontWeight: 'bold',
    marginTop: 12,
    marginBottom: 8,
  },
  card: {
    marginBottom: 16,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  cardTitle: {
    marginLeft: 12,
    fontWeight: '600',
  },
  input: {
    marginBottom: 8,
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  testButton: {
    flex: 1,
  },
  connectButton: {
    flex: 2,
  },
  dividerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: 8,
  },
  divider: {
    flex: 1,
  },
});

export default ServerConfigScreen;
