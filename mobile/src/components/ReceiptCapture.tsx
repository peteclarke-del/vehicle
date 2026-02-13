/**
 * ReceiptCapture - reusable receipt upload + OCR section for form screens.
 *
 * Renders camera/gallery buttons, attachment thumbnails with status indicators,
 * OCR scan button, and confidence/vendor info display.
 */

import React, {useState, useEffect, useRef} from 'react';
import {View, Image, StyleSheet, ScrollView} from 'react-native';
import {
  Button,
  Card,
  Text,
  IconButton,
  Chip,
  ProgressBar,
  ActivityIndicator,
  useTheme,
} from 'react-native-paper';
import {useTranslation} from 'react-i18next';
import {ReceiptAttachment, OcrResult} from '../hooks/useReceiptOcr';

interface ReceiptCaptureProps {
  attachments: ReceiptAttachment[];
  uploading: boolean;
  scanning: boolean;
  scanned: boolean;
  ocrResult: OcrResult | null;
  onTakePhoto: () => void;
  onChooseGallery: () => void;
  onScanAll: () => void;
  onRemoveAttachment: (index: number) => void;
  onClearAll: () => void;
  /** Optional existing receipt preview URI */
  existingReceiptUri?: string | null;
}

/**
 * ScanningCard — shows a prominent "processing" indicator with elapsed time
 * so the user knows the backend OCR is working (can take 10-30s).
 */
const ScanningCard: React.FC = () => {
  const {t} = useTranslation();
  const theme = useTheme();
  const [elapsed, setElapsed] = useState(0);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    timer.current = setInterval(() => setElapsed(s => s + 1), 1000);
    return () => {
      if (timer.current) clearInterval(timer.current);
    };
  }, []);

  return (
    <Card style={[styles.scanningCard, {backgroundColor: theme.colors.secondaryContainer}]}>
      <Card.Content>
        <View style={styles.scanningRow}>
          <ActivityIndicator size="small" color={theme.colors.primary} />
          <View style={styles.scanningTextCol}>
            <Text variant="bodyMedium" style={{fontWeight: '600', color: theme.colors.onSecondaryContainer}}>
              {t('ocr.scanningReceipt', 'Reading receipt with OCR...')}
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSecondaryContainer, opacity: 0.7, marginTop: 2}}>
              {t('ocr.scanningHint', 'This may take 10-30 seconds')}
            </Text>
          </View>
          <Text variant="labelLarge" style={{color: theme.colors.onSecondaryContainer, opacity: 0.5}}>
            {elapsed}s
          </Text>
        </View>
        <ProgressBar indeterminate style={[styles.progressBar, {marginTop: 8}]} />
      </Card.Content>
    </Card>
  );
};

