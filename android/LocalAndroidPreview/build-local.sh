#!/usr/bin/env bash
set -euo pipefail

SDK="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
ANDROID_PLATFORM="$(find "$SDK/platforms" -maxdepth 1 -mindepth 1 -type d -name 'android-*' | sort -V | tail -1)"
PLATFORM_API="${ANDROID_PLATFORM##*/android-}"
APP_MODE="${APP_MODE:-local}"
ARTIFACT_TYPE="${ARTIFACT_TYPE:-apk}"
BUILD_TYPE="${BUILD_TYPE:-}"
MIN_SDK="${MIN_SDK:-23}"
TARGET_SDK="${TARGET_SDK:-$PLATFORM_API}"
VERSION_CODE="${VERSION_CODE:-$(date +%s)}"
VERSION_NAME="${VERSION_NAME:-0.4.0-${APP_MODE}}"
JDK_HOME="${JDK_HOME:-/Applications/Android Studio.app/Contents/jbr/Contents/Home}"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD="$APP_DIR/build/$(date +%Y%m%d%H%M%S)"
KEYSTORE="$APP_DIR/debug.keystore"
RELEASE_KEYSTORE="${RELEASE_KEYSTORE:-$APP_DIR/keystores/zulors-upload.jks}"
RELEASE_KEYSTORE_TYPE="${RELEASE_KEYSTORE_TYPE:-JKS}"
RELEASE_KEY_ALIAS="${RELEASE_KEY_ALIAS:-zulors-upload}"
RELEASE_CERT_DNAME="${RELEASE_CERT_DNAME:-CN=Zulors,O=Zulors,C=US}"
RELEASE_KEY_VALIDITY_DAYS="${RELEASE_KEY_VALIDITY_DAYS:-10000}"
CREATE_RELEASE_KEYSTORE="${CREATE_RELEASE_KEYSTORE:-false}"
GOOGLE_SERVICES_JSON="${GOOGLE_SERVICES_JSON:-$APP_DIR/firebase/google-services.json}"
ANDROID_GRADLE_PLUGIN_VERSION="${ANDROID_GRADLE_PLUGIN_VERSION:-8.13.2}"
GOOGLE_SERVICES_PLUGIN_VERSION="${GOOGLE_SERVICES_PLUGIN_VERSION:-4.4.2}"
FIREBASE_MESSAGING_VERSION="${FIREBASE_MESSAGING_VERSION:-25.0.1}"
CREDENTIALS_VERSION="${CREDENTIALS_VERSION:-1.6.0}"
GOOGLE_ID_VERSION="${GOOGLE_ID_VERSION:-1.2.0}"
AGORA_VOICE_SDK_VERSION="${AGORA_VOICE_SDK_VERSION:-4.6.3}"
PLAY_APP_UPDATE_VERSION="${PLAY_APP_UPDATE_VERSION:-2.1.0}"
ANDROIDX_ACTIVITY_VERSION="${ANDROIDX_ACTIVITY_VERSION:-1.13.0}"
ANDROIDX_FRAGMENT_VERSION="${ANDROIDX_FRAGMENT_VERSION:-1.9.0}"
ANDROIDX_SPLASHSCREEN_VERSION="${ANDROIDX_SPLASHSCREEN_VERSION:-1.0.1}"
GOOGLE_WEB_CLIENT_ID="${GOOGLE_WEB_CLIENT_ID:-92185010272-6likk4ebn353qm2mjdembk8cttaivfl3.apps.googleusercontent.com}"

export JAVA_HOME="$JDK_HOME"
export ANDROID_HOME="$SDK"
export PATH="$JAVA_HOME/bin:$PATH"

if [ -z "$BUILD_TYPE" ]; then
	if [ "$ARTIFACT_TYPE" = "bundle" ]; then
		BUILD_TYPE="release"
	else
		BUILD_TYPE="debug"
	fi
fi

case "$ARTIFACT_TYPE" in
	apk|bundle) ;;
	*)
		echo "Unsupported ARTIFACT_TYPE: $ARTIFACT_TYPE. Use apk or bundle." >&2
		exit 1
		;;
esac

