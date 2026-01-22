import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  CircularProgress,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Box,
  Typography,
  IconButton,
  Tooltip,
} from '@mui/material';
import {
  CloudUpload as UploadIcon,
  Delete as DeleteIcon,
  Receipt as ReceiptIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useDistance } from '../hooks/useDistance';

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
  const [receiptFile, setReceiptFile] = useState(null);
  const [uploadingReceipt, setUploadingReceipt] = useState(false);
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
        console.warn('Unexpected fuel types response:', response.data);
        setFuelTypes([]);
      }
    } catch (error) {
      console.error('Error loading fuel types:', error);
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
      setReceiptFile(null);
    }
  }, [record, open]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleReceiptUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    setUploadingReceipt(true);
    try {
      const formDataUpload = new FormData();
      formDataUpload.append('file', file);
      formDataUpload.append('entityType', 'fuel_record');
      formDataUpload.append('description', 'Fuel receipt');

      const response = await api.post('/attachments', formDataUpload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      const attachmentId = response.data.id;
      setFormData({ ...formData, receiptAttachmentId: attachmentId });
      setReceiptFile(file);

      // Process OCR to extract receipt data
      try {
        const ocrResponse = await api.get(`/attachments/${attachmentId}/ocr`);
        const extractedData = ocrResponse.data;

        // Auto-fill form fields with extracted data
        const updates = {};
        if (extractedData.date) updates.date = extractedData.date;
        if (extractedData.cost) updates.cost = extractedData.cost;
        if (extractedData.litres) updates.litres = extractedData.litres;
        if (extractedData.station) updates.station = extractedData.station;
        if (extractedData.fuelType) updates.fuelType = extractedData.fuelType;

        if (Object.keys(updates).length > 0) {
          setFormData({ ...formData, ...updates, receiptAttachmentId: attachmentId });
        }
      } catch (ocrError) {
        console.log('OCR extraction failed or not applicable:', ocrError);
        // Continue without OCR data - attachment still uploaded
      }
    } catch (error) {
      console.error('Error uploading receipt:', error);
      alert(t('attachment.uploadError'));
    } finally {
      setUploadingReceipt(false);
    }
  };

  const handleRemoveReceipt = async () => {
    if (formData.receiptAttachmentId) {
      try {
        await api.delete(`/attachments/${formData.receiptAttachmentId}`);
      } catch (error) {
        console.error('Error deleting receipt:', error);
      }
    }
    setFormData({ ...formData, receiptAttachmentId: null });
    setReceiptFile(null);
  };

  const handleViewReceipt = () => {
    if (formData.receiptAttachmentId) {
      window.open(`${api.defaults.baseURL}/attachments/${formData.receiptAttachmentId}/download`, '_blank');
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
      };
      if (record) {
        await api.put(`/fuel-records/${record.id}`, data);
      } else {
        await api.post('/fuel-records', data);
      }
      onClose(true);
    } catch (error) {
      console.error('Error saving fuel record:', error);
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
                label={t('fuel.notes')}
                value={formData.notes}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <Box
                sx={{
                  border: '1px solid',
                  borderColor: 'divider',
                  borderRadius: 1,
                  height: '56px',
                  display: 'flex',
                  alignItems: 'center',
                  px: 2,
                  cursor: !formData.receiptAttachmentId && !receiptFile ? 'pointer' : 'default',
                  '&:hover': !formData.receiptAttachmentId && !receiptFile ? {
                    bgcolor: 'action.hover',
                    borderColor: 'primary.main'
                  } : {}
                }}
                component={!formData.receiptAttachmentId && !receiptFile ? 'label' : 'div'}
              >
                <input
                  accept="image/*,application/pdf"
                  style={{ display: 'none' }}
                  id="receipt-upload"
                  type="file"
                  onChange={handleReceiptUpload}
                  disabled={uploadingReceipt}
                />
                {!formData.receiptAttachmentId && !receiptFile ? (
                  uploadingReceipt ? (
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                      <CircularProgress size={20} />
                      <Typography variant="body2">
                        {t('attachment.uploading')}
                      </Typography>
                    </Box>
                  ) : (
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                      <UploadIcon color="action" />
                      <Typography variant="body2" color="textSecondary">
                        {t('attachment.uploadReceipt')}
                      </Typography>
                    </Box>
                  )
                ) : (
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, width: '100%' }}>
                    <ReceiptIcon color="primary" />
                    <Typography variant="body2" sx={{ flex: 1 }}>
                      {receiptFile?.name || t('fuel.receiptAttached')}
                    </Typography>
                    <Tooltip title={t('attachment.view')}>
                      <IconButton
                        size="small"
                        onClick={handleViewReceipt}
                      >
                        <ReceiptIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title={t('attachment.remove')}>
                      <IconButton
                        size="small"
                        onClick={handleRemoveReceipt}
                      >
                        <DeleteIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </Box>
                )}
              </Box>
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

export default FuelRecordDialog;