const ReceiptCapture: React.FC<ReceiptCaptureProps> = ({
  attachments,
  uploading,
  scanning,
  scanned,
  ocrResult,
  onTakePhoto,
  onChooseGallery,
  onScanAll,
  onRemoveAttachment,
  onClearAll,
  existingReceiptUri,
}) => {
  const {t} = useTranslation();
  const theme = useTheme();

  const uploadedCount = attachments.filter(
    a => a.status === 'uploaded' && a.id !== null,
  ).length;
  const hasErrors = attachments.some(a => a.status === 'error');
  const hasAttachments = attachments.length > 0;

  return (
    <View style={styles.container}>
      <Text variant="titleMedium" style={styles.sectionTitle}>
        {t('ocr.uploadReceiptImages', 'Receipt')}
      </Text>

      {/* Camera / Gallery buttons */}
      <View style={styles.buttonRow}>
        <Button
          mode="outlined"
          icon={hasAttachments ? 'plus' : 'camera'}
          onPress={onTakePhoto}
          style={styles.actionButton}
          compact>
          {hasAttachments
            ? t('ocr.addMoreImages', 'Add more')
            : t('common.camera', 'Camera')}
        </Button>
        <Button
          mode="outlined"
          icon="image-multiple"
          onPress={onChooseGallery}
          style={styles.actionButton}
          compact>
          {t('common.gallery', 'Gallery')}
        </Button>
      </View>

      {/* Upload progress */}
      {uploading && (
        <View style={styles.progressContainer}>
          <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant}}>
            {t('ocr.uploadingImages', 'Uploading images...')}
          </Text>
          <ProgressBar indeterminate style={styles.progressBar} />
        </View>
      )}

      {/* Attachment thumbnails */}
      {hasAttachments && (
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          style={styles.thumbnailScroll}>
          {attachments.map((attachment, index) => (
            <Card key={`${attachment.name}-${index}`} style={styles.thumbnailCard}>
              <View style={styles.thumbnailContainer}>
                {attachment.uri ? (
                  <Image
                    source={{uri: attachment.uri}}
                    style={styles.thumbnailImage}
                    resizeMode="cover"
                  />
                ) : (
                  <View
                    style={[
                      styles.thumbnailPlaceholder,
                      {backgroundColor: theme.colors.surfaceVariant},
                    ]}>
                    <Text variant="bodySmall">📄</Text>
                  </View>
                )}

                {/* Status indicator */}
                <View style={styles.statusBadge}>
                  {attachment.status === 'uploading' && (
                    <Chip
                      compact
                      textStyle={styles.chipText}
                      style={[styles.statusChip, {backgroundColor: theme.colors.primaryContainer}]}>
                      ⏳
                    </Chip>
                  )}
                  {attachment.status === 'uploaded' && (
                    <Chip
                      compact
                      textStyle={styles.chipText}
                      style={[styles.statusChip, {backgroundColor: '#e8f5e9'}]}>
                      ✓
                    </Chip>
                  )}
                  {attachment.status === 'error' && (
                    <Chip
                      compact
                      textStyle={styles.chipText}
                      style={[styles.statusChip, {backgroundColor: '#ffebee'}]}>
                      ✗
                    </Chip>
                  )}
                </View>

                {/* Remove button */}
                <IconButton
                  icon="close-circle"
                  size={18}
                  style={styles.removeButton}
                  onPress={() => onRemoveAttachment(index)}
                />
              </View>
            </Card>
          ))}
        </ScrollView>
      )}

      {/* Error banner */}
      {hasErrors && (
        <Card style={[styles.alertCard, {backgroundColor: '#fff3e0'}]}>
          <Card.Content>
            <Text variant="bodySmall" style={{color: '#e65100'}}>
              {t(
                'ocr.someUploadsFailed',
                'Some files failed to upload. You can try again or continue with the uploaded ones.',
              )}
            </Text>
          </Card.Content>
        </Card>
      )}

      {/* Scan button (for multi-image or manual re-scan) */}
      {uploadedCount > 1 && !scanning && (
        <Button
          mode="contained-tonal"
          icon="text-recognition"
          onPress={onScanAll}
          style={styles.scanButton}>
          {t('ocr.scanAll', 'Scan All ({{count}})', {count: uploadedCount})}
        </Button>
      )}

      {/* Scanning indicator — prominent card with elapsed timer */}
      {scanning && (
        <ScanningCard />
      )}

      {/* OCR result display */}
      {scanned && ocrResult?._meta && (
        <Card style={[styles.alertCard, {backgroundColor: theme.colors.primaryContainer}]}>
          <Card.Content>
            <View style={styles.ocrResultRow}>
              <Text variant="bodyMedium" style={{fontWeight: '600'}}>
                {ocrResult._meta.vendorName
                  ? t('ocr.vendorDetected', 'Detected: {{vendor}}', {
                      vendor: ocrResult._meta.vendorName,
                    })
                  : t('ocr.genericReceipt', 'Generic receipt')}
              </Text>
              {ocrResult._meta.pageCount && ocrResult._meta.pageCount > 1 && (
                <Chip compact style={styles.metaChip}>
                  {t('ocr.pagesProcessed', '{{count}} pages', {
                    count: ocrResult._meta.pageCount,
                  })}
                </Chip>
              )}
            </View>
            {ocrResult._meta.confidence !== undefined && (
              <Text
                variant="bodySmall"
                style={{color: theme.colors.onPrimaryContainer, marginTop: 4}}>
                {t('ocr.confidence', 'Confidence: {{pct}}%', {
                  pct: Math.round((ocrResult._meta.confidence || 0) * 100),
                })}
              </Text>
            )}
            {/* Extracted fields debug display */}
            <View style={styles.extractedFields}>
              {Object.entries(ocrResult)
                .filter(([key]) => key !== '_meta')
                .filter(([, value]) => value !== null && value !== undefined && value !== '')
                .map(([key, value]) => (
                  <View key={key} style={styles.fieldRow}>
                    <Text variant="labelSmall" style={{color: theme.colors.onPrimaryContainer, opacity: 0.7, width: 100}}>
                      {key}:
                    </Text>
                    <Text variant="bodySmall" style={{color: theme.colors.onPrimaryContainer, flex: 1}} numberOfLines={2}>
                      {String(value)}
                    </Text>
                  </View>
                ))}
            </View>
          </Card.Content>
        </Card>
      )}

      {/* Existing receipt image (edit mode) */}
      {existingReceiptUri && !hasAttachments && (
        <Card style={styles.existingPreview}>
          <Card.Content>
            <View style={styles.existingImageContainer}>
              <Image
                source={{uri: existingReceiptUri}}
                style={styles.existingImage}
                resizeMode="contain"
              />
              <IconButton
                icon="close"
                style={styles.existingRemoveButton}
                onPress={onClearAll}
              />
            </View>
          </Card.Content>
        </Card>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginTop: 8,
    marginBottom: 8,
  },
  sectionTitle: {
    marginBottom: 12,
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  actionButton: {
    flex: 1,
  },
  progressContainer: {
    marginBottom: 12,
  },
  progressBar: {
    marginTop: 4,
    borderRadius: 4,
  },
  thumbnailScroll: {
    marginBottom: 12,
  },
  thumbnailCard: {
    width: 80,
    height: 80,
    marginRight: 8,
    overflow: 'hidden',
  },
  thumbnailContainer: {
    width: 80,
    height: 80,
    position: 'relative',
  },
  thumbnailImage: {
    width: 80,
    height: 80,
  },
  thumbnailPlaceholder: {
    width: 80,
    height: 80,
    justifyContent: 'center',
    alignItems: 'center',
  },
  statusBadge: {
    position: 'absolute',
    bottom: 2,
    left: 2,
  },
  statusChip: {
    height: 20,
  },
  chipText: {
    fontSize: 10,
    lineHeight: 14,
  },
  removeButton: {
    position: 'absolute',
    top: -6,
    right: -6,
    margin: 0,
  },
  scanButton: {
    marginBottom: 12,
  },
  scanningCard: {
    marginBottom: 12,
    elevation: 2,
  },
  scanningRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  scanningTextCol: {
    flex: 1,
  },
  alertCard: {
    marginBottom: 12,
  },
  ocrResultRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    flexWrap: 'wrap',
  },
  metaChip: {
    marginLeft: 8,
  },
  extractedFields: {
    marginTop: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(0,0,0,0.12)',
    paddingTop: 8,
  },
  fieldRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingVertical: 2,
  },
  existingPreview: {
    marginBottom: 16,
  },
  existingImageContainer: {
    position: 'relative',
  },
  existingImage: {
    width: '100%',
    height: 200,
    borderRadius: 8,
  },
  existingRemoveButton: {
    position: 'absolute',
    top: -8,
    right: -8,
    backgroundColor: 'white',
  },
});

export default ReceiptCapture;
