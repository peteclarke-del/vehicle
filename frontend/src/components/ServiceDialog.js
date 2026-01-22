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
    items: [],
    mileage: '',
    serviceProvider: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState(null);
  const [motRecords, setMotRecords] = useState([]);
  const [motRecordId, setMotRecordId] = useState(null);
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
          items: serviceRecord.items || [],
          mileage: serviceRecord.mileage ? Math.round(convert(serviceRecord.mileage)) : '',
          serviceProvider: serviceRecord.serviceProvider || '',
          notes: serviceRecord.notes || '',
          notes: serviceRecord.notes || '',
        });
        setReceiptAttachmentId(serviceRecord.receiptAttachmentId || null);
        setMotRecordId(serviceRecord.motRecordId || null);
      } else {
        setFormData({
          serviceDate: new Date().toISOString().split('T')[0],
          serviceType: 'Full Service',
          laborCost: '',
          partsCost: '0',
          items: [],
          mileage: '',
          serviceProvider: '',
          notes: '',
          notes: '',
        });
        setReceiptAttachmentId(null);
        setMotRecordId(null);
      }
    }
  }, [open, serviceRecord]);

  useEffect(() => {
    if (!open || !vehicleId) return;
    (async () => {
      try {
        const resp = await api.get(`/mot-records?vehicleId=${vehicleId}`);
        setMotRecords(resp.data || []);
      } catch (err) {
        console.error('Error loading MOT records', err);
      }
    })();
  }, [open, vehicleId, api]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const updateItem = (index, key, value) => {
    const items = [...(formData.items || [])];
    items[index] = { ...(items[index] || {}), [key]: value };
    setFormData({ ...formData, items });
  };

  const addItem = () => {
    setFormData({ ...formData, items: [...(formData.items || []), { type: 'part', description: '', cost: '0.00', quantity: 1 }] });
  };

  const removeItem = (index) => {
    const items = [...(formData.items || [])];
    items.splice(index, 1);
    setFormData({ ...formData, items });
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
      // compute totals from itemised entries if present
      let laborTotal = parseFloat(formData.laborCost || 0);
      let partsTotal = parseFloat(formData.partsCost || 0);
      const items = (formData.items || []).map(it => ({
        type: it.type,
        description: it.description,
        cost: it.cost,
        quantity: it.quantity || 1
      }));
      if (items.length > 0) {
        laborTotal = items.filter(i => i.type === 'labour').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseInt(i.quantity || 1))), 0);
        partsTotal = items.filter(i => i.type === 'part').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseInt(i.quantity || 1))), 0);
      }

      const data = { 
        ...formData, 
        vehicleId,
        mileage: formData.mileage ? Math.round(toKm(parseFloat(formData.mileage))) : null,
        receiptAttachmentId,
        items,
        laborCost: laborTotal.toFixed(2),
        partsCost: partsTotal.toFixed(2),
        motRecordId: motRecordId || null,
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
            <Grid item xs={12}>
              <Button variant="outlined" onClick={addItem} style={{ marginBottom: 8 }}>
                {t('service.addItem') || 'Add item'}
              </Button>
              {(formData.items || []).map((it, idx) => (
                <Grid container spacing={1} key={idx} style={{ marginBottom: 8 }}>
                  <Grid item xs={12} sm={3}>
                    <TextField
                      fullWidth
                      select
                      value={it.type}
                      onChange={(e) => updateItem(idx, 'type', e.target.value)}
                    >
                      <MenuItem value="part">{t('service.part') || 'Part'}</MenuItem>
                      <MenuItem value="labour">{t('service.labour') || 'Labour'}</MenuItem>
                      <MenuItem value="consumable">{t('service.consumable') || 'Consumable'}</MenuItem>
                    </TextField>
                  </Grid>
                  <Grid item xs={12} sm={5}>
                    <TextField fullWidth value={it.description} onChange={(e) => updateItem(idx, 'description', e.target.value)} />
                  </Grid>
                  <Grid item xs={6} sm={2}>
                    <TextField type="number" fullWidth value={it.cost} onChange={(e) => updateItem(idx, 'cost', e.target.value)} inputProps={{ min: 0, step: 0.01 }} />
                  </Grid>
                  <Grid item xs={6} sm={1}>
                    <TextField type="number" fullWidth value={it.quantity} onChange={(e) => updateItem(idx, 'quantity', e.target.value)} inputProps={{ min: 1 }} />
                  </Grid>
                  <Grid item xs={12} sm={1}>
                    <Button color="secondary" onClick={() => removeItem(idx)}>×</Button>
                  </Grid>
                </Grid>
              ))}
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
            {/* workPerformed removed — use notes for freeform descriptions */}
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  select
                  name="motRecord"
                  label={t('mot.associateWithMot')}
                  value={motRecordId || ''}
                  onChange={(e) => setMotRecordId(e.target.value || null)}
                >
                  <MenuItem value="">{t('common.none')}</MenuItem>
                  {motRecords.map((m) => (
                    <MenuItem key={m.id} value={m.id}>{`${m.testDate} - ${m.result || ''}`}</MenuItem>
                  ))}
                </TextField>
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
            <Grid item xs={12}>
              <ReceiptUpload
                entityType="service"
                receiptAttachmentId={receiptAttachmentId}
                onReceiptUploaded={handleReceiptUploaded}
                onReceiptRemoved={() => setReceiptAttachmentId(null)}
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
