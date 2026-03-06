import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Box,
  Chip,
  CircularProgress,
  Dialog,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  IconButton,
  InputAdornment,
  List,
  ListItem,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Switch,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import SearchIcon from '@mui/icons-material/Search';
import InsertDriveFileIcon from '@mui/icons-material/InsertDriveFile';
import ImageIcon from '@mui/icons-material/Image';
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import LinkOffIcon from '@mui/icons-material/LinkOff';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import logger from '../utils/logger';

// Human-readable label for an entity type string from the backend
const ENTITY_TYPE_LABELS = {
  fuel_record: 'Fuel Record',
  service_record: 'Service Record',
  service: 'Service Record',
  mot_record: 'MOT',
  mot: 'MOT',
  part: 'Part',
  consumable: 'Consumable',
  vehicle: 'Vehicle',
  insurance: 'Insurance',
  road_tax: 'Road Tax',
  todo: 'To-Do',
};

const entityLabel = (type) => {
  if (!type) return null;
  return ENTITY_TYPE_LABELS[type.toLowerCase()] ?? type;
};

const fileIcon = (mimeType) => {
  if (!mimeType) return <InsertDriveFileIcon fontSize="small" />;
  if (mimeType.startsWith('image/')) return <ImageIcon fontSize="small" />;
  if (mimeType === 'application/pdf') return <PictureAsPdfIcon fontSize="small" />;
  return <InsertDriveFileIcon fontSize="small" />;
};

const fmtSize = (bytes) => {
  if (bytes == null) return '';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
};

/**
 * AttachmentPickerDialog
 *
 * Lets users pick an already-uploaded attachment and link (or move) it to the
 * current entity. Opens a list of all attachments for the vehicle; a toggle
 * limits the list to unlinked (orphan) attachments only.
 *
 * Props:
 *   open          – boolean
 *   onClose       – () => void
 *   vehicleId     – number | null       – filters the list to this vehicle
 *   entityType    – string              – target entity type
 *   entityId      – number             – target entity ID
 *   onAssigned    – (attachment) => void – called after successful link
 */
const AttachmentPickerDialog = ({
  open,
  onClose,
  vehicleId,
  entityType,
  entityId,
  onAssigned,
}) => {
  const { api } = useAuth();
  const { t } = useTranslation();

  const [attachments, setAttachments] = useState([]);
  const [loading, setLoading] = useState(false);
  const [linking, setLinking] = useState(null); // id of the attachment being linked
  const [unlinkedOnly, setUnlinkedOnly] = useState(false);
  const [search, setSearch] = useState('');

  // Fetch whenever the dialog opens or the unlinkedOnly toggle changes
  const fetchAttachments = useCallback(async () => {
    if (!api) return;
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (vehicleId) params.set('vehicleId', vehicleId);
      if (unlinkedOnly) params.set('unlinked', 'true');
      const resp = await api.get(`/attachments?${params.toString()}`);
      setAttachments(Array.isArray(resp.data) ? resp.data : []);
    } catch (err) {
      logger.error('AttachmentPickerDialog: failed to load attachments', err);
    } finally {
      setLoading(false);
    }
  }, [api, vehicleId, unlinkedOnly]);

  useEffect(() => {
    if (open) {
      setSearch('');
      fetchAttachments();
    }
  }, [open, fetchAttachments]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return attachments.filter((a) => {
      // Exclude the attachment that is already linked to this exact entity
      if (a.entityType === entityType && a.entityId === entityId) return false;
      if (!q) return true;
      return (
        (a.originalName || '').toLowerCase().includes(q) ||
        (a.description || '').toLowerCase().includes(q) ||
        (entityLabel(a.entityType) || '').toLowerCase().includes(q)
      );
    });
  }, [attachments, search, entityType, entityId]);

  const handleAssign = async (attachment) => {
    if (!entityType || !entityId) return;
    setLinking(attachment.id);
    try {
      const resp = await api.put(`/attachments/${attachment.id}`, {
        entityType,
        entityId,
      });
      if (onAssigned) onAssigned(resp.data);
      onClose();
    } catch (err) {
      logger.error('AttachmentPickerDialog: failed to link attachment', err);
    } finally {
      setLinking(null);
    }
  };

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', pr: 1 }}>
        <Typography variant="h6">{t('attachments.linkExistingTitle')}</Typography>
        <IconButton size="small" onClick={onClose} aria-label={t('common.close')}>
          <CloseIcon />
        </IconButton>
      </DialogTitle>

      <DialogContent sx={{ px: 2, pt: 1, pb: 2 }}>
        {/* Controls row */}
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1.5, flexWrap: 'wrap' }}>
          <TextField
            size="small"
            placeholder={t('common.search')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            sx={{ flex: 1, minWidth: 160 }}
            InputProps={{
              startAdornment: (
                <InputAdornment position="start">
                  <SearchIcon fontSize="small" />
                </InputAdornment>
              ),
            }}
          />
          <FormControlLabel
            control={
              <Switch
                size="small"
                checked={unlinkedOnly}
                onChange={(e) => setUnlinkedOnly(e.target.checked)}
              />
            }
            label={
              <Typography variant="body2">{t('attachments.unlinkedOnly')}</Typography>
            }
            sx={{ mr: 0 }}
          />
        </Box>

        {/* List */}
        {loading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
            <CircularProgress size={28} />
          </Box>
        ) : filtered.length === 0 ? (
          <Typography variant="body2" color="text.secondary" align="center" sx={{ py: 4 }}>
            {unlinkedOnly
              ? t('attachments.noUnlinked')
              : t('attachments.noFiles')}
          </Typography>
        ) : (
          <List disablePadding dense>
            {filtered.map((att) => {
              const isLinking = linking === att.id;
              const label = entityLabel(att.entityType);
              return (
                <ListItem
                  key={att.id}
                  disablePadding
                  divider
                  secondaryAction={
                    att.entityType && att.entityId ? (
                      <Tooltip title={t('attachments.currentlyLinked', { label: label || att.entityType })}>
                        <Chip
                          label={label || att.entityType}
                          size="small"
                          variant="outlined"
                          color="default"
                          sx={{ fontSize: '0.7rem' }}
                        />
                      </Tooltip>
                    ) : (
                      <Tooltip title={t('attachments.unlinkedTooltip')}>
                        <LinkOffIcon fontSize="small" color="disabled" />
                      </Tooltip>
                    )
                  }
                >
                  <ListItemButton
                    onClick={() => handleAssign(att)}
                    disabled={isLinking}
                    sx={{ pr: att.entityType ? 14 : 6 }}
                  >
                    <ListItemIcon sx={{ minWidth: 36 }}>
                      {isLinking ? (
                        <CircularProgress size={18} />
                      ) : (
                        fileIcon(att.mimeType)
                      )}
                    </ListItemIcon>
                    <ListItemText
                      primary={att.originalName}
                      secondary={fmtSize(att.fileSize)}
                      primaryTypographyProps={{ noWrap: true }}
                    />
                  </ListItemButton>
                </ListItem>
              );
            })}
          </List>
        )}
      </DialogContent>
    </Dialog>
  );
};

export default AttachmentPickerDialog;
