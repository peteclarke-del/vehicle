import React, { useEffect, useState } from 'react';
import {
  Alert,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Typography,
} from '@mui/material';
import { useTranslation } from 'react-i18next';

/**
 * Confirmation dialog for a Full import.
 *
 * - Without "wipe data", vehicles in the file replace vehicles with the same
 *   registration; everything else in the account is kept.
 * - "Wipe data" removes ALL of this account's vehicles, stock and attachments
 *   before importing (other users are never touched).
 * - "Delete files" (only with wipe) also deletes the uploaded images and
 *   documents of the removed records from disk.
 */
const FullImportDialog = ({ open, fileName, onClose, onConfirm }) => {
  const { t } = useTranslation();
  const [wipeData, setWipeData] = useState(false);
  const [deleteFiles, setDeleteFiles] = useState(false);

  useEffect(() => {
    if (open) {
      setWipeData(false);
      setDeleteFiles(false);
    }
  }, [open]);

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>{t('importExport.fullImport') || 'Full import'}</DialogTitle>
      <DialogContent>
        <Typography variant="body2" sx={{ mb: 2 }}>
          {t('importExport.fullImportFile', { file: fileName }) || `File: ${fileName}`}
        </Typography>
        <Typography variant="body2" sx={{ mb: 2 }}>
          {t('importExport.fullImportHelp')
            || 'Vehicles in the file replace vehicles with the same registration, so any changes made since the export was taken are applied. Vehicles not in the file are kept.'}
        </Typography>

        <FormControlLabel
          control={<Checkbox checked={wipeData} onChange={(e) => { setWipeData(e.target.checked); if (!e.target.checked) setDeleteFiles(false); }} />}
          label={t('importExport.fullImportWipeData') || 'Wipe my data first (start from a blank account)'}
        />
        <FormControlLabel
          sx={{ display: 'flex', ml: 3 }}
          control={<Checkbox checked={deleteFiles} disabled={!wipeData} onChange={(e) => setDeleteFiles(e.target.checked)} />}
          label={t('importExport.fullImportDeleteFiles') || 'Also delete uploaded images and documents from disk'}
        />

        {wipeData && (
          <Alert severity="warning" sx={{ mt: 2 }}>
            {deleteFiles
              ? (t('importExport.fullImportWarnFiles') || 'All of your vehicles, stock items and attachments will be deleted, and their files removed from disk, before importing. Anything not in this file will be lost.')
              : (t('importExport.fullImportWarnData') || 'All of your vehicles, stock items and attachments will be deleted before importing. Files on disk are kept. Anything not in this file will be lost.')}
          </Alert>
        )}
        <Alert severity="info" sx={{ mt: 2 }}>
          {t('importExport.fullImportSafety') || 'If any vehicle in the file fails to import, nothing is changed.'}
        </Alert>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>{t('common.cancel') || 'Cancel'}</Button>
        <Button
          variant="contained"
          color={wipeData ? 'error' : 'primary'}
          onClick={() => onConfirm({ wipeData, deleteFiles: wipeData && deleteFiles })}
        >
          {t('importExport.fullImportConfirm') || 'Start full import'}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default FullImportDialog;
