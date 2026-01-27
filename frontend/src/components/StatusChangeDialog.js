import React from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Button,
  Box,
  Typography,
} from '@mui/material';
import { useTranslation } from 'react-i18next';

export default function StatusChangeDialog({ open, initialData = {}, onClose, onConfirm }) {
  const { t } = useTranslation();
  const [data, setData] = React.useState({ newStatus: initialData.newStatus || '', date: initialData.date || '', notes: initialData.notes || '' });

  React.useEffect(() => {
    setData({ newStatus: initialData.newStatus || '', date: initialData.date || '', notes: initialData.notes || '' });
  }, [initialData, open]);

  const handleConfirm = () => {
    if (onConfirm) onConfirm({ ...initialData, ...data });
  };

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>{t('vehicleDialog.confirmStatus', { status: data.newStatus }) || `Confirm status: ${data.newStatus || ''}`}</DialogTitle>
      <DialogContent>
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, mt: 1 }}>
          <TextField
            label={t('vehicleDialog.statusChangeDate') || 'Date'}
            type="date"
            value={data.date}
            onChange={(e) => setData(d => ({ ...d, date: e.target.value }))}
            InputLabelProps={{ shrink: true }}
          />
          <TextField
            label={t('vehicleDialog.statusChangeNotes') || 'Notes'}
            multiline
            minRows={3}
            value={data.notes}
            onChange={(e) => setData(d => ({ ...d, notes: e.target.value }))}
          />
          <Typography variant="body2" color="text.secondary">{t('vehicleDialog.statusChangeHelp') || 'This will mark the vehicle as the selected status. You can add an optional note and a date for the status change.'}</Typography>
        </Box>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>{t('common.cancel')}</Button>
        <Button variant="contained" color="primary" onClick={handleConfirm}>{t('common.confirm') || 'Confirm'}</Button>
      </DialogActions>
    </Dialog>
  );
}
