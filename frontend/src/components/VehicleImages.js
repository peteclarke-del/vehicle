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
  CircularProgress,
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

const VehicleImages = ({ vehicle }) => {
  const { api } = useAuth();
  const [images, setImages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [editingCaption, setEditingCaption] = useState(null);
  const [captionText, setCaptionText] = useState('');

  const loadImages = useCallback(async () => {
    try {
      const response = await api.get(`/vehicles/${vehicle.id}/images`);
      setImages(response.data.images);
      setLoading(false);
    } catch (err) {
      setError('Failed to load images');
      setLoading(false);
    }
  }, [vehicle.id]);

  useEffect(() => {
    loadImages();
  }, [loadImages]);

  const handleFileSelect = async (event) => {
    const files = event.target.files;
    if (!files || files.length === 0) return;

    setUploading(true);
    setError('');
    setSuccess('');

    for (const file of files) {
      const formData = new FormData();
      formData.append('image', file);

      try {
        await api.post(`/vehicles/${vehicle.id}/images`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
      } catch (err) {
        setError(err.response?.data?.error || 'Failed to upload image');
      }
    }

    setUploading(false);
    setSuccess(`Successfully uploaded ${files.length} image(s)`);
    loadImages();
    
    // Clear the file input
    event.target.value = '';
  };

  const handleDelete = async (imageId) => {
    if (!window.confirm('Are you sure you want to delete this image?')) return;

    try {
      await api.delete(`/vehicles/${vehicle.id}/images/${imageId}`);
      setSuccess('Image deleted successfully');
      loadImages();
    } catch (err) {
      setError('Failed to delete image');
    }
  };

  const handleSetPrimary = async (imageId) => {
    try {
      await api.put(`/vehicles/${vehicle.id}/images/${imageId}/primary`);
      setSuccess('Primary image updated');
      loadImages();
    } catch (err) {
      setError('Failed to set primary image');
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
      setError('Failed to update caption');
    }
  };

  const openLightbox = (index) => {
    setCurrentIndex(index);
    setLightboxOpen(true);
  };

  const closeLightbox = () => {
    setLightboxOpen(false);
  };

  const goToPrevious = () => {
    setCurrentIndex((prevIndex) => (prevIndex === 0 ? images.length - 1 : prevIndex - 1));
  };

  const goToNext = () => {
    setCurrentIndex((prevIndex) => (prevIndex === images.length - 1 ? 0 : prevIndex + 1));
  };

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
  }, [lightboxOpen]);

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" p={4}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Card>
      <CardContent>
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
          <Typography variant="h6">Vehicle Pictures</Typography>
          <Button
            variant="contained"
            component="label"
            startIcon={uploading ? <CircularProgress size={20} /> : <UploadIcon />}
            disabled={uploading}
          >
            Upload Images
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
              No images uploaded yet. Click "Upload Images" to add pictures of your vehicle.
            </Typography>
          </Box>
        ) : (
          <ImageList cols={4} gap={8}>
            {images.map((image, index) => (
              <ImageListItem key={image.id} sx={{ cursor: 'pointer' }}>
                <img
                  src={`${process.env.REACT_APP_API_URL || 'http://localhost:8000'}${image.path}`}
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
                          {image.caption || 'Click to add caption'}
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
                    src={`${process.env.REACT_APP_API_URL || 'http://localhost:8000'}${
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
