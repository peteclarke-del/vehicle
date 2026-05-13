import React, { useState, useEffect, useRef } from 'react';
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
import { useUserPreferences } from '../contexts/UserPreferencesContext';
import { useDistance } from '../hooks/useDistance';
import useVehicleStatusFilter from '../hooks/useVehicleStatusFilter';
import FilteredVehicleSelector from './FilteredVehicleSelector';
import ReceiptUpload from './ReceiptUpload';
import UrlScraper from './UrlScraper';
import logger from '../utils/logger';

const normalizeListPayload = (payload, nestedKeys = []) => {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (!payload || typeof payload !== 'object') {
    return [];
  }

  for (const key of nestedKeys) {
    if (Array.isArray(payload[key])) {
      return payload[key];
    }
  }

  return [];
};

export default function PartDialog({ open, onClose, part, vehicleId, onVehicleMoved }) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const { setDefaultVehicle } = useUserPreferences();
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
  const [dialogVehicleId, setDialogVehicleId] = useState(part?.vehicleId || vehicleId || '');
  const [moveVehicles, setMoveVehicles] = useState([]);
  const [movingVehicle, setMovingVehicle] = useState(false);
  const [vehicleTypes, setVehicleTypes] = useState([]);
  const [partCategories, setPartCategories] = useState([]);
  const [vehicleTypeId, setVehicleTypeId] = useState(null);
  const initKeyRef = useRef(null);

  const actualVehicleId = dialogVehicleId;
  const isGeneralStock = !actualVehicleId;
  const {
    statusFilter: moveStatusFilter,
    filteredVehicles: filteredMoveVehicles,
    handleStatusFilterChange: handleMoveStatusFilterChange,
    STATUS_OPTIONS: MOVE_STATUS_OPTIONS,
  } = useVehicleStatusFilter(moveVehicles, 'partMoveStatusFilter');

  // Determine if we're in MOT/Service context (pre-filled IDs)
  // Only show link dropdown when creating NEW part from service/MOT, not when editing existing
  // Check if the part prop has the key (even if null) to detect context
  const isFromMotOrService = !part?.id && part && ('motRecordId' in part || 'serviceRecordId' in part);
  const canLinkExisting = !part?.id && !!actualVehicleId;

  // Load linkable parts when creating a part for a vehicle.
  useEffect(() => {
    const loadUnassociated = async () => {
      if (!open || !canLinkExisting) return;
      try {
        // Normal add flow: stock ledger only.
        // MOT/Service flow: current vehicle parts + stock ledger.
        const [vehicleResp, stockItemsResp] = await Promise.all([
          actualVehicleId && isFromMotOrService
            ? api.get('/parts', { params: { vehicleId: actualVehicleId, unassociated: 'true' } })
            : Promise.resolve({ data: [] }),
          vehicleTypeId
            ? api.get('/stock-items', { params: { itemType: 'part', vehicleTypeId, inStock: 'true' } })
            : Promise.resolve({ data: [] }),
        ]);
        const vehicleParts = (vehicleResp.data || []).map(p => ({ ...p, _source: 'vehicle', _selectId: `part:${p.id}` }));
        const stockItems = (stockItemsResp.data || []).map(s => ({
          ...s,
          _source: 'stockItem',
          _selectId: `stock:${s.id}`,
          name: s.description || s.category,
        }));
        setExistingParts([...vehicleParts, ...stockItems]);
      } catch (e) {
        logger.error('Failed to load unassociated parts', e);
        setExistingParts([]);
      }
    };
    loadUnassociated();
  }, [open, actualVehicleId, canLinkExisting, api, vehicleTypeId, isFromMotOrService]);

  // Handle selection of existing part from dropdown
  const handleExistingPartSelected = async (selectedId) => {
    setSelectedExistingPartId(selectedId);
    if (!selectedId) {
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

    if (String(selectedId).startsWith('stock:')) {
      const stockItemId = Number(String(selectedId).split(':')[1]);
      const stockItem = existingParts.find(p => p._source === 'stockItem' && Number(p.id) === stockItemId);
      if (!stockItem) {
        return;
      }

      const matchedCategory = partCategories.find(
        (pc) => (pc.name || '').toLowerCase() === (stockItem.category || '').toLowerCase()
      );

      setIsLinkingExisting(true);
      setFormData({
        description: stockItem.description || stockItem.category || '',
        partNumber: stockItem.partNumber || '',
        manufacturer: stockItem.manufacturer || '',
        partCategoryId: matchedCategory?.id ?? '',
        price: stockItem.price ?? '',
        quantity: 1,
        purchaseDate: stockItem.purchaseDate || '',
        installationDate: '',
        mileageAtInstallation: '',
        warranty: stockItem.warranty || '',
        supplier: stockItem.supplier || '',
        notes: ''
      });
      setReceiptAttachmentId(stockItem.receiptAttachmentId || null);
      setProductUrl('');
      return;
    }

    const partId = Number(String(selectedId).replace('part:', ''));

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
    if (!open) {
      initKeyRef.current = null;
      return;
    }

    const key = part?.id ?? 'new';
    if (initKeyRef.current === key) {
      return;
    }
    initKeyRef.current = key;

    if (part) {
      setDialogVehicleId(part.vehicleId || vehicleId || '');
      setVehicleTypeId(part.vehicleTypeId || null);
      setSelectedExistingPartId('');
      setIsLinkingExisting(false);
      // Only convert mileage if this is backend data (has id), prefill data is already in display units
      const mileageValue = part.mileageAtInstallation
        ? (part.id ? Math.round(convert(part.mileageAtInstallation)) : part.mileageAtInstallation)
        : '';
      setFormData({
        description: part.description || '',
        partNumber: part.partNumber || '',
        manufacturer: part.manufacturer || '',
        partCategoryId: part.partCategoryId ?? part.partCategory?.id ?? '',
        price: part.price ?? part.cost ?? '',
        quantity: part.quantity ?? 1,
        purchaseDate: part.purchaseDate || '',
        installationDate: part.installationDate || '',
        mileageAtInstallation: mileageValue,
        warranty: part.warranty || '',
        supplier: part.supplier || '',
        notes: part.notes || ''
      });
      setReceiptAttachmentId(part.receiptAttachmentId || null);
      setProductUrl(part.productUrl || '');
      setMotRecordId(part.motRecordId || null);
      setServiceRecordId(part.serviceRecordId || null);
    } else {
      setDialogVehicleId(vehicleId || '');
      setVehicleTypeId(null);
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
  }, [part, open, convert, vehicleId]);

  useEffect(() => {
    const loadVehicles = async () => {
      try {
        const resp = await api.get('/vehicles');
        const list = Array.isArray(resp.data) ? resp.data : [];
        setMoveVehicles([
          { id: '__stock__', registration: t('parts.generalStock', 'General Stock'), status: 'Live', alwaysVisible: true },
          ...list,
        ]);
      } catch (e) {
        logger.error('Failed to load vehicles for part move', e);
        setMoveVehicles([{ id: '__stock__', registration: t('parts.generalStock', 'General Stock'), status: 'Live', alwaysVisible: true }]);
      }
    };

    if (open && part?.id) {
      loadVehicles();
    }
  }, [open, part?.id, api, t]);

  // Fetch vehicle to get vehicleTypeId (for vehicle-bound parts)
  useEffect(() => {
    const loadVehicle = async () => {
      if (!actualVehicleId) return;
      try {
        const resp = await api.get(`/vehicles/${actualVehicleId}`);
        setVehicleTypeId(resp.data?.vehicleType?.id || null);
      } catch (e) {
        logger.error('Failed to load vehicle', e);
      }
    };
    if (open) loadVehicle();
  }, [open, actualVehicleId, api]);

  useEffect(() => {
    const loadVehicleTypes = async () => {
      try {
        const resp = await api.get('/vehicle-types');
        setVehicleTypes(normalizeListPayload(resp.data, ['items', 'data', 'vehicleTypes']));
      } catch (e) {
        logger.error('Failed to load vehicle types', e);
        setVehicleTypes([]);
      }
    };
    if (open) loadVehicleTypes();
  }, [open, api]);

  useEffect(() => {
    const loadCategories = async () => {
      try {
        const params = vehicleTypeId ? { vehicleTypeId } : {};
        const resp = await api.get('/part-categories', { params });
        setPartCategories(normalizeListPayload(resp.data, ['items', 'data', 'categories', 'partCategories']));
      } catch (e) {
        logger.error('Failed to load part categories', e);
        setPartCategories([]);
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
    if (ocrData.sku) updates.sku = ocrData.sku;
    if (ocrData.manufacturer) updates.manufacturer = ocrData.manufacturer;
    if (ocrData.supplier) updates.supplier = ocrData.supplier;
    if (ocrData.quantity) updates.quantity = ocrData.quantity;
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

  const handleMoveVehicleChange = async (newVehicleId) => {
    if (!part?.id || !newVehicleId || String(newVehicleId) === String(actualVehicleId)) {
      return;
    }

    setMovingVehicle(true);
    try {
      const moveToStock = newVehicleId === '__stock__';
      await api.put(`/parts/${part.id}`, {
        vehicleId: moveToStock ? null : newVehicleId,
        motRecordId: null,
        serviceRecordId: null,
      });
      setDialogVehicleId(moveToStock ? '' : newVehicleId);
      setMotRecordId(null);
      setServiceRecordId(null);
      if (!moveToStock) {
        setDefaultVehicle(newVehicleId);
      }
      onVehicleMoved?.(moveToStock ? '__stock__' : newVehicleId);
    } catch (error) {
      logger.error('Error moving part to vehicle:', error);
    } finally {
      setMovingVehicle(false);
    }
  };

  const getExistingPartLabel = (item) => {
    const prefix = item._source === 'stockItem'
      ? `[${t('stock.title', 'Stock Ledger')}] `
      : item._source === 'stock'
        ? `[${t('parts.generalStock', 'General Stock')}] `
        : `[${t('common.vehicle', 'Vehicle')}] `;

    return `${prefix}${item.description || item.name || t('common.noName')}`
      + `${item.partNumber ? ` (${item.partNumber})` : ''}`
      + ` - ${item.purchaseDate || t('common.noDate')}`;
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

      // If linking existing and a service is selected, use the service date as installationDate
      let installationDate = formData.installationDate;
      if (isLinkingExisting && serviceRecordId && serviceRecords && serviceRecords.length > 0) {
        const svc = serviceRecords.find(s => String(s.id) === String(serviceRecordId));
        if (svc && svc.serviceDate) {
          installationDate = svc.serviceDate;
        }
      }
      const data = {
        ...formData,
        partCategoryId: normalizedCategoryId,
        vehicleId: actualVehicleId,
        vehicleTypeId,
        price: price,
        quantity: quantity,
        cost: computedCost,
        receiptAttachmentId,
        productUrl,
        motRecordId: isGeneralStock ? null : motRecordId,
        serviceRecordId: isGeneralStock ? null : serviceRecordId,
        installationDate: isGeneralStock ? null : installationDate,
        mileageAtInstallation: isGeneralStock ? null : (formData.mileageAtInstallation ? Math.round(toKm(parseFloat(formData.mileageAtInstallation))) : null)
      };

      let resp;
      if (isLinkingExisting && selectedExistingPartId) {
        if (String(selectedExistingPartId).startsWith('stock:')) {
          data.stockItemId = Number(String(selectedExistingPartId).split(':')[1]);
          resp = await api.post('/parts', data);
        } else {
          // Update existing part to link with MOT/Service
          const existingId = Number(String(selectedExistingPartId).replace('part:', ''));
          resp = await api.put(`/parts/${existingId}`, data);
        }
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
          {part?.id && (
            <Box sx={{ ml: 'auto', minWidth: 380, display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 0.75, flexWrap: 'wrap' }}>
              <Box sx={{ minWidth: 78, fontSize: '0.85rem', lineHeight: 1.2 }}>{t('parts.movePartTo', 'Move part to:')}</Box>
              <FilteredVehicleSelector
                id="move-part"
                statusFilter={moveStatusFilter}
                onStatusFilterChange={handleMoveStatusFilterChange}
                statusOptions={MOVE_STATUS_OPTIONS}
                vehicles={filteredMoveVehicles}
                selectedVehicle={actualVehicleId || '__stock__'}
                onVehicleChange={handleMoveVehicleChange}
                includeViewAll={false}
                minWidth={220}
                compact={true}
              />
            </Box>
          )}
          {canLinkExisting && existingParts.length > 0 && (
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
                    <MenuItem key={p._selectId || p.id} value={p._selectId || p.id}>
                      {getExistingPartLabel(p)}
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
                    inputProps={{ step: '0.01', min: '0' }}
                  />
                </Grid>
              </Grid>
            </Grid>
            {isGeneralStock && (
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  required
                  select
                  name="vehicleTypeId"
                  label={t('common.vehicleType', 'Vehicle Type')}
                  value={vehicleTypeId || ''}
                  onChange={(e) => setVehicleTypeId(e.target.value)}
                >
                  <MenuItem value="">{t('common.selectVehicleType', 'Select vehicle type')}</MenuItem>
                  {vehicleTypes.map(vt => (
                    <MenuItem key={vt.id} value={vt.id}>{vt.name}</MenuItem>
                  ))}
                </TextField>
              </Grid>
            )}
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
            {!isGeneralStock && (
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
            )}
            {!isGeneralStock && (
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
            )}
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
            {!isGeneralStock && (
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
            )}
            {!isGeneralStock && (
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
            )}
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
                entityId={part?.id}
                vehicleId={actualVehicleId}
                receiptAttachmentId={receiptAttachmentId}
                onReceiptUploaded={handleReceiptUploaded}
                onReceiptRemoved={() => setReceiptAttachmentId(null)}
              />
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => onClose(false)} disabled={loading || movingVehicle}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" variant="contained" disabled={loading || movingVehicle}>
            {loading ? t('common.loading') : t('common.save')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
