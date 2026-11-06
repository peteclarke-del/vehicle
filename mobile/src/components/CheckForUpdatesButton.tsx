import React, {useState} from 'react';
import {Alert} from 'react-native';
import {Button, List} from 'react-native-paper';
import {useTranslation} from 'react-i18next';
import Config from '../config';
import {checkForUpdate, getInstalledVersion, installUpdate, isUpdaterAvailable} from '../services/appUpdate';

/** Manual "Check for updates" (Settings and the Update Required screen). */
const CheckForUpdatesButton = ({variant = 'button'}: {variant?: 'button' | 'list'}) => {
  const {t} = useTranslation();
  const [busy, setBusy] = useState(false);

  const run = async () => {
    if (!isUpdaterAvailable()) {
      Alert.alert(t('update.title'), t('update.unsupported'));
      return;
    }
    setBusy(true);
    try {
      const installed = await getInstalledVersion(Config.APP_VERSION);
      const update = await checkForUpdate(installed);
      if (!update) {
        Alert.alert(t('update.title'), t('update.upToDate', {version: installed}));
        return;
      }
      Alert.alert(t('update.available', {version: update.version}), update.notes || t('update.noNotes'), [
        {text: t('update.later'), style: 'cancel'},
        {
          text: t('update.install'),
          onPress: async () => {
            try {
              if ((await installUpdate(update)) === 'needs-permission') {
                Alert.alert(t('update.title'), t('update.permissionNeeded'));
              }
            } catch (e: any) {
              Alert.alert(t('update.title'), t('update.failed', {error: e?.message || String(e)}));
            }
          },
        },
      ]);
    } catch (e: any) {
      Alert.alert(t('update.title'), t('update.checkFailed', {error: e?.message || String(e)}));
    } finally {
      setBusy(false);
    }
  };

  if (variant === 'list') {
    return (
      <List.Item
        title={t('update.checkForUpdates')}
        description={busy ? t('update.checking') : undefined}
        left={props => <List.Icon {...props} icon="cloud-download" />}
        onPress={busy ? undefined : run}
      />
    );
  }
  return (
    <Button mode="contained-tonal" icon="cloud-download" onPress={run} loading={busy} disabled={busy}>
      {t('update.checkForUpdates')}
    </Button>
  );
};

export default CheckForUpdatesButton;
