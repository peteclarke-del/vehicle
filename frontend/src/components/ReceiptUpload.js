import React, { useState, useEffect, useCallback } from 'react';
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
  TextField,
  Chip,
  Alert,
  LinearProgress,
  Stack,
} from '@mui/material';
import {
  CloudUpload as UploadIcon,
  Receipt as ReceiptIcon,
  Delete as DeleteIcon,
  Visibility as ViewIcon,
  Edit as EditIcon,
  Add as AddIcon,
  DocumentScanner as ScanIcon,
  CheckCircle as CheckCircleIcon,
  Error as ErrorIcon,
  Image as ImageIcon,
} from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import KnightRiderLoader from './KnightRiderLoader';
import AttachmentViewerDialog from './AttachmentViewerDialog';
import { useDragDrop } from '../hooks/useDragDrop';
import logger from '../utils/logger';

/**
 * Multi-image receipt upload with smart OCR processing.
 *
 * Flow:
 * 1. User uploads one or more images (drag/drop or file picker)
 * 2. Each image is uploaded as an attachment immediately
 * 3. User clicks "Scan All" (or auto-scans on single image) to run OCR
 * 4. OCR results are merged across all pages and returned to parent
 * 5. User can add more images before or after scanning
 * 6. All attachments are associated with the entity
 */
