import React, { useState, useEffect, useCallback } from 'react';
import {
  Box,
  Card,
  CardContent,
  Button,
  Typography,
  Grid,
  IconButton,
  List,
  ListItem,
  ListItemText,
  ListItemSecondaryAction,
  Alert,
  Chip,
  TextField,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
} from '@mui/material';
import {
  CloudUpload as UploadIcon,
  Delete as DeleteIcon,
  Visibility as ViewIcon,
  GetApp as DownloadIcon,
  Description as DocumentIcon,
  PictureAsPdf as PdfIcon,
  Image as ImageIcon,
  InsertDriveFile as FileIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import KnightRiderLoader from './KnightRiderLoader';
import AttachmentViewerDialog from './AttachmentViewerDialog';
import { useDragDrop } from '../hooks/useDragDrop';
import logger from '../utils/logger';

const VehicleDocuments = ({ vehicle, category }) => {
  const { api } = useAuth();
  const { t } = useTranslation();
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [selectedDocument, setSelectedDocument] = useState(null);
  const [error, setError] = useState(null);
  const [description, setDescription] = useState('');
  const [viewerOpen, setViewerOpen] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [editingDocument, setEditingDocument] = useState(null);
  const [editDescription, setEditDescription] = useState('');

  const loadDocuments = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await api.get('/attachments', {
        params: {
          entityType: 'vehicle',
          entityId: vehicle.id,
          category: category,
        },
      });
      setDocuments(response.data || []);
    } catch (err) {
      logger.error('Error loading documents:', err);
      setError('Failed to load documents');
    } finally {
      setLoading(false);
    }
  }, [api, vehicle.id, category]);

  useEffect(() => {
    loadDocuments();
  }, [loadDocuments]);

  const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
      setSelectedFile(file);
      event.target.value = ''; // Reset file input
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
      setSelectedFile(null);
      setDescription('');
    }
  };

  const handleCancelUpload = () => {
    setSelectedFile(null);
    setDescription('');
  };

  const { isDragging, dragHandlers } = useDragDrop(handleFileDrop);

  const processFile = async (file) => {
    if (!file) return;

    setUploading(true);
    setError(null);

    const formData = new FormData();
    formData.append('file', file);
    formData.append('entityType', 'vehicle');
    formData.append('entityId', vehicle.id.toString());
    formData.append('vehicleId', vehicle.id.toString());
    formData.append('category', category);
    if (description) {
      formData.append('description', description);
    }

    try {
      await api.post('/attachments', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      setDescription('');
      await loadDocuments();
    } catch (err) {
      logger.error('Error uploading document:', err);
      logger.error('Error response:', err.response?.data);
      logger.error('Error status:', err.response?.status);
      setError(err.response?.data?.error || err.message || 'Failed to upload document');
    } finally {
      setUploading(false);
    }
  };

  const handleDelete = async (documentId) => {
    if (!window.confirm(t('common.confirmDelete'))) return;

    try {
      await api.delete(`/attachments/${documentId}`);
      await loadDocuments();
    } catch (err) {
      logger.error('Error deleting document:', err);
      setError('Failed to delete document');
    }
  };

  const handleEditClick = (doc) => {
    setEditingDocument(doc);
    setEditDescription(doc.description || '');
    setEditDialogOpen(true);
  };

  const handleSaveEdit = async () => {
    if (!editingDocument) return;

    try {
      await api.put(`/attachments/${editingDocument.id}`, {
        description: editDescription
      });
      await loadDocuments();
      setEditDialogOpen(false);
      setEditingDocument(null);
      setEditDescription('');
    } catch (err) {
      logger.error('Error updating attachment:', err);
      setError('Failed to update attachment description');
    }
  };

  const handleView = (document) => {
    setSelectedDocument(document);
    setViewerOpen(true);
  };

  const handleCloseViewer = () => {
    setViewerOpen(false);
    setSelectedDocument(null);
  };

  const handleDownload = async (document) => {
    try {
      const response = await api.get(`/attachments/${document.id}`, {
        responseType: 'blob',
      });
      
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', document.originalName);
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (err) {
      logger.error('Error downloading document:', err);
      setError('Failed to download document');
    }
  };

  const getFileIcon = (document) => {
    if (document.isImage) {
      return <ImageIcon />;
    } else if (document.isPdf) {
      return <PdfIcon color="error" />;
    } else if (document.mimeType?.includes('word')) {
      return <DocumentIcon color="primary" />;
    }
    return <FileIcon />;
  };

  const getCategoryLabel = () => {
    switch (category) {
      case 'documentation':
        return t('vehicleDetails.documentation') || 'Documentation';
      case 'user_manual':
        return t('vehicleDetails.userManual') || 'User Manual';
      case 'service_manual':
        return t('vehicleDetails.serviceManual') || 'Service Manual';
      default:
        return 'Documents';
    }
  };

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" p={3}>
        <KnightRiderLoader size={28} />
      </Box>
    );
  }

  return (
    <Card>
      <CardContent>
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
          <Typography variant="h6">{getCategoryLabel()}</Typography>
        </Box>

        <Box mb={3}>
          <Box
            {...dragHandlers}
            sx={{
              border: '2px dashed',
              borderColor: isDragging ? 'primary.main' : selectedFile ? 'success.main' : 'divider',
              borderRadius: 1,
              minHeight: '60px',
              display: 'flex',
              flexDirection: 'row',
              alignItems: 'center',
              justifyContent: 'center',
              cursor: 'pointer',
              bgcolor: isDragging ? 'action.hover' : selectedFile ? 'success.lighter' : 'transparent',
              transition: 'all 0.2s ease',
              p: 2,
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
              onChange={handleFileSelect}
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx"
              disabled={uploading || selectedFile}
            />
            {uploading ? (
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                <KnightRiderLoader size={16} />
                <Typography variant="body2">
                  {t('attachment.uploading')}
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
                  sx={{ width: 250 }}
                />
                <Button
                  variant="contained"
                  color="primary"
                  onClick={(e) => { e.preventDefault(); handleUploadClick(); }}
                  disabled={uploading}
                  startIcon={<UploadIcon />}
                >
                  {t('common.upload')}
                </Button>
                <Button
                  variant="outlined"
                  onClick={(e) => { e.preventDefault(); handleCancelUpload(); }}
                  disabled={uploading}
                >
                  {t('common.cancel')}
                </Button>
              </>
            ) : (
              <>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <UploadIcon color="action" />
                  <Typography variant="body2" color="textSecondary">
                    {isDragging ? t('attachment.dropHere') || 'Drop here' : (t('attachment.selectDocument') || 'Click or drag to select document')}
                  </Typography>
                </Box>
              </>
            )}
          </Box>
        </Box>

        {error && (
          <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
            {error}
          </Alert>
        )}

        {documents.length === 0 ? (
          <Box textAlign="center" py={4}>
            <Typography color="textSecondary">
              {t('vehicleDetails.noDocuments') || 'No documents uploaded yet'}
            </Typography>
          </Box>
        ) : (
          <List>
            {documents.map((doc) => (
              <ListItem
                key={doc.id}
                sx={{
                  border: '1px solid',
                  borderColor: 'divider',
                  borderRadius: 1,
                  mb: 1,
                  cursor: 'pointer',
                  '&:hover': {
                    bgcolor: 'action.hover'
                  }
                }}
                onClick={() => handleEditClick(doc)}
              >
                <Box mr={2}>{getFileIcon(doc)}</Box>
                <ListItemText
                  primary={doc.originalName}
                  secondary={
                    <Box>
                      <Typography variant="caption" display="block">
                        {doc.fileSizeFormatted}
                        {doc.uploadedAt && (
                          <> • {new Date(doc.uploadedAt).toLocaleDateString()}</>
                        )}
                      </Typography>
                      {doc.description && (
                        <Typography variant="caption" color="textSecondary">
                          {doc.description}
                        </Typography>
                      )}
                    </Box>
                  }
                />
                <ListItemSecondaryAction>
                  <Box display="flex" gap={1}>
                    <IconButton
                      size="small"
                      onClick={(e) => { e.stopPropagation(); handleView(doc); }}
                      title={t('common.view')}
                    >
                      <ViewIcon />
                    </IconButton>
                    <IconButton
                      size="small"
                      onClick={(e) => { e.stopPropagation(); handleDownload(doc); }}
                      title={t('common.download')}
                    >
                      <DownloadIcon />
                    </IconButton>
                    <IconButton
                      size="small"
                      onClick={(e) => { e.stopPropagation(); handleDelete(doc.id); }}
                      title={t('common.delete')}
                      color="error"
                    >
                      <DeleteIcon />
                    </IconButton>
                  </Box>
                </ListItemSecondaryAction>
              </ListItem>
            ))}
          </List>
        )}
      </CardContent>

      <AttachmentViewerDialog
        open={viewerOpen}
        onClose={handleCloseViewer}
        attachmentId={selectedDocument?.id}
        title={
          selectedDocument ? (
            <Box display="flex" alignItems="center" gap={2}>
              <Typography variant="inherit">{selectedDocument.originalName}</Typography>
              <Chip label={selectedDocument.fileSizeFormatted} size="small" />
            </Box>
          ) : (
            t('common.view')
          )
        }
        mimeType={selectedDocument?.mimeType}
        showDownload
        onDownload={
          selectedDocument ? () => handleDownload(selectedDocument) : undefined
        }
      />

      <Dialog
        open={editDialogOpen}
        onClose={() => setEditDialogOpen(false)}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>{t('attachment.editAttachment')}</DialogTitle>
        <DialogContent>
          {editingDocument && (
            <Box sx={{ mt: 2 }}>
              <Box display="flex" alignItems="center" gap={2} mb={3}>
                {getFileIcon(editingDocument)}
                <Box>
                  <Typography variant="body1">{editingDocument.originalName}</Typography>
                  <Typography variant="caption" color="textSecondary">
                    {editingDocument.fileSizeFormatted}
                    {editingDocument.uploadedAt && (
                      <> • {new Date(editingDocument.uploadedAt).toLocaleDateString()}</>
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
          <Button variant="contained" onClick={handleSaveEdit}>{t('common.save')}</Button>
        </DialogActions>
      </Dialog>
    </Card>
  );
};

export default VehicleDocuments;
