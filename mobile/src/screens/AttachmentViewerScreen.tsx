import React, {useState, useEffect} from 'react';
import {
  View,
  StyleSheet,
  Image,
  Dimensions,
  Alert,
  TouchableOpacity,
  ScrollView,
  Share,
} from 'react-native';
import {
  Text,
  useTheme,
  ActivityIndicator,
  IconButton,
  Menu,
  Divider,
} from 'react-native-paper';
import {useNavigation, useRoute, RouteProp} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import {useAuth} from '../contexts/AuthContext';
import {formatDate} from '../utils/formatters';
import {MainStackParamList} from '../navigation/MainNavigator';
import Config from '../config';

type NavigationProp = NativeStackNavigationProp<MainStackParamList>;
type RouteProps = RouteProp<MainStackParamList, 'AttachmentViewer'>;

const {width: SCREEN_WIDTH, height: SCREEN_HEIGHT} = Dimensions.get('window');

interface Attachment {
  id: number;
  filename: string;
  originalFilename: string;
  mimeType: string;
  size: number;
  uploadedAt: string;
  vehicleId: number | null;
  type: string;
}

const AttachmentViewerScreen: React.FC = () => {
  const theme = useTheme();
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const {api, token} = useAuth();

  const attachmentId = route.params?.attachmentId;

  const [attachment, setAttachment] = useState<Attachment | null>(null);
  const [loading, setLoading] = useState(true);
  const [menuVisible, setMenuVisible] = useState(false);
  const [imageError, setImageError] = useState(false);

  useEffect(() => {
    loadAttachment();
  }, [attachmentId]);

  const loadAttachment = async () => {
    try {
      const response = await api.get(`/attachments/${attachmentId}`);
      setAttachment(response.data);
    } catch (error) {
      console.error('Error loading attachment:', error);
      Alert.alert('Error', 'Failed to load attachment');
    } finally {
      setLoading(false);
    }
  };

  const getImageUrl = () => {
    if (!attachment) return null;
    const baseUrl = Config.API_URL?.replace('/api', '') || 'http://10.0.2.2:8081';
    return `${baseUrl}/uploads/attachments/${attachment.filename}`;
  };

  const isImage = () => {
    return attachment?.mimeType?.startsWith('image/');
  };

  const isPdf = () => {
    return attachment?.mimeType === 'application/pdf';
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const handleShare = async () => {
    try {
      const url = getImageUrl();
      if (url) {
        await Share.share({
          url,
          title: attachment?.originalFilename,
        });
      }
    } catch (error) {
      console.error('Share error:', error);
    }
    setMenuVisible(false);
  };

  const handleDelete = () => {
    setMenuVisible(false);
    Alert.alert(
      'Delete Attachment',
      'Are you sure you want to delete this attachment?',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await api.delete(`/attachments/${attachmentId}`);
              navigation.goBack();
            } catch (error) {
              console.error('Delete error:', error);
              Alert.alert('Error', 'Failed to delete attachment');
            }
          },
        },
      ],
    );
  };

  if (loading) {
    return (
      <View style={[styles.loadingContainer, {backgroundColor: theme.colors.background}]}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  if (!attachment) {
    return (
      <View style={[styles.errorContainer, {backgroundColor: theme.colors.background}]}>
        <Icon name="file-alert" size={64} color={theme.colors.error} />
        <Text variant="bodyLarge" style={{color: theme.colors.onSurface, marginTop: 16}}>
          Attachment not found
        </Text>
      </View>
    );
  }

  return (
    <View style={[styles.container, {backgroundColor: '#000'}]}>
      {/* Header */}
      <View style={[styles.header, {backgroundColor: 'rgba(0,0,0,0.7)'}]}>
        <IconButton
          icon="arrow-left"
          iconColor="#fff"
          onPress={() => navigation.goBack()}
        />
        <View style={styles.headerTitle}>
          <Text variant="titleMedium" style={styles.headerText} numberOfLines={1}>
            {attachment.originalFilename}
          </Text>
        </View>
        <Menu
          visible={menuVisible}
          onDismiss={() => setMenuVisible(false)}
          anchor={
            <IconButton
              icon="dots-vertical"
              iconColor="#fff"
              onPress={() => setMenuVisible(true)}
            />
          }>
          <Menu.Item onPress={handleShare} title="Share" leadingIcon="share" />
          <Divider />
          <Menu.Item
            onPress={handleDelete}
            title="Delete"
            leadingIcon="delete"
            titleStyle={{color: theme.colors.error}}
          />
        </Menu>
      </View>

      {/* Content */}
      {isImage() && !imageError ? (
        <View style={styles.imageContainer}>
          <Image
            source={{
              uri: getImageUrl() || undefined,
              headers: token ? {Authorization: `Bearer ${token}`} : {},
            }}
            style={styles.image}
            resizeMode="contain"
            onError={() => setImageError(true)}
          />
        </View>
      ) : isPdf() ? (
        <View style={styles.pdfContainer}>
          <Icon name="file-pdf-box" size={80} color={theme.colors.error} />
          <Text variant="titleLarge" style={styles.pdfText}>PDF Document</Text>
          <Text variant="bodyMedium" style={styles.pdfSubtext}>
            {attachment.originalFilename}
          </Text>
          <Text variant="bodySmall" style={styles.pdfInfo}>
            PDF viewing in the app coming soon.{'\n'}
            Use the Share button to open in another app.
          </Text>
        </View>
      ) : (
        <View style={styles.unsupportedContainer}>
          <Icon name="file-question" size={80} color={theme.colors.onSurfaceVariant} />
          <Text variant="titleLarge" style={styles.unsupportedText}>
            Preview not available
          </Text>
          <Text variant="bodyMedium" style={styles.unsupportedSubtext}>
            {attachment.mimeType}
          </Text>
        </View>
      )}

      {/* Footer with details */}
      <View style={[styles.footer, {backgroundColor: 'rgba(0,0,0,0.7)'}]}>
        <View style={styles.footerRow}>
          <View style={styles.footerItem}>
            <Icon name="file" size={16} color="#aaa" />
            <Text variant="bodySmall" style={styles.footerText}>
              {formatFileSize(attachment.size)}
            </Text>
          </View>
          <View style={styles.footerItem}>
            <Icon name="calendar" size={16} color="#aaa" />
            <Text variant="bodySmall" style={styles.footerText}>
              {formatDate(attachment.uploadedAt)}
            </Text>
          </View>
          {attachment.type && (
            <View style={styles.footerItem}>
              <Icon name="tag" size={16} color="#aaa" />
              <Text variant="bodySmall" style={styles.footerText}>
                {attachment.type}
              </Text>
            </View>
          )}
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingTop: 8,
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    zIndex: 10,
  },
  headerTitle: {
    flex: 1,
  },
  headerText: {
    color: '#fff',
  },
  imageContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  image: {
    width: SCREEN_WIDTH,
    height: SCREEN_HEIGHT - 150,
  },
  pdfContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  pdfText: {
    color: '#fff',
    marginTop: 16,
  },
  pdfSubtext: {
    color: '#aaa',
    marginTop: 8,
  },
  pdfInfo: {
    color: '#888',
    marginTop: 24,
    textAlign: 'center',
  },
  unsupportedContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  unsupportedText: {
    color: '#fff',
    marginTop: 16,
  },
  unsupportedSubtext: {
    color: '#aaa',
    marginTop: 8,
  },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    padding: 16,
  },
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
  },
  footerItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  footerText: {
    color: '#aaa',
  },
});

export default AttachmentViewerScreen;
