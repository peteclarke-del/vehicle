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
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { useDistance } from '../hooks/useDistance';

const MotDialog = ({ open, motRecord, vehicleId, onClose }) => {
  const [formData, setFormData] = useState({
    testDate: new Date().toISOString().split('T')[0],
    result: 'Pass',
    testCost: '',
    repairCost: '0',
    mileage: '',
    testCenter: '',
    advisories: '',
    failures: '',
    repairDetails: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);
  const { api } = useAuth();
  const { t } = useTranslation();
  const { convert, toKm, getLabel } = useDistance();

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
          advisories: motRecord.advisories || '',
          failures: motRecord.failures || '',
          repairDetails: motRecord.repairDetails || '',
          notes: motRecord.notes || '',
        });
      } else {
        setFormData({
          testDate: new Date().toISOString().split('T')[0],
          result: 'Pass',
          testCost: '',
          repairCost: '0',
          mileage: '',
          testCenter: '',
          advisories: '',
          failures: '',
          repairDetails: '',
          notes: '',
        });
      }
    }
  }, [open, motRecord]);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const data = { 
        ...formData, 
        vehicleId,
        mileage: formData.mileage ? Math.round(toKm(parseFloat(formData.mileage))) : null,
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
                type="number"
                name="repairCost"
                label={t('mot.repairCost')}
                value={formData.repairCost}
                onChange={handleChange}
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
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={2}
                name="advisories"
                label={t('mot.advisories')}
                value={formData.advisories}
                onChange={handleChange}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={2}
                name="failures"
                label={t('mot.failures')}
                value={formData.failures}
                onChange={handleChange}
              />
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

export default MotDialog;
