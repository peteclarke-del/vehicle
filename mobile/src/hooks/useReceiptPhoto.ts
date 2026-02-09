/**
 * useReceiptPhoto — shared hook for receipt photo capture and upload.
 *
 * Eliminates duplicate handleTakePhoto / handleChooseFromGallery / uploadReceipt
 * code from FuelRecordFormScreen, ServiceRecordFormScreen, and QuickFuelScreen.
 */

import {useState, useCallback} from 'react';
import {Alert} from 'react-native';
import {launchCamera, launchImageLibrary, Asset} from 'react-native-image-picker';
import {AxiosInstance} from 'axios';

interface UseReceiptPhotoOptions {
  api: AxiosInstance;
  isOnline: boolean;
  vehicleId?: number | null;
}

interface UseReceiptPhotoResult {
  receiptUri: string | null;
  receiptAttachmentId: number | null;
  handleTakePhoto: () => Promise<void>;
  handleChooseFromGallery: () => Promise<void>;
  clearReceipt: () => void;
  setReceiptAttachmentId: (id: number | null) => void;
}

export function useReceiptPhoto({
  api,
  isOnline,
  vehicleId,
}: UseReceiptPhotoOptions): UseReceiptPhotoResult {
  const [receiptUri, setReceiptUri] = useState<string | null>(null);
  const [receiptAttachmentId, setReceiptAttachmentId] = useState<number | null>(null);

  const uploadReceipt = useCallback(
    async (asset: Asset) => {
      if (!isOnline) {
        Alert.alert('Offline', 'Receipt will be uploaded when back online');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('file', {
          uri: asset.uri,
          type: asset.type || 'image/jpeg',
          name: asset.fileName || 'receipt.jpg',
        } as any);

        if (vehicleId) {
          formData.append('vehicleId', vehicleId.toString());
        }

        const response = await api.post('/attachments', formData, {
          headers: {'Content-Type': 'multipart/form-data'},
        });

        setReceiptAttachmentId(response.data.id);
      } catch (error) {
        console.error('Upload error:', error);
        Alert.alert('Error', 'Failed to upload receipt');
      }
    },
    [api, isOnline, vehicleId],
  );

  const handleTakePhoto = useCallback(async () => {
    try {
      const result = await launchCamera({
        mediaType: 'photo',
        quality: 0.8,
        saveToPhotos: false,
      });

      if (result.assets && result.assets[0]?.uri) {
        setReceiptUri(result.assets[0].uri);
        await uploadReceipt(result.assets[0]);
      }
    } catch (error) {
      console.error('Camera error:', error);
      Alert.alert('Error', 'Failed to take photo');
    }
  }, [uploadReceipt]);

  const handleChooseFromGallery = useCallback(async () => {
    try {
      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
      });

      if (result.assets && result.assets[0]?.uri) {
        setReceiptUri(result.assets[0].uri);
        await uploadReceipt(result.assets[0]);
      }
    } catch (error) {
      console.error('Gallery error:', error);
      Alert.alert('Error', 'Failed to select image');
    }
  }, [uploadReceipt]);

  const clearReceipt = useCallback(() => {
    setReceiptUri(null);
    setReceiptAttachmentId(null);
  }, []);

  return {
    receiptUri,
    receiptAttachmentId,
    handleTakePhoto,
    handleChooseFromGallery,
    clearReceipt,
    setReceiptAttachmentId,
  };
}
