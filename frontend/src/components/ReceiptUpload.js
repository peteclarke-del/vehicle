import React, { useState } from 'react';
import {
  Box,
  Typography,
  IconButton,
  Tooltip
} from '@mui/material';
import {
  CloudUpload as UploadIcon,
  Receipt as ReceiptIcon,
  Delete as DeleteIcon,
  Visibility as ViewIcon
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

  const processFile = async (file) => {
    if (!file) return;

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('entityType', entityType);
      
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
    }
  };

  const handleFileSelect = async (e) => {
    const file = e.target.files?.[0];
    await processFile(file);
  };

  const handleFileDrop = async (files) => {
    const file = files[0];
    await processFile(file);
  };

  const { isDragging, dragHandlers } = useDragDrop(handleFileDrop);

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

  return (
    <Box>
      {!receiptAttachmentId ? (
        <Box
          {...dragHandlers}
          sx={{
            border: '2px dashed',
            borderColor: isDragging ? 'primary.main' : 'divider',
            borderRadius: 1,
            height: '56px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
            bgcolor: isDragging ? 'action.hover' : 'transparent',
            transition: 'all 0.2s ease',
            '&:hover': { 
              bgcolor: 'action.hover',
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
            disabled={uploading || processing}
          />
          {uploading || processing ? (
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
              <KnightRiderLoader size={16} />
              <Typography variant="body2">
                {uploading ? t('attachment.uploading') : t('attachment.processing')}
              </Typography>
            </Box>
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
            px: 2
          }}
        >
          <ReceiptIcon color="primary" />
          <Typography variant="body2" sx={{ flex: 1 }}>{t('attachment.receiptAttached')}</Typography>
          <Tooltip title={t('attachment.view')}>
            <IconButton size="small" onClick={handleView}>
              <ViewIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title={t('attachment.remove')}>
            <IconButton size="small" onClick={handleRemove}>
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
    </Box>
  );
}
