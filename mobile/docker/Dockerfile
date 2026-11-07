# Android build environment for the Vehicle Manager app (React Native 0.87).
# Mirrors .github/workflows/mobile-release.yml so a local build produces the
# same APK as a GitHub release (same toolchain, same debug signing key).
#
# Usage (from the repo root):
#   MOBILE_VERSION=1.1.1 docker compose -f docker-compose.mobile.yml up --build
# The APK is written to mobile/output/vehicle-manager-<version>.apk

FROM node:22-bookworm

ENV ANDROID_HOME=/opt/android-sdk \
    ANDROID_SDK_ROOT=/opt/android-sdk \
    JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64 \
    GRADLE_VERSION=9.4.1
ENV PATH="${PATH}:${ANDROID_HOME}/cmdline-tools/latest/bin:${ANDROID_HOME}/platform-tools:/opt/gradle-${GRADLE_VERSION}/bin"

RUN apt-get update \
 && apt-get install -y --no-install-recommends openjdk-17-jdk-headless wget unzip \
 && rm -rf /var/lib/apt/lists/*

# Android command-line tools + the SDK packages android/build.gradle asks for
# (compileSdk 37, build-tools 37.0.0, NDK 27.1.12297006).
RUN mkdir -p ${ANDROID_HOME}/cmdline-tools \
 && cd ${ANDROID_HOME}/cmdline-tools \
 && wget -q https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip -O tools.zip \
 && unzip -q tools.zip && rm tools.zip && mv cmdline-tools latest \
 && yes | sdkmanager --licenses > /dev/null \
 && sdkmanager --install "platform-tools" "platforms;android-37.0" "build-tools;37.0.0" "ndk;27.1.12297006" > /dev/null

# Gradle (the repo ships wrapper properties but no wrapper jar; CI also runs
# a plain Gradle of the same version).
RUN wget -q "https://services.gradle.org/distributions/gradle-${GRADLE_VERSION}-bin.zip" -O /tmp/gradle.zip \
 && unzip -q /tmp/gradle.zip -d /opt && rm /tmp/gradle.zip

WORKDIR /app

# Dependencies first for layer caching (same flags as CI).
COPY package.json package-lock.json ./
RUN npm ci --legacy-peer-deps --no-audit --no-fund

COPY . .

CMD ["/bin/bash", "/app/docker/build.sh"]
