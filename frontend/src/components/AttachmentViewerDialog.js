import React, { useEffect, useRef, useState } from 'react';
import {
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  Typography,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import KnightRiderLoader from './KnightRiderLoader';

const AttachmentViewerDialog = ({
  open,
  onClose,
  attachmentId,
  title,
  mimeType,
  onDownload,
  showDownload = false,
}) => {
  const { api } = useAuth();
  const { t } = useTranslation();
  const [previewUrl, setPreviewUrl] = useState(null);
  const [previewType, setPreviewType] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewError, setPreviewError] = useState(null);
  const [imageZoomed, setImageZoomed] = useState(false);
  const [position, setPosition] = useState(null);
  const [size, setSize] = useState(null);
  const [dragging, setDragging] = useState(false);
  const [dragStart, setDragStart] = useState(null);
  const paperRef = useRef(null);
  const imageContainerRef = useRef(null);
  const [isPanning, setIsPanning] = useState(false);
  const panStartRef = useRef(null);
  const suppressClickRef = useRef(false);

  useEffect(() => {
    let active = true;

    const loadPreview = async () => {
      if (!open || !attachmentId) return;

      setPreviewLoading(true);
      setPreviewError(null);
      setImageZoomed(false);
      try {
        const response = await api.get(`/attachments/${attachmentId}`, {
          responseType: 'blob',
        });
        if (!active) return;

        const blob = response.data;
        const url = URL.createObjectURL(blob);
        setPreviewUrl(url);
        setPreviewType(blob.type || mimeType || null);
      } catch (error) {
        if (!active) return;
        setPreviewError(error);
      } finally {
        if (active) {
          setPreviewLoading(false);
        }
      }
    };

    loadPreview();

    return () => {
      active = false;
    };
  }, [open, attachmentId, api, mimeType]);

  useEffect(() => {
    return () => {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
      }
    };
  }, [previewUrl]);

  const handleToggleImageZoom = () => {
    setImageZoomed((prev) => !prev);
  };

  const handleImageMouseDown = (event) => {
    if (!imageZoomed) return;
    const container = imageContainerRef.current;
    if (!container) return;
    event.preventDefault();
    setIsPanning(true);
    panStartRef.current = {
      x: event.clientX,
      y: event.clientY,
      scrollLeft: container.scrollLeft,
      scrollTop: container.scrollTop,
    };
  };

  const handleImageClick = () => {
    if (suppressClickRef.current) {
      suppressClickRef.current = false;
      return;
    }
    handleToggleImageZoom();
  };

  const handleClose = () => {
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
    }
    setPreviewUrl(null);
    setPreviewType(null);
    setPreviewError(null);
    setPreviewLoading(false);
    setPosition(null);
    setSize(null);
    setDragging(false);
    setDragStart(null);
    onClose?.();
  };

  const handleStartDrag = (event) => {
    const rect = paperRef.current?.getBoundingClientRect();
    if (!rect) return;
    setPosition((prev) => prev || { x: rect.left, y: rect.top });
    setDragStart({
      x: event.clientX,
      y: event.clientY,
      left: rect.left,
      top: rect.top,
    });
    setDragging(true);
  };

  useEffect(() => {
    if (!dragging || !dragStart) return;

    const handleMove = (event) => {
      const nextLeft = dragStart.left + (event.clientX - dragStart.x);
      const nextTop = dragStart.top + (event.clientY - dragStart.y);
      setPosition({
        x: Math.max(0, nextLeft),
        y: Math.max(0, nextTop),
      });
    };

    const handleUp = () => {
      setDragging(false);
      setDragStart(null);
    };

    window.addEventListener('mousemove', handleMove);
    window.addEventListener('mouseup', handleUp);
    return () => {
      window.removeEventListener('mousemove', handleMove);
      window.removeEventListener('mouseup', handleUp);
    };
  }, [dragging, dragStart]);

  useEffect(() => {
    if (!isPanning) return;

    const handleMove = (event) => {
      const container = imageContainerRef.current;
      const start = panStartRef.current;
      if (!container || !start) return;
      const dx = event.clientX - start.x;
      const dy = event.clientY - start.y;
      if (Math.abs(dx) + Math.abs(dy) > 3) {
        suppressClickRef.current = true;
      }
      container.scrollLeft = start.scrollLeft - dx;
      container.scrollTop = start.scrollTop - dy;
    };

    const handleUp = () => {
      setIsPanning(false);
      panStartRef.current = null;
    };

    window.addEventListener('mousemove', handleMove);
    window.addEventListener('mouseup', handleUp);
    return () => {
      window.removeEventListener('mousemove', handleMove);
      window.removeEventListener('mouseup', handleUp);
    };
  }, [isPanning]);

  useEffect(() => {
    const handleResizeEnd = () => {
      const rect = paperRef.current?.getBoundingClientRect();
      if (!rect) return;
      setSize({ width: rect.width, height: rect.height });
      if (!position) {
        setPosition({ x: rect.left, y: rect.top });
      }
    };

    window.addEventListener('mouseup', handleResizeEnd);
    return () => {
      window.removeEventListener('mouseup', handleResizeEnd);
    };
  }, [position]);

  const stopDrag = (event) => event.stopPropagation();

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      maxWidth={false}
      fullWidth={false}
      PaperProps={{
        ref: paperRef,
        sx: {
          display: 'flex',
          flexDirection: 'column',
          resize: 'both',
          overflow: 'hidden',
          minWidth: 480,
          minHeight: 320,
          width: size?.width ? `${size.width}px` : '70vw',
          height: size?.height ? `${size.height}px` : '70vh',
          maxWidth: '90vw',
          maxHeight: '90vh',
          position: 'fixed',
          left: position?.x ?? '50%',
          top: position?.y ?? '50%',
          transform: position ? 'none' : 'translate(-50%, -50%)',
        },
      }}
    >
      <DialogTitle
        id="attachment-viewer-title"
        onMouseDown={handleStartDrag}
        sx={{
          cursor: 'move',
          userSelect: 'none',
          pr: 1,
          py: 2,
          display: 'flex',
          alignItems: 'center',
        }}
      >
        <Box display="flex" alignItems="center" justifyContent="space-between" gap={2} sx={{ width: '100%' }}>
          <Box sx={{ flex: 1, minWidth: 0 }}>
            {typeof title === 'string' || typeof title === 'number'
              ? title || t('common.view')
              : title || t('common.view')}
          </Box>
          <Box display="flex" alignItems="center" gap={0.5}>
            <IconButton
              size="small"
              onMouseDown={stopDrag}
              onClick={handleClose}
              aria-label={t('common.close') || 'Close'}
            >
              <CloseIcon fontSize="small" />
            </IconButton>
          </Box>
        </Box>
      </DialogTitle>
      <DialogContent
        dividers
        sx={{
          p: 0,
          flex: 1,
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        {previewLoading ? (
          <Box sx={{ p: 3, display: 'flex', alignItems: 'center', gap: 1 }}>
            <KnightRiderLoader size={16} />
            <Typography variant="body2">{t('common.loading')}</Typography>
          </Box>
        ) : previewError ? (
          <Box sx={{ p: 3 }}>
            <Typography variant="body2" color="error">
              {t('common.downloadFailed')}
            </Typography>
          </Box>
        ) : previewUrl ? (
          previewType && previewType.startsWith('image/') ? (
            <Box
              ref={imageContainerRef}
              sx={{
                width: '100%',
                height: '100%',
                overflow: 'auto',
                bgcolor: 'background.default',
                display: 'flex',
                alignItems: imageZoomed ? 'flex-start' : 'center',
                justifyContent: imageZoomed ? 'flex-start' : 'center',
                p: imageZoomed ? 0 : 2,
                cursor: imageZoomed ? (isPanning ? 'grabbing' : 'grab') : 'default',
              }}
            >
              <Box
                component="img"
                alt={t('common.view')}
                src={previewUrl}
                onClick={handleImageClick}
                onMouseDown={handleImageMouseDown}
                sx={{
                  display: 'block',
                  cursor: imageZoomed ? (isPanning ? 'grabbing' : 'grab') : 'zoom-in',
                  maxWidth: imageZoomed ? 'none' : '100%',
                  maxHeight: imageZoomed ? 'none' : '100%',
                }}
              />
            </Box>
          ) : previewType === 'application/pdf' ? (
            <Box
              component="iframe"
              title={t('common.view')}
              src={previewUrl}
              sx={{
                border: 0,
                width: '100%',
                height: '100%',
              }}
            />
          ) : (
            <Box sx={{ p: 3 }}>
              <Typography variant="body2" color="text.secondary">
                {t('reports.previewUnavailable')}
              </Typography>
            </Box>
          )
        ) : null}
      </DialogContent>
      <DialogActions>
        <Button onClick={handleClose}>{t('common.close')}</Button>
        {showDownload && onDownload ? (
          <Button variant="contained" onClick={onDownload}>
            {t('common.download')}
          </Button>
        ) : null}
      </DialogActions>
    </Dialog>
  );
};

export default AttachmentViewerDialog;
