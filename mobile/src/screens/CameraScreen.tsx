import React, {useState, useRef} from 'react';
import {
  View,
  StyleSheet,
  Image,
  Alert,
  TouchableOpacity,
  Dimensions,
} from 'react-native';
import {
  Text,
  Button,
  useTheme,
  IconButton,
  ActivityIndicator,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {launchCamera, launchImageLibrary, Asset} from 'react-native-image-picker';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useAuth} from '../contexts/AuthContext';
import {useSync} from '../contexts/SyncContext';
import {MainStackParamList} from '../navigation/MainNavigator';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'Camera'>;

const {width: SCREEN_WIDTH} = Dimensions.get('window');

const CameraScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api} = useAuth();
  const {isOnline} = useSync();

  const vehicleId = route.params?.vehicleId;
  const attachmentType = route.params?.attachmentType || 'general';
  const returnTo = route.params?.returnTo;

  const [capturedImage, setCapturedImage] = useState<Asset | null>(null);
  const [uploading, setUploading] = useState(false);

  const handleTakePhoto = async () => {
    try {
      const result = await launchCamera({
        mediaType: 'photo',
        quality: 0.8,
        saveToPhotos: false,
        cameraType: 'back',
      });

      if (result.errorCode) {
        if (result.errorCode === 'camera_unavailable') {
          Alert.alert('Error', 'Camera is not available on this device');
        } else if (result.errorCode === 'permission') {
          Alert.alert('Permission Required', 'Please grant camera permission to use this feature');
        }
        return;
      }

      if (result.assets && result.assets[0]) {
        setCapturedImage(result.assets[0]);
      }
    } catch (error) {
      console.error('Camera error:', error);
      Alert.alert('Error', 'Failed to take photo');
    }
  };

  const handleChooseFromGallery = async () => {
    try {
      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
        selectionLimit: 1,
      });

      if (result.assets && result.assets[0]) {
        setCapturedImage(result.assets[0]);
      }
    } catch (error) {
      console.error('Gallery error:', error);
      Alert.alert('Error', 'Failed to select image');
    }
  };

  const handleRetake = () => {
    setCapturedImage(null);
  };

  const handleUpload = async () => {
    if (!capturedImage?.uri) {
      Alert.alert('Error', 'No image to upload');
      return;
    }

    if (!isOnline) {
      Alert.alert('Offline', 'You need to be online to upload images. Please try again when connected.');
      return;
    }

    setUploading(true);

    try {
      const formData = new FormData();
      formData.append('file', {
        uri: capturedImage.uri,
        type: capturedImage.type || 'image/jpeg',
        name: capturedImage.fileName || `photo_${Date.now()}.jpg`,
      } as any);

      if (vehicleId) {
        formData.append('vehicleId', vehicleId.toString());
      }
      formData.append('type', attachmentType);

      const response = await api.post('/attachments', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      Alert.alert('Success', 'Image uploaded successfully!', [
        {
          text: 'OK',
          onPress: () => {
            if (returnTo) {
              navigation.navigate(returnTo as any, {
                attachmentId: response.data.id,
                vehicleId,
              });
            } else {
              navigation.goBack();
            }
          },
        },
      ]);
    } catch (error: any) {
      console.error('Upload error:', error);
      const message = error.response?.data?.error || 'Failed to upload image';
      Alert.alert('Error', message);
    } finally {
      setUploading(false);
    }
  };

  // If we have a captured image, show preview
  if (capturedImage?.uri) {
    return (
      <View style={[styles.container, {backgroundColor: theme.colors.background}]}>
        <View style={styles.previewContainer}>
          <Image
            source={{uri: capturedImage.uri}}
            style={styles.previewImage}
            resizeMode="contain"
          />
        </View>

        <View style={styles.previewActions}>
          <Button
            mode="outlined"
            onPress={handleRetake}
            icon="camera-retake"
            disabled={uploading}
            style={styles.actionButton}>
            Retake
          </Button>
          <Button
            mode="contained"
            onPress={handleUpload}
            icon="cloud-upload"
            loading={uploading}
            disabled={uploading}
            style={styles.actionButton}>
            Upload
          </Button>
        </View>

        {!isOnline && (
          <View style={[styles.offlineBanner, {backgroundColor: theme.colors.errorContainer}]}>
            <Icon name="cloud-off-outline" size={20} color={theme.colors.onErrorContainer} />
            <Text style={[styles.offlineText, {color: theme.colors.onErrorContainer}]}>
              You're offline. Connect to upload.
            </Text>
          </View>
        )}
      </View>
    );
  }

  // Camera selection view
  return (
    <View style={[styles.container, {backgroundColor: theme.colors.background}]}>
      <View style={styles.cameraOptions}>
        <Text variant="headlineSmall" style={styles.title}>
          Add Photo
        </Text>
        <Text variant="bodyMedium" style={[styles.subtitle, {color: theme.colors.onSurfaceVariant}]}>
          Take a photo or choose from your gallery
        </Text>

        <View style={styles.optionsContainer}>
          <TouchableOpacity
            style={[styles.optionButton, {backgroundColor: theme.colors.primaryContainer}]}
            onPress={handleTakePhoto}
            activeOpacity={0.8}>
            <Icon name="camera" size={48} color={theme.colors.primary} />
            <Text variant="titleMedium" style={{color: theme.colors.primary, marginTop: 12}}>
              Take Photo
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant, marginTop: 4}}>
              Use camera
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.optionButton, {backgroundColor: theme.colors.secondaryContainer}]}
            onPress={handleChooseFromGallery}
            activeOpacity={0.8}>
            <Icon name="image-multiple" size={48} color={theme.colors.secondary} />
            <Text variant="titleMedium" style={{color: theme.colors.secondary, marginTop: 12}}>
              Gallery
            </Text>
            <Text variant="bodySmall" style={{color: theme.colors.onSurfaceVariant, marginTop: 4}}>
              Choose existing
            </Text>
          </TouchableOpacity>
        </View>

        {attachmentType !== 'general' && (
          <View style={[styles.typeHint, {backgroundColor: theme.colors.surfaceVariant}]}>
            <Icon name="information" size={20} color={theme.colors.onSurfaceVariant} />
            <Text variant="bodySmall" style={[styles.typeHintText, {color: theme.colors.onSurfaceVariant}]}>
              This photo will be attached as: {attachmentType}
            </Text>
          </View>
        )}
      </View>

      <Button
        mode="text"
        onPress={() => navigation.goBack()}
        style={styles.cancelButton}>
        Cancel
      </Button>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  cameraOptions: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  title: {
    marginBottom: 8,
    textAlign: 'center',
  },
  subtitle: {
    marginBottom: 40,
    textAlign: 'center',
  },
  optionsContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 20,
  },
  optionButton: {
    width: (SCREEN_WIDTH - 80) / 2,
    aspectRatio: 1,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 16,
  },
  typeHint: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 32,
    padding: 12,
    borderRadius: 8,
    gap: 8,
  },
  typeHintText: {
    flex: 1,
  },
  cancelButton: {
    marginBottom: 24,
  },
  previewContainer: {
    flex: 1,
    backgroundColor: '#000',
    justifyContent: 'center',
    alignItems: 'center',
  },
  previewImage: {
    width: '100%',
    height: '100%',
  },
  previewActions: {
    flexDirection: 'row',
    padding: 16,
    gap: 16,
  },
  actionButton: {
    flex: 1,
  },
  offlineBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 12,
    gap: 8,
  },
  offlineText: {
    fontSize: 14,
  },
});

export default CameraScreen;
