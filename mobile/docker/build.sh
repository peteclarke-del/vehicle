#!/bin/bash
# Builds the Android APK inside the docker-compose.mobile.yml container.
#
# Environment:
#   MOBILE_VERSION   X.Y.Z version name (default: "version" in package.json).
#                    versionCode = X*1000000 + Y*1000 + Z, same as CI, so a
#                    local build installs over / updates to GitHub releases.
#   BUILD_VARIANT    release (default) | debug
#   RUN_TESTS        1 to run the jest suite first
#   Release signing (optional, otherwise the repo debug key is used, which is
#   also what GitHub releases use unless the keystore secrets are set):
#     ORG_GRADLE_PROJECT_VEHICLE_RELEASE_STORE_FILE (path inside container)
#     ORG_GRADLE_PROJECT_VEHICLE_RELEASE_STORE_PASSWORD
#     ORG_GRADLE_PROJECT_VEHICLE_RELEASE_KEY_ALIAS
#     ORG_GRADLE_PROJECT_VEHICLE_RELEASE_KEY_PASSWORD
set -euo pipefail
cd /app

VERSION="${MOBILE_VERSION:-$(node -p "require('./package.json').version")}"
if ! [[ "$VERSION" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
  echo "MOBILE_VERSION must be X.Y.Z (got '$VERSION')" >&2
  exit 1
fi
CODE=$(( BASH_REMATCH[1] * 1000000 + BASH_REMATCH[2] * 1000 + BASH_REMATCH[3] ))
VARIANT="${BUILD_VARIANT:-release}"

# Compose passes unset signing vars as empty strings; Gradle would treat an
# empty property as "configured", so drop them to fall back to the debug key.
for v in STORE_FILE STORE_PASSWORD KEY_ALIAS KEY_PASSWORD; do
  name="ORG_GRADLE_PROJECT_VEHICLE_RELEASE_$v"
  [ -n "${!name:-}" ] || unset "$name"
done
echo "=== Vehicle Manager Android build: $VERSION (code $CODE, $VARIANT) ==="

if [ "${RUN_TESTS:-0}" = "1" ]; then
  npx jest --ci
fi

mkdir -p android/app/src/main/assets/fonts
cp node_modules/react-native-vector-icons/Fonts/*.ttf android/app/src/main/assets/fonts/
echo "sdk.dir=${ANDROID_HOME}" > android/local.properties

# Keep memory modest for small hosts; override via GRADLE_OPTS.
export GRADLE_OPTS="${GRADLE_OPTS:--Xmx2g -Dorg.gradle.daemon=false}"

cd android
case "$VARIANT" in
  release) TASK=assembleRelease; EXTRA=(-x lintVitalRelease) ;;
  debug)   TASK=assembleDebug;   EXTRA=() ;;
  *) echo "BUILD_VARIANT must be release or debug" >&2; exit 1 ;;
esac
gradle --no-daemon "$TASK" "${EXTRA[@]}" \
  -PappVersionName="$VERSION" -PappVersionCode="$CODE"

APK="app/build/outputs/apk/$VARIANT/app-$VARIANT.apk"
OUT_NAME="vehicle-manager-$VERSION.apk"
if [ "$VARIANT" = debug ]; then
  OUT_NAME="vehicle-manager-$VERSION-debug.apk"
fi
mkdir -p /app/output
cp "$APK" "/app/output/$OUT_NAME"
(cd /app/output && sha256sum "$OUT_NAME" > "$OUT_NAME.sha256")

echo
echo "=== Build complete: mobile/output/$OUT_NAME ==="
ls -la /app/output/
