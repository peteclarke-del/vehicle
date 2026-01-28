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

  const removeItem = (index) => {
    const it = (formData.items || [])[index];
    if (!it) return;
    const confirmDelete = window.confirm(t('service.deletePermanentlyPrompt'));
    if (confirmDelete) {
      // attempt to delete from backend if we have an id and type
      (async () => {
        try {
          if (it.id || it.consumableId) {
            const targetId = it.consumableId || it.id;
            if (it.type === 'part') await api.delete(`/parts/${targetId}`);
            else if (it.type === 'consumable') await api.delete(`/consumables/${targetId}`);
            else if (it.type === 'service') await api.delete(`/service-records/${targetId}`);
          }
        } catch (err) {
          // ignore delete errors
        }
      })();
    }
    // in both cases remove association from this service form
    const items = [...(formData.items || [])];
    items.splice(index, 1);
    setFormData({ ...formData, items });
  };

  // (AddRepairItemModal removed) adding/linking handled via child dialogs and addItem()

  // edit handlers
  const handleEditItem = async (index) => {
    const it = (formData.items || [])[index];
    if (!it) return;
    setEditingItemIndex(index);
    if (it.type === 'part') {
      setOpenPartDialog(true);
    } else if (it.type === 'consumable') {
      const consumableId = it.consumableId || it.id;
      if (consumableId) {
        try {
          const resp = await api.get(`/consumables/${consumableId}`);
          setSelectedConsumable(resp.data);
        } catch (err) {
          console.error('Error loading consumable', err);
          setSelectedConsumable(it);
        }
      } else {
        setSelectedConsumable(it);
      }
      setOpenConsumableDialog(true);
    } else if (it.type === 'labour') {
      setLabourEditorIndex(index);
    }
  };

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
      const { type, id, index } = confirmTarget;
      if (action === 'delete') {
        if (id) {
          if (type === 'consumable') await api.delete(`/consumables/${id}`);
          else if (type === 'part') await api.delete(`/parts/${id}`);
          else if (type === 'service') await api.delete(`/service-records/${id}`);
        }
      } else if (action === 'disassociate') {
        if (id) {
          let url;
          if (type === 'consumable') url = `/consumables/${id}`;
          else if (type === 'part') url = `/parts/${id}`;
          else if (type === 'service') url = `/service-records/${id}`;
          if (url) await api.put(url, { serviceRecordId: 0 });
        }
      }
      // remove from local items
      const items = [...(formData.items || [])];
      if (typeof index === 'number') items.splice(index, 1);
      setFormData({ ...formData, items });
    } catch (err) {
      console.error('Error performing confirm action', err);
    } finally {
      setConfirmOpen(false);
      setConfirmTarget(null);
    }
  };

  const handlePartSavedFromDialog = (savedPart) => {
    if (editingItemIndex === null) return;
    const items = [...(formData.items || [])];
    items[editingItemIndex] = { ...items[editingItemIndex], description: savedPart.description || savedPart.name || items[editingItemIndex].description, cost: String(savedPart.cost || items[editingItemIndex].cost), id: savedPart.id };
    setFormData({ ...formData, items });
    setOpenPartDialog(false);
    setEditingItemIndex(null);
  };

  const handleConsumableSavedFromDialog = (savedConsumable) => {
    if (editingItemIndex === null) return;
    const items = [...(formData.items || [])];
    const desc = savedConsumable.description || items[editingItemIndex].description || '';
    items[editingItemIndex] = { ...items[editingItemIndex], description: desc, cost: String(savedConsumable.cost || items[editingItemIndex].cost), consumableId: savedConsumable.id };
    setFormData({ ...formData, items });
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
      }));
      if (items.length > 0) {
        laborTotal = items.filter(i => i.type === 'labour').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseInt(i.quantity || 1))), 0);
        partsTotal = items.filter(i => i.type === 'part').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseInt(i.quantity || 1))), 0);
      }

      const consumablesTotal = (formData.items || []).filter(i => i.type === 'consumable').reduce((s, i) => s + (parseFloat(i.cost || 0) * (parseInt(i.quantity || 1))), 0);

      const data = { 
        ...formData, 
        vehicleId,
        mileage: formData.mileage ? Math.round(toKm(parseFloat(formData.mileage))) : null,
        receiptAttachmentId,
        items,
        laborCost: laborTotal.toFixed(2),
        partsCost: partsTotal.toFixed(2),
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
            <Grid item xs={12} sm={4}>
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
            <Grid item xs={12} sm={4}>
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
            <Grid item xs={12} sm={4}>
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
                {(formData.items || []).map((it, idx) => (
                  <div key={idx} style={{ padding: 6, borderBottom: '1px solid #eee', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                      <Typography component="button" onClick={async (e) => {
                        e.preventDefault();
                        if (it.type === 'part') {
                          setEditingItemIndex(idx);
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
                              console.error('Error loading consumable', err);
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
                            console.error('Error loading service record', err);
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
                      <span>{`— ${it.quantity || 1} × ${parseFloat(it.cost || 0).toFixed(2)}`}</span>
                    </div>
                    <div>
                      <Tooltip title={t('common.delete')}> 
                        <IconButton size="small" onClick={() => { setConfirmTarget({ type: it.type, id: it.consumableId || it.id, name: it.description || it.name || '', index: idx }); setConfirmOpen(true); }}>
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </div>
                  </div>
                ))}
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
      <PartDialog
        open={openPartDialog}
        onClose={async (saved) => {
          setOpenPartDialog(false);
          const idx = editingItemIndex;
          setEditingItemIndex(null);
          if (saved) {
            const savedPart = saved;
            if (idx !== null && idx !== undefined) {
              const items = [...(formData.items || [])];
              items[idx] = { ...items[idx], description: savedPart.description || savedPart.name || items[idx].description, cost: String(savedPart.cost || items[idx].cost), id: savedPart.id };
              setFormData({ ...formData, items });
            } else {
              const newItem = { type: 'part', description: savedPart.description || savedPart.name || '', cost: String(savedPart.cost || 0), quantity: savedPart.quantity || 1, id: savedPart.id };
              setFormData(prev => ({ ...prev, items: [...(prev.items || []), newItem] }));
            }
          }
          setSelectedPart(null);
        }}
        part={editingItemIndex !== null ? (formData.items || [])[editingItemIndex] : selectedPart}
        vehicleId={vehicleId}
      />
      <ConsumableDialog
        open={openConsumableDialog}
        onClose={async (saved) => {
          setOpenConsumableDialog(false);
          const idx = editingItemIndex;
          setEditingItemIndex(null);
            if (saved) {
              const savedConsumable = saved;
              const desc = savedConsumable.description || '';
              if (idx !== null && idx !== undefined) {
                const items = [...(formData.items || [])];
                items[idx] = { ...items[idx], description: desc || items[idx].description, cost: String(savedConsumable.cost || items[idx].cost), consumableId: savedConsumable.id };
                setFormData({ ...formData, items });
              } else {
                const newItem = { type: 'consumable', description: desc, cost: String(savedConsumable.cost || 0), quantity: savedConsumable.quantity || 1, consumableId: savedConsumable.id };
                setFormData(prev => ({ ...prev, items: [...(prev.items || []), newItem] }));
              }
            }
          setSelectedConsumable(null);
        }}
        consumable={selectedConsumable || (editingItemIndex !== null ? (formData.items || [])[editingItemIndex] : null)}
        vehicleId={vehicleId}
      />

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
