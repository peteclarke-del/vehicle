import React, { useEffect, useState } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Autocomplete,
  Box,
  Chip,
  Checkbox,
  FormControlLabel,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { fetchArrayData } from '../hooks/useApiData';

const TodoDialog = ({ open, onClose, vehicleId, todo }) => {
  const { api } = useAuth();
  const { t } = useTranslation();
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [parts, setParts] = useState([]);
  const [consumables, setConsumables] = useState([]);
  const [selectedParts, setSelectedParts] = useState([]);
  const [selectedConsumables, setSelectedConsumables] = useState([]);
  const [done, setDone] = useState(false);
  const [dueDate, setDueDate] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!open) return;

    const init = async () => {
      const { parts: loadedParts, consumables: loadedConsumables } = await loadOptions();

      if (todo) {
        setTitle(todo.title || '');
        setDescription(todo.description || '');
        // todo.parts/consumables are arrays of ids from the API; map to full objects
        const selParts = (todo.parts || []).map((pid) => loadedParts.find((p) => p.id === pid)).filter(Boolean);
        const selCons = (todo.consumables || []).map((cid) => loadedConsumables.find((c) => c.id === cid)).filter(Boolean);
        setSelectedParts(selParts);
        setSelectedConsumables(selCons);
        setDone(!!todo.done);
        setDueDate(todo.dueDate ? todo.dueDate.split('T')[0] : '');
      } else {
        setTitle(''); setDescription(''); setSelectedParts([]); setSelectedConsumables([]); setDone(false); setDueDate('');
      }
    };

    init();
  }, [open, todo]);

  const loadOptions = async () => {
    if (!vehicleId) return { parts: [], consumables: [] };
    const p = await fetchArrayData(api, `/parts?vehicleId=${vehicleId}`) || [];
    const c = await fetchArrayData(api, `/consumables?vehicleId=${vehicleId}`) || [];
    setParts(p);
    setConsumables(c);
    return { parts: p, consumables: c };
  };

  const handleSave = async () => {
    const payload = {
      vehicleId,
      title,
      description,
      parts: selectedParts.map((p) => p.id),
      consumables: selectedConsumables.map((c) => c.id),
      done,
      dueDate: dueDate || null,
      completedBy: done ? new Date().toISOString() : null,
    };

    setLoading(true);
    try {
      if (todo && todo.id) {
        await api.put(`/todos/${todo.id}`, payload);
      } else {
        await api.post('/todos', payload);
      }
      onClose(true);
    } catch (err) {
      console.error('Failed to save todo', err);
      onClose(false);
    } finally {
      setLoading(false);
    }
  };

  // Only offer parts/consumables that are not already installed/changed,
  // but keep any currently-selected items available so they remain visible in edit mode.
  const availableParts = parts.filter((p) => !p.installationDate || selectedParts.some((sp) => sp.id === p.id));
  const availableConsumables = consumables.filter((cItem) => !cItem.lastChanged || selectedConsumables.some((sc) => sc.id === cItem.id));

  return (
    <Dialog open={open} onClose={() => onClose(false)} fullWidth maxWidth="md">
      <DialogTitle>{todo ? t('todo.edit') || 'Edit TODO' : t('todo.add') || 'Add TODO'}</DialogTitle>
      <DialogContent>
        <Box display="flex" flexDirection="column" gap={2} mt={1}>
          <TextField label={t('todo.titleLabel') || 'Title'} value={title} onChange={(e) => setTitle(e.target.value)} fullWidth />
          <TextField label={t('todo.description') || 'Description'} value={description} onChange={(e) => setDescription(e.target.value)} fullWidth multiline rows={3} />

          <Autocomplete
            multiple
            options={availableParts}
            getOptionLabel={(opt) => opt.description || opt.partNumber || opt.id}
            value={selectedParts}
            onChange={(e, v) => setSelectedParts(v)}
            renderTags={(value, getTagProps) => value.map((option, index) => (<Chip label={option.description || option.partNumber} {...getTagProps({ index })} />))}
            renderInput={(params) => <TextField {...params} label={t('todo.parts') || 'Parts'} />}
          />

          <Autocomplete
            multiple
            options={availableConsumables}
            getOptionLabel={(opt) => opt.name || opt.id}
            value={selectedConsumables}
            onChange={(e, v) => setSelectedConsumables(v)}
            renderTags={(value, getTagProps) => value.map((option, index) => (<Chip label={option.name} {...getTagProps({ index })} />))}
            renderInput={(params) => <TextField {...params} label={t('todo.consumables') || 'Consumables'} />}
          />

          <TextField type="date" label={t('todo.due') || 'Due'} value={dueDate} onChange={(e) => setDueDate(e.target.value)} InputLabelProps={{ shrink: true }} />

          <FormControlLabel control={<Checkbox checked={done} onChange={(e) => setDone(e.target.checked)} />} label={t('todo.done') || 'Completed'} />
        </Box>
      </DialogContent>
      <DialogActions>
        <Button onClick={() => onClose(false)} disabled={loading}>{t('common.cancel') || 'Cancel'}</Button>
        <Button variant="contained" onClick={handleSave} disabled={loading}>{loading ? t('common.loading') : (t('common.save') || 'Save')}</Button>
      </DialogActions>
    </Dialog>
  );
};

export default TodoDialog;
