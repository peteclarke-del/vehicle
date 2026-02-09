#!/bin/bash
# Build script for Android APK

set -e

echo "=== Vehicle Manager Android Build ==="

# Navigate to app directory
cd /app

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "Installing npm dependencies..."
    npm install
fi

# Ensure local.properties exists
echo "sdk.dir=${ANDROID_HOME}" > android/local.properties

# Generate gradle wrapper if it doesn't exist (volume mount may overwrite it)
cd android
if [ ! -f "gradle/wrapper/gradle-wrapper.jar" ]; then
    echo "Generating Gradle wrapper..."
    gradle wrapper --gradle-version=8.6
fi

# Copy react-native-vector-icons fonts into android assets
echo "Copying vector icon fonts..."
mkdir -p /app/android/app/src/main/assets/fonts
cp /app/node_modules/react-native-vector-icons/Fonts/*.ttf /app/android/app/src/main/assets/fonts/ 2>/dev/null || echo "Warning: Could not copy vector icon fonts"

# Clean previous builds
echo "Cleaning previous builds..."
./gradlew clean

# Build release APK
echo "Building release APK..."
./gradlew assembleRelease

# Copy APK to output directory
echo "Copying APK to output..."
mkdir -p /app/output
cp app/build/outputs/apk/release/*.apk /app/output/

echo ""
echo "=== Build Complete ==="
echo "APK location: /app/output/"
ls -la /app/output/
