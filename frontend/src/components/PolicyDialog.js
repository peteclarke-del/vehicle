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
  Select,
  FormControl,
  InputLabel,
  CircularProgress,
  Autocomplete,
  Chip,
  Checkbox,
  FormControlLabel,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import ReceiptUpload from './ReceiptUpload';
import AttachmentUpload from './AttachmentUpload';

const PolicyDialog = ({ open, policy, vehicles, selectedVehicleId, existingPolicies, onClose }) => {
  const [formData, setFormData] = useState({
    provider: '',
    policyNumber: '',
    coverType: '',
    notes: '',
    startDate: new Date().toISOString().split('T')[0],
    expiryDate: '',
    annualCost: '',
    ncdYears: '',
    excess: '',
    mileageLimit: '',
    autoRenewal: false,
    vehicleIds: [],
  });
  const [pendingAttachmentIds, setPendingAttachmentIds] = useState([]);
  const [loading, setLoading] = useState(false);
  const { api } = useAuth();
  const { t } = useTranslation();

  const coverTypes = [
    { value: 'comprehensive', label: t('insurance.policies.coverTypes.comprehensive') },
    { value: 'thirdParty', label: t('insurance.policies.coverTypes.thirdParty') },
    { value: 'thirdPartyFireTheft', label: t('insurance.policies.coverTypes.thirdPartyFireTheft') },
  ];

  useEffect(() => {
    if (open) {
      if (policy) {
        // Editing an existing policy
        setFormData({
          provider: policy.provider || '',
          policyNumber: policy.policyNumber || '',
          coverType: policy.coverageType || '',
          notes: policy.notes || '',
          startDate: policy.startDate || new Date().toISOString().split('T')[0],
          expiryDate: policy.expiryDate || '',
          annualCost: policy.annualCost || '',
          ncdYears: policy.ncdYears ?? '',
          excess: policy.excess ?? '',
          mileageLimit: policy.mileageLimit ?? '',
          autoRenewal: !!policy.autoRenewal,
          vehicleIds: (policy.vehicles || []).map(v => v.id),
        });
      } else {
        // Adding a new policy
        let initialData = {
          provider: '',
          policyNumber: '',
          coverType: '',
          notes: '',
          startDate: new Date().toISOString().split('T')[0],
          expiryDate: '',
          annualCost: '',
          ncdYears: '',
          excess: '',
          mileageLimit: '',
          autoRenewal: false,
          vehicleIds: [],
        };

        // If a vehicle is selected and it's part of an existing multi-vehicle policy,
        // pre-fill the form with that policy's data
        if (selectedVehicleId && selectedVehicleId !== '__all__' && existingPolicies) {
          const existingPolicy = existingPolicies.find(p => 
            p.vehicles && p.vehicles.some(v => String(v.id) === String(selectedVehicleId)) && 
            p.vehicles.length > 1 // Only for multi-vehicle policies
          );

          if (existingPolicy) {
            initialData = {
              provider: existingPolicy.provider || '',
              policyNumber: existingPolicy.policyNumber || '',
              coverType: existingPolicy.coverageType || '',
              notes: existingPolicy.notes || '',
              startDate: existingPolicy.startDate || new Date().toISOString().split('T')[0],
              expiryDate: existingPolicy.expiryDate || '',
              annualCost: existingPolicy.annualCost || '',
              ncdYears: existingPolicy.ncdYears ?? '',
              excess: existingPolicy.excess ?? '',
              mileageLimit: existingPolicy.mileageLimit ?? '',
              autoRenewal: !!existingPolicy.autoRenewal,
              vehicleIds: [parseInt(selectedVehicleId)], // Only include the selected vehicle
            };
          } else if (selectedVehicleId) {
            // No existing policy, just pre-select the vehicle
            initialData.vehicleIds = [parseInt(selectedVehicleId)];
          }
        }

        setFormData(initialData);
      }
    }
  }, [open, policy, selectedVehicleId, existingPolicies]);

  const handleChange = (e) => {
    setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      // map frontend field names to backend expectations
      const payload = {
        provider: formData.provider,
        policyNumber: formData.policyNumber,
        coverageType: formData.coverType,
        startDate: formData.startDate || null,
        expiryDate: formData.expiryDate || null,
        annualCost: formData.annualCost || null,
        excess: formData.excess || null,
        mileageLimit: formData.mileageLimit || null,
        autoRenewal: !!formData.autoRenewal,
        ncdYears: formData.ncdYears || null,
        vehicleIds: formData.vehicleIds || [],
        notes: formData.notes || null,
      };

      // attach any uploaded-but-unlinked attachments
      if (pendingAttachmentIds && pendingAttachmentIds.length > 0) {
        payload.pendingAttachmentIds = pendingAttachmentIds;
      }

      if (!policy || !policy.id) {
        await api.post('/insurance/policies', payload);
      } else {
        await api.put(`/insurance/policies/${policy.id}`, payload);
      }
      onClose(true);
    } catch (err) {
      console.error('Error saving policy', err);
      alert(t('common.saveError', { type: 'policy' }));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>{policy ? t('insurance.policies.editPolicy') : t('insurance.policies.addPolicy')}</DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6}>
              <TextField fullWidth required name="provider" label={t('insurance.policies.provider')} value={formData.provider} onChange={handleChange} />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField fullWidth name="policyNumber" label={t('insurance.policies.policyNumber')} value={formData.policyNumber} onChange={handleChange} />
            </Grid>
            {/* holder is removed — policies are owned by the logged-in user */}
            <Grid item xs={12} sm={6}>
              <FormControl fullWidth>
                <InputLabel>{t('insurance.policies.coverType')}</InputLabel>
                <Select
                  name="coverType"
                  value={formData.coverType}
                  onChange={handleChange}
                  label={t('insurance.policies.coverType')}
                >
                  {coverTypes.map(ct => (
                    <MenuItem key={ct.value} value={ct.value}>{ct.label}</MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField fullWidth type="number" name="ncdYears" label={t('insurance.policies.ncdYears')} value={formData.ncdYears} onChange={handleChange} inputProps={{ min: 0 }} />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField fullWidth type="date" name="startDate" label={t('common.startDate')} value={formData.startDate} onChange={handleChange} InputLabelProps={{ shrink: true }} />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField fullWidth type="date" name="expiryDate" label={t('insurance.policies.expiryDate')} value={formData.expiryDate} onChange={handleChange} InputLabelProps={{ shrink: true }} />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField fullWidth type="number" name="annualCost" label={t('insurance.policies.annualCost')} value={formData.annualCost} onChange={handleChange} inputProps={{ min: 0, step: 0.01 }} />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField fullWidth type="number" name="excess" label={t('insurance.policies.excess')} value={formData.excess} onChange={handleChange} inputProps={{ min: 0, step: 0.01 }} />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField fullWidth type="number" name="mileageLimit" label={t('insurance.policies.mileageLimit')} value={formData.mileageLimit} onChange={handleChange} inputProps={{ min: 0 }} />
            </Grid>
            <Grid item xs={12} sm={6}>
              <FormControlLabel
                control={<Checkbox checked={!!formData.autoRenewal} onChange={(e) => setFormData(prev => ({ ...prev, autoRenewal: e.target.checked }))} />}
                label={t('insurance.policies.autoRenewal')}
              />
            </Grid>
            {/* NCD percentage removed */}
            <Grid item xs={12}>
              <TextField fullWidth multiline rows={2} name="notes" label={t('common.notes') || 'Notes'} value={formData.notes} onChange={handleChange} />
            </Grid>
            <Grid item xs={12}>
              <Autocomplete
                multiple
                options={vehicles}
                getOptionLabel={(v) => {
                  const makeModel = v.make ? `${v.make}${v.model ? ' ' + v.model : ''}` : v.name;
                  return `${makeModel} (${v.registrationNumber || ''})`;
                }}
                value={vehicles.filter(v => formData.vehicleIds.includes(v.id))}
                onChange={(e, newVal) => setFormData(prev => ({ ...prev, vehicleIds: newVal.map(v => v.id) }))}
                renderTags={(value, getTagProps) => value.map((option, index) => (
                  <Chip label={`${option.make ? option.make + (option.model ? ' ' + option.model : '') : option.name} (${option.registrationNumber || ''})`} {...getTagProps({ index })} />
                ))}
                renderInput={(params) => (
                  <TextField {...params} label={t('insurance.policies.vehicles')} placeholder={t('insurance.policies.selectVehicles')} />
                )}
              />
            </Grid>
            <Grid item xs={12}>
              <AttachmentUpload
                compact={true}
                entityType="insurancePolicy"
                entityId={policy?.id}
                onChange={(attachments) => {
                  // collect IDs for attachments that are uploaded without an entityId
                  const pending = (attachments || []).filter(a => !a.entityId).map(a => a.id);
                  setPendingAttachmentIds(pending);
                }}
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
    </Dialog>
  );
};

export default PolicyDialog;
