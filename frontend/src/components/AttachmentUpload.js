import React, { useState, useEffect } from 'react';
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
  CircularProgress,
  Chip,
} from '@mui/material';
import {
  CloudUpload,
  Delete as DeleteIcon,
  Download as DownloadIcon,
  InsertDriveFile,
  Image as ImageIcon,
  PictureAsPdf,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';

const AttachmentUpload = ({ entityType, entityId, onChange }) => {
  const [attachments, setAttachments] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [loading, setLoading] = useState(false);
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
    const files = Array.from(event.target.files);
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
        setAttachments(prev => [...prev, response.data]);
        if (onChange) onChange([...attachments, response.data]);
      } catch (error) {
        console.error('Error uploading file:', error);
        alert(t('common.uploadFailed', { filename: file.name }));
      }
    }
    setUploading(false);
    event.target.value = '';
  };

  const handleDelete = async (id) => {
    if (!window.confirm(t('attachments.deleteConfirm'))) return;

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
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', attachment.originalName);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (error) {
      console.error('Error downloading attachment:', error);
      alert(t('common.downloadFailed'));
    }
  };

  const getFileIcon = (mimeType) => {
    if (mimeType.startsWith('image/')) return <ImageIcon />;
    if (mimeType === 'application/pdf') return <PictureAsPdf />;
    return <InsertDriveFile />;
  };

  const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  };

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
        <Typography variant="subtitle1">{t('attachments.title')}</Typography>
        <Button
          variant="outlined"
          component="label"
          startIcon={uploading ? <CircularProgress size={20} /> : <CloudUpload />}
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

      {loading ? (
        <Box display="flex" justifyContent="center" p={2}>
          <CircularProgress />
        </Box>
      ) : attachments.length === 0 ? (
        <Typography variant="body2" color="text.secondary" align="center" py={2}>
          {t('attachments.noFiles')}
        </Typography>
      ) : (
        <List dense>
          {attachments.map((attachment) => (
            <ListItem key={attachment.id} divider>
              {getFileIcon(attachment.mimeType)}
              <ListItemText
                primary={attachment.originalName}
                secondary={
                  <>
                    {formatFileSize(attachment.fileSize)} • {new Date(attachment.uploadedAt).toLocaleDateString()}
                  </>
                }
                sx={{ ml: 2 }}
              />
              <ListItemSecondaryAction>
                <IconButton edge="end" onClick={() => handleDownload(attachment)} size="small">
                  <DownloadIcon />
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
    </Box>
  );
};

export default AttachmentUpload;
