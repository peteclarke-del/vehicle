import React, { useState, useEffect } from 'react';
import { formatDateISO } from '../utils/formatDate';
import {
  Box,
  Button,
  IconButton,
  List,
  ListItem,
  ListItemText,
  ListItemSecondaryAction,
  Typography,
  TextField,
  Chip,
} from '@mui/material';
import {
  CloudUpload,
  Delete as DeleteIcon,
  Download as DownloadIcon,
  Visibility as ViewIcon,
  InsertDriveFile,
  Image as ImageIcon,
  PictureAsPdf,
  Receipt as ReceiptIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import KnightRiderLoader from './KnightRiderLoader';
import AttachmentViewerDialog from './AttachmentViewerDialog';
import { useDragDrop } from '../hooks/useDragDrop';

const AttachmentUpload = ({ entityType, entityId, onChange, compact = false }) => {
  const [attachments, setAttachments] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [loading, setLoading] = useState(false);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [selectedAttachment, setSelectedAttachment] = useState(null);
  const { api } = useAuth();
  const { t } = useTranslation();

  useEffect(() => {
    if (entityId) {
      loadAttachments();
    }
  }, [entityId]);

  const loadAttachments = async () => {
    if (!entityId) return;
    setLoading(true);
    try {
      const response = await api.get(`/attachments?entityType=${entityType}&entityId=${entityId}`);
      setAttachments(response.data);
      if (onChange) onChange(response.data);
    } catch (error) {
      console.error('Error loading attachments:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleFileSelect = async (event) => {
    const files = Array.from(event.target.files || []);
    await processFiles(files);
    event.target.value = '';
  };

  const handleFileDrop = async (files) => {
    await processFiles(files);
  };

  const { isDragging, dragHandlers } = useDragDrop(handleFileDrop);

  const processFiles = async (files) => {
    if (files.length === 0) return;

    setUploading(true);
    for (const file of files) {
      const formData = new FormData();
      formData.append('file', file);
      if (entityType) formData.append('entityType', entityType);
      if (entityId) formData.append('entityId', entityId);

      try {
        const response = await api.post('/attachments', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        const newAttachments = [...attachments, response.data];
        setAttachments(newAttachments);
        if (onChange) onChange(newAttachments);
      } catch (error) {
        console.error('Error uploading file:', error);
        alert(t('common.uploadFailed', { filename: file.name }));
      }
    }
    setUploading(false);
  };

  const handleDelete = async (id) => {
    if (!window.confirm(t('common.confirmDelete'))) return;

    try {
      await api.delete(`/attachments/${id}`);
      const newAttachments = attachments.filter(a => a.id !== id);
      setAttachments(newAttachments);
      if (onChange) onChange(newAttachments);
    } catch (error) {
      console.error('Error deleting attachment:', error);
      alert(t('common.deleteFailed'));
    }
  };

  const handleDownload = async (attachment) => {
    try {
      const response = await api.get(`/attachments/${attachment.id}`, {
        responseType: 'blob',
      });
      // response.data is a Blob already (axios with responseType: 'blob')
      const blob = response.data instanceof Blob ? response.data : new Blob([response.data]);
      // lazy-import to avoid circular issues
      const { saveBlob } = await import('../components/DownloadHelpers');
      saveBlob(blob, attachment.originalName);
    } catch (error) {
      console.error('Error downloading attachment:', error);
      alert(t('common.downloadFailed'));
    }
  };

  const handleView = (attachment) => {
    setSelectedAttachment(attachment);
    setViewerOpen(true);
  };

  const handleCloseViewer = () => {
    setViewerOpen(false);
    setSelectedAttachment(null);
  };

  const getFileIcon = (mimeType) => {
    if (!mimeType) return <InsertDriveFile />;
    if (mimeType.startsWith('image/')) return <ImageIcon />;
    if (mimeType === 'application/pdf') return <PictureAsPdf />;
    return <InsertDriveFile />;
  };

  const formatFileSize = (bytes) => {
    if (bytes == null) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  };

  return (
    <Box>
      {!compact && (
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
          <Typography variant="subtitle1">{t('attachments.title')}</Typography>
          <Button
            variant="outlined"
            component="label"
            startIcon={uploading ? <KnightRiderLoader size={16} /> : <CloudUpload />}
            disabled={uploading}
          >
            {t('attachments.upload')}
            <input
              type="file"
              hidden
              multiple
              accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx"
              onChange={handleFileSelect}
            />
          </Button>
        </Box>
      )}

      {loading ? (
        <Box display="flex" justifyContent="center" p={2}>
          <KnightRiderLoader size={24} />
        </Box>
      ) : attachments.length === 0 ? (
        compact ? (
          // Compact upload box full-width
          <Box component="label" sx={{ display: 'block', width: '100%' }} {...dragHandlers}>
            <input
              type="file"
              hidden
              multiple
              accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx"
              onChange={handleFileSelect}
            />
            <Box
              sx={{
                border: '2px dashed',
                borderColor: isDragging ? 'primary.main' : 'divider',
                borderRadius: 1,
                height: '56px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: 'pointer',
                width: '100%',
                bgcolor: isDragging ? 'action.hover' : 'transparent',
                transition: 'all 0.2s ease',
                '&:hover': {
                  bgcolor: 'action.hover',
                  borderColor: 'primary.main'
                }
              }}
            >
              {uploading ? (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <KnightRiderLoader size={16} />
                  <Typography variant="body2">{t('attachment.uploading')}</Typography>
                </Box>
              ) : (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <CloudUpload color="action" />
                  <Typography variant="body2" color="textSecondary">
                    {isDragging ? (t('attachment.dropHere') || 'Drop here') : t('attachment.uploadReceipt')}
                  </Typography>
                </Box>
              )}
            </Box>
          </Box>
        ) : (
          <Typography variant="body2" color="text.secondary" align="center" py={2}>
            {t('attachments.noFiles')}
          </Typography>
        )
      ) : compact ? (
        // Compact mode: mirror ReceiptUpload attached view for the first attachment
        (() => {
          const att = attachments[0];
          return (
            <Box
              sx={{
                display: 'flex',
                alignItems: 'center',
                gap: 1,
                border: '1px solid',
                borderColor: 'divider',
                borderRadius: 1,
                height: '56px',
                px: 2,
                width: '100%'
              }}
            >
              <ReceiptIcon color="primary" />
              <Typography variant="body2" sx={{ flex: 1 }}>{t('attachment.receiptAttached')}</Typography>
              <IconButton size="small" onClick={() => handleView(att)}>
                <ViewIcon />
              </IconButton>
              <IconButton size="small" onClick={() => handleDownload(att)}>
                <DownloadIcon />
              </IconButton>
              <IconButton size="small" onClick={() => handleDelete(att.id)}>
                <DeleteIcon />
              </IconButton>
            </Box>
          );
        })()
      ) : (
        <List dense>
          {attachments.map((attachment) => (
            <ListItem key={attachment.id} divider>
              {getFileIcon(attachment.mimeType)}
              <ListItemText
                primary={attachment.originalName}
                secondary={
                  <>
                    {formatFileSize(attachment.fileSize)} • {formatDateISO(attachment.uploadedAt)}
                  </>
                }
                sx={{ ml: 2 }}
              />
              <ListItemSecondaryAction>
                <IconButton edge="end" onClick={() => handleDownload(attachment)} size="small">
                  <DownloadIcon />
                </IconButton>
                <IconButton edge="end" onClick={() => handleView(attachment)} size="small">
                  <ViewIcon />
                </IconButton>
                <IconButton edge="end" onClick={() => handleDelete(attachment.id)} size="small">
                  <DeleteIcon />
                </IconButton>
              </ListItemSecondaryAction>
            </ListItem>
          ))}
        </List>
      )}

      {!entityId && (
        <Typography variant="caption" color="text.secondary" display="block" mt={1}>
          {t('attachments.saveFirst')}
        </Typography>
      )}

      <AttachmentViewerDialog
        open={viewerOpen}
        onClose={handleCloseViewer}
        attachmentId={selectedAttachment?.id}
        title={selectedAttachment?.originalName || t('common.view')}
        mimeType={selectedAttachment?.mimeType}
        showDownload
        onDownload={
          selectedAttachment ? () => handleDownload(selectedAttachment) : undefined
        }
      />
    </Box>
  );
};

export default AttachmentUpload;

