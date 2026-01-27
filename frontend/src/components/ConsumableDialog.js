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
  CircularProgress
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import { useDistance } from '../hooks/useDistance';
import ReceiptUpload from './ReceiptUpload';
import UrlScraper from './UrlScraper';

export default function ConsumableDialog({ open, onClose, consumable, vehicleId }) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const { convert, toKm, getLabel } = useDistance();
  const [formData, setFormData] = useState({
    consumableTypeId: '',
    description: '',
    quantity: '',
    lastChanged: '',
    mileageAtChange: '',
    cost: '',
    brand: '',
    partNumber: '',
    supplier: '',
    notes: ''
  });
  const [consumableTypes, setConsumableTypes] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingTypes, setLoadingTypes] = useState(false);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState(null);
  const [productUrl, setProductUrl] = useState('');
  const [motRecords, setMotRecords] = useState([]);
  const [motRecordId, setMotRecordId] = useState(null);
  const [serviceRecords, setServiceRecords] = useState([]);
  const [serviceRecordId, setServiceRecordId] = useState(null);

  useEffect(() => {
    if (open && vehicleId) {
      loadConsumableTypes();
    }
  }, [open, vehicleId]);

  useEffect(() => {
    if (consumable) {
      setFormData({
        consumableTypeId: consumable.consumableType?.id || '',
        description: consumable.description || '',
        quantity: consumable.quantity || '',
        lastChanged: consumable.lastChanged || '',
        mileageAtChange: consumable.mileageAtChange ? Math.round(convert(consumable.mileageAtChange)) : '',
        cost: consumable.cost || '',
        brand: consumable.brand || '',
        partNumber: consumable.partNumber || '',
        supplier: consumable.supplier || '',
        notes: consumable.notes || ''
      });
      setReceiptAttachmentId(consumable.receiptAttachmentId || null);
      setProductUrl(consumable.productUrl || '');
      setMotRecordId(consumable.motRecordId || null);
      setServiceRecordId(consumable.serviceRecordId || null);
    } else {
      setFormData({
        consumableTypeId: '',
        description: '',
        quantity: '',
        lastChanged: '',
        mileageAtChange: '',
        cost: '',
        brand: '',
        partNumber: '',
        supplier: '',
        notes: ''
      });
      setReceiptAttachmentId(null);
      setProductUrl('');
      setMotRecordId(null);
      setServiceRecordId(null);
    }
  }, [consumable, open]);

  useEffect(() => {
    const load = async () => {
      if (!vehicleId) return;
      try {
        const resp = await api.get('/mot-records', { params: { vehicleId } });
        setMotRecords(resp.data || []);
        const serv = await api.get('/service-records', { params: { vehicleId } });
        setServiceRecords(serv.data || []);
      } catch (e) {
        console.error('Failed to load MOT or service records', e);
      }
    };
    if (open) load();
  }, [open, vehicleId, api]);

  const loadConsumableTypes = async () => {
    setLoadingTypes(true);
    try {
      // First get the vehicle to find its type
      const vehicleResponse = await api.get(`/vehicles/${vehicleId}`);
      const vehicleTypeId = vehicleResponse.data.vehicleType.id;
      
      // Then get consumable types for this vehicle type
      const typesResponse = await api.get(`/vehicle-types/${vehicleTypeId}/consumable-types`);
      setConsumableTypes(typesResponse.data);
    } catch (error) {
      console.error('Error loading consumable types:', error);
    } finally {
      setLoadingTypes(false);
    }
  };

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  const handleReceiptUploaded = (attachmentId, ocrData) => {
    setReceiptAttachmentId(attachmentId);
    const updates = {};
    if (ocrData.name) updates.description = ocrData.name;
    if (ocrData.price) updates.cost = ocrData.price;
    if (ocrData.quantity) updates.quantity = ocrData.quantity;
    if (ocrData.manufacturer) updates.brand = ocrData.manufacturer;
    if (ocrData.date) updates.lastChanged = ocrData.date;
    if (Object.keys(updates).length > 0) {
      setFormData(prev => ({ ...prev, ...updates }));
    }
  };

  const handleDataScraped = (scrapedData, url) => {
    setProductUrl(url);
    const updates = {};
    if (scrapedData.name) updates.description = scrapedData.name;
    if (scrapedData.price) updates.cost = scrapedData.price;
    if (scrapedData.partNumber) updates.partNumber = scrapedData.partNumber;
    if (scrapedData.manufacturer) updates.brand = scrapedData.manufacturer;
    if (scrapedData.supplier) updates.supplier = scrapedData.supplier;
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
        cost: parseFloat(formData.cost) || 0,
        quantity: parseFloat(formData.quantity) || 0,
        mileageAtChange: formData.mileageAtChange ? Math.round(toKm(parseFloat(formData.mileageAtChange))) : null,
        receiptAttachmentId,
        productUrl,
        motRecordId,
        serviceRecordId
      };
      // send `description` only

      let resp;
      if (consumable && consumable.id) {
        resp = await api.put(`/consumables/${consumable.id}`, data);
      } else {
        resp = await api.post('/consumables', data);
      }
      onClose(resp.data);
    } catch (error) {
      console.error('Error saving consumable:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        {consumable ? t('consumables.editConsumable') : t('consumables.addConsumable')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          {loadingTypes ? (
            <CircularProgress />
          ) : (
            <Grid container spacing={2}>
              <Grid item xs={12}>
                <UrlScraper
                  endpoint="/consumables/scrape-url"
                  onDataScraped={handleDataScraped}
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  required
                  name="description"
                  label={t('consumables.name')}
                  value={formData.description}
                  onChange={handleChange}
                  placeholder={t('common.namePlaceholder')}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  name="partNumber"
                  label={t('consumables.partNumber')}
                  value={formData.partNumber}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  name="brand"
                  label={t('consumables.brand')}
                  value={formData.brand}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  required
                  select
                  name="consumableTypeId"
                  label={t('consumables.type')}
                  value={formData.consumableTypeId}
                  onChange={handleChange}
                >
                  <MenuItem value="">{t('consumables.selectType')}</MenuItem>
                  {consumableTypes.map((type) => (
                    <MenuItem key={type.id} value={type.id}>
                      {type.name} ({type.unit})
                    </MenuItem>
                  ))}
                </TextField>
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  name="supplier"
                  label={t('consumables.supplier')}
                  value={formData.supplier}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  required
                  type="number"
                  name="cost"
                  label={t('consumables.cost')}
                  value={formData.cost}
                  onChange={handleChange}
                  inputProps={{ step: '0.01', min: '0' }}
                />
              </Grid>

              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  required
                  type="number"
                  name="quantity"
                  label={t('consumables.quantity')}
                  value={formData.quantity}
                  onChange={handleChange}
                  inputProps={{ step: '0.01', min: '0' }}
                />
              </Grid>

              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  type="date"
                  name="lastChanged"
                  label={t('consumables.lastChanged')}
                  value={formData.lastChanged}
                  onChange={handleChange}
                  InputLabelProps={{ shrink: true }}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  type="number"
                  name="mileageAtChange"
                  label={`${t('consumables.mileageAtChange')} (${getLabel()})`}
                  value={formData.mileageAtChange}
                  onChange={handleChange}
                />
              </Grid>

              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  select
                  name="motRecordId"
                  label={t('consumables.motRecord')}
                  value={motRecordId || ''}
                  onChange={(e) => setMotRecordId(e.target.value)}
                >
                  <MenuItem value="">{t('consumables.selectMot')}</MenuItem>
                  {motRecords.map(m => (
                    <MenuItem key={m.id} value={m.id}>{`${m.testDate || ''} ${m.motTestNumber ? '- ' + m.motTestNumber : ''}`}</MenuItem>
                  ))}
                </TextField>
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  select
                  name="serviceRecordId"
                  label={t('consumables.serviceRecord')}
                  value={serviceRecordId || ''}
                  onChange={(e) => setServiceRecordId(e.target.value)}
                >
                  <MenuItem value="">{t('consumables.selectService')}</MenuItem>
                  {serviceRecords.map(s => (
                    <MenuItem key={s.id} value={s.id}>{`${s.serviceDate || ''} ${s.serviceProvider ? '- ' + s.serviceProvider : ''}`}</MenuItem>
                  ))}
                </TextField>
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  multiline
                  rows={3}
                  name="notes"
                  label={t('consumables.notes')}
                  value={formData.notes}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12}>
                <ReceiptUpload
                  entityType="consumable"
                  receiptAttachmentId={receiptAttachmentId}
                  onReceiptUploaded={handleReceiptUploaded}
                  onReceiptRemoved={() => setReceiptAttachmentId(null)}
                />
              </Grid>
            </Grid>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => onClose(false)} disabled={loading}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" variant="contained" disabled={loading || loadingTypes}>
            {loading ? t('common.loading') : t('common.save')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
