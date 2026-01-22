import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  RadioGroup,
  FormControlLabel,
  Radio,
  MenuItem,
  Switch,
  FormControl,
  InputLabel,
  Select,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import ServiceDialog from './ServiceDialog';

const AddRepairItemModal = ({ open, vehicleId, motRecordId, onClose }) => {
  const { api } = useAuth();
  const { t } = useTranslation();
  const PART_CATEGORIES = [
    'Body',
    'Cooling',
    'Electrical',
    'Engine',
    'Exhaust',
    'Interior',
    'Other',
    'Suspension',
    'Transmission',
    'Brakes'
  ].sort((a, b) => a.localeCompare(b));
  const [type, setType] = useState('part');
  const [linkExisting, setLinkExisting] = useState(false);
  const [existingItems, setExistingItems] = useState([]);
  const [selectedExistingId, setSelectedExistingId] = useState(null);
  const [consumableTypes, setConsumableTypes] = useState([]);
  const [selectedConsumableTypeId, setSelectedConsumableTypeId] = useState(null);
  const [vehicleMileageKm, setVehicleMileageKm] = useState(null);
  const [form, setForm] = useState({ name: '', description: '', quantity: 1, cost: '' });
  const [serviceDialogOpen, setServiceDialogOpen] = useState(false);

  useEffect(() => {
    if (!open) return;
    setType('part');
    setLinkExisting(false);
    setExistingItems([]);
    setSelectedExistingId(null);
    setForm({ name: '', description: '', quantity: 1, cost: '' });
    setSelectedConsumableTypeId(null);
    // load consumable types for vehicle (if any)
    (async () => {
      if (!vehicleId) return;
      try {
        const vehicleResp = await api.get(`/vehicles/${vehicleId}`);
        const vehicleTypeId = vehicleResp.data.vehicleType?.id;
        // vehicleResp.data.currentMileage is stored in km in the API
        if (vehicleResp.data?.currentMileage) {
          setVehicleMileageKm(vehicleResp.data.currentMileage);
        }
        if (vehicleTypeId) {
          const typesResp = await api.get(`/vehicle-types/${vehicleTypeId}/consumable-types`);
          const types = (typesResp.data || []).slice().sort((a, b) => (a.name || '').localeCompare(b.name || ''));
          setConsumableTypes(types);
        }
      } catch (err) {
        // ignore
      }
    })();
  }, [open]);

  useEffect(() => {
    if (!open || !linkExisting) return;
    (async () => {
      try {
        if (type === 'part') {
          const res = await api.get(`/parts?vehicleId=${vehicleId}`);
          const items = (res.data || []).filter((it) => it.motRecordId == null);
          setExistingItems(items);
        } else if (type === 'consumable') {
          const r = await api.get(`/consumables?vehicleId=${vehicleId}`);
          const items = (r.data || []).filter((it) => it.motRecordId == null);
          setExistingItems(items);
        } else if (type === 'service') {
          const s = await api.get(`/service-records?vehicleId=${vehicleId}`);
          const items = (s.data || []).filter((it) => it.motRecordId == null);
          setExistingItems(items);
        }
      } catch (err) {
        console.error('Error loading existing items', err);
      }
    })();
  }, [open, linkExisting, type, vehicleId, api]);

  const submit = async () => {
    try {
      if (linkExisting) {
        if (!selectedExistingId) return;
        let url;
        let payload = { motRecordId };
        if (type === 'consumable' && vehicleMileageKm) payload.mileageAtChange = Math.round(vehicleMileageKm);
        if (type === 'part') url = `/parts/${selectedExistingId}`;
        else if (type === 'consumable') url = `/consumables/${selectedExistingId}`;
        else if (type === 'service') url = `/service-records/${selectedExistingId}`;
        if (url) await api.put(url, payload);
        onClose(true);
        return;
      }

      if (type === 'part') {
        const payload = { ...form, vehicleId, motRecordId };
        if (vehicleMileageKm) payload.mileageAtInstallation = Math.round(vehicleMileageKm);
        await api.post('/parts', payload);
      } else {
        // backend is kept strict: require consumableTypeId
        if (!selectedConsumableTypeId) {
          alert(t('consumables.selectTypeRequired') || 'Please select a consumable type');
          return;
        }
        const payload = { ...form, vehicleId, motRecordId, consumableTypeId: selectedConsumableTypeId };
        if (vehicleMileageKm) payload.mileageAtChange = Math.round(vehicleMileageKm);
        await api.post('/consumables', payload);
      }
      onClose(true);
    } catch (err) {
      console.error('Error creating/linking item', err);
      alert(t('common.saveError', { type: type === 'part' ? 'Part' : 'Consumable' }));
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="sm" fullWidth>
      <DialogTitle>{t('mot.addRepairItem')}</DialogTitle>
      <DialogContent>
        <Grid container spacing={2}>
          <Grid item xs={12} container spacing={2} alignItems="center">
            <Grid item xs={12} sm={6}>
              <RadioGroup row value={type} onChange={(e) => setType(e.target.value)}>
                <FormControlLabel value="part" control={<Radio />} label={t('parts.part')} />
                <FormControlLabel value="consumable" control={<Radio />} label={t('consumables.consumable')} />
                <FormControlLabel value="service" control={<Radio />} label={t('service.service') || t('service.serviceRecord') || 'Service'} />
              </RadioGroup>
            </Grid>
            <Grid item xs={12} sm={6}>
              {!linkExisting && type === 'part' && (
                <FormControl fullWidth>
                  <InputLabel>{t('parts.category')}</InputLabel>
                  <Select value={form.category || ''} label={t('parts.category')} onChange={(e) => setForm({ ...form, category: e.target.value })}>
                    <MenuItem value="">{t('partCategories.selectCategory')}</MenuItem>
                    {PART_CATEGORIES.map(cat => (
                      <MenuItem key={cat} value={cat}>{cat}</MenuItem>
                    ))}
                  </Select>
                </FormControl>
              )}

              {!linkExisting && type === 'consumable' && (
                <FormControl fullWidth>
                  <InputLabel>{t('consumables.type')}</InputLabel>
                  <Select value={selectedConsumableTypeId || ''} label={t('consumables.type')} onChange={(e) => setSelectedConsumableTypeId(e.target.value)}>
                    <MenuItem value="">Select Type</MenuItem>
                    {consumableTypes.map((ct) => (
                      <MenuItem key={ct.id} value={ct.id}>{`${ct.name} (${ct.unit || ''})`}</MenuItem>
                    ))}
                  </Select>
                </FormControl>
              )}
              {!linkExisting && type === 'service' && (
                <FormControl fullWidth>
                  <Button variant="outlined" onClick={() => setServiceDialogOpen(true)} fullWidth>
                    {t('service.createService')}
                  </Button>
                </FormControl>
              )}
            </Grid>
          </Grid>

          <Grid item xs={12}>
            <FormControlLabel control={<Switch checked={linkExisting} onChange={(e) => setLinkExisting(e.target.checked)} />} label={t('mot.linkExisting')} />
          </Grid>

          {linkExisting ? (
            <Grid item xs={12}>
              <FormControl fullWidth>
                <InputLabel>{t('mot.selectExisting')}</InputLabel>
                <Select
                  value={selectedExistingId || ''}
                  label={t('mot.selectExisting')}
                  onChange={(e) => setSelectedExistingId(e.target.value)}
                  renderValue={(val) => {
                    if (!val) return '';
                    const it = existingItems.find((x) => x.id == val);
                    return it ? (it.name || it.description || it.specification || `#${val}`) : `#${val}`;
                  }}
                >
                  {existingItems.map((it) => (
                    <MenuItem key={it.id} value={it.id}>{it.name || it.description || it.specification || `${it.id}`}</MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Grid>
          ) : (
            <>
              <Grid item xs={12}>
                <TextField fullWidth label={t('common.name')} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
              </Grid>
              <Grid item xs={12}>
                <TextField fullWidth label={t('common.description')} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
              </Grid>

              <Grid item xs={12} sm={6}>
                <TextField fullWidth type="number" label={t('parts.cost')} value={form.cost} onChange={(e) => setForm({ ...form, cost: e.target.value })} inputProps={{ step: '0.01', min: '0' }} />
              </Grid>

              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  type="number"
                  label={t('common.quantity')}
                  value={form.quantity}
                  onChange={(e) => {
                    const val = e.target.value;
                    if (type === 'consumable') {
                      const n = parseFloat(val || '0');
                      setForm({ ...form, quantity: Number(n.toFixed(2)) });
                    } else {
                      setForm({ ...form, quantity: parseInt(val || '0', 10) });
                    }
                  }}
                  inputProps={{ step: type === 'consumable' ? '0.01' : '1', min: type === 'consumable' ? '0' : '1' }}
                />
              </Grid>
              </>
          )}
        </Grid>
      </DialogContent>
      <DialogActions>
        <Button onClick={() => onClose(false)}>{t('common.cancel')}</Button>
        <Button variant="contained" onClick={submit}>{t('common.save')}</Button>
      </DialogActions>
      <ServiceDialog open={serviceDialogOpen} serviceRecord={null} vehicleId={vehicleId} onClose={(reload) => {
        setServiceDialogOpen(false);
        if (reload) onClose(true);
      }} />
    </Dialog>
  );
};

export default AddRepairItemModal;
