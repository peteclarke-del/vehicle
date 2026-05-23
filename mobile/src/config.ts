// App configuration - replaces react-native-config to avoid native module dependency
// For physical device testing, change API_URL to your server's actual IP address

const Config = {
  API_URL: 'http://10.0.2.2:8081/api',
  APP_NAME: 'Vehicle Manager',
  APP_VERSION: '1.0.0',
  APP_INTERNAL_VERSION: '2026.05.19+mobile.compat.1',
  SUPPORTED_SERVER_API_COMPATIBILITY_VERSIONS: [1],
};

export default Config;