export default function ReceiptUpload({ 
  entityType, 
  entityId,
  vehicleId,
  receiptAttachmentId, 
  onReceiptUploaded, 
  onReceiptRemoved 
}) {
  const { t } = useTranslation();
  const { api } = useAuth();

  // Multi-image state
  const [attachments, setAttachments] = useState([]); // [{id, name, status, error}]
  const [uploading, setUploading] = useState(false);
  const [scanning, setScanning] = useState(false);
  const [scanned, setScanned] = useState(false);
  const [ocrResult, setOcrResult] = useState(null);
  const [uploadProgress, setUploadProgress] = useState(0);

  // Single attachment view state (when editing existing receipt)
  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewAttachmentId, setViewAttachmentId] = useState(null);
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [editDescription, setEditDescription] = useState('');
  const [attachmentDetails, setAttachmentDetails] = useState(null);

  // Description for batch upload
  const [description, setDescription] = useState('');

  // Load existing attachment details
  useEffect(() => {
    const loadAttachmentDetails = async () => {
      if (receiptAttachmentId) {
        try {
          const response = await api.get(`/attachments/${receiptAttachmentId}`, {
            params: { metadata: 'true' }
          });
          setAttachmentDetails(response.data);
        } catch (error) {
          logger.error('Failed to load attachment details:', error);
        }
      } else {
        setAttachmentDetails(null);
      }
    };
    loadAttachmentDetails();
  }, [receiptAttachmentId, api]);

  /**
   * Upload a single file and add it to the attachments list.
   */
  const uploadFile = useCallback(async (file) => {
    const tempId = `pending_${Date.now()}_${Math.random()}`;
    
    setAttachments(prev => [...prev, {
      id: tempId,
      name: file.name,
      status: 'uploading',
      error: null,
    }]);

    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('entityType', entityType);
      if (entityId) formData.append('entityId', String(entityId));
      if (vehicleId) formData.append('vehicleId', String(vehicleId));
      if (description) formData.append('description', description);
      formData.append('category', 'receipt');

      const response = await api.post('/attachments', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });

      const attachmentId = response.data.id;

      setAttachments(prev => prev.map(a =>
        a.id === tempId
          ? { ...a, id: attachmentId, status: 'uploaded', error: null }
          : a
      ));

      return attachmentId;
    } catch (error) {
      logger.error('File upload failed:', error);
      setAttachments(prev => prev.map(a =>
        a.id === tempId
          ? { ...a, status: 'error', error: error.response?.data?.error || 'Upload failed' }
          : a
      ));
      return null;
    }
  }, [api, entityType, entityId, vehicleId, description]);

  /**
   * Handle multiple file selection.
   */
  const handleFilesSelected = useCallback(async (files) => {
    if (!files || files.length === 0) return;

    setUploading(true);
    setScanned(false);
    setOcrResult(null);
    setUploadProgress(0);

    const fileArray = Array.from(files);
    for (let i = 0; i < fileArray.length; i++) {
      await uploadFile(fileArray[i]);
      setUploadProgress(((i + 1) / fileArray.length) * 100);
    }

    setUploading(false);
    setUploadProgress(0);
  }, [uploadFile]);

  const handleFileSelect = (e) => {
    handleFilesSelected(e.target.files);
    e.target.value = '';
  };

  const handleFileDrop = (files) => {
    handleFilesSelected(files);
  };

  const { isDragging, dragHandlers } = useDragDrop(handleFileDrop);

  /**
   * Remove an attachment from the list (and delete from server).
   * If the list becomes empty, notify the parent so receiptAttachmentId
   * is cleared and the upload UI is shown again (not the locked "Receipt attached" view).
   */
  const handleRemoveAttachment = async (attachmentId) => {
    try {
      if (typeof attachmentId === 'number') {
        await api.delete(`/attachments/${attachmentId}`);
      }
      setAttachments(prev => {
        const remaining = prev.filter(a => a.id !== attachmentId);
        // If no attachments left, tell the parent so it clears receiptAttachmentId
        if (remaining.length === 0) {
          setScanned(false);
          setOcrResult(null);
          onReceiptRemoved();
        }
        return remaining;
      });
    } catch (error) {
      logger.error('Failed to remove attachment:', error);
    }
  };

  /**
   * Run OCR on all uploaded attachments (single or multi-page merge).
   */
  const handleScanAll = async () => {
    const uploadedIds = attachments
      .filter(a => a.status === 'uploaded' && typeof a.id === 'number')
      .map(a => a.id);

    if (uploadedIds.length === 0) return;

    setScanning(true);
    try {
      let ocrData;
      
      if (uploadedIds.length === 1) {
        // Single image: use existing endpoint
        const response = await api.get(`/attachments/${uploadedIds[0]}/ocr`, {
          params: { type: entityType }
        });
        ocrData = response.data;
      } else {
        // Multi-image: use new batch endpoint
        const response = await api.post('/attachments/ocr/multi', {
          attachmentIds: uploadedIds,
          type: entityType,
        });
        ocrData = response.data;
      }

      setOcrResult(ocrData);
      setScanned(true);

      // Report the first attachment as the "receipt" and pass OCR data
      onReceiptUploaded(uploadedIds[0], ocrData, uploadedIds);
    } catch (error) {
      logger.warn('OCR scanning failed:', error);
      // Still mark as scanned — attach files even if OCR fails
      setScanned(true);
      onReceiptUploaded(uploadedIds[0], {}, uploadedIds);
    } finally {
      setScanning(false);
    }
  };

  /**
   * Auto-scan when a single image is uploaded (preserves original UX).
   */
  useEffect(() => {
    const uploaded = attachments.filter(a => a.status === 'uploaded' && typeof a.id === 'number');
    if (uploaded.length === 1 && !scanned && !scanning && !uploading) {
      // Auto-scan single uploads after a brief delay
      const timer = setTimeout(() => handleScanAll(), 500);
      return () => clearTimeout(timer);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [attachments, scanned, scanning, uploading]);

  // ─── Existing attachment management (view/edit/delete) ────────────

  const handleRemoveExisting = async () => {
    if (receiptAttachmentId) {
      try {
        await api.delete(`/attachments/${receiptAttachmentId}`);
        onReceiptRemoved();
      } catch (error) {
        logger.error('Failed to delete receipt:', error);
      }
    }
  };

  const handleView = (attachmentId) => {
    setViewAttachmentId(attachmentId || receiptAttachmentId);
    setViewerOpen(true);
  };

  const handleEditClick = async () => {
    if (receiptAttachmentId) {
      if (attachmentDetails) {
        setEditDescription(attachmentDetails.description || '');
        setEditDialogOpen(true);
      } else {
        try {
          const response = await api.get(`/attachments/${receiptAttachmentId}`, {
            params: { metadata: 'true' }
          });
          setAttachmentDetails(response.data);
          setEditDescription(response.data?.description || '');
          setEditDialogOpen(true);
        } catch (error) {
          logger.error('Failed to load attachment for editing:', error);
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
      const response = await api.get(`/attachments/${receiptAttachmentId}`, {
        params: { metadata: 'true' }
      });
      setAttachmentDetails(response.data);
      setEditDialogOpen(false);
    } catch (error) {
      logger.error('Failed to update attachment:', error);
    }
  };

  // ─── Render ───────────────────────────────────────────────────────

  // If we already have a receipt attachment (editing an existing record)
  if (receiptAttachmentId && attachments.length === 0) {
    return (
      <Box>
        <Box 
          sx={{ 
            display: 'flex', alignItems: 'center', gap: 1,
            border: '1px solid', borderColor: 'divider', borderRadius: 1,
            height: '56px', px: 2, cursor: 'pointer',
            '&:hover': { bgcolor: 'action.hover' }
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
            <IconButton size="small" onClick={(e) => { e.stopPropagation(); handleRemoveExisting(); }}>
              <DeleteIcon />
            </IconButton>
          </Tooltip>
        </Box>

        <AttachmentViewerDialog
          open={viewerOpen}
          onClose={() => setViewerOpen(false)}
          attachmentId={viewAttachmentId}
          title={t('attachment.view')}
        />

        <Dialog open={editDialogOpen} onClose={() => setEditDialogOpen(false)} maxWidth="sm" fullWidth>
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
                        <> &bull; {new Date(attachmentDetails.uploadedAt).toLocaleDateString()}</>
                      )}
                    </Typography>
                  </Box>
                </Box>
                <TextField
                  fullWidth multiline rows={3}
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
            <Button variant="contained" onClick={handleSaveEdit} disabled={!attachmentDetails}>
              {t('common.save')}
            </Button>
          </DialogActions>
        </Dialog>
      </Box>
    );
  }

  // ─── Multi-image upload mode ──────────────────────────────────────

  const uploadedCount = attachments.filter(a => a.status === 'uploaded').length;
  const hasErrors = attachments.some(a => a.status === 'error');

  return (
    <Box>
      {/* Upload area */}
      <Box
        {...dragHandlers}
        sx={{
          border: '2px dashed',
          borderColor: isDragging ? 'primary.main' : 'divider',
          borderRadius: 1,
          minHeight: attachments.length > 0 ? '44px' : '56px',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          cursor: 'pointer',
          bgcolor: isDragging ? 'action.hover' : 'transparent',
          transition: 'all 0.2s ease',
          p: 1,
          '&:hover': { bgcolor: 'action.hover', borderColor: 'primary.main' }
        }}
        component="label"
      >
        <input
          type="file"
          hidden
          accept="image/*,application/pdf"
          multiple
          onChange={handleFileSelect}
          disabled={uploading || scanning}
        />
        {uploading ? (
          <Box sx={{ width: '100%', px: 2 }}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
              <KnightRiderLoader size={16} />
              <Typography variant="body2">
                {t('ocr.uploadingImages', 'Uploading images...')}
              </Typography>
            </Box>
            <LinearProgress variant="determinate" value={uploadProgress} />
          </Box>
        ) : (
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
            {attachments.length > 0 ? (
              <AddIcon color="action" fontSize="small" />
            ) : (
              <UploadIcon color="action" />
            )}
            <Typography variant="body2" color="textSecondary">
              {isDragging
                ? (t('attachment.dropHere') || 'Drop here')
                : attachments.length > 0
                  ? t('ocr.addMoreImages', 'Add more images')
                  : t('ocr.uploadReceiptImages', 'Upload receipt image(s)')
              }
            </Typography>
          </Box>
        )}
      </Box>

      {/* Attachment thumbnails / chips */}
      {attachments.length > 0 && (
        <Stack direction="row" spacing={0.5} sx={{ mt: 1, flexWrap: 'wrap', gap: 0.5 }}>
          {attachments.map((att) => (
            <Chip
              key={att.id}
              icon={
                att.status === 'uploading' ? <KnightRiderLoader size={14} /> :
                att.status === 'error' ? <ErrorIcon fontSize="small" /> :
                att.status === 'uploaded' ? <CheckCircleIcon fontSize="small" /> :
                <ImageIcon fontSize="small" />
              }
              label={att.name.length > 25 ? att.name.substring(0, 22) + '...' : att.name}
              size="small"
              color={att.status === 'error' ? 'error' : att.status === 'uploaded' ? 'success' : 'default'}
              variant="outlined"
              onDelete={att.status !== 'uploading' ? () => handleRemoveAttachment(att.id) : undefined}
              onClick={att.status === 'uploaded' && typeof att.id === 'number'
                ? () => handleView(att.id)
                : undefined}
              sx={{ maxWidth: '200px' }}
            />
          ))}
        </Stack>
      )}

      {/* OCR confidence info */}
      {ocrResult?._meta && (
        <Alert
          severity={ocrResult._meta.confidence > 0.5 ? 'success' : ocrResult._meta.confidence > 0.2 ? 'info' : 'warning'}
          sx={{ mt: 1, py: 0 }}
          icon={<ScanIcon fontSize="small" />}
        >
          <Typography variant="caption">
            {ocrResult._meta.vendorName && ocrResult._meta.vendorName !== 'Generic Receipt'
              ? t('ocr.vendorDetected', 'Detected: {{vendor}}', { vendor: ocrResult._meta.vendorName })
              : t('ocr.genericReceipt', 'Generic receipt')
            }
            {ocrResult._meta.pageCount > 1 && (
              <> &bull; {t('ocr.pagesProcessed', '{{count}} pages', { count: ocrResult._meta.pageCount })}</>
            )}
            {' '}&bull; {t('ocr.confidence', 'Confidence: {{pct}}%', { pct: Math.round(ocrResult._meta.confidence * 100) })}
          </Typography>
        </Alert>
      )}

      {/* Scan / Add More buttons */}
      {uploadedCount > 0 && !scanned && (
        <Box sx={{ mt: 1, display: 'flex', gap: 1, justifyContent: 'flex-end' }}>
          {uploadedCount > 1 && (
            <Button
              variant="contained"
              size="small"
              color="primary"
              onClick={handleScanAll}
              disabled={scanning || uploading}
              startIcon={scanning ? <KnightRiderLoader size={14} /> : <ScanIcon />}
            >
              {scanning
                ? t('ocr.scanning', 'Scanning...')
                : t('ocr.scanAll', 'Scan All ({{count}})', { count: uploadedCount })
              }
            </Button>
          )}
        </Box>
      )}

      {hasErrors && (
        <Alert severity="warning" sx={{ mt: 1, py: 0 }}>
          <Typography variant="caption">
            {t('ocr.someUploadsFailed', 'Some files failed to upload. You can try again or continue with the uploaded ones.')}
          </Typography>
        </Alert>
      )}

      {/* Attachment viewer */}
      <AttachmentViewerDialog
        open={viewerOpen}
        onClose={() => setViewerOpen(false)}
        attachmentId={viewAttachmentId}
        title={t('attachment.view')}
      />
    </Box>
  );
}
