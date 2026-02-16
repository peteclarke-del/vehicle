/**
 * useReceiptOcr - multi-image receipt upload with smart OCR processing.
 *
 * Flow: attach receipt(s) → upload → auto-scan OCR → callback fills form.
 *
 * Supports:
 * - Multiple photo captures and gallery picks
 * - Sequential upload with status tracking per file
 * - Automatic OCR scan after upload completes
 * - Manual "Scan All" for re-scanning
 * - OCR data returned to parent for form auto-fill
 * - Backward compatible with single-receipt entity fields
 */

import {useState, useCallback, useRef} from 'react';
import {Alert} from 'react-native';
import {
  launchCamera,
  launchImageLibrary,
  Asset,
} from 'react-native-image-picker';
import {AxiosInstance} from 'axios';

export interface ReceiptAttachment {
  id: number | null;
  name: string;
  uri: string;
  status: 'uploading' | 'uploaded' | 'error';
  error?: string;
}

export interface OcrMeta {
  vendor?: string;
  vendorName?: string;
  category?: string;
  confidence?: number;
  pageCount?: number;
}

export interface OcrResult {
  _meta?: OcrMeta;
  [key: string]: any;
}

interface UseReceiptOcrOptions {
  api: AxiosInstance;
  isOnline: boolean;
  vehicleId?: number | null;
  entityType?: string; // fuel, part, consumable, service, mot
  entityId?: number | null; // for editing — links attachment to entity at upload time
  onOcrComplete?: (
    primaryAttachmentId: number,
    ocrData: OcrResult,
    allAttachmentIds: number[],
  ) => void;
}

interface UseReceiptOcrResult {
  attachments: ReceiptAttachment[];
  uploading: boolean;
  scanning: boolean;
  scanned: boolean;
  ocrResult: OcrResult | null;
  /** Legacy single-receipt ID (first uploaded attachment) */
  receiptAttachmentId: number | null;
  handleTakePhoto: () => Promise<void>;
  handleChooseFromGallery: () => Promise<void>;
  handleScanAll: () => Promise<void>;
  removeAttachment: (index: number) => void;
  clearAll: () => void;
  /** Set existing receipt attachment ID (for editing) */
  setExistingReceipt: (id: number | null) => void;
}

