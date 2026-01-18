# Attachment Integration Guide

## Overview
The AttachmentUpload component provides a reusable file upload interface that can be integrated into any dialog or page to attach scans, receipts, and documents to cost records.

## Component Features
- Multi-file upload support
- File type validation (images, PDFs, documents)
- 10MB file size limit
- Visual file type icons
- Download functionality
- Delete with confirmation
- File metadata display (name, size, date)
- Works with any entity type

## Supported File Types
- **Images**: JPEG, PNG, GIF, WebP
- **Documents**: PDF, DOC, DOCX, XLS, XLSX

## Integration Examples

### Example 1: Adding Attachments to PartDialog

```javascript
import React, { useState, useEffect } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Grid,
  Divider,
  Box
} from '@mui/material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../contexts/AuthContext';
import AttachmentUpload from './AttachmentUpload';

export default function PartDialog({ open, onClose, part, vehicleId }) {
  const { t } = useTranslation();
  const { api } = useAuth();
  const [formData, setFormData] = useState({
    description: '',
    partNumber: '',
    cost: '',
    // ... other fields
  });

  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        {part ? t('parts.editPart') : t('parts.addPart')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            {/* Regular form fields */}
            <Grid item xs={12}>
              <TextField
                fullWidth
                required
                name="description"
                label={t('parts.description')}
                value={formData.description}
                onChange={handleChange}
              />
            </Grid>
            
            {/* More fields... */}
            
            {/* Attachments Section */}
            {part?.id && (
              <>
                <Grid item xs={12}>
                  <Box mt={2} mb={1}>
                    <Divider />
                  </Box>
                </Grid>
                <Grid item xs={12}>
                  <AttachmentUpload
                    entityType="part"
                    entityId={part.id}
                    onChange={(attachments) => console.log('Attachments updated', attachments)}
                  />
                </Grid>
              </>
            )}
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => onClose(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" variant="contained">
            {t('common.save')}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
```

### Example 2: Adding Attachments to FuelRecordDialog

```javascript
import AttachmentUpload from './AttachmentUpload';

export default function FuelRecordDialog({ open, onClose, fuelRecord, vehicleId }) {
  // ... component logic
  
  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            {/* Regular fields */}
            <Grid item xs={12} sm={6}>
              <TextField
                type="date"
                name="date"
                label={t('fuel.date')}
                value={formData.date}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
                fullWidth
                required
              />
            </Grid>
            
            <Grid item xs={12} sm={6}>
              <TextField
                type="number"
                name="cost"
                label={t('fuel.cost')}
                value={formData.cost}
                onChange={handleChange}
                fullWidth
                required
              />
            </Grid>
            
            {/* Add attachments section for receipts */}
            {fuelRecord?.id && (
              <>
                <Grid item xs={12}>
                  <Divider sx={{ my: 2 }} />
                </Grid>
                <Grid item xs={12}>
                  <AttachmentUpload
                    entityType="fuelRecord"
                    entityId={fuelRecord.id}
                  />
                </Grid>
              </>
            )}
          </Grid>
        </DialogContent>
        {/* ... actions */}
      </form>
    </Dialog>
  );
}
```

### Example 3: Adding Attachments to ServiceDialog

```javascript
import AttachmentUpload from './AttachmentUpload';

export default function ServiceDialog({ open, serviceRecord, vehicleId, onClose }) {
  // ... component logic
  
  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <DialogTitle>
        {serviceRecord ? t('service.editService') : t('service.addService')}
      </DialogTitle>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            {/* Service fields */}
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="date"
                name="serviceDate"
                label={t('service.serviceDate')}
                value={formData.serviceDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                select
                required
                name="serviceType"
                label={t('service.serviceType')}
                value={formData.serviceType}
                onChange={handleChange}
              >
                <MenuItem value="Full Service">{t('service.fullService')}</MenuItem>
                <MenuItem value="Oil Change">{t('service.oilChange')}</MenuItem>
              </TextField>
            </Grid>
            
            {/* Attachments for invoices and work orders */}
            {serviceRecord?.id && (
              <>
                <Grid item xs={12}>
                  <Divider sx={{ my: 2 }} />
                </Grid>
                <Grid item xs={12}>
                  <AttachmentUpload
                    entityType="serviceRecord"
                    entityId={serviceRecord.id}
                  />
                </Grid>
              </>
            )}
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => onClose(false)}>{t('common.cancel')}</Button>
          <Button type="submit" variant="contained">{t('common.save')}</Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
```

### Example 4: Adding Attachments to MOT Dialog

```javascript
import AttachmentUpload from './AttachmentUpload';

export default function MotDialog({ open, motRecord, vehicleId, onClose }) {
  return (
    <Dialog open={open} onClose={() => onClose(false)} maxWidth="md" fullWidth>
      <form onSubmit={handleSubmit}>
        <DialogContent>
          <Grid container spacing={2}>
            {/* MOT fields */}
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                required
                type="date"
                name="testDate"
                label={t('mot.testDate')}
                value={formData.testDate}
                onChange={handleChange}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                select
                required
                name="result"
                label={t('mot.result')}
                value={formData.result}
                onChange={handleChange}
              >
                <MenuItem value="Pass">{t('mot.pass')}</MenuItem>
                <MenuItem value="Fail">{t('mot.fail')}</MenuItem>
                <MenuItem value="Advisory">{t('mot.advisory')}</MenuItem>
              </TextField>
            </Grid>
            
            {/* Attachments for certificates and reports */}
            {motRecord?.id && (
              <>
                <Grid item xs={12}>
                  <Divider sx={{ my: 2 }} />
                </Grid>
                <Grid item xs={12}>
                  <AttachmentUpload
                    entityType="motRecord"
                    entityId={motRecord.id}
                  />
                </Grid>
              </>
            )}
          </Grid>
        </DialogContent>
        {/* ... actions */}
      </form>
    </Dialog>
  );
}
```

