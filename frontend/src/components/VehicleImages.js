import React, { useState, useEffect, useCallback } from 'react';
import {
  Box,
  Card,
  CardContent,
  Typography,
  Button,
  ImageList,
  ImageListItem,
  ImageListItemBar,
  IconButton,
  Dialog,
  DialogContent,
  TextField,
  Alert,
  Checkbox,
  FormControlLabel,
} from '@mui/material';
import {
  Delete as DeleteIcon,
  CloudUpload as UploadIcon,
  Close as CloseIcon,
  ArrowBack as ArrowBackIcon,
  ArrowForward as ArrowForwardIcon,
  Star as StarIcon,
  StarBorder as StarBorderIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import KnightRiderLoader from './KnightRiderLoader';
import { useDragDrop } from '../hooks/useDragDrop';

const VehicleImages = ({ vehicle }) => {
  const { api } = useAuth();
  const { t } = useTranslation();
  const [images, setImages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [editingCaption, setEditingCaption] = useState(null);
  const [captionText, setCaptionText] = useState('');

  const handleImageDrop = async (files) => {
    await processFiles(files);
  };

  const { isDragging, dragHandlers } = useDragDrop(handleImageDrop);

  const loadImages = useCallback(async () => {
    try {
      const response = await api.get(`/vehicles/${vehicle.id}/images`);
      setImages(response.data.images);
      setLoading(false);
    } catch (err) {
      setError(t('vehicleImages.failedLoad'));
      setLoading(false);
    }
  }, [vehicle.id]);

  useEffect(() => {
    loadImages();
  }, [loadImages]);

  const handleFileSelect = async (event) => {
    const files = event.target.files;
    await processFiles(files);
    event.target.value = '';
  };

  const processFiles = async (files) => {
    if (!files || files.length === 0) return;

    setUploading(true);
    setError('');
    setSuccess('');

    let successCount = 0;
    let errorMessage = '';

    for (const file of files) {
      const formData = new FormData();
      formData.append('image', file);

      try {
        await api.post(`/vehicles/${vehicle.id}/images`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        successCount += 1;
      } catch (err) {
        errorMessage = err.response?.data?.error || t('vehicleImages.failedUpload');
      }
    }

    setUploading(false);
    if (errorMessage) {
      setSuccess('');
      setError(errorMessage);
    } else {
      setSuccess(t('vehicleImages.uploadSuccess', { count: successCount }));
      setError('');
    }
    loadImages();
  };

  const handleDelete = async (imageId) => {
    if (!window.confirm(t('vehicleImages.confirmDelete'))) return;

    try {
      await api.delete(`/vehicles/${vehicle.id}/images/${imageId}`);
      setSuccess(t('vehicleImages.deletedSuccess'));
      loadImages();
    } catch (err) {
      setError(t('vehicleImages.failedDelete'));
    }
  };

  const handleSetPrimary = async (imageId) => {
    try {
      await api.put(`/vehicles/${vehicle.id}/images/${imageId}/primary`);
      setSuccess(t('vehicleImages.primaryUpdated'));
      loadImages();
    } catch (err) {
      setError(t('vehicleImages.failedPrimary'));
    }
  };

  const handleCaptionSave = async (imageId) => {
    try {
      await api.put(`/vehicles/${vehicle.id}/images/${imageId}`, {
        caption: captionText,
      });
      setEditingCaption(null);
      setCaptionText('');
      loadImages();
    } catch (err) {
      setError(t('vehicleImages.failedUpdateCaption'));
    }
  };

  const openLightbox = (index) => {
    setCurrentIndex(index);
    setLightboxOpen(true);
  };

  const closeLightbox = useCallback(() => {
    setLightboxOpen(false);
  }, []);

  const goToPrevious = useCallback(() => {
    setCurrentIndex((prevIndex) => (prevIndex === 0 ? images.length - 1 : prevIndex - 1));
  }, [images.length]);

  const goToNext = useCallback(() => {
    setCurrentIndex((prevIndex) => (prevIndex === images.length - 1 ? 0 : prevIndex + 1));
  }, [images.length]);

  useEffect(() => {
    const handleKeyPress = (e) => {
      if (!lightboxOpen) return;
      
      if (e.key === 'Escape') {
        closeLightbox();
      } else if (e.key === 'ArrowLeft') {
        goToPrevious();
      } else if (e.key === 'ArrowRight') {
        goToNext();
      }
    };

    window.addEventListener('keydown', handleKeyPress);
    return () => window.removeEventListener('keydown', handleKeyPress);
  }, [lightboxOpen, closeLightbox, goToPrevious, goToNext]);

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" p={4}>
        <KnightRiderLoader size={28} />
      </Box>
    );
  }

  return (
    <Card>
      <CardContent>
        <Box 
          display="flex" 
          justifyContent="space-between" 
          alignItems="center" 
          mb={2}
          {...dragHandlers}
          sx={{
            p: 1,
            borderRadius: 1,
            border: '2px dashed',
            borderColor: isDragging ? 'primary.main' : 'transparent',
            bgcolor: isDragging ? 'action.hover' : 'transparent',
            transition: 'all 0.2s ease'
          }}
        >
          <Typography variant="h6">{t('vehicleImages.title')}</Typography>
          <Button
            variant="contained"
            component="label"
            startIcon={uploading ? <KnightRiderLoader size={16} /> : <UploadIcon />}
            disabled={uploading}
          >
            {isDragging ? (t('attachment.dropHere') || 'Drop here') : t('vehicleImages.upload')}
            <input
              type="file"
              hidden
              multiple
              accept="image/jpeg,image/png,image/webp"
              onChange={handleFileSelect}
            />
          </Button>
        </Box>

        {error && (
          <Alert severity="error" onClose={() => setError('')} sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}

        {success && (
          <Alert severity="success" onClose={() => setSuccess('')} sx={{ mb: 2 }}>
            {success}
          </Alert>
        )}

        {images.length === 0 ? (
          <Box textAlign="center" py={4}>
              <Typography variant="body1" color="textSecondary">
              {t('vehicleImages.noImages')}
            </Typography>
          </Box>
        ) : (
          <ImageList cols={4} gap={8}>
            {images.map((image, index) => (
              <ImageListItem key={image.id} sx={{ cursor: 'pointer' }}>
                <img
                  src={`${(process.env.REACT_APP_API_URL || 'http://localhost:8000').replace(/\/api\/?$/, '')}${image.path}`}
                  alt={image.caption || `Vehicle image ${index + 1}`}
                  loading="lazy"
                  style={{ height: 200, objectFit: 'cover' }}
                  onClick={() => openLightbox(index)}
                />
                <ImageListItemBar
                  title={
                    <Box display="flex" alignItems="center" gap={1}>
                      {image.isPrimary && <StarIcon fontSize="small" />}
                          {editingCaption === image.id ? (
                        <TextField
                          size="small"
                          value={captionText}
                          onChange={(e) => setCaptionText(e.target.value)}
                          onBlur={() => handleCaptionSave(image.id)}
                          onKeyPress={(e) => {
                            if (e.key === 'Enter') handleCaptionSave(image.id);
                          }}
                          onClick={(e) => e.stopPropagation()}
                          autoFocus
                        />
                      ) : (
                        <span
                          onClick={(e) => {
                            e.stopPropagation();
                            setEditingCaption(image.id);
                            setCaptionText(image.caption || '');
                          }}
                        >
                          {image.caption || t('vehicleImages.clickToAddCaption')}
                        </span>
                      )}
                    </Box>
                  }
                  actionIcon={
                    <Box>
                      <IconButton
                        sx={{ color: 'white' }}
                        onClick={(e) => {
                          e.stopPropagation();
                          handleSetPrimary(image.id);
                        }}
                        size="small"
                      >
                        {image.isPrimary ? <StarIcon /> : <StarBorderIcon />}
                      </IconButton>
                      <IconButton
                        sx={{ color: 'white' }}
                        onClick={(e) => {
                          e.stopPropagation();
                          handleDelete(image.id);
                        }}
                        size="small"
                      >
                        <DeleteIcon />
                      </IconButton>
                    </Box>
                  }
                />
              </ImageListItem>
            ))}
          </ImageList>
        )}

        {/* Lightbox Dialog */}
        <Dialog
          open={lightboxOpen}
          onClose={closeLightbox}
          maxWidth={false}
          PaperProps={{
            sx: {
              backgroundColor: 'rgba(0, 0, 0, 0.95)',
              boxShadow: 'none',
              maxHeight: '95vh',
              maxWidth: '95vw',
            },
          }}
        >
          <DialogContent sx={{ position: 'relative', padding: 0, overflow: 'hidden' }}>
            <IconButton
              onClick={closeLightbox}
              sx={{
                position: 'absolute',
                top: 10,
                right: 10,
                color: 'white',
                backgroundColor: 'rgba(0, 0, 0, 0.5)',
                '&:hover': { backgroundColor: 'rgba(0, 0, 0, 0.7)' },
                zIndex: 1,
              }}
            >
              <CloseIcon />
            </IconButton>

            {images.length > 1 && (
              <>
                <IconButton
                  onClick={goToPrevious}
                  sx={{
                    position: 'absolute',
                    left: 10,
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: 'white',
                    backgroundColor: 'rgba(0, 0, 0, 0.5)',
                    '&:hover': { backgroundColor: 'rgba(0, 0, 0, 0.7)' },
                    zIndex: 1,
                  }}
                >
                  <ArrowBackIcon />
                </IconButton>

                <IconButton
                  onClick={goToNext}
                  sx={{
                    position: 'absolute',
                    right: 10,
                    top: '50%',
                    transform: 'translateY(-50%)',
                    color: 'white',
                    backgroundColor: 'rgba(0, 0, 0, 0.5)',
                    '&:hover': { backgroundColor: 'rgba(0, 0, 0, 0.7)' },
                    zIndex: 1,
                  }}
                >
                  <ArrowForwardIcon />
                </IconButton>
              </>
            )}

            <Box
              display="flex"
              flexDirection="column"
              alignItems="center"
              justifyContent="center"
              minHeight="80vh"
            >
              {images[currentIndex] && (
                <>
                  <img
                    src={`${(process.env.REACT_APP_API_URL || 'http://localhost:8000').replace(/\/api\/?$/, '')}${
                      images[currentIndex].path
                    }`}
                    alt={images[currentIndex].caption || 'Vehicle image'}
                    style={{
                      maxHeight: '85vh',
                      maxWidth: '90vw',
                      objectFit: 'contain',
                    }}
                  />
                  {images[currentIndex].caption && (
                    <Typography
                      variant="h6"
                      sx={{
                        color: 'white',
                        mt: 2,
                        textAlign: 'center',
                        textShadow: '2px 2px 4px rgba(0,0,0,0.8)',
                      }}
                    >
                      {images[currentIndex].caption}
                    </Typography>
                  )}
                  <Typography
                    variant="body2"
                    sx={{
                      color: 'white',
                      mt: 1,
                      textShadow: '2px 2px 4px rgba(0,0,0,0.8)',
                    }}
                  >
                    {currentIndex + 1} / {images.length}
                  </Typography>
                </>
              )}
            </Box>
          </DialogContent>
        </Dialog>
      </CardContent>
    </Card>
  );
};

export default VehicleImages;
