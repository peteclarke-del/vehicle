import React, { useState, useEffect, useMemo } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  MenuItem,
  IconButton,
  Tooltip,
  Typography,
} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import BuildIcon from '@mui/icons-material/Build';
import OpacityIcon from '@mui/icons-material/Opacity';
import MiscellaneousServicesIcon from '@mui/icons-material/MiscellaneousServices';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useDistance } from '../hooks/useDistance';
import ReceiptUpload from './ReceiptUpload';
import PartDialog from './PartDialog';
import ConsumableDialog from './ConsumableDialog';
import KnightRiderLoader from './KnightRiderLoader';
import logger from '../utils/logger';

const ServiceDialog = ({ open, serviceRecord, vehicleId, onClose }) => {
  const [formData, setFormData] = useState({
    serviceDate: new Date().toISOString().split('T')[0],
    serviceType: 'Full Service',
    laborCost: '',
    partsCost: '0',
    items: [],
    mileage: '',
    serviceProvider: '',
    workPerformed: '',
    additionalCosts: '0.00',
    nextServiceDate: '',
    nextServiceMileage: '',
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
          items: (serviceRecord.items || []).map(it => ({ ...it, description: it.description || it.name || null })),
          mileage: serviceRecord.mileage ? Math.round(convert(serviceRecord.mileage)) : '',
          serviceProvider: serviceRecord.serviceProvider || '',
          workPerformed: serviceRecord.workPerformed || '',
          additionalCosts: serviceRecord.additionalCosts || '0.00',
          nextServiceDate: serviceRecord.nextServiceDate || '',
          nextServiceMileage: serviceRecord.nextServiceMileage || '',
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
          workPerformed: '',
          additionalCosts: '0.00',
          nextServiceDate: '',
          nextServiceMileage: '',
          notes: '',
        });
        setReceiptAttachmentId(null);
        setMotRecordId(null);
      }
    }
  }, [open, serviceRecord, convert]);

  useEffect(() => {
    if (!open || !vehicleId) return;
    (async () => {
      try {
        const resp = await api.get(`/mot-records?vehicleId=${vehicleId}`);
        setMotRecords(Array.isArray(resp.data) ? resp.data : []);
      } catch (err) {
        logger.error('Error loading MOT records', err);
        setMotRecords([]);
      }
    })();
  }, [open, vehicleId, api]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };


  const addItem = async () => {
    const svcPrefill = {
      serviceRecordId: serviceRecord?.id || null,
      serviceDate: formData.serviceDate || '',
      mileage: formData.mileage ? Math.round(toKm(parseFloat(formData.mileage))) : null,
    };
    if (selectedAddType === 'part') {
      setSelectedPart({ serviceRecordId: svcPrefill.serviceRecordId, installationDate: svcPrefill.serviceDate, mileageAtInstallation: svcPrefill.mileage });
      setOpenPartDialog(true);
    } else if (selectedAddType === 'consumable') {
      setSelectedConsumable({ serviceRecordId: svcPrefill.serviceRecordId, lastChanged: svcPrefill.serviceDate, mileageAtChange: svcPrefill.mileage });
      setOpenConsumableDialog(true);
    } else if (selectedAddType === 'labour') {
      const idx = (formData.items || []).length;
      const newItem = { type: 'labour', description: '', cost: '0.00', quantity: 1 };
      setFormData(prev => ({ ...prev, items: [...(prev.items || []), newItem] }));
      setLabourEditorIndex(idx);
    }
  };

  const [openPartDialog, setOpenPartDialog] = useState(false);
  const [openConsumableDialog, setOpenConsumableDialog] = useState(false);
  const [selectedAddType, setSelectedAddType] = useState('part');
  const [selectedPart, setSelectedPart] = useState(null);
  const [selectedConsumable, setSelectedConsumable] = useState(null);
  const [labourEditorIndex, setLabourEditorIndex] = useState(null);
  const [editingItemIndex, setEditingItemIndex] = useState(null);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [confirmTarget, setConfirmTarget] = useState(null); // { type, id, name, index }
  const [openNestedServiceDialog, setOpenNestedServiceDialog] = useState(false);
  const [nestedServiceRecord, setNestedServiceRecord] = useState(null);
  const [recentlyUpdatedItems, setRecentlyUpdatedItems] = useState(new Set());

  // No current vs historical comparison shown in UI


  // (AddRepairItemModal removed) adding/linking handled via child dialogs and addItem()

  // edit handlers

  const handleLabourSave = (index, updated) => {
    const items = [...(formData.items || [])];
    items[index] = { ...items[index], ...updated };
    setFormData({ ...formData, items });
    setLabourEditorIndex(null);
    setEditingItemIndex(null);
  };

  const handleConfirmAction = async (action) => {
    if (!confirmTarget) return;
    try {
      const { serviceItemId, consumableId, partId, index } = confirmTarget;

      // If there is a ServiceItem id, prefer operating on that
      if (serviceItemId) {
        // action === 'delete' -> remove linked entity too
        // action === 'disassociate' -> keep linked entity but remove association
        const removeLinked = action === 'delete' ? 1 : 0;
        await api.delete(`/service-items/${serviceItemId}?removeLinked=${removeLinked}`);
      } else {
        // No ServiceItem row: fall back to operating on linked entity directly
        if (action === 'delete') {
          if (consumableId) await api.delete(`/consumables/${consumableId}`);
          else if (partId) await api.delete(`/parts/${partId}`);
        } else if (action === 'disassociate') {
          if (consumableId) await api.put(`/consumables/${consumableId}`, { serviceRecordId: 0 });
          else if (partId) await api.put(`/parts/${partId}`, { serviceRecordId: 0 });
        }
      }

      // remove from local items list in UI
      const items = [...(formData.items || [])];
      if (typeof index === 'number') items.splice(index, 1);
      setFormData({ ...formData, items });
    } catch (err) {
      logger.error('Error performing confirm action', err);
    } finally {
      setConfirmOpen(false);
      setConfirmTarget(null);
    }
  };

  const handlePartSavedFromDialog = async (savedPart) => {
    if (editingItemIndex === null) return;
    const items = [...(formData.items || [])];
    const currentItem = items[editingItemIndex];
    
    // Fetch the full updated part data from the API
    let fullPartData = null;
    try {
      const resp = await api.get(`/parts/${savedPart.id}`);
      fullPartData = resp.data;
      const unitPrice = fullPartData.price ?? fullPartData.cost ?? currentItem.cost;
      const newQuantity = fullPartData.quantity ?? currentItem.quantity ?? 1;
      // If includedInServiceCost is false (existing item), set cost to 0
      const newCost = fullPartData.includedInServiceCost === false ? '0.00' : String(unitPrice ?? 0);
      
      items[editingItemIndex] = { 
        ...currentItem, 
        description: fullPartData.description || fullPartData.name || currentItem.description, 
        cost: newCost,
        quantity: newQuantity,
        partId: fullPartData.id,
        name: fullPartData.name,
        includedInServiceCost: fullPartData.includedInServiceCost
      };
      
      // Update the ServiceItem in the database if it exists
      if (currentItem.id) {
        try {
          await api.patch(`/service-items/${currentItem.id}`, {
            cost: parseFloat(newCost),
            quantity: parseFloat(newQuantity),
            description: fullPartData.description || fullPartData.name
          });
        } catch (err) {
          logger.error('Error updating service item cost', err);
        }
      }
    } catch (err) {
      logger.error('Error fetching updated part data', err);
      const fallbackQuantity = savedPart.quantity ?? currentItem.quantity ?? 1;
      items[editingItemIndex] = { ...currentItem, description: savedPart.description || savedPart.name || currentItem.description, cost: String(savedPart.cost || currentItem.cost), quantity: fallbackQuantity, id: savedPart.id };
    }
    
    setFormData({ ...formData, items });
    
    // Mark as recently updated for highlighting
    setRecentlyUpdatedItems(prev => new Set(prev).add(editingItemIndex));
    setTimeout(() => {
      setRecentlyUpdatedItems(prev => {
        const newSet = new Set(prev);
        newSet.delete(editingItemIndex);
        return newSet;
      });
    }, 3000);
    
    setOpenPartDialog(false);
    setEditingItemIndex(null);
  };

  const handleConsumableSavedFromDialog = async (savedConsumable) => {
    if (editingItemIndex === null) return;
    const items = [...(formData.items || [])];
    const currentItem = items[editingItemIndex];
    
    // Fetch the full updated consumable data from the API
    let fullConsumableData = null;
    try {
      const resp = await api.get(`/consumables/${savedConsumable.id}`);
      fullConsumableData = resp.data;
      const unitPrice = fullConsumableData.cost ?? currentItem.cost;
      const newQuantity = fullConsumableData.quantity ?? currentItem.quantity ?? 1;
      // If includedInServiceCost is false (existing item), set cost to 0
      const newCost = fullConsumableData.includedInServiceCost === false ? '0.00' : String(unitPrice ?? 0);
      
      items[editingItemIndex] = { 
        ...currentItem, 
        description: fullConsumableData.description || currentItem.description || '', 
        cost: newCost,
        quantity: newQuantity,
        consumableId: fullConsumableData.id,
        name: fullConsumableData.name || fullConsumableData.description,
        includedInServiceCost: fullConsumableData.includedInServiceCost
      };
      
      // Update the ServiceItem in the database if it exists
      if (currentItem.id) {
        try {
          await api.patch(`/service-items/${currentItem.id}`, {
            cost: parseFloat(newCost),
            quantity: parseFloat(newQuantity),
            description: fullConsumableData.description
          });
        } catch (err) {
          logger.error('Error updating service item cost', err);
        }
      }
    } catch (err) {
      logger.error('Error fetching updated consumable data', err);
      const desc = savedConsumable.description || currentItem.description || '';
      const fallbackQuantity = savedConsumable.quantity ?? currentItem.quantity ?? 1;
      items[editingItemIndex] = { ...currentItem, description: desc, cost: String(savedConsumable.cost || currentItem.cost), quantity: fallbackQuantity, consumableId: savedConsumable.id };
    }
    
    setFormData({ ...formData, items });
    
    // Mark as recently updated for highlighting
    setRecentlyUpdatedItems(prev => new Set(prev).add(editingItemIndex));
    setTimeout(() => {
      setRecentlyUpdatedItems(prev => {
        const newSet = new Set(prev);
        newSet.delete(editingItemIndex);
        return newSet;
      });
    }, 3000);
    
    setOpenConsumableDialog(false);
    setEditingItemIndex(null);
  };

  // recalc totals when items change
  useEffect(() => {
    const items = formData.items || [];
    const partsTotal = items.filter(i => i.type === 'part').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseFloat(i.quantity || 1) || 1)), 0);
    const labourTotal = items.filter(i => i.type === 'labour').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseFloat(i.quantity || 1) || 1)), 0);
    // update state only if different to avoid loops
    setFormData(prev => {
      const p = (parseFloat(prev.partsCost || 0)).toFixed(2);
      const l = (parseFloat(prev.laborCost || 0)).toFixed(2);
      if (parseFloat(p) !== parseFloat(partsTotal.toFixed(2)) || parseFloat(l) !== parseFloat(labourTotal.toFixed(2))) {
        return { ...prev, partsCost: partsTotal.toFixed(2), laborCost: labourTotal.toFixed(2) };
      }
      return prev;
    });
  }, [formData.items]);

  const consumablesTotal = useMemo(() => {
    const items = formData.items || [];
    const total = items
      .filter((i) => i.type === 'consumable')
      .reduce(
        (sum, i) => sum + (parseFloat(i.cost || 0) * (parseFloat(i.quantity || 1) || 1)),
        0
      );
    return total.toFixed(2);
  }, [formData.items]);

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
        quantity: it.quantity || 1,
        consumableId: it.consumableId || null,
        partId: it.type === 'part' ? (it.partId || it.id || null) : null,
      }));
      if (items.length > 0) {
        laborTotal = items.filter(i => i.type === 'labour').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseFloat(i.quantity || 1))), 0);
        partsTotal = items.filter(i => i.type === 'part').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseFloat(i.quantity || 1))), 0);
      }

      // Calculate consumables total from items
      // This will correctly exclude existing items (which already have cost=0 in the items array)
      const consumablesTotal = (formData.items || [])
        .filter(i => i.type === 'consumable')
        .reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseFloat(i.quantity || 1))), 0);

      const data = { 
        ...formData, 
        vehicleId,
        mileage: formData.mileage ? Math.round(toKm(parseFloat(formData.mileage))) : null,
        receiptAttachmentId,
        items,
        laborCost: laborTotal.toFixed(2),
        partsCost: partsTotal.toFixed(2),
        // Send calculated consumablesCost (items with cost=0 won't contribute to total)
        consumablesCost: consumablesTotal.toFixed(2),
        motRecordId: motRecordId || null,
        additionalCosts: parseFloat(formData.additionalCosts) || 0,
        nextServiceDate: formData.nextServiceDate || null,
        nextServiceMileage: formData.nextServiceMileage ? Math.round(toKm(parseFloat(formData.nextServiceMileage))) : null,
      };
      if (serviceRecord && serviceRecord.id) {
        await api.put(`/service-records/${serviceRecord.id}`, data);
      } else {
        await api.post('/service-records', data);
      }
      onClose(true);
    } catch (error) {
      logger.error('Error saving service record:', error);
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
                <MenuItem value="Tyres">{t('service.tyres') || 'Tyres'}</MenuItem>
                <MenuItem value="Other">{t('service.other')}</MenuItem>
              </TextField>
            </Grid>
            <Grid item xs={12} sm={3}>
              <TextField
                fullWidth
                type="number"
                name="partsCost"
                label={t('service.partsCost')}
                value={formData.partsCost}
                onChange={handleChange}
                inputProps={{ step: '0.01', min: '0', readOnly: true }}
              />
            </Grid>
            <Grid item xs={12} sm={3}>
              <TextField
                fullWidth
                type="number"
                name="consumablesCost"
                label={t('service.consumablesCost')}
                value={consumablesTotal}
                inputProps={{ step: '0.01', min: '0', readOnly: true }}
              />
            </Grid>
            <Grid item xs={12} sm={3}>
              <TextField
                fullWidth
                type="number"
                name="laborCost"
                label={t('service.laborCost')}
                value={formData.laborCost}
                onChange={handleChange}
                inputProps={{ step: '0.01', min: '0', readOnly: true }}
              />
            </Grid>
            <Grid item xs={12} sm={3}>
              <TextField
                fullWidth
                type="number"
                name="additionalCosts"
                label={t('service.additionalCosts')}
                value={formData.additionalCosts}
                onChange={handleChange}
                inputProps={{ step: '0.01', min: '0' }}
              />
            </Grid>
            <Grid item xs={12}>
              <Grid container spacing={1} alignItems="center">
                <Grid item>
                  <TextField select size="small" value={selectedAddType} onChange={(e) => setSelectedAddType(e.target.value)} sx={{ minWidth: 200 }}>
                    <MenuItem value="part">{t('parts.part') || 'Part'}</MenuItem>
                    <MenuItem value="consumable">{t('consumables.consumable') || 'Consumable'}</MenuItem>
                    <MenuItem value="labour">{t('service.labour') || 'Labour'}</MenuItem>
                  </TextField>
                </Grid>
                <Grid item>
                  <Button onClick={addItem}>{`+ ${t('service.addItem')}`}</Button>
                </Grid>
              </Grid>
              <div style={{ marginTop: 8 }}>
                {(formData.items || []).map((it, idx) => {
                  const historicalPrice = parseFloat(it.cost || 0);
                  
                  const isRecentlyUpdated = recentlyUpdatedItems.has(idx);
                  return (
                  <div key={idx} style={{ 
                    padding: 6, 
                    borderBottom: '1px solid #eee', 
                    display: 'flex', 
                    alignItems: 'center', 
                    justifyContent: 'space-between',
                    backgroundColor: isRecentlyUpdated ? '#c8e6c9' : 'transparent',
                    transition: 'background-color 0.3s ease'
                  }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                      <Typography component="button" onClick={async (e) => {
                        e.preventDefault();
                        if (it.type === 'part') {
                          setEditingItemIndex(idx);
                          const partId = it.partId || it.id;
                          if (partId) {
                            try {
                              const resp = await api.get(`/parts/${partId}`);
                              setSelectedPart(resp.data);
                            } catch (err) {
                              logger.error('Error loading part', err);
                              setSelectedPart(it);
                            }
                          } else {
                            setSelectedPart(it);
                          }
                          setOpenPartDialog(true);
                        } else if (it.type === 'consumable') {
                          setEditingItemIndex(idx);
                          // ensure dialog receives full consumable data (including `description`)
                          const consumableId = it.consumableId || it.id;
                          if (consumableId) {
                            try {
                              const resp = await api.get(`/consumables/${consumableId}`);
                              setSelectedConsumable(resp.data);
                            } catch (err) {
                              logger.error('Error loading consumable', err);
                              setSelectedConsumable(it);
                            }
                          } else {
                            setSelectedConsumable(it);
                          }
                          setOpenConsumableDialog(true);
                        } else if (it.type === 'labour') {
                          setLabourEditorIndex(idx);
                        } else if (it.type === 'service') {
                          try {
                            const resp = await api.get(`/service-records/${it.id}`);
                            setNestedServiceRecord(resp.data);
                            setOpenNestedServiceDialog(true);
                          } catch (err) {
                            logger.error('Error loading service record', err);
                          }
                        }
                      }} sx={{ background: 'none', border: 'none', padding: 0, textDecoration: 'underline', cursor: 'pointer', color: 'primary.main' }}>
                        {it.type === 'part' && <BuildIcon fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />}
                        {it.type === 'consumable' && <OpacityIcon fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />}
                        {it.type === 'service' && <MiscellaneousServicesIcon fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />}
                        {it.type === 'labour' && <MiscellaneousServicesIcon fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />}
                        {(() => {
                          if (it.type === 'part') return it.description || it.name || t('common.none');
                          if (it.type === 'consumable') return it.description || it.name || t('common.none');
                          if (it.type === 'service') return (it.items && it.items.length > 0) ? (it.items.map(i => i.description || '').filter(Boolean).join(', ')) : (it.workPerformed || it.serviceProvider || (t('service.service') || 'Service'));
                          if (it.type === 'labour') return it.description || t('common.none');
                          return t('common.none');
                        })()}
                      </Typography>
                      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', fontSize: '0.9em' }}>
                        {/* Don't show cost for existing items (includedInServiceCost=false) */}
                        {it.includedInServiceCost !== false ? (
                          <span>{`${it.quantity || 1} × ${historicalPrice.toFixed(2)}`}</span>
                        ) : (
                          <span style={{ fontStyle: 'italic', color: '#666' }}>{t('service.existingItem') || 'Existing item'}</span>
                        )}
                      </div>
                    </div>
                    <div>
                      <Tooltip title={t('common.delete')}>
                        <IconButton
                          size="small"
                          onClick={() => {
                            setConfirmTarget({
                              type: it.type,
                              serviceItemId: it.id ?? null,
                              consumableId: it.consumableId ?? null,
                              partId: it.partId ?? null,
                              name: it.description || it.name || '',
                              index: idx,
                            });
                            setConfirmOpen(true);
                          }}
                        >
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </div>
                  </div>
                )})}
              </div>
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="mileage"
                label={`${t('common.mileage')} (${getLabel()})`}
                value={formData.mileage}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="date"
                name="nextServiceDate"
                label={t('service.nextServiceDate')}
                value={formData.nextServiceDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="nextServiceMileage"
                label={t('service.nextServiceMileage')}
                value={formData.nextServiceMileage}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                name="serviceProvider"
                label={t('service.serviceProvider')}
                value={formData.serviceProvider}
                onChange={handleChange}
              />
            </Grid>
            {/* workPerformed removed — use notes for freeform descriptions */}
              <Grid item xs={12} sm={6}>
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
                name="workPerformed"
                label={t('service.workPerformed')}
                value={formData.workPerformed}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={2}
                name="notes"
                label={t('common.notes')}
                value={formData.notes}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <ReceiptUpload
                entityType="service"
                entityId={serviceRecord?.id}
                vehicleId={formData.vehicleId || vehicleId}
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
            {loading ? <KnightRiderLoader size={18} /> : t('common.save')}
          </Button>
        </DialogActions>
      </form>
      <PartDialog
        open={openPartDialog}
        onClose={async (saved) => {
          const idx = editingItemIndex;
          if (saved) {
            // Existing item: update ServiceItem and refresh UI
            if (idx !== null && idx !== undefined && saved.id) {
              await handlePartSavedFromDialog(saved);
              setSelectedPart(null);
              return;
            }

            // New item - add to items with historical snapshot
            // If includedInServiceCost is false (existing item), set cost to 0
            const itemCost = saved.includedInServiceCost === false ? '0.00' : String(saved.price || saved.cost || 0);
            const newItem = { type: 'part', description: saved.description || saved.name || '', cost: itemCost, quantity: saved.quantity || 1, partId: saved.id, includedInServiceCost: saved.includedInServiceCost };
            setFormData(prev => ({ ...prev, items: [...(prev.items || []), newItem] }));
          }

          setOpenPartDialog(false);
          setEditingItemIndex(null);
          setSelectedPart(null);
        }}
        part={selectedPart || (editingItemIndex !== null ? (formData.items || [])[editingItemIndex] : null)}
        vehicleId={vehicleId}
      />
      <ConsumableDialog
        open={openConsumableDialog}
        onClose={async (saved) => {
          const idx = editingItemIndex;
          if (saved) {
            // Existing item: update ServiceItem and refresh UI
            if (idx !== null && idx !== undefined && saved.id) {
              await handleConsumableSavedFromDialog(saved);
              setSelectedConsumable(null);
              return;
            }

            // New item - add to items with historical snapshot
            // If includedInServiceCost is false (existing item), set cost to 0
            const desc = saved.description || '';
            const itemCost = saved.includedInServiceCost === false ? '0.00' : String(saved.cost || 0);
            const newItem = { type: 'consumable', description: desc, cost: itemCost, quantity: saved.quantity || 1, consumableId: saved.id, includedInServiceCost: saved.includedInServiceCost };
            setFormData(prev => ({ ...prev, items: [...(prev.items || []), newItem] }));
          }

          setOpenConsumableDialog(false);
          setEditingItemIndex(null);
          setSelectedConsumable(null);
        }}
        consumable={selectedConsumable || (editingItemIndex !== null ? (formData.items || [])[editingItemIndex] : null)}
        vehicleId={vehicleId}
      />

      <Dialog open={confirmOpen} onClose={() => setConfirmOpen(false)}>
        <DialogTitle>{t('service.confirmRemoveTitle') || t('mot.confirmRemoveTitle')}</DialogTitle>
        <DialogContent>
          <div>{(t('service.confirmRemoveMessage') || t('mot.confirmRemoveMessage')).replace('{{name}}', confirmTarget?.name || '')}</div>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => { setConfirmOpen(false); setConfirmTarget(null); }}>{t('common.cancel')}</Button>
          <Button color="primary" onClick={() => handleConfirmAction('disassociate')}>{t('service.disassociate') || t('mot.disassociate')}</Button>
          <Button color="error" onClick={() => handleConfirmAction('delete')}>{t('common.delete')}</Button>
        </DialogActions>
      </Dialog>

      {/* Labour editor dialog */}
      <Dialog open={labourEditorIndex !== null} onClose={() => setLabourEditorIndex(null)} maxWidth="sm" fullWidth>
        <DialogTitle>{t('service.editLabour')}</DialogTitle>
        <form onSubmit={(e) => { e.preventDefault(); const idx = labourEditorIndex; const it = (formData.items || [])[idx]; if (!it) return; const updated = { description: e.target.description.value, cost: e.target.cost.value, quantity: e.target.quantity.value }; handleLabourSave(idx, updated); }}>
          <DialogContent>
            <TextField fullWidth name="description" label={t('common.description')} defaultValue={labourEditorIndex !== null ? ((formData.items || [])[labourEditorIndex] || {}).description : ''} />
            <TextField fullWidth name="cost" type="number" label={t('parts.cost') || 'Cost'} defaultValue={labourEditorIndex !== null ? ((formData.items || [])[labourEditorIndex] || {}).cost : ''} inputProps={{ step: '0.01', min: '0' }} />
            <TextField fullWidth name="quantity" type="number" label={t('common.quantity') || 'Quantity'} defaultValue={labourEditorIndex !== null ? ((formData.items || [])[labourEditorIndex] || {}).quantity || 1 : 1} inputProps={{ step: '1', min: '1' }} />
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setLabourEditorIndex(null)}>{t('common.cancel')}</Button>
            <Button type="submit" variant="contained" color="primary">{t('common.save')}</Button>
          </DialogActions>
        </form>
      </Dialog>
    </Dialog>
  );
};

export default ServiceDialog;
