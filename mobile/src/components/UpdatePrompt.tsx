import React, {useCallback, useEffect, useState} from 'react';
import {Alert} from 'react-native';
import {Button, Dialog, Portal, Text} from 'react-native-paper';
import {useTranslation} from 'react-i18next';
import Config from '../config';
import {
  AvailableUpdate,
  checkForUpdate,
  compareVersions,
  dismissVersion,
  getDismissedVersion,
  getInstalledVersion,
  installUpdate,
  isUpdaterAvailable,
} from '../services/appUpdate';

interface Props {
  /**
   * App version the connected server expects. When the installed app is
   * older, the prompt is shown even if the user dismissed that release before.
   */
  serverExpectsVersion?: string;
}

/**
 * Update check on launch (and when the server reports a newer expected app
 * version); prompts once per new release unless the server needs it.
 */
const UpdatePrompt = ({serverExpectsVersion}: Props) => {
  const {t} = useTranslation();
  const [update, setUpdate] = useState<AvailableUpdate | null>(null);
  const [installedVersion, setInstalledVersion] = useState<string | null>(null);
  // Server wants a newer app but no downloadable release was found.
  const [serverOnly, setServerOnly] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const installed = await getInstalledVersion(Config.APP_VERSION);
      if (cancelled) {
        return;
      }
      setInstalledVersion(installed);
      const neededByServer = !!serverExpectsVersion
        && compareVersions(installed, serverExpectsVersion) < 0;
      let found: AvailableUpdate | null = null;
      if (isUpdaterAvailable()) {
        try {
          found = await checkForUpdate(installed);
        } catch {
          // offline or rate-limited: try again next launch
        }
      }
      if (cancelled) {
        return;
      }
      if (found && (neededByServer || (await getDismissedVersion()) !== found.version)) {
        setUpdate(found);
        setServerOnly(false);
      } else if (neededByServer) {
        setUpdate(null);
        setServerOnly(true);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [serverExpectsVersion]);

  const later = useCallback(async () => {
    if (update) {
      await dismissVersion(update.version);
    }
    setUpdate(null);
    setServerOnly(false);
  }, [update]);

  const install = useCallback(async () => {
    if (!update) {
      return;
    }
    setBusy(true);
    try {
      const result = await installUpdate(update);
      if (result === 'needs-permission') {
        Alert.alert(t('update.title'), t('update.permissionNeeded'));
        return;
      }
      setUpdate(null);
    } catch (e: any) {
      Alert.alert(t('update.title'), t('update.failed', {error: e?.message || String(e)}));
    } finally {
      setBusy(false);
    }
  }, [update, t]);

  const neededByServer = !!serverExpectsVersion && !!installedVersion
    && compareVersions(installedVersion, serverExpectsVersion) < 0;

  if (!update && !serverOnly) {
    return null;
  }

  return (
    <Portal>
      <Dialog visible onDismiss={later}>
        <Dialog.Title>
          {neededByServer
            ? t('update.serverNewerTitle')
            : t('update.available', {version: update?.version})}
        </Dialog.Title>
        <Dialog.Content>
          {neededByServer ? (
            <Text variant="bodyMedium" style={{marginBottom: 8}}>
              {t('update.serverNewer', {installed: installedVersion, expected: serverExpectsVersion})}
            </Text>
          ) : null}
          {update ? (
            <Text variant="bodyMedium" numberOfLines={12}>
              {update.notes || t('update.noNotes')}
            </Text>
          ) : (
            <Text variant="bodyMedium">{t('update.serverNewerNoDownload')}</Text>
          )}
        </Dialog.Content>
        <Dialog.Actions>
          <Button onPress={later} disabled={busy}>{t('update.later')}</Button>
          {update ? (
            <Button mode="contained" onPress={install} loading={busy} disabled={busy}>
              {t('update.install')}
            </Button>
          ) : null}
        </Dialog.Actions>
      </Dialog>
    </Portal>
  );
};

export default UpdatePrompt;