## Component Props

### AttachmentUpload Props

| Prop | Type | Required | Description |
|------|------|----------|-------------|
| `entityType` | string | Yes | Type of entity (e.g., 'part', 'fuelRecord', 'serviceRecord', 'motRecord', 'insurance', 'consumable') |
| `entityId` | number | Yes | ID of the entity to attach files to |
| `onChange` | function | No | Callback when attachments change, receives array of attachment objects |

## Entity Types

Use these entity type values when integrating AttachmentUpload:

- `part` - For parts and spare parts
- `consumable` - For consumables (tyres, oils, filters)
- `fuelRecord` - For fuel purchase receipts
- `serviceRecord` - For service invoices and work orders
- `motRecord` - For MOT certificates and failure reports
- `insurance` - For insurance policy documents

## Important Notes

### Save Before Attach
The AttachmentUpload component requires an `entityId` to function. Users must save the record first before they can attach files. The component automatically displays a helpful message when no ID is present:

```
"Save the record first to attach files"
```

### Conditional Rendering
Always conditionally render the AttachmentUpload component based on whether the record exists:

```javascript
{record?.id && (
  <AttachmentUpload
    entityType="part"
    entityId={record.id}
  />
)}
```

### Layout Recommendations

1. **Add a Divider**: Separate attachments section from form fields
2. **Full Width**: Use `xs={12}` for the Grid item
3. **Add Margin**: Add `mt={2}` or `my={2}` for spacing

```javascript
<Grid item xs={12}>
  <Box mt={2} mb={1}>
    <Divider />
  </Box>
</Grid>
<Grid item xs={12}>
  <AttachmentUpload entityType="part" entityId={part.id} />
</Grid>
```

## API Integration

The component automatically handles:
- File uploads via `POST /api/attachments`
- File downloads via `GET /api/attachments/{id}`
- File deletions via `DELETE /api/attachments/{id}`
- Listing files via `GET /api/attachments?entityType={type}&entityId={id}`

## Validation

The backend automatically validates:
- File size (maximum 10MB)
- MIME types (images, PDFs, Office documents only)
- User authentication

## File Storage

Files are stored in the backend at `/backend/uploads/` with:
- Unique filenames (slugified original name + unique ID)
- Original name preserved in database
- MIME type and file size tracked

## User Experience

The component provides:
- ✅ Visual file type icons (images, PDFs, documents)
- ✅ Human-readable file sizes (KB/MB)
- ✅ Upload date display
- ✅ One-click download
- ✅ Confirmation dialog for deletion
- ✅ Multi-file upload support
- ✅ Progress indicators during upload
- ✅ Error messages for failed uploads

## Translation Keys Required

Ensure these keys exist in your translation files:

```json
{
  "attachments": {
    "title": "Attachments",
    "upload": "Upload Files",
    "noFiles": "No files attached",
    "deleteConfirm": "Are you sure you want to delete this attachment?",
    "saveFirst": "Save the record first to attach files"
  }
}
```

## Complete Integration Checklist

- [ ] Import AttachmentUpload component
- [ ] Add conditional rendering based on record ID
- [ ] Wrap in Grid item with xs={12}
- [ ] Add Divider for visual separation
- [ ] Specify correct entityType
- [ ] Pass record ID as entityId
- [ ] (Optional) Add onChange callback for custom handling
- [ ] Test file upload, download, and delete
- [ ] Verify translations are present
- [ ] Check mobile responsiveness

## Testing

To test the integration:

1. Create or edit a record
2. Save the record to get an ID
3. Upload a test file (image, PDF, or document)
4. Verify file appears in the list
5. Test download functionality
6. Test delete functionality with confirmation
7. Refresh page and verify files persist
8. Test with multiple files
9. Test file size limit (try >10MB file)
10. Test unsupported file types

## Troubleshooting

**Problem**: "Save the record first" message appears
- **Solution**: The record must be saved and have an ID before attaching files

**Problem**: Upload fails silently
- **Solution**: Check browser console for errors, verify file size <10MB and file type is supported

**Problem**: Files don't appear after upload
- **Solution**: Check that entityType and entityId match exactly, verify API endpoint is working

**Problem**: Download fails
- **Solution**: Check that file exists in backend/uploads directory, verify user has permission

## Future Enhancements

Potential improvements for the attachment system:
- Image preview/thumbnail generation
- File compression for large images
- Cloud storage integration (S3, Azure Blob, etc.)
- Drag-and-drop upload interface
- Bulk file operations
- File search and filtering
- File categories/tags
- Version history for attachments
