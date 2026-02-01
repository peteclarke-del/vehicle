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
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import { useDistance } from '../hooks/useDistance';
import ReceiptUpload from './ReceiptUpload';
import UrlScraper from './UrlScraper';
import KnightRiderLoader from './KnightRiderLoader';
import logger from '../utils/logger';

export default function ConsumableDialog({ open, onClose, consumable, vehicleId }) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const { convert, toKm, getLabel } = useDistance();
  const [formData, setFormData] = useState({
    consumableTypeId: '',
    consumableTypeName: '',
    description: '',
    quantity: '',
    lastChanged: '',
    mileageAtChange: '',
    replacementIntervalMiles: '',
    nextReplacementMileage: '',
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

  // Determine the actual vehicle ID - use consumable's vehicleId if available, otherwise use prop
  const actualVehicleId = consumable?.vehicleId || vehicleId;

  useEffect(() => {
    if (open && actualVehicleId) {
      loadConsumableTypes();
    }
  }, [open, actualVehicleId]);

  useEffect(() => {
    if (consumable) {
      setFormData({
        consumableTypeId: consumable.consumableType?.id || '',
        consumableTypeName: '',
        description: consumable.description || '',
        quantity: consumable.quantity || '',
        lastChanged: consumable.lastChanged || '',
        mileageAtChange: consumable.mileageAtChange ? Math.round(convert(consumable.mileageAtChange)) : '',
        replacementIntervalMiles: consumable.replacementIntervalMiles ? Math.round(convert(consumable.replacementIntervalMiles)) : '',
        nextReplacementMileage: consumable.nextReplacementMileage ? Math.round(convert(consumable.nextReplacementMileage)) : '',
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
        consumableTypeName: '',
        description: '',
        quantity: '',
        lastChanged: '',
        mileageAtChange: '',
        replacementIntervalMiles: '',
        nextReplacementMileage: '',
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
      if (!actualVehicleId) return;
      try {
        const resp = await api.get('/mot-records', { params: { vehicleId: actualVehicleId } });
        setMotRecords(Array.isArray(resp.data) ? resp.data : []);
        const serv = await api.get('/service-records', { params: { vehicleId: actualVehicleId } });
        const sdata = serv.data;
        if (Array.isArray(sdata)) setServiceRecords(sdata);
        else if (sdata && Array.isArray(sdata.serviceRecords)) setServiceRecords(sdata.serviceRecords);
        else setServiceRecords([]);
      } catch (e) {
        logger.error('Failed to load MOT or service records', e);
        setMotRecords([]);
        setServiceRecords([]);
      }
    };
    if (open) load();
  }, [open, actualVehicleId, api]);

  const loadConsumableTypes = async () => {
    setLoadingTypes(true);
    try {
      // First get the vehicle to find its type
      const vehicleResponse = await api.get(`/vehicles/${actualVehicleId}`);
      const vehicleTypeId = vehicleResponse.data.vehicleType.id;
      
      // Then get consumable types for this vehicle type
      const typesResponse = await api.get(`/vehicle-types/${vehicleTypeId}/consumable-types`);
      setConsumableTypes(typesResponse.data);
    } catch (error) {
      logger.error('Error loading consumable types:', error);
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
        vehicleId: actualVehicleId,
        cost: parseFloat(formData.cost) || 0,
        quantity: parseFloat(formData.quantity) || 0,
        mileageAtChange: formData.mileageAtChange ? Math.round(toKm(parseFloat(formData.mileageAtChange))) : null,
        replacementIntervalMiles: formData.replacementIntervalMiles ? Math.round(toKm(parseFloat(formData.replacementIntervalMiles))) : null,
        nextReplacementMileage: formData.nextReplacementMileage ? Math.round(toKm(parseFloat(formData.nextReplacementMileage))) : null,
        receiptAttachmentId,
        productUrl,
        motRecordId,
        serviceRecordId
      };

      // The 'other' override is currently disabled (commented out)
      // if (formData.consumableTypeId === 'other') {
      //   data.consumableTypeId = null;
      //   data.consumableTypeName = (formData.consumableTypeName || '').trim();
      // }
      // send `description` only

      let resp;
      if (consumable && consumable.id) {
        resp = await api.put(`/consumables/${consumable.id}`, data);
      } else {
        resp = await api.post('/consumables', data);
      }
      onClose(resp.data);
    } catch (error) {
      logger.error('Error saving consumable:', error);
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
            <KnightRiderLoader size={28} />
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
                  label={t('common.partNumber')}
                  value={formData.partNumber}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  name="brand"
                  label={t('common.brand')}
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
                  label={t('common.type')}
                  value={formData.consumableTypeId}
                  onChange={handleChange}
                >
                  <MenuItem value="">{t('consumables.selectType')}</MenuItem>
                  {/* <MenuItem value="other">{t('service.other')}</MenuItem> */}
                  {consumableTypes
                    .slice()
                    .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
                    .map((type) => (
                      <MenuItem key={type.id} value={type.id}>
                        {type.name} ({type.unit})
                      </MenuItem>
                    ))}
                </TextField>
              </Grid>
              {/*
              {formData.consumableTypeId === 'other' && (
                <Grid item xs={12} sm={6}>
                  <TextField
                    fullWidth
                    required
                    name="consumableTypeName"
                    label={`${t('service.other')} ${t('common.type')}`}
                    value={formData.consumableTypeName}
                    onChange={handleChange}
                  />
                </Grid>
              )}
              */}
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  name="supplier"
                  label={t('common.supplier')}
                  value={formData.supplier}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12} sm={4}>
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

              <Grid item xs={12} sm={4}>
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

              <Grid item xs={12} sm={4}>
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
              <Grid item xs={12} sm={4}>
                <TextField
                  fullWidth
                  type="number"
                  name="mileageAtChange"
                  label={`${t('consumables.mileageAtChange')} (${getLabel()})`}
                  value={formData.mileageAtChange}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12} sm={4}>
                <TextField
                  fullWidth
                  type="number"
                  name="replacementIntervalMiles"
                  label={`${t('consumables.replacementInterval')} (${getLabel()})`}
                  value={formData.replacementIntervalMiles}
                  onChange={handleChange}
                />
              </Grid>
              <Grid item xs={12} sm={4}>
                <TextField
                  fullWidth
                  type="number"
                  name="nextReplacementMileage"
                  label={`${t('consumables.nextReplacement')} (${getLabel()})`}
                  value={formData.nextReplacementMileage}
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
                  label={t('common.notes')}
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
