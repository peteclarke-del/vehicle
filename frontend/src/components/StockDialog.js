import React, { useEffect, useMemo, useState } from 'react';
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
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import ReceiptUpload from './ReceiptUpload';
import logger from '../utils/logger';

const EMPTY_FORM = {
  itemType: 'part',
  category: '',
  supplier: '',
  quantity: '',
  price: '',
  description: '',
  notes: '',
  purchaseDate: '',
  partNumber: '',
  manufacturer: '',
  warranty: '',
};

export default function StockDialog({
  open,
  onClose,
  item,
  defaultVehicleTypeId,
  vehicleTypes,
}) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const [saving, setSaving] = useState(false);
  const [scrapeUrl, setScrapeUrl] = useState('');
  const [scraping, setScraping] = useState(false);
  const [vehicleTypeId, setVehicleTypeId] = useState(defaultVehicleTypeId || null);
  const [partCategories, setPartCategories] = useState([]);
  const [consumableTypes, setConsumableTypes] = useState([]);
  const [formData, setFormData] = useState(EMPTY_FORM);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState(null);

  const isEdit = Boolean(item?.id);

  useEffect(() => {
    if (!open) return;

    if (item) {
      setVehicleTypeId(item.vehicleTypeId || defaultVehicleTypeId || null);
      setReceiptAttachmentId(item.receiptAttachmentId || null);
      setFormData({
        itemType: item.itemType || 'part',
        category: item.category || '',
        supplier: item.supplier || '',
        quantity: item.quantity || '',
        price: item.price || '',
        description: item.description || '',
        notes: item.notes || '',
        purchaseDate: item.purchaseDate || '',
        partNumber: item.partNumber || '',
        manufacturer: item.manufacturer || '',
        warranty: item.warranty || '',
      });
    } else {
      setVehicleTypeId(defaultVehicleTypeId || null);
      setReceiptAttachmentId(null);
      setFormData(EMPTY_FORM);
    }

    setScrapeUrl('');
  }, [open, item, defaultVehicleTypeId]);

  useEffect(() => {
    const loadCategories = async () => {
      if (!open || !vehicleTypeId) {
        setPartCategories([]);
        setConsumableTypes([]);
        return;
      }

      try {
        const [partResp, consumableResp] = await Promise.all([
          api.get('/part-categories', { params: { vehicleTypeId } }),
          api.get(`/vehicle-types/${vehicleTypeId}/consumable-types`),
        ]);

        const sortedPartCategories = (Array.isArray(partResp.data) ? partResp.data : [])
          .slice()
          .sort((a, b) => (a?.name || '').localeCompare(b?.name || '', undefined, { sensitivity: 'base' }));
        const sortedConsumableTypes = (Array.isArray(consumableResp.data) ? consumableResp.data : [])
          .slice()
          .sort((a, b) => (a?.name || '').localeCompare(b?.name || '', undefined, { sensitivity: 'base' }));

        setPartCategories(sortedPartCategories);
        setConsumableTypes(sortedConsumableTypes);
      } catch (e) {
        logger.error('Failed to load stock dialog categories', e);
        setPartCategories([]);
        setConsumableTypes([]);
      }
    };

    loadCategories();
  }, [open, vehicleTypeId, api]);

  const handleDataScraped = (scrapedData) => {
    if (!scrapedData) return;

    setFormData((prev) => ({
      ...prev,
      description: scrapedData.name || prev.description,
      supplier: scrapedData.supplier || prev.supplier,
      price: scrapedData.price || prev.price,
      manufacturer: scrapedData.manufacturer || prev.manufacturer,
    }));
  };

  const handleReceiptUploaded = (attachmentId, ocrData = {}) => {
    setReceiptAttachmentId(attachmentId || null);

    if (!ocrData) return;

    setFormData((prev) => ({
      ...prev,
      description: ocrData.name || prev.description,
      supplier: ocrData.supplier || prev.supplier,
      price: ocrData.price || prev.price,
      manufacturer: ocrData.manufacturer || prev.manufacturer,
    }));
  };

  const handleReceiptRemoved = () => {
    setReceiptAttachmentId(null);
  };

  const handleScrape = async () => {
    if (!scrapeUrl) return;

    setScraping(true);
    try {
      const response = await api.post('/stock-items/scrape-url', { url: scrapeUrl });
      handleDataScraped(response.data);
      setScrapeUrl('');
    } catch (error) {
      logger.error('URL scraping failed:', error);
      const errorMessage = error.response?.data?.error || t('scraper.failed', 'Scraping failed');
      alert(errorMessage);
    } finally {
      setScraping(false);
    }
  };

  const categoryOptions = useMemo(() => {
    if (formData.itemType === 'part') {
      return partCategories;
    }

    return consumableTypes;
  }, [formData.itemType, partCategories, consumableTypes]);

  const handleSave = async () => {
    const qty = parseFloat(formData.quantity || 0);
    if (!formData.category.trim()) return;
    if (!isEdit && !(qty > 0)) return;
    if (isEdit && qty < 0) return;

    setSaving(true);
    try {
      const basePayload = {
        vehicleTypeId: vehicleTypeId || null,
        itemType: formData.itemType,
        category: formData.category,
        supplier: formData.supplier || null,
        description: formData.description || null,
        price: formData.price || null,
        notes: formData.notes || null,
        purchaseDate: formData.purchaseDate || null,
        partNumber: formData.partNumber || null,
        manufacturer: formData.manufacturer || null,
        warranty: formData.warranty || null,
        receiptAttachmentId: receiptAttachmentId,
      };

      if (isEdit) {
        await api.put(`/stock-items/${item.id}`, {
          ...basePayload,
          quantity: qty,
        });
      } else {
        await api.post('/stock-items/adjust', {
          ...basePayload,
          delta: qty,
        });
      }

      onClose(true);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={() => onClose(false)}
      maxWidth="md"
      fullWidth
      PaperProps={{
        sx: {
          maxHeight: '88vh',
        },
      }}
    >
      <DialogTitle>
        {isEdit ? t('stock.editItem', 'Edit Stock Item') : t('stock.addNew', 'Add Stock Item')}
      </DialogTitle>
      <DialogContent sx={{ display: 'flex', flexDirection: 'column' }}>
        <Box sx={{ display: 'flex', gap: 1.5, mt: 1, mb: 2 }}>
          <TextField
            fullWidth
            size="small"
            value={scrapeUrl}
            onChange={(e) => setScrapeUrl(e.target.value)}
            placeholder={t('scraper.pasteUrl', 'Paste product URL')}
            disabled={scraping}
          />
          <Button
            variant="outlined"
            onClick={handleScrape}
            disabled={!scrapeUrl || scraping}
            sx={{ flexShrink: 0, height: '40px' }}
          >
            {t('scraper.scrape', 'Scrape')}
          </Button>
        </Box>

        <Grid container spacing={1.5} sx={{ mb: 2 }}>
          <Grid item xs={12} sm={6} md={4}>
            <TextField
              select
              fullWidth
              label={t('common.selectVehicleType', 'Select Vehicle Type')}
              value={vehicleTypeId || ''}
              onChange={(e) => setVehicleTypeId(e.target.value ? parseInt(e.target.value, 10) : null)}
              size="small"
            >
              <MenuItem value="">{t('common.none', 'None')}</MenuItem>
              {vehicleTypes.map((vt) => (
                <MenuItem key={vt.id} value={vt.id}>
                  {vt.name}
                </MenuItem>
              ))}
            </TextField>
          </Grid>

          <Grid item xs={12} sm={6} md={2}>
            <TextField
              select
              fullWidth
              label={t('stock.type', 'Type')}
              value={formData.itemType}
              onChange={(e) => setFormData((prev) => ({ ...prev, itemType: e.target.value, category: '' }))}
              size="small"
            >
              <MenuItem value="part">{t('parts.title', 'Parts')}</MenuItem>
              <MenuItem value="consumable">{t('consumables.title', 'Consumables')}</MenuItem>
            </TextField>
          </Grid>

          <Grid item xs={12} sm={6} md={4}>
            {vehicleTypeId && categoryOptions.length > 0 ? (
              <TextField
                select
                fullWidth
                label={t('stock.category', 'Category')}
                value={formData.category}
                onChange={(e) => setFormData((prev) => ({ ...prev, category: e.target.value }))}
                size="small"
              >
                {categoryOptions.map((option) => (
                  <MenuItem key={option.id} value={option.name}>
                    {option.name}
                  </MenuItem>
                ))}
              </TextField>
            ) : (
              <TextField
                fullWidth
                label={t('stock.category', 'Category')}
                value={formData.category}
                onChange={(e) => setFormData((prev) => ({ ...prev, category: e.target.value }))}
                size="small"
              />
            )}
          </Grid>

          <Grid item xs={12} sm={6} md={2}>
            <TextField
              fullWidth
              label={t('stock.supplier', 'Supplier')}
              value={formData.supplier}
              onChange={(e) => setFormData((prev) => ({ ...prev, supplier: e.target.value }))}
              size="small"
            />
          </Grid>
        </Grid>

        <TextField
          fullWidth
          label={t('stock.description', 'Item Description')}
          value={formData.description}
          onChange={(e) => setFormData((prev) => ({ ...prev, description: e.target.value }))}
          size="small"
          sx={{ mb: 1.5 }}
        />

        <Grid container spacing={1.5} sx={{ mb: 1.5 }}>
          <Grid item xs={12} sm={6} md={4}>
            <TextField
              fullWidth
              label={t('stock.purchaseDate', 'Purchase Date')}
              type="date"
              InputLabelProps={{ shrink: true }}
              value={formData.purchaseDate}
              onChange={(e) => setFormData((prev) => ({ ...prev, purchaseDate: e.target.value }))}
              size="small"
            />
          </Grid>
          <Grid item xs={12} sm={6} md={4}>
            <TextField
              fullWidth
              label={t('stock.quantity', 'Qty')}
              type="number"
              inputProps={{ step: '0.01', min: '0' }}
              value={formData.quantity}
              onChange={(e) => setFormData((prev) => ({ ...prev, quantity: e.target.value }))}
              size="small"
            />
          </Grid>
          <Grid item xs={12} sm={6} md={4}>
            <TextField
              fullWidth
              label={t('stock.price', 'Price')}
              type="number"
              inputProps={{ step: '0.01', min: '0' }}
              value={formData.price}
              onChange={(e) => setFormData((prev) => ({ ...prev, price: e.target.value }))}
              size="small"
            />
          </Grid>
        </Grid>

        <Grid container spacing={1.5} sx={{ mb: 1.5 }}>
          <Grid item xs={12} sm={6} md={4}>
            <TextField
              fullWidth
              label={t('stock.warranty', 'Warranty')}
              value={formData.warranty}
              onChange={(e) => setFormData((prev) => ({ ...prev, warranty: e.target.value }))}
              size="small"
            />
          </Grid>
          <Grid item xs={12} sm={6} md={4}>
            <TextField
              fullWidth
              label={t('stock.partNumber', 'Part Number')}
              value={formData.partNumber}
              onChange={(e) => setFormData((prev) => ({ ...prev, partNumber: e.target.value }))}
              size="small"
            />
          </Grid>
          <Grid item xs={12} sm={6} md={4}>
            <TextField
              fullWidth
              label={formData.itemType === 'consumable' ? t('stock.brand', 'Brand') : t('stock.manufacturer', 'Manufacturer')}
              value={formData.manufacturer}
              onChange={(e) => setFormData((prev) => ({ ...prev, manufacturer: e.target.value }))}
              size="small"
            />
          </Grid>
        </Grid>

        <TextField
          fullWidth
          multiline
          rows={2}
          label={t('stock.notes', 'Notes')}
          value={formData.notes}
          onChange={(e) => setFormData((prev) => ({ ...prev, notes: e.target.value }))}
          size="small"
        />

        <ReceiptUpload
          entityType="stockItem"
          entityId={item?.id || null}
          vehicleId={null}
          receiptAttachmentId={receiptAttachmentId}
          onReceiptUploaded={handleReceiptUploaded}
          onReceiptRemoved={handleReceiptRemoved}
        />
      </DialogContent>
      <DialogActions>
        <Button onClick={() => onClose(false)}>{t('common.cancel', 'Cancel')}</Button>
        <Button onClick={handleSave} variant="contained" disabled={saving}>
          {t('common.save', 'Save')}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
