# Android build environment for React Native
FROM node:18-bullseye

# Set environment variables
ENV ANDROID_HOME=/opt/android-sdk
ENV ANDROID_SDK_ROOT=/opt/android-sdk
ENV PATH="${PATH}:${ANDROID_HOME}/cmdline-tools/latest/bin:${ANDROID_HOME}/platform-tools:${ANDROID_HOME}/build-tools/35.0.0"

# Install dependencies
RUN apt-get update && apt-get install -y \
    openjdk-17-jdk \
    wget \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Set JAVA_HOME
ENV JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64

# Download and install Android command line tools
RUN mkdir -p ${ANDROID_HOME}/cmdline-tools && \
    cd ${ANDROID_HOME}/cmdline-tools && \
    wget -q https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip -O cmdline-tools.zip && \
    unzip -q cmdline-tools.zip && \
    rm cmdline-tools.zip && \
    mv cmdline-tools latest

# Accept licenses and install SDK components (including SDK 35 for newer dependencies)
RUN yes | sdkmanager --licenses && \
    sdkmanager "platform-tools" \
    "platforms;android-34" \
    "platforms;android-35" \
    "build-tools;34.0.0" \
    "build-tools;35.0.0" \
    "ndk;26.1.10909125"

# Install Gradle globally to generate wrapper
ENV GRADLE_VERSION=8.7
RUN wget -q "https://services.gradle.org/distributions/gradle-${GRADLE_VERSION}-bin.zip" -O /tmp/gradle.zip && \
    unzip -q /tmp/gradle.zip -d /opt && \
    rm /tmp/gradle.zip && \
    ln -s /opt/gradle-${GRADLE_VERSION}/bin/gradle /usr/local/bin/gradle

# Create app directory
WORKDIR /app

# Copy package files first for better caching
COPY package*.json ./

# Install npm dependencies
RUN npm install

# Copy the rest of the application
COPY . .

# Generate gradle wrapper files manually (to avoid evaluating build.gradle)
# First, generate wrapper in a temp directory, then copy to android
RUN mkdir -p /tmp/gradle-init && \
    cd /tmp/gradle-init && \
    gradle init --type basic --dsl groovy --project-name temp && \
    gradle wrapper --gradle-version ${GRADLE_VERSION} && \
    mkdir -p /app/android/gradle/wrapper && \
    cp -r gradle/wrapper/* /app/android/gradle/wrapper/ && \
    cp gradlew /app/android/ && \
    cp gradlew.bat /app/android/ && \
    chmod +x /app/android/gradlew && \
    rm -rf /tmp/gradle-init

# Create local.properties for Android
RUN echo "sdk.dir=${ANDROID_HOME}" > android/local.properties

# Default command - build release APK
CMD ["npm", "run", "build:android"]
