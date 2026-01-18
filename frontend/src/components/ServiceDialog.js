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
import { useDistance } from '../hooks/useDistance';
import ReceiptUpload from './ReceiptUpload';

const ServiceDialog = ({ open, serviceRecord, vehicleId, onClose }) => {
  const [formData, setFormData] = useState({
    serviceDate: new Date().toISOString().split('T')[0],
    serviceType: 'Full Service',
    laborCost: '',
    partsCost: '0',
    mileage: '',
    serviceProvider: '',
    workPerformed: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState(null);
  const { api } = useAuth();
  const { t } = useTranslation();
  const { convert, toKm, getLabel } = useDistance();

  useEffect(() => {
    if (open) {
      if (serviceRecord) {
        setFormData({
          serviceDate: serviceRecord.serviceDate || '',
          serviceType: serviceRecord.serviceType || 'Full Service',
          laborCost: serviceRecord.laborCost || '',
          partsCost: serviceRecord.partsCost || '0',
          mileage: serviceRecord.mileage ? Math.round(convert(serviceRecord.mileage)) : '',
          serviceProvider: serviceRecord.serviceProvider || '',
          workPerformed: serviceRecord.workPerformed || '',
          notes: serviceRecord.notes || '',
        });
        setReceiptAttachmentId(serviceRecord.receiptAttachmentId || null);
      } else {
        setFormData({
          serviceDate: new Date().toISOString().split('T')[0],
          serviceType: 'Full Service',
          laborCost: '',
          partsCost: '0',
          mileage: '',
          serviceProvider: '',
          workPerformed: '',
          notes: '',
        });
        setReceiptAttachmentId(null);
      }
    }
  }, [open, serviceRecord]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleReceiptUploaded = (attachmentId, ocrData) => {
    setReceiptAttachmentId(attachmentId);
    const updates = {};
    if (ocrData.serviceType) updates.serviceType = ocrData.serviceType;
    if (ocrData.laborCost) updates.laborCost = ocrData.laborCost;
    if (ocrData.partsCost) updates.partsCost = ocrData.partsCost;
    if (ocrData.serviceProvider) updates.serviceProvider = ocrData.serviceProvider;
    if (ocrData.date) updates.serviceDate = ocrData.date;
    if (ocrData.mileage) updates.mileage = ocrData.mileage;
    if (ocrData.workPerformed) updates.workPerformed = ocrData.workPerformed;
    if (Object.keys(updates).length > 0) {
      setFormData(prev => ({ ...prev, ...updates }));
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const data = { 
        ...formData, 
        vehicleId,
        mileage: formData.mileage ? Math.round(toKm(parseFloat(formData.mileage))) : null,
        receiptAttachmentId
      };
      if (serviceRecord) {
        await api.put(`/service-records/${serviceRecord.id}`, data);
      } else {
        await api.post('/service-records', data);
      }
      onClose(true);
    } catch (error) {
      console.error('Error saving service record:', error);
      alert(t('common.saveError', { type: 'service record' }));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        {serviceRecord ? t('service.editService') : t('service.addService')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="date"
                name="serviceDate"
                label={t('service.serviceDate')}
                value={formData.serviceDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                select
                required
                name="serviceType"
                label={t('service.serviceType')}
                value={formData.serviceType}
                onChange={handleChange}
              >
                <MenuItem value="Full Service">{t('service.fullService')}</MenuItem>
                <MenuItem value="Interim Service">{t('service.interimService')}</MenuItem>
                <MenuItem value="Oil Change">{t('service.oilChange')}</MenuItem>
                <MenuItem value="Brake Service">{t('service.brakeService')}</MenuItem>
                <MenuItem value="Other">{t('service.other')}</MenuItem>
              </TextField>
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                required
                type="number"
                name="laborCost"
                label={t('service.laborCost')}
                value={formData.laborCost}
                onChange={handleChange}
                inputProps={{ min: 0, step: 0.01 }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="partsCost"
                label={t('service.partsCost')}
                value={formData.partsCost}
                onChange={handleChange}
                inputProps={{ min: 0, step: 0.01 }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="mileage"
                label={`${t('service.mileage')} (${getLabel()})`}
                value={formData.mileage}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                name="serviceProvider"
                label={t('service.serviceProvider')}
                value={formData.serviceProvider}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={3}
                name="workPerformed"
                label={t('service.workPerformed')}
                value={formData.workPerformed}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <ReceiptUpload
                entityType="service"
                receiptAttachmentId={receiptAttachmentId}
                onReceiptUploaded={handleReceiptUploaded}
                onReceiptRemoved={() => setReceiptAttachmentId(null)}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={2}
                name="notes"
                label={t('service.notes')}
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

export default ServiceDialog;
