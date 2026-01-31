import React, { useState } from 'react';
import { IconButton, Tooltip, Dialog, DialogTitle, DialogContent, List, ListItem, ListItemButton, ListItemText, DialogActions, Button } from '@mui/material';
import { Visibility as ViewIcon } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import AttachmentViewerDialog from './AttachmentViewerDialog';

/**
 * Component to display a view icon for attachments in table rows
 * Handles single attachment (direct view) or multiple attachments (selection dialog)
 */
export default function ViewAttachmentIconButton({ attachments = [], record }) {
  const { t } = useTranslation();
  const [selectionOpen, setSelectionOpen] = useState(false);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [selectedAttachmentId, setSelectedAttachmentId] = useState(null);

  // Convert single receiptAttachmentId to array format if needed
  const attachmentList = React.useMemo(() => {
    if (attachments && attachments.length > 0) {
      return attachments;
    }
    // Fallback: check if record has receiptAttachmentId
    if (record?.receiptAttachmentId) {
      return [{ id: record.receiptAttachmentId, name: t('attachment.receipt') || 'Receipt' }];
    }
    return [];
  }, [attachments, record, t]);

  if (attachmentList.length === 0) {
    return null;
  }

  const handleClick = () => {
    if (attachmentList.length === 1) {
      // Direct view for single attachment
      setSelectedAttachmentId(attachmentList[0].id);
      setViewerOpen(true);
    } else {
      // Show selection dialog for multiple attachments
      setSelectionOpen(true);
    }
  };

  const handleSelectAttachment = (attachmentId) => {
    setSelectedAttachmentId(attachmentId);
    setSelectionOpen(false);
    setViewerOpen(true);
  };

  return (
    <>
      <Tooltip title={t('attachment.view')}>
        <IconButton size="small" onClick={handleClick}>
          <ViewIcon />
        </IconButton>
      </Tooltip>

      {/* Selection Dialog for multiple attachments */}
      <Dialog open={selectionOpen} onClose={() => setSelectionOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>{t('attachment.selectToView') || 'Select Attachment to View'}</DialogTitle>
        <DialogContent>
          <List>
            {attachmentList.map((attachment) => (
              <ListItem key={attachment.id} disablePadding>
                <ListItemButton onClick={() => handleSelectAttachment(attachment.id)}>
                  <ListItemText primary={attachment.name || `${t('attachment.attachment')} #${attachment.id}`} />
                </ListItemButton>
              </ListItem>
            ))}
          </List>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setSelectionOpen(false)}>{t('common.cancel')}</Button>
        </DialogActions>
      </Dialog>

      {/* Attachment Viewer Dialog */}
      <AttachmentViewerDialog
        open={viewerOpen}
        onClose={() => setViewerOpen(false)}
        attachmentId={selectedAttachmentId}
        title={t('attachment.view')}
      />
    </>
  );
}