case "$BUILD_TYPE" in
	debug)
		BUILD_TYPE_CAP="Debug"
		;;
	release)
		BUILD_TYPE_CAP="Release"
		;;
	*)
		echo "Unsupported BUILD_TYPE: $BUILD_TYPE. Use debug or release." >&2
		exit 1
		;;
esac

if [ "$ARTIFACT_TYPE" = "bundle" ]; then
	GRADLE_TASK="bundle${BUILD_TYPE_CAP}"
else
	GRADLE_TASK="assemble${BUILD_TYPE_CAP}"
fi

if [ "$APP_MODE" = "production" ]; then
	APP_ID="${APP_ID:-com.zulors.app}"
	APP_URL="${APP_URL:-https://zulors.com/}"
	APP_LABEL="${APP_LABEL:-Zulors}"
	TRUSTED_HOST="${TRUSTED_HOST:-zulors.com}"
	USES_CLEARTEXT="${USES_CLEARTEXT:-false}"
	DEBUG_WEBVIEW="${DEBUG_WEBVIEW:-false}"
	NO_CACHE="${NO_CACHE:-false}"
	ALLOW_MIXED_CONTENT="${ALLOW_MIXED_CONTENT:-false}"
	ALLOW_HTTP_APP_URL="${ALLOW_HTTP_APP_URL:-false}"
	ENABLE_GEOLOCATION="${ENABLE_GEOLOCATION:-false}"
	ENABLE_FIREBASE_MESSAGING="${ENABLE_FIREBASE_MESSAGING:-true}"
	ENABLE_PLAY_FLEXIBLE_UPDATES="${ENABLE_PLAY_FLEXIBLE_UPDATES:-true}"
	NATIVE_GOOGLE_AUTH_ENABLED="${NATIVE_GOOGLE_AUTH_ENABLED:-true}"
	USER_AGENT_SUFFIX="${USER_AGENT_SUFFIX:-ZulorsAndroidApp}"
else
	APP_ID="${APP_ID:-com.zulors.localpreview}"
	APP_URL="${APP_URL:-http://127.0.0.1:8000/}"
	APP_LABEL="${APP_LABEL:-Zulors Local}"
	TRUSTED_HOST="${TRUSTED_HOST:-zulors.com}"
	USES_CLEARTEXT="${USES_CLEARTEXT:-true}"
	DEBUG_WEBVIEW="${DEBUG_WEBVIEW:-true}"
	NO_CACHE="${NO_CACHE:-true}"
	ALLOW_MIXED_CONTENT="${ALLOW_MIXED_CONTENT:-true}"
	ALLOW_HTTP_APP_URL="${ALLOW_HTTP_APP_URL:-true}"
	ENABLE_GEOLOCATION="${ENABLE_GEOLOCATION:-true}"
	ENABLE_FIREBASE_MESSAGING="${ENABLE_FIREBASE_MESSAGING:-false}"
	ENABLE_PLAY_FLEXIBLE_UPDATES="${ENABLE_PLAY_FLEXIBLE_UPDATES:-false}"
	NATIVE_GOOGLE_AUTH_ENABLED="${NATIVE_GOOGLE_AUTH_ENABLED:-false}"
	USER_AGENT_SUFFIX="${USER_AGENT_SUFFIX:-ZulorsLocalApp}"
fi

WEB_MEDIA_PERMISSIONS="${WEB_MEDIA_PERMISSIONS:-    <uses-permission android:name=\"android.permission.CAMERA\" />
    <uses-permission android:name=\"android.permission.RECORD_AUDIO\" />
    <uses-feature android:name=\"android.hardware.camera\" android:required=\"false\" />
    <uses-feature android:name=\"android.hardware.microphone\" android:required=\"false\" />}"

if [ "$ENABLE_GEOLOCATION" = "true" ]; then
	LOCATION_PERMISSIONS="${LOCATION_PERMISSIONS:-    <uses-permission android:name=\"android.permission.ACCESS_FINE_LOCATION\" />
    <uses-permission android:name=\"android.permission.ACCESS_COARSE_LOCATION\" />}"
else
	LOCATION_PERMISSIONS="${LOCATION_PERMISSIONS:-}"
fi

if [ "$ENABLE_FIREBASE_MESSAGING" = "true" ] && [ ! -f "$GOOGLE_SERVICES_JSON" ]; then
	echo "Firebase Messaging is enabled, but google-services.json was not found at: $GOOGLE_SERVICES_JSON" >&2
	exit 1
