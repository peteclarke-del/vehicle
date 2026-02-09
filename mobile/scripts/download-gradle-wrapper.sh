#!/bin/bash
# Download gradle wrapper jar if not present

WRAPPER_DIR="android/gradle/wrapper"
WRAPPER_JAR="$WRAPPER_DIR/gradle-wrapper.jar"
GRADLE_VERSION="8.6"

if [ ! -f "$WRAPPER_JAR" ]; then
    echo "Downloading gradle wrapper..."
    mkdir -p "$WRAPPER_DIR"
    curl -sL "https://raw.githubusercontent.com/gradle/gradle/v${GRADLE_VERSION}/gradle/wrapper/gradle-wrapper.jar" -o "$WRAPPER_JAR"
    echo "Gradle wrapper downloaded."
else
    echo "Gradle wrapper already exists."
fi
