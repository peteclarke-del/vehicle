# Vehicle Management Mobile App

A React Native mobile application for Android that provides vehicle management on the go. This app syncs with the existing web application and backend API.

## Features

- **Vehicle Management**: View, add, edit, and delete vehicles
- **Fuel Records**: Track fuel purchases with mileage, cost, and MPG calculations
- **Service Records**: Log maintenance and service history
- **Parts Inventory**: Manage spare parts and components
- **Stock Items**: Browse and edit shared stock buckets for parts and consumables
- **Current Inventory Scope**: Mobile now includes a dedicated Stock Items screen alongside Parts and Consumables workflows
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

### Updating the app (from GitHub Releases)

The app updates itself from this repository's GitHub Releases. No Play Store is needed.

- **On launch** the app checks the latest release and, if it's newer, offers *Download & install* or *Later*. *Later* skips that version until a newer one appears.
- **Settings → Check for updates** runs the same check manually. The *Update required* screen (shown when the server needs a newer app) has the same button.
- The first time, Android asks you to allow **Install unknown apps** for Vehicle Manager. Allow it, then tap *Download & install* again.
- Android installs the update over the existing app and keeps your data. It only accepts an APK with the same package name and signing key and a higher version code.

#### Server version check

On launch and whenever the app returns to the foreground, it asks the server (`GET /api/app-compatibility`, no login needed) which app versions it expects:

- **Older than `MOBILE_LATEST_APP_VERSION`:** the app asks the user to update. This is shown even if they chose *Later* for that release, and still explains the situation when the download isn't available. The app keeps working.
- **Older than `MOBILE_MIN_APP_VERSION`:** the *Update required* screen blocks the app until it is updated.

Both are set in the server's `backend/.env` (defaults: latest `1.1.0`, minimum `1.0.0`). After publishing a release, raise `MOBILE_LATEST_APP_VERSION` to it so existing installs are prompted. Raise `MOBILE_MIN_APP_VERSION` only when the server no longer works with older apps.

#### Publishing a release

Tag a commit and push the tag:

```bash
git tag mobile-v1.1.0
git push origin mobile-v1.1.0
```

Then set `MOBILE_LATEST_APP_VERSION=1.1.0` in the server's `backend/.env` and restart the PHP container so existing installs are asked to update (see *Server version check*).

`.github/workflows/mobile-release.yml` runs the tests, builds the release APK with `versionName=1.1.0` and `versionCode=1001000`, and publishes it to the GitHub Release `mobile-v1.1.0` as `vehicle-manager-1.1.0.apk` (with a `.sha256`). You can also run the workflow manually from the Actions tab with a version. PRs that touch `mobile/` build and test the APK but publish nothing.

Rules:
- Tags must be `mobile-vX.Y.Z`, and each release must be higher than the last.
- Only non-draft, non-prerelease releases with an `.apk` asset are offered. Mark a release *pre-release* to hold it back.

#### Signing key

Updates only install if they are signed with the **same key** as the installed app.

- Builds up to 1.0.0 were signed with the repo's public **debug keystore**. Until you configure a release key, CI uses the same debug key, so updates install over existing installs.
- The debug key is public, so anyone could build an APK that your phone would accept as an update. The app only downloads from this repo's releases, which limits the risk, but a private release key is better.
- To switch to a release key, create one (see *Signing for Release* below) and add these repository secrets: `ANDROID_KEYSTORE_BASE64` (`base64 -w0 vehicle-release.keystore`), `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS` and `ANDROID_KEY_PASSWORD`.
- The first release signed with the new key **cannot** update a debug-signed install. Uninstall the app once and install that APK by hand. Updates install automatically from then on. Export a backup first if you use standalone mode.

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
