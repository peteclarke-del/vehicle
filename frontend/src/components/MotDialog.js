import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  MenuItem,
  Checkbox,
  FormControlLabel,
  FormControl,
  InputLabel,
  Select,
  Box,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useDistance } from '../hooks/useDistance';
import ReceiptUpload from './ReceiptUpload';
import ServiceDialog from './ServiceDialog';
import PartDialog from './PartDialog';
import ConsumableDialog from './ConsumableDialog';
import KnightRiderLoader from './KnightRiderLoader';
import { IconButton, Typography, Tooltip } from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import BuildIcon from '@mui/icons-material/Build';
import OpacityIcon from '@mui/icons-material/Opacity';
import MiscellaneousServicesIcon from '@mui/icons-material/MiscellaneousServices';
import LinkIcon from '@mui/icons-material/Link';
import logger from '../utils/logger';

const MotDialog = ({ open, motRecord, vehicleId, vehicles, onClose }) => {
  const [formData, setFormData] = useState({
    vehicleId: vehicleId || '',
    testDate: new Date().toISOString().split('T')[0],
    result: 'Pass',
    testCost: '',
    repairCost: '0',
    mileage: '',
    testCenter: '',
    expiryDate: '',
    motTestNumber: '',
    testerName: '',
    isRetest: false,
    advisories: [],
    failures: [],
    repairDetails: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState(null);
  const [motItems, setMotItems] = useState({ parts: [], consumables: [] });
  const [selectedPart, setSelectedPart] = useState(null);
  const [selectedConsumable, setSelectedConsumable] = useState(null);
  const [selectedAddType, setSelectedAddType] = useState('part');
  const [openServiceDialog, setOpenServiceDialog] = useState(false);
  const [openPartDialog, setOpenPartDialog] = useState(false);
  const [openConsumableDialog, setOpenConsumableDialog] = useState(false);
  const [selectedServiceRecord, setSelectedServiceRecord] = useState(null);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const lastLoadedMotIdRef = useRef(null);
  const [confirmTarget, setConfirmTarget] = useState(null); // { type, id, name }
  const [unlinkedServices, setUnlinkedServices] = useState([]);
  const [selectedExistingServiceId, setSelectedExistingServiceId] = useState('');
  const [linkServiceDialogOpen, setLinkServiceDialogOpen] = useState(false);
  const [serviceToLink, setServiceToLink] = useState(null);
  const [includeCostInMot, setIncludeCostInMot] = useState(true);
  const { api } = useAuth();
  const { t } = useTranslation();
  const { convert, toKm, getLabel } = useDistance();

  const loadMotItems = useCallback(async (id) => {
    if (!id) return;
    try {
      const res = await api.get(`/mot-records/${id}/items`);
      setMotItems(res.data || { parts: [], consumables: [], serviceRecords: [] });
    } catch (err) {
      logger.error('Error loading MOT items', err);
    }
  }, [api]);

  // Load unlinked services for the vehicle when dialog opens
  const loadUnlinkedServices = useCallback(async (vId) => {
    if (!vId) return;
    try {
      const resp = await api.get('/service-records', { params: { vehicleId: vId, unassociated: 'true' } });
      setUnlinkedServices(resp.data || []);
    } catch (err) {
      logger.error('Error loading unlinked services', err);
      setUnlinkedServices([]);
    }
  }, [api]);

  // Load unlinked services when dialog opens
  useEffect(() => {
    const vId = formData.vehicleId || vehicleId;
    if (open && vId && motRecord?.id) {
      loadUnlinkedServices(vId);
    } else if (!open) {
      setUnlinkedServices([]);
      setSelectedExistingServiceId('');
    }
  }, [open, formData.vehicleId, vehicleId, motRecord?.id, loadUnlinkedServices]);

  // Recompute repair cost when mot items change
  useEffect(() => {
    const parts = motItems.parts || [];
    const consumables = motItems.consumables || [];
    const services = motItems.serviceRecords || [];
    let total = 0;
    parts.forEach(p => {
      // Exclude existing items (includedInServiceCost=false) from repair cost
      if (p.includedInServiceCost === false) return;
      const cost = parseFloat(p.cost || 0) || 0;
      const qty = parseFloat(p.quantity || 1) || 1;
      total += cost * qty;
    });
    consumables.forEach(c => {
      // Exclude existing items (includedInServiceCost=false) from repair cost
      if (c.includedInServiceCost === false) return;
      const cost = parseFloat(c.cost || 0) || 0;
      const qty = parseFloat(c.quantity || 1) || 1;
      total += cost * qty;
    });
    services.forEach(s => {
      // Exclude services where includedInMotCost=false from repair cost
      if (s.includedInMotCost === false) return;
      const t = parseFloat(s.totalCost || s.total || 0) || 0;
      total += t;
    });
    setFormData(prev => ({ ...prev, repairCost: total.toFixed(2) }));
  }, [motItems]);

  // Handle selecting a service to link - show dialog first
  const handleSelectServiceToLink = (serviceId) => {
    setSelectedExistingServiceId(serviceId);
    if (!serviceId) return;
    
    const service = unlinkedServices.find(s => s.id === parseInt(serviceId, 10));
    if (service) {
      setServiceToLink(service);
      setIncludeCostInMot(true); // Default to including cost
      setLinkServiceDialogOpen(true);
    }
  };

  // Actually link the service with the chosen cost option
  const handleConfirmLinkService = async () => {
    if (!serviceToLink || !motRecord?.id) return;

    try {
      // Update the service record to link it to this MOT with cost option
      await api.put(`/service-records/${serviceToLink.id}`, { 
        motRecordId: motRecord.id,
        includedInMotCost: includeCostInMot
      });
      // Reload items and unlinked services
      await loadMotItems(motRecord.id);
      const vId = formData.vehicleId || vehicleId;
      if (vId) await loadUnlinkedServices(vId);
      setSelectedExistingServiceId('');
      setLinkServiceDialogOpen(false);
      setServiceToLink(null);
    } catch (err) {
      logger.error('Error linking existing service to MOT', err);
    }
  };

  // Toggle cost inclusion for an already-linked service
  const handleToggleServiceCostInclusion = async (service) => {
    try {
      await api.put(`/service-records/${service.id}`, { 
        includedInMotCost: !service.includedInMotCost
      });
      // Reload items to reflect the change
      if (motRecord?.id) await loadMotItems(motRecord.id);
    } catch (err) {
      logger.error('Error toggling service cost inclusion', err);
    }
  };

  const handleConfirmAction = async (action) => {
    if (!confirmTarget) return;
    try {
      const { type, id } = confirmTarget;
      if (action === 'delete') {
        if (type === 'consumable') await api.delete(`/consumables/${id}`);
        else await api.delete(`/parts/${id}`);
      } else if (action === 'disassociate') {
        // Choose correct endpoint based on type
        let url;
        if (type === 'consumable') url = `/consumables/${id}`;
        else if (type === 'part') url = `/parts/${id}`;
        else if (type === 'service') url = `/service-records/${id}`;
        else url = `/parts/${id}`;

        // Send 0 so backend will treat as explicit intent to clear association
        await api.put(url, { motRecordId: 0 });
      }
      setConfirmOpen(false);
      setConfirmTarget(null);
      if (motRecord?.id) {
        // reload items and update computed repair cost in UI and backend
        const itemsResp = await api.get(`/mot-records/${motRecord.id}/items`);
        const items = itemsResp.data || { parts: [], consumables: [], serviceRecords: [] };
        setMotItems(items);

        // compute repair total from items (parts, consumables, services)
        let total = 0;
        (items.parts || []).forEach(p => {
          // Exclude existing items (includedInServiceCost=false) from repair cost
          if (p.includedInServiceCost === false) return;
          const cost = parseFloat(p.cost || 0) || 0;
          const qty = parseFloat(p.quantity || 1) || 1;
          total += cost * qty;
        });
        (items.consumables || []).forEach(c => {
          // Exclude existing items (includedInServiceCost=false) from repair cost
          if (c.includedInServiceCost === false) return;
          const cost = parseFloat(c.cost || 0) || 0;
          const qty = parseFloat(c.quantity || 1) || 1;
          total += cost * qty;
        });
        (items.serviceRecords || []).forEach(s => {
          // Exclude services where includedInMotCost=false from repair cost
          if (s.includedInMotCost === false) return;
          const t = parseFloat(s.totalCost || s.total || 0) || 0;
          total += t;
        });

        const newRepairCost = total.toFixed(2);
        setFormData(prev => ({ ...prev, repairCost: newRepairCost }));

        try {
          await api.put(`/mot-records/${motRecord.id}`, { repairCost: newRepairCost });
        } catch (e) {
          logger.error('Failed to persist MOT repairCost', e);
        }
      }
    } catch (err) {
      logger.error('Error performing confirm action', err);
      setConfirmOpen(false);
    }
  };

  useEffect(() => {
    if (open) {
      if (motRecord) {
        setFormData({
          vehicleId: motRecord.vehicleId || vehicleId || '',
          testDate: motRecord.testDate || '',
          result: motRecord.result || 'Pass',
          testCost: motRecord.testCost || '',
          repairCost: motRecord.repairCost || '0',
            mileage: motRecord.mileage ? Math.round(convert(motRecord.mileage)) : '',
            testCenter: motRecord.testCenter || '',
            expiryDate: motRecord.expiryDate || '',
            motTestNumber: motRecord.motTestNumber || '',
            testerName: motRecord.testerName || '',
            isRetest: !!motRecord.isRetest,
          advisories: (motRecord.advisoryItems ? motRecord.advisoryItems.map(a => (typeof a === 'string' ? a : (a.text ?? JSON.stringify(a)))) : (motRecord.advisories ? motRecord.advisories.split('\n') : [])),
          failures: (motRecord.failureItems ? motRecord.failureItems.map(f => (typeof f === 'string' ? f : (f.text ?? JSON.stringify(f)))) : (motRecord.failures ? motRecord.failures.split('\n') : [])),
          repairDetails: motRecord.repairDetails || '',
          notes: motRecord.notes || '',
        });
        setReceiptAttachmentId(motRecord.receiptAttachmentId || null);
      } else {
        setFormData({
          vehicleId: vehicleId || '',
          testDate: new Date().toISOString().split('T')[0],
          result: 'Pass',
          testCost: '',
          repairCost: '0',
          mileage: '',
          testCenter: '',
          expiryDate: '',
          motTestNumber: '',
          testerName: '',
          isRetest: false,
          advisories: [],
          failures: [],
          repairDetails: '',
          notes: '',
        });
        setReceiptAttachmentId(null);
      }
    }
    // load linked items for this mot record (only if not already loaded for this ID)
    if (open && motRecord && motRecord.id && lastLoadedMotIdRef.current !== motRecord.id) {
      lastLoadedMotIdRef.current = motRecord.id;
      loadMotItems(motRecord.id);
    } else if (!open) {
      setMotItems({ parts: [], consumables: [] });
      lastLoadedMotIdRef.current = null;
    }
  }, [open, motRecord, convert, vehicleId]); // Removed loadMotItems from dependencies to prevent infinite loop

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleListChange = (field, index, value) => {
    const copy = Array.isArray(formData[field]) ? [...formData[field]] : [];
    copy[index] = value;
    setFormData({ ...formData, [field]: copy });
  };

  const addListItem = (field) => {
    const copy = Array.isArray(formData[field]) ? [...formData[field]] : [];
    copy.push('');
    setFormData({ ...formData, [field]: copy });
  };

  const removeListItem = (field, index) => {
    const copy = Array.isArray(formData[field]) ? [...formData[field]] : [];
    copy.splice(index, 1);
    setFormData({ ...formData, [field]: copy });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const data = { 
        ...formData, 
        vehicleId: formData.vehicleId,
        mileage: formData.mileage ? Math.round(toKm(parseFloat(formData.mileage))) : null,
        receiptAttachmentId,
      };
      if (motRecord) {
        await api.put(`/mot-records/${motRecord.id}`, data);
      } else {
        await api.post('/mot-records', data);
      }
      onClose(true);
    } catch (error) {
      logger.error('Error saving MOT record:', error);
      alert(t('common.saveError', { type: 'MOT record' }));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        {motRecord ? t('mot.editMot') : t('mot.addMot')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            {(!vehicleId || vehicleId === '__all__') && vehicles && vehicles.length > 0 && (
              <Grid item xs={12}>
                <FormControl fullWidth required>
                  <InputLabel>{t('common.vehicle')}</InputLabel>
                  <Select
                    name="vehicleId"
                    value={formData.vehicleId}
                    onChange={handleChange}
                    label={t('common.vehicle')}
                  >
                    {vehicles.map((v) => (
                      <MenuItem key={v.id} value={v.id}>
                        {v.registrationNumber} - {v.name}
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>
              </Grid>
            )}
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                required
                type="date"
                name="testDate"
                label={t('mot.testDate')}
                value={formData.testDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                select
                required
                name="result"
                label={t('mot.result')}
                value={formData.result}
                onChange={handleChange}
              >
                <MenuItem value="Pass">{t('mot.pass')}</MenuItem>
                <MenuItem value="Fail">{t('mot.fail')}</MenuItem>
                <MenuItem value="Advisory">{t('mot.advisory')}</MenuItem>
              </TextField>
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="mileage"
                label={`${t('mot.mileage')} (${getLabel()})`}
                value={formData.mileage}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                required
                type="number"
                name="testCost"
                label={t('mot.testCost')}
                value={formData.testCost}
                onChange={handleChange}
                inputProps={{ min: 0, step: 0.01 }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="date"
                name="expiryDate"
                label={t('mot.expiryDate')}
                value={formData.expiryDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                name="motTestNumber"
                label={t('mot.motTestNumber')}
                value={formData.motTestNumber}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                name="testerName"
                label={t('mot.testerName')}
                value={formData.testerName}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                type="number"
                name="repairCost"
                label={t('mot.repairCost')}
                value={formData.repairCost}
                InputProps={{ readOnly: true }}
                inputProps={{ min: 0, step: 0.01 }}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <TextField
                fullWidth
                name="testCenter"
                label={t('mot.testCenter')}
                value={formData.testCenter}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12} sm={4}>
              <FormControlLabel
                control={(
                  <Checkbox
                    checked={!!formData.isRetest}
                    onChange={(e) => setFormData({ ...formData, isRetest: e.target.checked })}
                    name="isRetest"
                  />
                )}
                label={t('mot.isRetest')}
              />
            </Grid>
            <Grid item xs={12}>
              <label>{t('mot.advisories')}</label>
              {formData.advisories.map((adv, i) => (
                <Grid container spacing={1} key={`adv-${i}`} alignItems="center" sx={{ mb: 1 }}>
                  <Grid item xs={11}>
                    <TextField fullWidth size="small" inputProps={{ style: { paddingTop: 6, paddingBottom: 6 } }} value={adv} onChange={(e) => handleListChange('advisories', i, e.target.value)} />
                  </Grid>
                  <Grid item xs={1}>
                    <Button onClick={() => removeListItem('advisories', i)}>–</Button>
                  </Grid>
                </Grid>
              ))}
              <Button onClick={() => addListItem('advisories')}>+ {t('mot.addAdvisory')}</Button>
            </Grid>
            <Grid item xs={12}>
              <label>{t('mot.failures')}</label>
              {formData.failures.map((f, i) => (
                <Grid container spacing={1} key={`fail-${i}`} alignItems="center" sx={{ mb: 1 }}>
                  <Grid item xs={11}>
                    <TextField fullWidth size="small" inputProps={{ style: { paddingTop: 6, paddingBottom: 6 } }} value={f} onChange={(e) => handleListChange('failures', i, e.target.value)} />
                  </Grid>
                  <Grid item xs={1}>
                    <Button onClick={() => removeListItem('failures', i)}>–</Button>
                  </Grid>
                </Grid>
              ))}
              <Button onClick={() => addListItem('failures')}>+ {t('mot.addFailure')}</Button>
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={2}
                name="repairDetails"
                label={t('mot.repairDetails')}
                value={formData.repairDetails}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <Grid container alignItems="center" justifyContent="space-between">
                <Grid item>
                  <strong>{t('mot.repairItems')}</strong>
                </Grid>
                <Grid item>
                  <Grid container spacing={1} alignItems="center">
                    <Grid item>
                      <TextField select size="small" value={selectedAddType} onChange={(e) => setSelectedAddType(e.target.value)} sx={{ minWidth: 200 }}>
                        <MenuItem value="part">{t('parts.part') || 'Part'}</MenuItem>
                        <MenuItem value="consumable">{t('consumables.consumable') || 'Consumable'}</MenuItem>
                        <MenuItem value="service">{t('service.service') || 'Service'}</MenuItem>
                      </TextField>
                    </Grid>
                    <Grid item>
                      <Button onClick={async () => {
                        // Prefill dialogs with MOT data where possible
                        // mileage from motRecord is in km (backend), convert to display units for prefill
                        const mileageDisplay = motRecord?.mileage ? Math.round(convert(motRecord.mileage)) : '';
                        const motPrefill = {
                          motRecordId: motRecord?.id || null,
                          motTestNumber: motRecord?.motTestNumber || '',
                          date: motRecord?.testDate || '',
                          mileage: mileageDisplay,
                        };

                        if (selectedAddType === 'part') {
                          setSelectedPart({
                            motRecordId: motPrefill.motRecordId,
                            installationDate: motPrefill.date,
                            mileageAtInstallation: motPrefill.mileage,
                          });
                          setOpenPartDialog(true);
                        } else if (selectedAddType === 'consumable') {
                          setSelectedConsumable({
                            motRecordId: motPrefill.motRecordId,
                            lastChanged: motPrefill.date,
                            mileageAtChange: motPrefill.mileage,
                          });
                          setOpenConsumableDialog(true);
                        } else {
                          setSelectedServiceRecord({
                            motRecordId: motPrefill.motRecordId,
                            serviceDate: motPrefill.date,
                            mileage: motPrefill.mileage,
                          });
                          setOpenServiceDialog(true);
                        }
                      }}>{`+ ${t('mot.addRepairItem')}`}</Button>
                    </Grid>
                  </Grid>
                </Grid>
              </Grid>
              <div style={{ marginTop: 8 }}>
                {(motItems.parts || []).map((p) => (
                  <div key={`part-${p.id || p.name}`} style={{ padding: 6, borderBottom: '1px solid #eee', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                      <Typography component="button" onClick={async (e) => { e.preventDefault(); setSelectedPart(p); setOpenPartDialog(true); }} sx={{ background: 'none', border: 'none', padding: 0, textDecoration: 'underline', cursor: 'pointer', color: 'primary.main' }}>
                        <BuildIcon fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />{p.name || p.description}
                      </Typography>
                      {/* Don't show cost for existing items (includedInServiceCost=false) */}
                      {p.includedInServiceCost !== false ? (
                        <span>— {p.quantity || 1} @ {p.cost || ''}</span>
                      ) : (
                        <span style={{ fontStyle: 'italic', color: '#666' }}>— {t('mot.existingItem') || 'Existing item'}</span>
                      )}
                    </div>
                    <div>
                      <Tooltip title={t('common.delete')}>
                        <IconButton size="small" onClick={() => { setConfirmTarget({ type: 'part', id: p.id, name: p.name || p.description }); setConfirmOpen(true); }}>
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </div>
                  </div>
                ))}
                {motItems.serviceRecords && motItems.serviceRecords.map((s) => (
                  <div key={`svc-${s.id}`} style={{ padding: 6, borderBottom: '1px solid #eee', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                      <Tooltip title={s.includedInMotCost ? t('mot.costIncluded') || 'Cost included in MOT' : t('mot.costNotIncluded') || 'Cost not included in MOT'}>
                        <Checkbox 
                          size="small" 
                          checked={s.includedInMotCost !== false}
                          onChange={() => handleToggleServiceCostInclusion(s)}
                          sx={{ p: 0, mr: 1 }}
                        />
                      </Tooltip>
                      <Typography component="button" onClick={async (e) => {
                        e.preventDefault();
                        try {
                          const resp = await api.get(`/service-records/${s.id}`);
                          setSelectedServiceRecord(resp.data);
                          setOpenServiceDialog(true);
                        } catch (err) {
                          logger.error('Error loading service record', err);
                        }
                      }} sx={{ background: 'none', border: 'none', padding: 0, textDecoration: 'underline', cursor: 'pointer', color: 'primary.main' }}>
                        <MiscellaneousServicesIcon fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />
                        { (s.items && s.items.length > 0) ? (s.items.map(it => it.description || '').filter(Boolean).join(', ')) : (s.workPerformed || s.serviceProvider || (t('service.service') || 'Service')) }
                      </Typography>
                      {s.includedInMotCost !== false ? (
                        <span>— {s.totalCost || s.total || ''} ({s.mileage || ''})</span>
                      ) : (
                        <span style={{ fontStyle: 'italic', color: '#666' }}>— {t('mot.linkedService') || 'Linked (cost separate)'}</span>
                      )}
                    </div>
                    <div>
                      <Tooltip title={t('common.delete')}>
                        <IconButton size="small" onClick={() => { setConfirmTarget({ type: 'service', id: s.id, name: s.items && s.items.length > 0 ? s.items.map(it => it.description).join(', ') : `${t('service.service')} ${s.id}` }); setConfirmOpen(true); }}>
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </div>
                  </div>
                ))}
                {(motItems.consumables || []).map((c) => (
                  <div key={`cons-${c.id || c.name}`} style={{ padding: 6, borderBottom: '1px solid #eee', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                      <Typography component="button" onClick={async (e) => { e.preventDefault(); setSelectedConsumable(c); setOpenConsumableDialog(true); }} sx={{ background: 'none', border: 'none', padding: 0, textDecoration: 'underline', cursor: 'pointer', color: 'primary.main' }}>
                        <OpacityIcon fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />{c.name || c.description || ''}
                      </Typography>
                      {/* Don't show cost for existing items (includedInServiceCost=false) */}
                      {c.includedInServiceCost !== false ? (
                        <span>— {c.quantity || 1} @ {c.cost || ''}</span>
                      ) : (
                        <span style={{ fontStyle: 'italic', color: '#666' }}>— {t('mot.existingItem') || 'Existing item'}</span>
                      )}
                    </div>
                    <div>
                      <Tooltip title={t('common.delete')}>
                        <IconButton size="small" onClick={() => { setConfirmTarget({ type: 'consumable', id: c.id, name: c.name || c.description || '' }); setConfirmOpen(true); }}>
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </div>
                  </div>
                ))}
                {((motItems.parts || []).length === 0 && (motItems.consumables || []).length === 0) && (
                  <div style={{ color: '#666', padding: 6 }}>{t('mot.noRepairItems')}</div>
                )}
              </div>
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
                entityType="mot"
                entityId={motRecord?.id}
                vehicleId={formData.vehicleId || vehicleId}
                receiptAttachmentId={receiptAttachmentId}
                onReceiptUploaded={(id, ocrData) => setReceiptAttachmentId(id)}
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
      {/* Dialogs for adding items directly from the M.O.T. screen */}
      <ServiceDialog
        open={openServiceDialog}
        serviceRecord={selectedServiceRecord}
        vehicleId={vehicleId}
        unlinkedServices={unlinkedServices}
        onClose={async (saved) => {
          setOpenServiceDialog(false);
          setSelectedServiceRecord(null);
          if (saved && motRecord?.id) {
            await loadMotItems(motRecord.id);
            // Also reload unlinked services since one may have been linked
            const vId = formData.vehicleId || vehicleId;
            if (vId) await loadUnlinkedServices(vId);
          }
        }}
      />
      <PartDialog
        open={openPartDialog}
        onClose={async (saved) => {
          setOpenPartDialog(false);
          setSelectedPart(null);
          if (saved && motRecord?.id) await loadMotItems(motRecord.id);
        }}
        part={selectedPart}
        vehicleId={vehicleId}
      />
      <ConsumableDialog
        open={openConsumableDialog}
        onClose={async (saved) => {
          setOpenConsumableDialog(false);
          setSelectedConsumable(null);
          if (saved && motRecord?.id) await loadMotItems(motRecord.id);
        }}
        consumable={selectedConsumable}
        vehicleId={vehicleId}
      />

      <Dialog open={confirmOpen} onClose={() => setConfirmOpen(false)}>
        <DialogTitle>{t('mot.confirmRemoveTitle')}</DialogTitle>
        <DialogContent>
          <div>{t('mot.confirmRemoveMessage', { name: confirmTarget?.name || '' })}</div>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => { setConfirmOpen(false); setConfirmTarget(null); }}>{t('common.cancel')}</Button>
          <Button color="primary" onClick={() => handleConfirmAction('disassociate')}>{t('mot.disassociate')}</Button>
          <Button color="error" onClick={() => handleConfirmAction('delete')}>{t('common.delete')}</Button>
        </DialogActions>
      </Dialog>
    </Dialog>
  );
};

export default MotDialog;
