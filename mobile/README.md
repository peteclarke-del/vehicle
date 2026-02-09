# Vehicle Management Mobile App

A React Native mobile application for Android that provides vehicle management on the go. This app syncs with the existing web application and backend API.

## Features

- **Vehicle Management**: View, add, edit, and delete vehicles
- **Fuel Records**: Track fuel purchases with mileage, cost, and MPG calculations
- **Service Records**: Log maintenance and service history
- **Parts Inventory**: Manage spare parts and components
- **Camera Integration**: Take photos of receipts and documents directly from the app
- **Gallery Upload**: Upload existing photos from your device
- **Offline Support**: Continue working offline with automatic sync when back online
- **Theme Support**: Light and dark mode that matches the web app

## Prerequisites

- Node.js 18 or higher
- Java Development Kit (JDK) 17
- Android Studio with Android SDK
- Android device or emulator (API 24+)

## Setup

### 1. Install dependencies

```bash
cd mobile
npm install
```

### 2. Configure environment

Copy the example environment file:
```bash
cp .env.example .env
```

Edit `.env` to point to your backend API:
```
API_URL=http://YOUR_SERVER_IP:8081/api
```

For Android emulator connecting to localhost, use:
```
API_URL=http://10.0.2.2:8081/api
```

### 3. Android Setup

1. Install Android Studio
2. Install Android SDK (API 34 recommended)
3. Create an Android Virtual Device (AVD) or connect a physical device
4. Enable USB debugging on your device

### 4. Run the app

Start Metro bundler:
```bash
npm start
```

In a new terminal, build and run for Android:
```bash
npm run android
```

## Project Structure

```
mobile/
├── src/
│   ├── components/          # Reusable UI components
│   │   └── VehicleSelector.tsx
│   ├── contexts/            # React Context providers
│   │   ├── AuthContext.tsx
│   │   ├── SyncContext.tsx
│   │   └── UserPreferencesContext.tsx
│   ├── navigation/          # React Navigation configuration
│   │   ├── AuthNavigator.tsx
│   │   ├── MainNavigator.tsx
│   │   └── RootNavigator.tsx
│   ├── screens/             # Screen components
│   │   ├── auth/
│   │   │   ├── LoginScreen.tsx
│   │   │   └── RegisterScreen.tsx
│   │   ├── AttachmentViewerScreen.tsx
│   │   ├── CameraScreen.tsx
│   │   ├── DashboardScreen.tsx
│   │   ├── FuelRecordFormScreen.tsx
│   │   ├── FuelRecordsScreen.tsx
│   │   ├── PartFormScreen.tsx
│   │   ├── PartsScreen.tsx
│   │   ├── ServiceRecordFormScreen.tsx
│   │   ├── ServiceRecordsScreen.tsx
│   │   ├── SettingsScreen.tsx
│   │   ├── VehicleDetailScreen.tsx
│   │   ├── VehicleFormScreen.tsx
│   │   └── VehiclesScreen.tsx
│   ├── theme/               # Theme configuration
│   │   └── index.ts
│   └── utils/               # Utility functions
│       └── formatters.ts
├── App.tsx                  # Main app component
├── app.json                 # App configuration
├── package.json             # Dependencies
└── tsconfig.json            # TypeScript configuration
```

## Key Libraries

- **React Native 0.73**: Core framework
- **React Native Paper**: Material Design 3 component library
- **React Navigation**: Navigation with stack and bottom tabs
- **Axios**: HTTP client for API communication
- **AsyncStorage**: Local data persistence
- **NetInfo**: Network connectivity monitoring
- **React Native Image Picker**: Camera and gallery access

## API Communication

The app communicates with the existing Symfony backend via REST API. Authentication uses JWT tokens stored in AsyncStorage.

### Offline Support

The app implements offline-first architecture:
1. Data is cached locally
2. Changes made offline are queued
3. When online, pending changes are synced automatically
4. Conflict resolution favors server data

## Building for Production

### Option 1: Build with Docker (Recommended)

This method requires no local Android SDK installation:

```bash
# From the project root directory
docker compose -f docker-compose.mobile.yml up --build
```

The APK will be output to `mobile/output/app-release.apk`

### Option 2: Local Build

If you have Android SDK installed locally:

#### Debug APK
```bash
cd android
./gradlew assembleDebug
```
The APK will be at `android/app/build/outputs/apk/debug/app-debug.apk`

#### Release APK
```bash
cd android
./gradlew assembleRelease
```
The APK will be at `android/app/build/outputs/apk/release/app-release.apk`

### Installing the APK

1. Transfer the APK to your Android device
2. Enable "Install from unknown sources" in Settings > Security
3. Open the APK file to install
4. Launch "Vehicle Manager" from your app drawer

### Signing for Release

For Google Play Store distribution, create a release keystore:

```bash
keytool -genkey -v -keystore vehicle-release.keystore \
  -alias vehicle-key -keyalg RSA -keysize 2048 -validity 10000
```

Then add to `android/gradle.properties`:
```
VEHICLE_RELEASE_STORE_FILE=vehicle-release.keystore
VEHICLE_RELEASE_STORE_PASSWORD=your_store_password
VEHICLE_RELEASE_KEY_ALIAS=vehicle-key
VEHICLE_RELEASE_KEY_PASSWORD=your_key_password
```

## Troubleshooting

### Metro bundler cache
```bash
npm start -- --reset-cache
```

### Android build issues
```bash
cd android
./gradlew clean
cd ..
npm run android
```

### Permission issues
Make sure you've granted camera and storage permissions on your device.

## Development Notes

- The app uses the same color scheme as the web application
- All API endpoints match the existing backend API
- User preferences are synced between web and mobile
- Receipt photos are uploaded as attachments linked to records

## Future Enhancements

- iOS support
- Push notifications for service reminders
- Barcode scanning for parts
- PDF viewing for invoices
- Widget for quick fuel logging
