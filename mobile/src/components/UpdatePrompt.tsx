import React, {useCallback, useEffect, useState} from 'react';
import {Alert} from 'react-native';
import {Button, Dialog, Portal, Text} from 'react-native-paper';
import {useTranslation} from 'react-i18next';
import Config from '../config';
import {
  AvailableUpdate,
  checkForUpdate,
  dismissVersion,
  getDismissedVersion,
  getInstalledVersion,
  installUpdate,
  isUpdaterAvailable,
} from '../services/appUpdate';

/** Silent update check on launch; prompts once per new version. */
const UpdatePrompt = () => {
  const {t} = useTranslation();
  const [update, setUpdate] = useState<AvailableUpdate | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!isUpdaterAvailable()) {
      return;
    }
    let cancelled = false;
    (async () => {
      try {
        const installed = await getInstalledVersion(Config.APP_VERSION);
        const found = await checkForUpdate(installed);
        if (!found || cancelled) {
          return;
        }
        if ((await getDismissedVersion()) === found.version) {
          return;
        }
        setUpdate(found);
      } catch {
        // offline or rate-limited: try again next launch
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const later = useCallback(async () => {
    if (update) {
      await dismissVersion(update.version);
    }
    setUpdate(null);
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

  if (!update) {
    return null;
  }

  return (
    <Portal>
      <Dialog visible onDismiss={later}>
        <Dialog.Title>{t('update.available', {version: update.version})}</Dialog.Title>
        <Dialog.Content>
          <Text variant="bodyMedium" numberOfLines={12}>
            {update.notes || t('update.noNotes')}
          </Text>
        </Dialog.Content>
        <Dialog.Actions>
          <Button onPress={later} disabled={busy}>{t('update.later')}</Button>
          <Button mode="contained" onPress={install} loading={busy} disabled={busy}>
            {t('update.install')}
          </Button>
        </Dialog.Actions>
      </Dialog>
    </Portal>
  );
};

export default UpdatePrompt;