fi

GRADLE_BIN="${GRADLE:-}"

if [ -z "$GRADLE_BIN" ] && command -v gradle >/dev/null 2>&1; then
	GRADLE_BIN="$(command -v gradle)"
fi

if [ -z "$GRADLE_BIN" ]; then
	GRADLE_BIN="$(find "$HOME/.gradle/wrapper/dists" -type f -path '*/gradle-8*/bin/gradle' | sort -V | tail -1 || true)"
fi

if [ -z "$GRADLE_BIN" ] || [ ! -x "$GRADLE_BIN" ]; then
	echo "Gradle was not found. Set GRADLE=/path/to/gradle or install/use an Android Studio Gradle wrapper." >&2
	exit 1
fi

mkdir -p "$BUILD/res"
cp -R "$APP_DIR/res/." "$BUILD/res/"

escape_sed() {
	printf '%s' "$1" | sed -e 's/[\/&]/\\&/g'
}

gradle_string_literal() {
	local escaped
	escaped="$(printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"
	printf '"\\"%s\\""' "$escaped"
}

gradle_plain_string() {
	printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/\\\\'/g"
}

sed \
	-e "s/__USES_CLEARTEXT__/$(escape_sed "$USES_CLEARTEXT")/g" \
	-e "/__WEB_MEDIA_PERMISSIONS__/{
		r /dev/stdin
		d
	}" \
	"$APP_DIR/AndroidManifest.template.xml" <<EOF > "$BUILD/AndroidManifest.partial.xml"
$WEB_MEDIA_PERMISSIONS
EOF

sed \
	-e "/__LOCATION_PERMISSIONS__/{
		r /dev/stdin
		d
	}" \
	"$BUILD/AndroidManifest.partial.xml" <<EOF > "$BUILD/AndroidManifest.xml"
$LOCATION_PERMISSIONS
EOF

sed "s/Zulors/$APP_LABEL/g" "$APP_DIR/res/values/strings.xml" > "$BUILD/res/values/strings.xml"

if [ ! -f "$KEYSTORE" ]; then
	"$JDK_HOME/bin/keytool" \
		-genkeypair \
		-keystore "$KEYSTORE" \
		-storepass android \
		-keypass android \
		-alias androiddebugkey \
		-dname "CN=Android Debug,O=Android,C=US" \
		-validity 10000 \
		-keyalg RSA \
		-keysize 2048 \
		>/dev/null 2>&1
fi

if [ "$BUILD_TYPE" = "release" ]; then
	if [ -z "${RELEASE_STORE_PASSWORD:-}" ] || [ -z "${RELEASE_KEY_PASSWORD:-}" ]; then
		echo "Release builds require RELEASE_STORE_PASSWORD and RELEASE_KEY_PASSWORD." >&2
		echo "Set RELEASE_KEYSTORE and RELEASE_KEY_ALIAS too if you do not want the defaults." >&2
		exit 1
	fi

	if [ ! -f "$RELEASE_KEYSTORE" ]; then
		if [ "$CREATE_RELEASE_KEYSTORE" != "true" ]; then
			echo "Release keystore was not found: $RELEASE_KEYSTORE" >&2
			echo "Create it first, or set CREATE_RELEASE_KEYSTORE=true for the first upload key build." >&2
			exit 1
		fi

		mkdir -p "$(dirname "$RELEASE_KEYSTORE")"
		"$JDK_HOME/bin/keytool" \
			-genkeypair \
			-keystore "$RELEASE_KEYSTORE" \
			-storetype "$RELEASE_KEYSTORE_TYPE" \
			-storepass "$RELEASE_STORE_PASSWORD" \
			-keypass "$RELEASE_KEY_PASSWORD" \
			-alias "$RELEASE_KEY_ALIAS" \
			-dname "$RELEASE_CERT_DNAME" \
			-validity "$RELEASE_KEY_VALIDITY_DAYS" \
			-keyalg RSA \
			-keysize 2048 \
			>/dev/null
	fi
fi

GRADLE_PROJECT="$BUILD/gradle"
GRADLE_APP="$GRADLE_PROJECT/app"

