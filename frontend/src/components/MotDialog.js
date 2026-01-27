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
  Checkbox,
  FormControlLabel,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useDistance } from '../hooks/useDistance';
import ReceiptUpload from './ReceiptUpload';
import ServiceDialog from './ServiceDialog';
import PartDialog from './PartDialog';
import ConsumableDialog from './ConsumableDialog';
import { IconButton, Typography, Tooltip } from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import LinkIcon from '@mui/icons-material/Link';
import BuildIcon from '@mui/icons-material/Build';
import OpacityIcon from '@mui/icons-material/Opacity';
import MiscellaneousServicesIcon from '@mui/icons-material/MiscellaneousServices';

const MotDialog = ({ open, motRecord, vehicleId, onClose }) => {
  const [formData, setFormData] = useState({
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
  const [addItemOpen, setAddItemOpen] = useState(false);
  const [selectedPart, setSelectedPart] = useState(null);
  const [selectedConsumable, setSelectedConsumable] = useState(null);
  const [selectedAddType, setSelectedAddType] = useState('part');
  const [openServiceDialog, setOpenServiceDialog] = useState(false);
  const [openPartDialog, setOpenPartDialog] = useState(false);
  const [openConsumableDialog, setOpenConsumableDialog] = useState(false);
  const [selectedServiceRecord, setSelectedServiceRecord] = useState(null);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [confirmTarget, setConfirmTarget] = useState(null); // { type, id, name }
  const { api } = useAuth();
  const { t } = useTranslation();
  const { convert, toKm, getLabel } = useDistance();

  const loadMotItems = async (id) => {
    if (!id) return;
    try {
      const res = await api.get(`/mot-records/${id}/items`);
      setMotItems(res.data || { parts: [], consumables: [], serviceRecords: [] });
    } catch (err) {
      console.error('Error loading MOT items', err);
    }
  };

  // Recompute repair cost when mot items change
  useEffect(() => {
    const parts = motItems.parts || [];
    const consumables = motItems.consumables || [];
    const services = motItems.serviceRecords || [];
    let total = 0;
    parts.forEach(p => {
      const cost = parseFloat(p.cost || 0) || 0;
      const qty = parseFloat(p.quantity || 1) || 1;
      total += cost * qty;
    });
    consumables.forEach(c => {
      const cost = parseFloat(c.cost || 0) || 0;
      const qty = parseFloat(c.quantity || 1) || 1;
      total += cost * qty;
    });
    services.forEach(s => {
      const t = parseFloat(s.totalCost || s.total || 0) || 0;
      total += t;
    });
    setFormData(prev => ({ ...prev, repairCost: total.toFixed(2) }));
  }, [motItems]);

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
          const cost = parseFloat(p.cost || 0) || 0;
          const qty = parseFloat(p.quantity || 1) || 1;
          total += cost * qty;
        });
        (items.consumables || []).forEach(c => {
          const cost = parseFloat(c.cost || 0) || 0;
          const qty = parseFloat(c.quantity || 1) || 1;
          total += cost * qty;
        });
        (items.serviceRecords || []).forEach(s => {
          const t = parseFloat(s.totalCost || s.total || 0) || 0;
          total += t;
        });

        const newRepairCost = total.toFixed(2);
        setFormData(prev => ({ ...prev, repairCost: newRepairCost }));

        try {
          await api.put(`/mot-records/${motRecord.id}`, { repairCost: newRepairCost });
        } catch (e) {
          console.error('Failed to persist MOT repairCost', e);
        }
      }
    } catch (err) {
      console.error('Error performing confirm action', err);
      setConfirmOpen(false);
    }
  };

  useEffect(() => {
    if (open) {
      if (motRecord) {
        setFormData({
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
    // load linked items for this mot record
    if (open && motRecord && motRecord.id) {
      loadMotItems(motRecord.id);
    } else {
      setMotItems({ parts: [], consumables: [] });
    }
  }, [open, motRecord]);

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
        vehicleId,
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
      console.error('Error saving MOT record:', error);
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
                        const motPrefill = {
                          motRecordId: motRecord?.id || null,
                          motTestNumber: motRecord?.motTestNumber || '',
                          // mileage and dates are stored in km in backend; pass raw values so dialogs convert
                          date: motRecord?.testDate || '',
                          mileage: motRecord?.mileage || null,
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
                      <span>— {p.quantity || 1} @ {p.cost || ''}</span>
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
                      <Typography component="button" onClick={async (e) => {
                        e.preventDefault();
                        try {
                          const resp = await api.get(`/service-records/${s.id}`);
                          setSelectedServiceRecord(resp.data);
                          setOpenServiceDialog(true);
                        } catch (err) {
                          console.error('Error loading service record', err);
                        }
                      }} sx={{ background: 'none', border: 'none', padding: 0, textDecoration: 'underline', cursor: 'pointer', color: 'primary.main' }}>
                        <MiscellaneousServicesIcon fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />
                        { (s.items && s.items.length > 0) ? (s.items.map(it => it.description || '').filter(Boolean).join(', ')) : (s.workPerformed || s.serviceProvider || (t('service.service') || 'Service')) }
                      </Typography>
                      <span>— {s.totalCost || s.total || ''} ({s.mileage || ''})</span>
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
                      <span>— {c.quantity || 1} @ {c.cost || ''}</span>
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
                label={t('mot.notes')}
                value={formData.notes}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <ReceiptUpload
                entityType="mot"
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
            {loading ? <CircularProgress size={24} /> : t('common.save')}
          </Button>
        </DialogActions>
      </form>
      {/* Dialogs for adding items directly from the M.O.T. screen */}
      <ServiceDialog
        open={openServiceDialog}
        serviceRecord={selectedServiceRecord}
        vehicleId={vehicleId}
        onClose={async (saved) => {
          setOpenServiceDialog(false);
          setSelectedServiceRecord(null);
          if (saved && motRecord?.id) await loadMotItems(motRecord.id);
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
