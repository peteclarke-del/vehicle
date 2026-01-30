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
      console.error('Error loading documents:', err);
      setError('Failed to load documents');
    } finally {
      setLoading(false);
    }
  }, [api, vehicle.id, category]);

  useEffect(() => {
    loadDocuments();
  }, [loadDocuments]);

  const handleUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    setUploading(true);
    setError(null);

    const formData = new FormData();
    formData.append('file', file);
    formData.append('entityType', 'vehicle');
    formData.append('entityId', vehicle.id.toString());
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
      event.target.value = ''; // Reset file input
      await loadDocuments();
    } catch (err) {
      console.error('Error uploading document:', err);
      console.error('Error response:', err.response?.data);
      console.error('Error status:', err.response?.status);
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
      console.error('Error deleting document:', err);
      setError('Failed to delete document');
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
      console.error('Error downloading document:', err);
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
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
          <Typography variant="h6">{getCategoryLabel()}</Typography>
          <Box display="flex" gap={2} alignItems="center">
            <TextField
              size="small"
              placeholder={t('common.description')}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              sx={{ width: 200 }}
            />
            <Button
              variant="contained"
              component="label"
              startIcon={uploading ? <KnightRiderLoader size={16} /> : <UploadIcon />}
              disabled={uploading}
            >
              {t('common.upload')}
              <input
                type="file"
                hidden
                onChange={handleUpload}
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx"
              />
            </Button>
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
                }}
              >
                <Box mr={2}>{getFileIcon(doc)}</Box>
                <ListItemText
                  primary={doc.originalName}
                  secondary={
                    <Box>
                      <Typography variant="caption" display="block">
                        {doc.fileSizeFormatted} • {new Date(doc.uploadedAt).toLocaleDateString()}
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
                      onClick={() => handleView(doc)}
                      title={t('common.view')}
                    >
                      <ViewIcon />
                    </IconButton>
                    <IconButton
                      size="small"
                      onClick={() => handleDownload(doc)}
                      title={t('common.download')}
                    >
                      <DownloadIcon />
                    </IconButton>
                    <IconButton
                      size="small"
                      onClick={() => handleDelete(doc.id)}
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
    </Card>
  );
};

export default VehicleDocuments;
