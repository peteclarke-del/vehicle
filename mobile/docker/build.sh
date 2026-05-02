#!/bin/bash
# Build script for Android APK

set -e

echo "=== Vehicle Manager Android Build ==="

# Navigate to app directory
cd /app

# Use an isolated Gradle user home per container run to avoid cache/journal
# lock contention when previous runs left daemons behind in the shared cache.
export GRADLE_USER_HOME="${GRADLE_USER_HOME:-/tmp/.gradle-$(date +%s)-$$}"
mkdir -p "$GRADLE_USER_HOME"
echo "Using GRADLE_USER_HOME=$GRADLE_USER_HOME"

# Use conservative Gradle settings to reduce memory pressure in containers.
# These can be overridden from docker-compose with environment variables.
export GRADLE_OPTS="${GRADLE_OPTS:--Xmx1536m -Dorg.gradle.daemon=false -Dorg.gradle.workers.max=1 -Dkotlin.daemon.jvm.options=-Xmx512m}"
GRADLE_MAX_WORKERS="${GRADLE_MAX_WORKERS:-1}"
GRADLE_FLAGS=(--no-daemon "--max-workers=${GRADLE_MAX_WORKERS}")

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
./gradlew "${GRADLE_FLAGS[@]}" clean

# Build APK(s). Default behavior is release with fallback to debug when
# release fails (commonly due to OOM / exit 137 on constrained hosts).
BUILD_VARIANT="${BUILD_VARIANT:-release}"

build_release() {
    echo "Building release APK..."
    ./gradlew "${GRADLE_FLAGS[@]}" assembleRelease -x lintVitalRelease
}

build_debug() {
    echo "Building debug APK..."
    ./gradlew "${GRADLE_FLAGS[@]}" assembleDebug
}

case "$BUILD_VARIANT" in
    release)
        if ! build_release; then
            echo "Release build failed; attempting debug APK fallback..."
            build_debug
        fi
        ;;
    debug)
        build_debug
        ;;
    both)
        if ! build_release; then
            echo "Release build failed; continuing to debug APK build..."
        fi
        build_debug
        ;;
    *)
        echo "Invalid BUILD_VARIANT='$BUILD_VARIANT' (expected: release|debug|both)"
        exit 1
        ;;
esac

# Copy APK to output directory
echo "Copying APK to output..."
mkdir -p /app/output
copied=0
for apk in app/build/outputs/apk/release/*.apk app/build/outputs/apk/debug/*.apk; do
    if [ -f "$apk" ]; then
        cp "$apk" /app/output/
        copied=1
    fi
done

if [ "$copied" -ne 1 ]; then
    echo "No APK artifacts were found to copy."
    exit 1
fi

echo ""
echo "=== Build Complete ==="
echo "APK location: /app/output/"
ls -la /app/output/