mkdir -p "$GRADLE_APP/src/main/java/com/zulors/app" "$GRADLE_APP/src/main/res"
cp "$BUILD/AndroidManifest.xml" "$GRADLE_APP/src/main/AndroidManifest.xml"
cp -R "$BUILD/res/." "$GRADLE_APP/src/main/res/"
find "$APP_DIR/src" -name '*.java' -exec cp {} "$GRADLE_APP/src/main/java/com/zulors/app/" \;

if [ "$ENABLE_FIREBASE_MESSAGING" = "true" ]; then
	cp "$GOOGLE_SERVICES_JSON" "$GRADLE_APP/google-services.json"
fi

cat > "$GRADLE_PROJECT/settings.gradle" <<'EOF'
pluginManagement {
    repositories {
        google()
        mavenCentral()
        gradlePluginPortal()
    }
}

dependencyResolutionManagement {
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {
        google()
        mavenCentral()
    }
}

rootProject.name = 'ZulorsAndroidPreview'
include ':app'
EOF

SDK_FIELD="$(gradle_plain_string "$SDK")"

cat > "$GRADLE_PROJECT/local.properties" <<EOF
sdk.dir=$SDK_FIELD
EOF

cat > "$GRADLE_PROJECT/gradle.properties" <<'EOF'
android.useAndroidX=true
android.enableJetifier=false
org.gradle.jvmargs=-Xmx2048m -Dfile.encoding=UTF-8
EOF

cat > "$GRADLE_PROJECT/build.gradle" <<EOF
plugins {
    id 'com.android.application' version '$ANDROID_GRADLE_PLUGIN_VERSION' apply false
    id 'com.google.gms.google-services' version '$GOOGLE_SERVICES_PLUGIN_VERSION' apply false
}
EOF

APP_URL_FIELD="$(gradle_string_literal "$APP_URL")"
TRUSTED_HOST_FIELD="$(gradle_string_literal "$TRUSTED_HOST")"
USER_AGENT_SUFFIX_FIELD="$(gradle_string_literal "$USER_AGENT_SUFFIX")"
GOOGLE_WEB_CLIENT_ID_FIELD="$(gradle_string_literal "$GOOGLE_WEB_CLIENT_ID")"
KEYSTORE_FIELD="$(gradle_plain_string "$KEYSTORE")"
VERSION_NAME_FIELD="$(gradle_plain_string "$VERSION_NAME")"

SIGNING_CONFIGS="        debug {
            storeFile file('$KEYSTORE_FIELD')
            storePassword 'android'
            keyAlias 'androiddebugkey'
            keyPassword 'android'
        }"

BUILD_TYPES="        debug {
            signingConfig signingConfigs.debug
        }"

if [ "$BUILD_TYPE" = "release" ]; then
	RELEASE_KEYSTORE_FIELD="$(gradle_plain_string "$RELEASE_KEYSTORE")"
	RELEASE_STORE_PASSWORD_FIELD="$(gradle_plain_string "$RELEASE_STORE_PASSWORD")"
	RELEASE_KEYSTORE_TYPE_FIELD="$(gradle_plain_string "$RELEASE_KEYSTORE_TYPE")"
	RELEASE_KEY_ALIAS_FIELD="$(gradle_plain_string "$RELEASE_KEY_ALIAS")"
	RELEASE_KEY_PASSWORD_FIELD="$(gradle_plain_string "$RELEASE_KEY_PASSWORD")"

	SIGNING_CONFIGS="$SIGNING_CONFIGS

        release {
            storeFile file('$RELEASE_KEYSTORE_FIELD')
            storeType '$RELEASE_KEYSTORE_TYPE_FIELD'
            storePassword '$RELEASE_STORE_PASSWORD_FIELD'
            keyAlias '$RELEASE_KEY_ALIAS_FIELD'
            keyPassword '$RELEASE_KEY_PASSWORD_FIELD'
        }"

	BUILD_TYPES="$BUILD_TYPES

        release {
            signingConfig signingConfigs.release
            minifyEnabled false
            shrinkResources false
        }"
fi

cat > "$GRADLE_APP/build.gradle" <<EOF
plugins {
    id 'com.android.application'
}

if (file('google-services.json').exists()) {
    apply plugin: 'com.google.gms.google-services'
}