export function useReceiptOcr({
  api,
  isOnline,
  vehicleId,
  entityType = 'fuel',
  entityId,
  onOcrComplete,
}: UseReceiptOcrOptions): UseReceiptOcrResult {
  const [attachments, setAttachments] = useState<ReceiptAttachment[]>([]);
  const [uploading, setUploading] = useState(false);
  const [scanning, setScanning] = useState(false);
  const [scanned, setScanned] = useState(false);
  const [ocrResult, setOcrResult] = useState<OcrResult | null>(null);
  const [existingReceiptId, setExistingReceiptId] = useState<number | null>(
    null,
  );

  // Stable ref for onOcrComplete to avoid stale closures
  const onOcrCompleteRef = useRef(onOcrComplete);
  onOcrCompleteRef.current = onOcrComplete;

  // Compute primary receipt ID
  const receiptAttachmentId =
    attachments.find(a => a.status === 'uploaded' && a.id !== null)?.id ??
    existingReceiptId;

  /**
   * Upload a single asset to the backend. Returns the attachment ID or null.
   */
  const uploadSingleAsset = useCallback(
    async (asset: Asset, index: number): Promise<number | null> => {
      if (!isOnline) {
        setAttachments(prev => {
          const updated = [...prev];
          updated[index] = {
            ...updated[index],
            status: 'error',
            error: 'Offline',
          };
          return updated;
        });
        return null;
      }

      try {
        const formData = new FormData();
        formData.append('file', {
          uri: asset.uri,
          type: asset.type || 'image/jpeg',
          name: asset.fileName || `receipt_${Date.now()}.jpg`,
        } as any);

        formData.append('entityType', entityType);
        formData.append('category', 'receipt');
        formData.append('description', 'Receipt');
        if (entityId) {
          formData.append('entityId', entityId.toString());
        }
        if (vehicleId) {
          formData.append('vehicleId', vehicleId.toString());
        }

        console.log('[OCR] Uploading receipt image...');
        const response = await api.post('/attachments', formData, {
          headers: {'Content-Type': 'multipart/form-data'},
        });

        const attachmentId = response.data.id;
        console.log('[OCR] Upload success, attachmentId:', attachmentId);

        setAttachments(prev => {
          const updated = [...prev];
          updated[index] = {
            ...updated[index],
            id: attachmentId,
            status: 'uploaded',
          };
          return updated;
        });

        return attachmentId;
      } catch (error: any) {
        console.error('[OCR] Upload error:', error?.response?.data || error?.message);
        setAttachments(prev => {
          const updated = [...prev];
          updated[index] = {
            ...updated[index],
            status: 'error',
            error: error?.response?.data?.error || error.message || 'Upload failed',
          };
          return updated;
        });
        return null;
      }
    },
    [api, isOnline, vehicleId, entityType, entityId],
  );

  /**
   * Run OCR scan on the given attachment IDs.
   * This is the core scan function — takes IDs as a parameter to avoid stale closures.
   */
  const scanAttachmentIds = useCallback(
    async (ids: number[]) => {
      if (ids.length === 0) {
        console.warn('[OCR] No attachment IDs to scan');
        return;
      }
      if (!isOnline) {
        Alert.alert('Offline', 'OCR scanning requires an internet connection');
        return;
      }

      console.log('[OCR] Scanning', ids.length, 'attachment(s) for type:', entityType);
      setScanning(true);

      try {
        let ocrData: OcrResult;

        if (ids.length === 1) {
          const response = await api.get(`/attachments/${ids[0]}/ocr`, {
            params: {type: entityType},
          });
          ocrData = response.data;
        } else {
          const response = await api.post('/attachments/ocr/multi', {
            attachmentIds: ids,
            type: entityType,
          });
          ocrData = response.data;
        }

        console.log('[OCR] Raw response:', JSON.stringify(ocrData, null, 2));

        setOcrResult(ocrData);
        setScanned(true);

        if (onOcrCompleteRef.current) {
          onOcrCompleteRef.current(ids[0], ocrData, ids);
        }
      } catch (error: any) {
        console.warn('[OCR] Scanning failed:', error?.response?.data || error?.message || error);
        setOcrResult(null);
        setScanned(true);
        // Still call callback so attachments get associated
        if (onOcrCompleteRef.current) {
          onOcrCompleteRef.current(ids[0], {}, ids);
        }
      } finally {
        setScanning(false);
      }
    },
    [isOnline, api, entityType],
  );

  /**
   * Process assets: upload all, then auto-scan.
   * This is the main flow: attach → upload → scan → fill form.
   */
  const processAssets = useCallback(
    async (assets: Asset[]) => {
      if (!assets.length) return;

      setUploading(true);
      setScanned(false);
      setOcrResult(null);

      const startIndex = attachments.length;
      const newAttachments: ReceiptAttachment[] = assets.map(asset => ({
        id: null,
        name: asset.fileName || 'receipt.jpg',
        uri: asset.uri || '',
        status: 'uploading' as const,
      }));

      setAttachments(prev => [...prev, ...newAttachments]);

      // Upload sequentially, collect successful IDs
      const uploadedIds: number[] = [];
      for (let i = 0; i < assets.length; i++) {
        const id = await uploadSingleAsset(assets[i], startIndex + i);
        if (id !== null) {
          uploadedIds.push(id);
        }
      }

      setUploading(false);

      // Auto-scan immediately after upload completes
      if (uploadedIds.length > 0) {
        console.log('[OCR] Upload complete, auto-scanning', uploadedIds.length, 'image(s)...');
        await scanAttachmentIds(uploadedIds);
      } else {
        console.warn('[OCR] All uploads failed, skipping scan');
      }
    },
    [attachments.length, uploadSingleAsset, scanAttachmentIds],
  );

  /**
   * Take photo with camera.
   */
  const handleTakePhoto = useCallback(async () => {
    try {
      const result = await launchCamera({
        mediaType: 'photo',
        quality: 0.8,
        saveToPhotos: false,
      });

      if (result.assets && result.assets.length > 0) {
        await processAssets(result.assets);
      }
    } catch (error) {
      console.error('[OCR] Camera error:', error);
      Alert.alert('Error', 'Failed to take photo');
    }
  }, [processAssets]);

  /**
   * Pick from gallery (supports multiple selection).
   */
  const handleChooseFromGallery = useCallback(async () => {
    try {
      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
        selectionLimit: 0, // 0 = unlimited
      });

      if (result.assets && result.assets.length > 0) {
        await processAssets(result.assets);
      }
    } catch (error) {
      console.error('[OCR] Gallery error:', error);
      Alert.alert('Error', 'Failed to select images');
    }
  }, [processAssets]);

  /**
   * Manual "Scan All" — reads IDs from current state for re-scanning.
   */
  const handleScanAll = useCallback(async () => {
    const uploadedIds = attachments
      .filter(a => a.status === 'uploaded' && a.id !== null)
      .map(a => a.id as number);

    await scanAttachmentIds(uploadedIds);
  }, [attachments, scanAttachmentIds]);

  /**
   * Remove an attachment (and delete from server if uploaded).
   */
  const removeAttachment = useCallback(
    (index: number) => {
      const attachment = attachments[index];
      if (attachment?.id && isOnline) {
        api.delete(`/attachments/${attachment.id}`).catch(() => {});
      }
      setAttachments(prev => prev.filter((_, i) => i !== index));
      if (attachments.length <= 1) {
        setScanned(false);
        setOcrResult(null);
      }
    },
    [attachments, api, isOnline],
  );

  /**
   * Clear all attachments.
   */
  const clearAll = useCallback(() => {
    attachments.forEach(a => {
      if (a.id && isOnline) {
        api.delete(`/attachments/${a.id}`).catch(() => {});
      }
    });
    setAttachments([]);
    setScanned(false);
    setOcrResult(null);
    setExistingReceiptId(null);
  }, [attachments, api, isOnline]);

  /**
   * Set existing receipt for edit mode.
   */
  const setExistingReceipt = useCallback((id: number | null) => {
    setExistingReceiptId(id);
  }, []);

  return {
    attachments,
    uploading,
    scanning,
    scanned,
    ocrResult,
    receiptAttachmentId,
    handleTakePhoto,
    handleChooseFromGallery,
    handleScanAll,
    removeAttachment,
    clearAll,
    setExistingReceipt,
  };
}
