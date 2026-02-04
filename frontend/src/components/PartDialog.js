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
  Box,
  FormControl,
  Select,
  InputLabel
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import { useDistance } from '../hooks/useDistance';
import ReceiptUpload from './ReceiptUpload';
import UrlScraper from './UrlScraper';
import logger from '../utils/logger';

export default function PartDialog({ open, onClose, part, vehicleId }) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const { convert, toKm, getLabel } = useDistance();
  const [formData, setFormData] = useState({
    description: '',
    partNumber: '',
    manufacturer: '',
    partCategoryId: '',
    price: '',
    quantity: 1,
    purchaseDate: '',
    installationDate: '',
    mileageAtInstallation: '',
    warranty: '',
    supplier: '',
    notes: ''
  });
  const [loading, setLoading] = useState(false);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState(null);
  const [productUrl, setProductUrl] = useState('');
  const [motRecords, setMotRecords] = useState([]);
  const [motRecordId, setMotRecordId] = useState(null);
  const [serviceRecords, setServiceRecords] = useState([]);
  const [serviceRecordId, setServiceRecordId] = useState(null);
  const [existingParts, setExistingParts] = useState([]);
  const [selectedExistingPartId, setSelectedExistingPartId] = useState('');
  const [isLinkingExisting, setIsLinkingExisting] = useState(false);

  // Determine the actual vehicle ID - use part's vehicleId if available, otherwise use prop
  const actualVehicleId = part?.vehicleId || vehicleId;

  // Determine if we're in MOT/Service context (pre-filled IDs)
  // Only show link dropdown when creating NEW part from service/MOT, not when editing existing
  const isFromMotOrService = !part?.id && (part?.motRecordId || part?.serviceRecordId);

  // Load unassociated parts when opened from MOT/Service context
  useEffect(() => {
    const loadUnassociated = async () => {
      if (!open || !actualVehicleId || !isFromMotOrService) return;
      try {
        const resp = await api.get('/parts', { params: { vehicleId: actualVehicleId, unassociated: 'true' } });
        setExistingParts(resp.data || []);
      } catch (e) {
        logger.error('Failed to load unassociated parts', e);
        setExistingParts([]);
      }
    };
    loadUnassociated();
  }, [open, actualVehicleId, isFromMotOrService, api]);

  // Handle selection of existing part from dropdown
  const handleExistingPartSelected = async (partId) => {
    setSelectedExistingPartId(partId);
    if (!partId) {
      // Clear form when "Create New" is selected
      setIsLinkingExisting(false);
      setFormData({
        description: '',
        partNumber: '',
        manufacturer: '',
        partCategoryId: '',
        price: '',
        quantity: 1,
        purchaseDate: '',
        installationDate: '',
        mileageAtInstallation: '',
        warranty: '',
        supplier: '',
        notes: ''
      });
      setReceiptAttachmentId(null);
      setProductUrl('');
      return;
    }

    try {
      const resp = await api.get(`/parts/${partId}`);
      const existingPart = resp.data;
      setIsLinkingExisting(true);
      setFormData({
        description: existingPart.description || '',
        partNumber: existingPart.partNumber || '',
        manufacturer: existingPart.manufacturer || '',
        partCategoryId: existingPart.partCategoryId ?? existingPart.partCategory?.id ?? '',
        price: existingPart.price ?? existingPart.cost ?? '',
        quantity: existingPart.quantity ?? 1,
        purchaseDate: existingPart.purchaseDate || '',
        installationDate: existingPart.installationDate || '',
        mileageAtInstallation: existingPart.mileageAtInstallation ? Math.round(convert(existingPart.mileageAtInstallation)) : '',
        warranty: existingPart.warranty || '',
        supplier: existingPart.supplier || '',
        notes: existingPart.notes || ''
      });
      setReceiptAttachmentId(existingPart.receiptAttachmentId || null);
      setProductUrl(existingPart.productUrl || '');
    } catch (e) {
      logger.error('Failed to load existing part', e);
    }
  };

  useEffect(() => {
    if (part) {
      setSelectedExistingPartId('');
      setIsLinkingExisting(false);
      setFormData({
        description: part.description || '',
        partNumber: part.partNumber || '',
        manufacturer: part.manufacturer || '',
        partCategoryId: part.partCategoryId ?? part.partCategory?.id ?? '',
        price: part.price ?? part.cost ?? '',
        quantity: part.quantity ?? 1,
        purchaseDate: part.purchaseDate || '',
        installationDate: part.installationDate || '',
        mileageAtInstallation: part.mileageAtInstallation ? Math.round(convert(part.mileageAtInstallation)) : '',
        warranty: part.warranty || '',
        supplier: part.supplier || '',
        notes: part.notes || ''
      });
      setReceiptAttachmentId(part.receiptAttachmentId || null);
      setProductUrl(part.productUrl || '');
      setMotRecordId(part.motRecordId || null);
      setServiceRecordId(part.serviceRecordId || null);
    } else {
      setSelectedExistingPartId('');
      setIsLinkingExisting(false);
      setFormData({
        description: '',
        partNumber: '',
        manufacturer: '',
        partCategoryId: '',
        price: '',
        quantity: 1,
        purchaseDate: '',
        installationDate: '',
        mileageAtInstallation: '',
        warranty: '',
        supplier: '',
        notes: ''
      });
      setReceiptAttachmentId(null);
      setProductUrl('');
      setMotRecordId(null);
      setServiceRecordId(null);
    }
  }, [part, open]);

  const [partCategories, setPartCategories] = useState([]);
  const [vehicleTypeId, setVehicleTypeId] = useState(null);
  
  // Fetch vehicle to get vehicleTypeId
  useEffect(() => {
    const loadVehicle = async () => {
      if (!actualVehicleId) return;
      try {
        const resp = await api.get(`/vehicles/${actualVehicleId}`);
        setVehicleTypeId(resp.data?.vehicleTypeId || null);
      } catch (e) {
        logger.error('Failed to load vehicle', e);
      }
    };
    if (open) loadVehicle();
  }, [open, actualVehicleId, api]);

  useEffect(() => {
    const loadCategories = async () => {
      try {
        const params = vehicleTypeId ? { vehicleTypeId } : {};
        const resp = await api.get('/part-categories', { params });
        setPartCategories(resp.data || []);
      } catch (e) {
        logger.error('Failed to load part categories', e);
      }
    };
    if (open) loadCategories();
  }, [open, vehicleTypeId, api]);

  useEffect(() => {
    const load = async () => {
      if (!actualVehicleId) return;
      try {
        const resp = await api.get('/mot-records', { params: { vehicleId: actualVehicleId } });
        setMotRecords(Array.isArray(resp.data) ? resp.data : []);
      } catch (e) {
        logger.error('Failed to load MOT records', e);
        setMotRecords([]);
      }
    };
    if (open) load();
  }, [open, actualVehicleId, api]);

  useEffect(() => {
    const loadSvc = async () => {
      if (!actualVehicleId) return;
      try {
        const resp = await api.get('/service-records', { params: { vehicleId: actualVehicleId } });
        setServiceRecords(Array.isArray(resp.data) ? resp.data : []);
      } catch (e) {
        logger.error('Failed to load service records', e);
        setServiceRecords([]);
      }
    };
    if (open) loadSvc();
  }, [open, actualVehicleId, api]);

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
    if (ocrData.price) updates.price = ocrData.price;
    if (ocrData.partNumber) updates.partNumber = ocrData.partNumber;
    if (ocrData.manufacturer) updates.manufacturer = ocrData.manufacturer;
    if (ocrData.supplier) updates.supplier = ocrData.supplier;
    if (ocrData.date) updates.purchaseDate = ocrData.date;
    if (Object.keys(updates).length > 0) {
      setFormData(prev => ({ ...prev, ...updates }));
    }
  };

  const handleDataScraped = (scrapedData, url) => {
    setProductUrl(url);
    const updates = {};
    if (scrapedData.name) updates.description = scrapedData.name;
    if (scrapedData.price) updates.price = scrapedData.price;
    if (scrapedData.partNumber) updates.partNumber = scrapedData.partNumber;
    if (scrapedData.manufacturer) updates.manufacturer = scrapedData.manufacturer;
    if (scrapedData.supplier) updates.supplier = scrapedData.supplier;
    if (Object.keys(updates).length > 0) {
      setFormData(prev => ({ ...prev, ...updates }));
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const price = parseFloat(formData.price) || 0;
      const quantity = parseInt(formData.quantity || 0, 10) || 0;
      const computedCost = +(price * quantity).toFixed(2);
      const normalizedCategoryId = (() => {
        if (formData.partCategoryId === '' || formData.partCategoryId === null || formData.partCategoryId === undefined) {
          return '';
        }
        const parsed = Number(formData.partCategoryId);
        return Number.isFinite(parsed) ? parsed : '';
      })();

      const data = {
        ...formData,
        partCategoryId: normalizedCategoryId,
        vehicleId: actualVehicleId,
        price: price,
        quantity: quantity,
        cost: computedCost,
        mileageAtInstallation: formData.mileageAtInstallation ? Math.round(toKm(parseFloat(formData.mileageAtInstallation))) : null,
        receiptAttachmentId,
        productUrl,
        motRecordId,
        serviceRecordId
      };

      let resp;
      if (isLinkingExisting && selectedExistingPartId) {
        // Update existing part to link with MOT/Service
        resp = await api.put(`/parts/${selectedExistingPartId}`, data);
      } else if (part && part.id) {
        resp = await api.put(`/parts/${part.id}`, data);
      } else {
        resp = await api.post('/parts', data);
      }
      onClose(resp.data);
    } catch (error) {
      logger.error('Error saving part:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}>
          <Box sx={{ flexShrink: 0 }}>
            {part ? t('parts.editPart') : t('parts.addPart')}
          </Box>
          {isFromMotOrService && existingParts.length > 0 && (
            <Box sx={{ flexGrow: 1, minWidth: 300 }}>
              <FormControl size="small" fullWidth>
                <InputLabel>{t('parts.linkExisting')}</InputLabel>
                <Select
                  value={selectedExistingPartId}
                  onChange={(e) => handleExistingPartSelected(e.target.value)}
                  label={t('parts.linkExisting')}
                >
                  <MenuItem value="">{t('common.createNew')}</MenuItem>
                  {existingParts.map((p) => (
                    <MenuItem key={p.id} value={p.id}>
                      {p.description || p.name} {p.partNumber ? `(${p.partNumber})` : ''} - {p.purchaseDate || t('common.noDate')}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Box>
          )}
        </Box>
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            <Grid item xs={12}>
              <UrlScraper
                endpoint="/parts/scrape-url"
                onDataScraped={handleDataScraped}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                required
                name="description"
                label={t('parts.description')}
                value={formData.description}
                onChange={handleChange}
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
                name="manufacturer"
                label={t('common.manufacturer')}
                value={formData.manufacturer}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                select
                name="partCategoryId"
                label={t('parts.category')}
                value={formData.partCategoryId ?? ''}
                onChange={(e) => {
                  setFormData(prev => ({ ...prev, partCategoryId: e.target.value }));
                }}
              >
                <MenuItem value="">{t('partCategories.selectCategory')}</MenuItem>
                {partCategories.map(pc => (
                  <MenuItem key={pc.id} value={pc.id}>{pc.name}</MenuItem>
                ))}
                <MenuItem value="other">{t('partCategories.Other')}</MenuItem>
              </TextField>
            </Grid>
            <Grid item xs={12} sm={6}>
              <Grid container spacing={1}>
                <Grid item xs={6}>
                  <TextField
                    fullWidth
                    required
                    type="number"
                    name="price"
                    label={t('parts.price') || 'Price'}
                    value={formData.price}
                    onChange={handleChange}
                    inputProps={{ step: '0.01', min: '0' }}
                  />
                </Grid>
                <Grid item xs={6}>
                  <TextField
                    fullWidth
                    required
                    type="number"
                    name="quantity"
                    label={t('common.quantity') || 'Quantity'}
                    value={formData.quantity}
                    onChange={handleChange}
                    inputProps={{ step: '1', min: '0' }}
                  />
                </Grid>
              </Grid>
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="date"
                name="purchaseDate"
                label={t('common.purchaseDate')}
                value={formData.purchaseDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="date"
                name="installationDate"
                label={t('common.installationDate')}
                value={formData.installationDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="mileageAtInstallation"
                label={`${t('parts.mileageAtInstallation')} (${getLabel()})`}
                value={formData.mileageAtInstallation}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="warranty"
                label={t('common.warranty')}
                value={formData.warranty}
                onChange={handleChange}
                placeholder={t('common.warrantyPlaceholder')}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="supplier"
                label={t('common.supplier')}
                value={formData.supplier}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                select
                name="motRecordId"
                label={t('parts.motRecord')}
                value={motRecordId || ''}
                onChange={(e) => setMotRecordId(e.target.value)}
              >
                <MenuItem value="">{t('parts.selectMot')}</MenuItem>
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
                label={t('service.serviceRecord')}
                value={serviceRecordId || ''}
                onChange={(e) => setServiceRecordId(e.target.value)}
              >
                <MenuItem value="">{t('service.selectService')}</MenuItem>
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
                entityType="part"
                vehicleId={actualVehicleId}
                receiptAttachmentId={receiptAttachmentId}
                onReceiptUploaded={handleReceiptUploaded}
                onReceiptRemoved={() => setReceiptAttachmentId(null)}
              />
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => onClose(false)} disabled={loading}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" variant="contained" disabled={loading}>
            {loading ? t('common.loading') : t('common.save')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
