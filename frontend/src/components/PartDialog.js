import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  MenuItem
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import { useDistance } from '../hooks/useDistance';
import ReceiptUpload from './ReceiptUpload';
import UrlScraper from './UrlScraper';

export default function PartDialog({ open, onClose, part, vehicleId }) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const { convert, toKm, getLabel } = useDistance();
  const [formData, setFormData] = useState({
    description: '',
    partNumber: '',
    manufacturer: '',
    category: '',
    cost: '',
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

  useEffect(() => {
    if (part) {
      setFormData({
        description: part.description || '',
        partNumber: part.partNumber || '',
        manufacturer: part.manufacturer || '',
        category: part.category || '',
        cost: part.cost || '',
        purchaseDate: part.purchaseDate || '',
        installationDate: part.installationDate || '',
        mileageAtInstallation: part.mileageAtInstallation ? Math.round(convert(part.mileageAtInstallation)) : '',
        warranty: part.warranty || '',
        supplier: part.supplier || '',
        notes: part.notes || ''
      });
      setReceiptAttachmentId(part.receiptAttachmentId || null);
      setProductUrl(part.productUrl || '');
    } else {
      setFormData({
        description: '',
        partNumber: '',
        manufacturer: '',
        category: '',
        cost: '',
        purchaseDate: '',
        installationDate: '',
        mileageAtInstallation: '',
        warranty: '',
        supplier: '',
        notes: ''
      });
      setReceiptAttachmentId(null);
      setProductUrl('');
    }
  }, [part, open]);

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
    if (scrapedData.price) updates.cost = scrapedData.price;
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
      const data = {
        ...formData,
        vehicleId,
        cost: parseFloat(formData.cost) || 0,
        mileageAtInstallation: formData.mileageAtInstallation ? Math.round(toKm(parseFloat(formData.mileageAtInstallation))) : null,
        receiptAttachmentId,
        productUrl
      };

      if (part) {
        await api.put(`/parts/${part.id}`, data);
      } else {
        await api.post('/parts', data);
      }
      onClose(true);
    } catch (error) {
      console.error('Error saving part:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        {part ? t('parts.editPart') : t('parts.addPart')}
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
                label={t('parts.partNumber')}
                value={formData.partNumber}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="manufacturer"
                label={t('parts.manufacturer')}
                value={formData.manufacturer}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                select
                name="category"
                label={t('parts.category')}
                value={formData.category}
                onChange={handleChange}
              >
                <MenuItem value="">{t('partCategories.selectCategory')}</MenuItem>
                <MenuItem value="Engine">Engine</MenuItem>
                <MenuItem value="Transmission">Transmission</MenuItem>
                <MenuItem value="Brakes">Brakes</MenuItem>
                <MenuItem value="Suspension">Suspension</MenuItem>
                <MenuItem value="Electrical">Electrical</MenuItem>
                <MenuItem value="Body">Body</MenuItem>
                <MenuItem value="Interior">Interior</MenuItem>
                <MenuItem value="Exhaust">Exhaust</MenuItem>
                <MenuItem value="Cooling">Cooling</MenuItem>
                <MenuItem value="Other">Other</MenuItem>
              </TextField>
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="number"
                name="cost"
                label={t('parts.cost')}
                value={formData.cost}
                onChange={handleChange}
                inputProps={{ step: '0.01', min: '0' }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                type="date"
                name="purchaseDate"
                label={t('parts.purchaseDate')}
                value={formData.purchaseDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                type="date"
                name="installationDate"
                label={t('parts.installationDate')}
                value={formData.installationDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
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
                label={t('parts.warranty')}
                value={formData.warranty}
                onChange={handleChange}
                placeholder={t('common.warrantyPlaceholder')}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="supplier"
                label={t('parts.supplier')}
                value={formData.supplier}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <ReceiptUpload
                entityType="part"
                receiptAttachmentId={receiptAttachmentId}
                onReceiptUploaded={handleReceiptUploaded}
                onReceiptRemoved={() => setReceiptAttachmentId(null)}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={3}
                name="notes"
                label={t('parts.notes')}
                value={formData.notes}
                onChange={handleChange}
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
