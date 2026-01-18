import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  MenuItem,
  CircularProgress,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';

const InsuranceDialog = ({ open, insurance, vehicleId, onClose }) => {
  const [formData, setFormData] = useState({
    provider: '',
    policyNumber: '',
    coverageType: 'Comprehensive',
    annualCost: '',
    startDate: new Date().toISOString().split('T')[0],
    expiryDate: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const { api } = useAuth();
  const { t } = useTranslation();

  useEffect(() => {
    if (open) {
      if (insurance) {
        setFormData({
          provider: insurance.provider || '',
          policyNumber: insurance.policyNumber || '',
          coverageType: insurance.coverageType || 'Comprehensive',
          annualCost: insurance.annualCost || '',
          startDate: insurance.startDate || '',
          expiryDate: insurance.expiryDate || '',
          notes: insurance.notes || '',
        });
      } else {
        setFormData({
          provider: '',
          policyNumber: '',
          coverageType: 'Comprehensive',
          annualCost: '',
          startDate: new Date().toISOString().split('T')[0],
          expiryDate: '',
          notes: '',
        });
      }
    }
  }, [open, insurance]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const data = { ...formData, vehicleId };
      if (insurance) {
        await api.put(`/insurance/${insurance.id}`, data);
      } else {
        await api.post('/insurance', data);
      }
      onClose(true);
    } catch (error) {
      console.error('Error saving insurance:', error);
      alert(t('common.saveError', { type: 'insurance' }));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        {insurance ? t('insurance.editInsurance') : t('insurance.addInsurance')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                name="provider"
                label={t('insurance.provider')}
                value={formData.provider}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="policyNumber"
                label={t('insurance.policyNumber')}
                value={formData.policyNumber}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                select
                required
                name="coverageType"
                label={t('insurance.coverageType')}
                value={formData.coverageType}
                onChange={handleChange}
              >
                <MenuItem value="Comprehensive">{t('insurance.comprehensive')}</MenuItem>
                <MenuItem value="Third Party">{t('insurance.thirdParty')}</MenuItem>
                <MenuItem value="Third Party, Fire & Theft">{t('insurance.thirdPartyFireTheft')}</MenuItem>
              </TextField>
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="number"
                name="annualCost"
                label={t('insurance.annualCost')}
                value={formData.annualCost}
                onChange={handleChange}
                inputProps={{ min: 0, step: 0.01 }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="date"
                name="startDate"
                label={t('insurance.startDate')}
                value={formData.startDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="date"
                name="expiryDate"
                label={t('insurance.expiryDate')}
                value={formData.expiryDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={3}
                name="notes"
                label={t('insurance.notes')}
                value={formData.notes}
                onChange={handleChange}
              />
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => onClose(false)}>{t('common.cancel')}</Button>
          <Button type="submit" variant="contained" color="primary" disabled={loading}>
            {loading ? <CircularProgress size={24} /> : t('common.save')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
};

export default InsuranceDialog;
