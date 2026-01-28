import React, { useEffect, useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  CircularProgress,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';

const RoadTaxDialog = ({ open, roadTaxRecord, vehicleId, onClose }) => {
  const [formData, setFormData] = useState({
    startDate: new Date().toISOString().split('T')[0],
    expiryDate: '',
    amount: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const { api } = useAuth();
  const { t } = useTranslation();

  useEffect(() => {
    if (open) {
      if (roadTaxRecord) {
        setFormData({
          startDate: roadTaxRecord.startDate || '',
          expiryDate: roadTaxRecord.expiryDate || '',
          amount: roadTaxRecord.amount || '',
          notes: roadTaxRecord.notes || '',
        });
      } else {
        setFormData({ startDate: new Date().toISOString().split('T')[0], expiryDate: '', amount: '', notes: '' });
      }
    }
  }, [open, roadTaxRecord]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const data = {
        ...formData,
        vehicleId,
        amount: formData.amount ? parseFloat(formData.amount) : null,
      };
      if (roadTaxRecord) {
        await api.put(`/road-tax/${roadTaxRecord.id}`, data);
      } else {
        await api.post('/road-tax', data);
      }
      onClose(true);
    } catch (error) {
      console.error('Error saving road tax record:', error);
      alert(t('common.saveError', { type: 'Road tax record' }));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>{roadTaxRecord ? t('roadTax.edit') : t('roadTax.add')}</DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="date"
                name="startDate"
                label={t('roadTax.startDate')}
                value={formData.startDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                type="date"
                name="expiryDate"
                label={t('roadTax.expiryDate')}
                value={formData.expiryDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                type="number"
                name="amount"
                label={t('roadTax.amount')}
                value={formData.amount}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={3}
                name="notes"
                label={t('common.notes')}
                value={formData.notes}
                onChange={handleChange}
              />
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => onClose(false)}>{t('common.cancel')}</Button>
          <Button type="submit" variant="contained" disabled={loading}>
            {loading ? <CircularProgress size={24} /> : t('common.save')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
};

export default RoadTaxDialog;
