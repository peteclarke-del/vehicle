import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useDistance } from '../hooks/useDistance';
import KnightRiderLoader from './KnightRiderLoader';
import ReceiptUpload from './ReceiptUpload';
import logger from '../utils/logger';

const FuelRecordDialog = ({ open, record, vehicleId, onClose }) => {
  const [formData, setFormData] = useState({
    date: new Date().toISOString().split('T')[0],
    litres: '',
    cost: '',
    mileage: '',
    fuelType: '',
    station: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [fuelTypes, setFuelTypes] = useState([]);
  const { api } = useAuth();
  const { t } = useTranslation();
  const { convert, toKm, getLabel } = useDistance();

  useEffect(() => {
    loadFuelTypes();
  }, []);

  const loadFuelTypes = async () => {
    try {
      const response = await api.get('/fuel-records/fuel-types');
      if (Array.isArray(response.data)) {
        setFuelTypes(response.data);
      } else {
        logger.warn('Unexpected fuel types response:', response.data);
        setFuelTypes([]);
      }
    } catch (error) {
      logger.error('Error loading fuel types:', error);
    }
  };

  useEffect(() => {
    if (record) {
      setFormData({
        ...record,
        mileage: record.mileage ? Math.round(convert(record.mileage)) : '',
      });
    } else if (open) {
      // Reset form when opening for new record
      setFormData({
        date: new Date().toISOString().split('T')[0],
        litres: '',
        cost: '',
        mileage: '',
        fuelType: '',
        station: '',
        notes: '',
      });
    }
  }, [record, open]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleReceiptUploaded = (attachmentId, ocrData) => {
    const updates = { receiptAttachmentId: attachmentId };
    
    // Auto-fill form fields with OCR extracted data
    if (ocrData.date) updates.date = ocrData.date;
    if (ocrData.cost) updates.cost = ocrData.cost;
    if (ocrData.litres) updates.litres = ocrData.litres;
    if (ocrData.station) updates.station = ocrData.station;
    if (ocrData.fuelType) updates.fuelType = ocrData.fuelType;

    setFormData({ ...formData, ...updates });
  };

  const handleReceiptRemoved = () => {
    setFormData({ ...formData, receiptAttachmentId: null });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const data = { 
        ...formData, 
        vehicleId,
        mileage: formData.mileage ? Math.round(toKm(parseFloat(formData.mileage))) : null,
      };
      if (record) {
        await api.put(`/fuel-records/${record.id}`, data);
      } else {
        await api.post('/fuel-records', data);
      }
      onClose(true);
    } catch (error) {
      logger.error('Error saving fuel record:', error);
      alert(t('common.saveError', { type: t('fuel.title').toLowerCase() }));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="sm" fullWidth>
      <DialogTitle>
        {record ? t('fuelDialog.editRecord') : t('fuel.addRecord')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="date"
                name="date"
                label={t('fuel.date')}
                value={formData.date}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="number"
                name="mileage"
                label={`${t('fuel.mileage')} (${getLabel()})`}
                value={formData.mileage}
                onChange={handleChange}
                inputProps={{ step: "0.1" }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="number"
                name="litres"
                label={t('fuel.litres')}
                value={formData.litres}
                onChange={handleChange}
                inputProps={{ step: "0.01" }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="number"
                name="cost"
                label={t('fuel.cost')}
                value={formData.cost}
                onChange={handleChange}
                inputProps={{ step: "0.01" }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <FormControl fullWidth>
                <InputLabel>{t('fuel.fuelType')}</InputLabel>
                <Select
                  name="fuelType"
                  value={formData.fuelType || ''}
                  onChange={handleChange}
                  label={t('fuel.fuelType')}
                >
                  <MenuItem value="">
                    <em>{t('attachment.none')}</em>
                  </MenuItem>
                  {Array.isArray(fuelTypes) ? fuelTypes.map((type) => (
                    <MenuItem key={type} value={type}>
                      {type}
                    </MenuItem>
                  )) : null}
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="station"
                label={t('fuel.station')}
                value={formData.station}
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
            <Grid item xs={12}>
              <ReceiptUpload
                entityType="fuel_record"
                entityId={record?.id}
                vehicleId={vehicleId}
                receiptAttachmentId={formData.receiptAttachmentId}
                onReceiptUploaded={handleReceiptUploaded}
                onReceiptRemoved={handleReceiptRemoved}
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

export default FuelRecordDialog;
