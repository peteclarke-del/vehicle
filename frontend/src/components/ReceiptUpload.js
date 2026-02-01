import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  IconButton,
  Tooltip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField
} from '@mui/material';
import {
  CloudUpload as UploadIcon,
  Receipt as ReceiptIcon,
  Delete as DeleteIcon,
  Visibility as ViewIcon,
  Edit as EditIcon
} from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import KnightRiderLoader from './KnightRiderLoader';
import AttachmentViewerDialog from './AttachmentViewerDialog';
import { useDragDrop } from '../hooks/useDragDrop';

export default function ReceiptUpload({ 
  entityType, 
  receiptAttachmentId, 
  onReceiptUploaded, 
  onReceiptRemoved 
}) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const [uploading, setUploading] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [editDescription, setEditDescription] = useState('');
  const [attachmentDetails, setAttachmentDetails] = useState(null);
  const [selectedFile, setSelectedFile] = useState(null);
  const [description, setDescription] = useState('');

  const processFile = async (file) => {
    if (!file) return;

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('entityType', entityType);
      if (description) {
        formData.append('description', description);
      }
      
      const response = await api.post('/attachments', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });

      const attachmentId = response.data.id;
      
      // Call OCR to extract data
      setProcessing(true);
      try {
        const ocrResponse = await api.get(`/attachments/${attachmentId}/ocr`, {
          params: { type: entityType }
        });
        onReceiptUploaded(attachmentId, ocrResponse.data);
      } catch (ocrError) {
        console.warn('OCR failed, receipt attached without data extraction:', ocrError);
        onReceiptUploaded(attachmentId, {});
      }
    } catch (error) {
      console.error('Receipt upload failed:', error);
    } finally {
      setUploading(false);
      setProcessing(false);
      setSelectedFile(null);
      setDescription('');
    }
  };

  const handleFileSelect = (e) => {
    const file = e.target.files?.[0];
    if (file) {
      setSelectedFile(file);
      e.target.value = '';
    }
  };

  const handleFileDrop = (files) => {
    const file = files[0];
    if (file) {
      setSelectedFile(file);
    }
  };

  const handleUploadClick = async () => {
    if (selectedFile) {
      await processFile(selectedFile);
    }
  };

  const handleCancelUpload = () => {
    setSelectedFile(null);
    setDescription('');
  };

  const { isDragging, dragHandlers } = useDragDrop(handleFileDrop);

  useEffect(() => {
    const loadAttachmentDetails = async () => {
      if (receiptAttachmentId) {
        try {
          const response = await api.get(`/attachments/${receiptAttachmentId}`, {
            params: { metadata: 'true' }
          });
          setAttachmentDetails(response.data);
        } catch (error) {
          console.error('Failed to load attachment details:', error);
        }
      } else {
        setAttachmentDetails(null);
      }
    };
    loadAttachmentDetails();
  }, [receiptAttachmentId, api]);

  const handleRemove = async () => {
    if (receiptAttachmentId) {
      try {
        await api.delete(`/attachments/${receiptAttachmentId}`);
        onReceiptRemoved();
      } catch (error) {
        console.error('Failed to delete receipt:', error);
      }
    }
  };

  const handleView = () => {
    if (receiptAttachmentId) {
      setViewerOpen(true);
    }
  };

  const handleCloseViewer = () => {
    setViewerOpen(false);
  };

  const handleEditClick = async () => {
    if (receiptAttachmentId) {
      // If we already have attachment details, use them immediately
      if (attachmentDetails) {
        setEditDescription(attachmentDetails.description || '');
        setEditDialogOpen(true);
      } else {
        // Only fetch if we don't have details yet
        try {
          const response = await api.get(`/attachments/${receiptAttachmentId}`, {
            params: { metadata: 'true' }
          });
          setAttachmentDetails(response.data);
          setEditDescription(response.data?.description || '');
          setEditDialogOpen(true);
        } catch (error) {
          console.error('Failed to load attachment for editing:', error);
        }
      }
    }
  };

  const handleSaveEdit = async () => {
    if (!receiptAttachmentId) return;

    try {
      await api.put(`/attachments/${receiptAttachmentId}`, {
        description: editDescription
      });
      // Reload attachment details
      const response = await api.get(`/attachments/${receiptAttachmentId}`, {
        params: { metadata: 'true' }
      });
      setAttachmentDetails(response.data);
      setEditDialogOpen(false);
    } catch (error) {
      console.error('Failed to update attachment:', error);
    }
  };

  return (
    <Box>
      {!receiptAttachmentId ? (
        <Box
          {...dragHandlers}
          sx={{
            border: '2px dashed',
            borderColor: isDragging ? 'primary.main' : selectedFile ? 'success.main' : 'divider',
            borderRadius: 1,
            minHeight: '56px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
            bgcolor: isDragging ? 'action.hover' : selectedFile ? 'success.lighter' : 'transparent',
            transition: 'all 0.2s ease',
            p: 1,
            gap: 2,
            '&:hover': { 
              bgcolor: selectedFile ? 'success.lighter' : 'action.hover',
              borderColor: 'primary.main'
            }
          }}
          component="label"
        >
          <input
            type="file"
            hidden
            accept="image/*,application/pdf"
            onChange={handleFileSelect}
            disabled={uploading || processing || selectedFile}
          />
          {uploading || processing ? (
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
              <KnightRiderLoader size={16} />
              <Typography variant="body2">
                {uploading ? t('attachment.uploading') : t('attachment.processing')}
              </Typography>
            </Box>
          ) : selectedFile ? (
            <>
              <Typography variant="body2" color="textSecondary">
                {t('attachment.fileSelected')}: <strong>{selectedFile.name}</strong>
              </Typography>
              <TextField
                size="small"
                placeholder={`${t('common.description')} (${t('common.optional')})`}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                onClick={(e) => e.stopPropagation()}
                sx={{ width: 200 }}
              />
              <Button
                variant="contained"
                color="primary"
                size="small"
                onClick={(e) => { e.preventDefault(); handleUploadClick(); }}
                disabled={uploading || processing}
                startIcon={<UploadIcon />}
              >
                {t('common.upload')}
              </Button>
              <Button
                variant="outlined"
                size="small"
                onClick={(e) => { e.preventDefault(); handleCancelUpload(); }}
                disabled={uploading || processing}
              >
                {t('common.cancel')}
              </Button>
            </>
          ) : (
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
              <UploadIcon color="action" />
              <Typography variant="body2" color="textSecondary">
                {isDragging ? t('attachment.dropHere') || 'Drop here' : t('attachment.uploadReceipt')}
              </Typography>
            </Box>
          )}
        </Box>
      ) : (
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
            cursor: 'pointer',
            '&:hover': {
              bgcolor: 'action.hover'
            }
          }}
          onClick={handleEditClick}
        >
          <ReceiptIcon color="primary" />
          <Box sx={{ flex: 1 }}>
            <Typography variant="body2">{t('attachment.receiptAttached')}</Typography>
            {attachmentDetails?.description && (
              <Typography variant="caption" color="textSecondary">
                {attachmentDetails.description}
              </Typography>
            )}
          </Box>
          <Tooltip title={t('attachment.view')}>
            <IconButton size="small" onClick={(e) => { e.stopPropagation(); handleView(); }}>
              <ViewIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title={t('attachment.remove')}>
            <IconButton size="small" onClick={(e) => { e.stopPropagation(); handleRemove(); }}>
              <DeleteIcon />
            </IconButton>
          </Tooltip>
        </Box>
      )}
      <AttachmentViewerDialog
        open={viewerOpen}
        onClose={handleCloseViewer}
        attachmentId={receiptAttachmentId}
        title={t('attachment.view')}
      />

      <Dialog
        open={editDialogOpen}
        onClose={() => setEditDialogOpen(false)}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>{t('attachment.editAttachment')}</DialogTitle>
        <DialogContent>
          {!attachmentDetails ? (
            <Box sx={{ mt: 2, display: 'flex', justifyContent: 'center', p: 3 }}>
              <KnightRiderLoader size={28} />
            </Box>
          ) : (
            <Box sx={{ mt: 2 }}>
              <Box display="flex" alignItems="center" gap={2} mb={3}>
                <ReceiptIcon color="primary" />
                <Box>
                  <Typography variant="body1">{attachmentDetails.originalName || 'Unknown file'}</Typography>
                  <Typography variant="caption" color="textSecondary">
                    {attachmentDetails.fileSizeFormatted || ''}
                    {attachmentDetails.uploadedAt && (
                      <> • {new Date(attachmentDetails.uploadedAt).toLocaleDateString()}</>
                    )}
                  </Typography>
                </Box>
              </Box>
              <TextField
                fullWidth
                multiline
                rows={3}
                label={t('common.description')}
                value={editDescription}
                onChange={(e) => setEditDescription(e.target.value)}
                placeholder={t('attachment.updateDescription')}
              />
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditDialogOpen(false)}>{t('common.cancel')}</Button>
          <Button variant="contained" onClick={handleSaveEdit} disabled={!attachmentDetails}>{t('common.save')}</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
