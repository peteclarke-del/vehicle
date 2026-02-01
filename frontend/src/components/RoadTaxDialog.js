import React, { useEffect, useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  FormControlLabel,
  Checkbox,
  MenuItem,
  Select,
  FormControl,
  InputLabel,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import KnightRiderLoader from './KnightRiderLoader';
import logger from '../utils/logger';

const RoadTaxDialog = ({ open, roadTaxRecord, vehicleId, vehicles, onClose }) => {
  const [formData, setFormData] = useState({
    vehicleId: vehicleId || '',
    startDate: new Date().toISOString().split('T')[0],
    expiryDate: '',
    amount: '',
    sorn: false,
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const { api } = useAuth();
  const { t } = useTranslation();

  useEffect(() => {
    if (open) {
      if (roadTaxRecord) {
        setFormData({
          vehicleId: roadTaxRecord.vehicleId || vehicleId || '',
          startDate: roadTaxRecord.startDate || '',
          expiryDate: roadTaxRecord.expiryDate || '',
          amount: roadTaxRecord.amount || '',
          sorn: roadTaxRecord.sorn || false,
          notes: roadTaxRecord.notes || '',
        });
      } else {
        setFormData({ 
          vehicleId: vehicleId || '', 
          startDate: new Date().toISOString().split('T')[0], 
          expiryDate: '', 
          amount: '', 
          sorn: false, 
          notes: '' 
        });
      }
    }
  }, [open, roadTaxRecord, vehicleId]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const data = {
        ...formData,
        vehicleId: formData.vehicleId,
        amount: formData.amount ? parseFloat(formData.amount) : null,
      };
      if (roadTaxRecord) {
        await api.put(`/road-tax/${roadTaxRecord.id}`, data);
      } else {
        await api.post('/road-tax', data);
      }
      onClose(true);
    } catch (error) {
      logger.error('Error saving road tax record:', error);
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
            {(!vehicleId || vehicleId === '__all__') && vehicles && vehicles.length > 0 && (
              <Grid item xs={12}>
                <FormControl fullWidth required>
                  <InputLabel>{t('common.vehicle')}</InputLabel>
                  <Select
                    name="vehicleId"
                    value={formData.vehicleId}
                    onChange={handleChange}
                    label={t('common.vehicle')}
                  >
                    {vehicles.map((v) => (
                      <MenuItem key={v.id} value={v.id}>
                        {v.registrationNumber} - {v.name}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>
              </Grid>
            )}
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
              <FormControlLabel
                control={
                  <Checkbox
                    checked={formData.sorn}
                    onChange={(e) => setFormData({ ...formData, sorn: e.target.checked, amount: e.target.checked ? '' : formData.amount })}
                    name="sorn"
                  />
                }
                label={t('roadTax.sorn')}
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
                disabled={formData.sorn}
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
            {loading ? <KnightRiderLoader size={18} /> : t('common.save')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
};

export default RoadTaxDialog;