android {
    namespace 'com.zulors.app'
    compileSdk $PLATFORM_API

    defaultConfig {
        applicationId '$APP_ID'
        minSdk $MIN_SDK
        targetSdk $TARGET_SDK
        versionCode $VERSION_CODE
        versionName '$VERSION_NAME_FIELD'
    }

    signingConfigs {
$SIGNING_CONFIGS
    }

    buildTypes {
$BUILD_TYPES
    }

    buildFeatures {
        buildConfig true
    }

    compileOptions {
        sourceCompatibility JavaVersion.VERSION_17
        targetCompatibility JavaVersion.VERSION_17
    }

    defaultConfig {
        buildConfigField 'String', 'APP_URL', $APP_URL_FIELD
        buildConfigField 'String', 'TRUSTED_HOST', $TRUSTED_HOST_FIELD
        buildConfigField 'String', 'USER_AGENT_SUFFIX', $USER_AGENT_SUFFIX_FIELD
        buildConfigField 'boolean', 'DEBUG_WEBVIEW', '$DEBUG_WEBVIEW'
        buildConfigField 'boolean', 'NO_CACHE', '$NO_CACHE'
        buildConfigField 'boolean', 'ALLOW_MIXED_CONTENT', '$ALLOW_MIXED_CONTENT'
        buildConfigField 'boolean', 'ALLOW_HTTP_APP_URL', '$ALLOW_HTTP_APP_URL'
        buildConfigField 'boolean', 'ENABLE_GEOLOCATION', '$ENABLE_GEOLOCATION'
        buildConfigField 'boolean', 'ENABLE_FIREBASE_MESSAGING', '$ENABLE_FIREBASE_MESSAGING'
        buildConfigField 'boolean', 'ENABLE_PLAY_FLEXIBLE_UPDATES', '$ENABLE_PLAY_FLEXIBLE_UPDATES'
        buildConfigField 'boolean', 'NATIVE_GOOGLE_AUTH_ENABLED', '$NATIVE_GOOGLE_AUTH_ENABLED'
        buildConfigField 'String', 'GOOGLE_WEB_CLIENT_ID', $GOOGLE_WEB_CLIENT_ID_FIELD
    }
}

dependencies {
    implementation 'io.agora.rtc:voice-sdk:$AGORA_VOICE_SDK_VERSION'
    implementation 'com.google.android.play:app-update:$PLAY_APP_UPDATE_VERSION'
    implementation 'androidx.activity:activity:$ANDROIDX_ACTIVITY_VERSION'
    implementation 'androidx.fragment:fragment:$ANDROIDX_FRAGMENT_VERSION'
    implementation 'androidx.core:core-splashscreen:$ANDROIDX_SPLASHSCREEN_VERSION'
    implementation 'com.google.firebase:firebase-messaging:$FIREBASE_MESSAGING_VERSION'
    implementation 'androidx.credentials:credentials:$CREDENTIALS_VERSION'
    implementation 'androidx.credentials:credentials-play-services-auth:$CREDENTIALS_VERSION'
    implementation 'com.google.android.libraries.identity.googleid:googleid:$GOOGLE_ID_VERSION'
}
EOF

"$GRADLE_BIN" \
	--no-daemon \
	--project-dir "$GRADLE_PROJECT" \
	":app:$GRADLE_TASK"

mkdir -p "$APP_DIR/build/latest"
if [ "$ARTIFACT_TYPE" = "bundle" ]; then
	OUTPUT_NAME="zulors-${APP_MODE}-${BUILD_TYPE}.aab"
	OUTPUT_SOURCE="$GRADLE_APP/build/outputs/bundle/$BUILD_TYPE/app-$BUILD_TYPE.aab"
else
	OUTPUT_NAME="zulors-${APP_MODE}-${BUILD_TYPE}.apk"
	OUTPUT_SOURCE="$GRADLE_APP/build/outputs/apk/$BUILD_TYPE/app-$BUILD_TYPE.apk"
fi

cp "$OUTPUT_SOURCE" "$APP_DIR/build/latest/$OUTPUT_NAME"
cp "$BUILD/AndroidManifest.xml" "$APP_DIR/build/latest/AndroidManifest.$APP_MODE.xml"

echo "$APP_DIR/build/latest/$OUTPUT_NAME"
