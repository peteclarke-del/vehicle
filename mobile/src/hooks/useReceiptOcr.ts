/**
 * useReceiptOcr - multi-image receipt upload with smart OCR processing.
 *
 * Supports:
 * - Multiple photo captures and gallery picks
 * - Sequential upload with status tracking per file
 * - Single-image auto-scan or manual "Scan All" for multiple
 * - OCR data returned to parent for form auto-fill
 * - Backward compatible with single-receipt entity fields
 */

import {useState, useCallback, useRef, useEffect} from 'react';
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
  const autoScanTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Compute primary receipt ID
  const receiptAttachmentId =
    attachments.find(a => a.status === 'uploaded' && a.id !== null)?.id ??
    existingReceiptId;

  /**
   * Upload a single asset to the backend.
   */
  const uploadAsset = useCallback(
    async (asset: Asset, index: number) => {
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
        return;
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

        const response = await api.post('/attachments', formData, {
          headers: {'Content-Type': 'multipart/form-data'},
        });

        setAttachments(prev => {
          const updated = [...prev];
          updated[index] = {
            ...updated[index],
            id: response.data.id,
            status: 'uploaded',
          };
          return updated;
        });
      } catch (error: any) {
        console.error('Upload error:', error);
        setAttachments(prev => {
          const updated = [...prev];
          updated[index] = {
            ...updated[index],
            status: 'error',
            error: error.message || 'Upload failed',
          };
          return updated;
        });
      }
    },
    [api, isOnline, vehicleId, entityType, entityId],
  );

  /**
   * Process multiple assets (from camera or gallery).
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

      // Upload sequentially
      for (let i = 0; i < assets.length; i++) {
        await uploadAsset(assets[i], startIndex + i);
      }

      setUploading(false);
    },
    [attachments.length, uploadAsset],
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
      console.error('Camera error:', error);
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
      console.error('Gallery error:', error);
      Alert.alert('Error', 'Failed to select images');
    }
  }, [processAssets]);

  /**
   * Run OCR on all uploaded attachments.
   */
  const handleScanAll = useCallback(async () => {
    const uploadedIds = attachments
      .filter(a => a.status === 'uploaded' && a.id !== null)
      .map(a => a.id as number);

    if (uploadedIds.length === 0) return;
    if (!isOnline) {
      Alert.alert('Offline', 'OCR scanning requires an internet connection');
      return;
    }

    setScanning(true);
    try {
      let ocrData: OcrResult;

      if (uploadedIds.length === 1) {
        const response = await api.get(`/attachments/${uploadedIds[0]}/ocr`, {
          params: {type: entityType},
        });
        ocrData = response.data;
      } else {
        const response = await api.post('/attachments/ocr/multi', {
          attachmentIds: uploadedIds,
          type: entityType,
        });
        ocrData = response.data;
      }

      console.log('[OCR] Raw response for type=' + entityType + ':', JSON.stringify(ocrData, null, 2));

      setOcrResult(ocrData);
      setScanned(true);

      if (onOcrComplete) {
        onOcrComplete(uploadedIds[0], ocrData, uploadedIds);
      }
    } catch (error: any) {
      console.warn('[OCR] Scanning failed:', error?.response?.data || error?.message || error);
      setScanned(true);
      // Still call callback so attachments get associated
      if (onOcrComplete) {
        onOcrComplete(uploadedIds[0], {}, uploadedIds);
      }
    } finally {
      setScanning(false);
    }
  }, [attachments, isOnline, api, entityType, onOcrComplete]);

  /**
   * Auto-scan when a single image finishes uploading.
   */
  useEffect(() => {
    const uploaded = attachments.filter(
      a => a.status === 'uploaded' && a.id !== null,
    );
    if (uploaded.length === 1 && !scanned && !scanning && !uploading) {
      if (autoScanTimer.current) clearTimeout(autoScanTimer.current);
      autoScanTimer.current = setTimeout(() => handleScanAll(), 600);
      return () => {
        if (autoScanTimer.current) clearTimeout(autoScanTimer.current);
      };
    }
  }, [attachments, scanned, scanning, uploading, handleScanAll]);

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
    // Delete uploaded attachments from server
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
