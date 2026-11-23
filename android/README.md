# GodAnime Native Android Web App

A lightweight, high-performance, battery-friendly native Android Web App for **GodAnime** (`https://godanime2.vercel.app`).

---

## Features

- **Blazing Fast WebView Engine**: Optimized with hardware acceleration, DOM storage, database caching, and responsive viewport sizing.
- **HTML5 Fullscreen Video**: Custom `WebChromeClient` automatically rotates anime video playback to immersive landscape and hides system status/navigation bars.
- **Native Android Download Manager**: Tapping download buttons directly initiates downloads through Android's native `DownloadManager` with notification progress, saving files directly to the user's `Downloads` directory (`Environment.DIRECTORY_DOWNLOADS`).
- **Swipe-To-Refresh**: GodAnime orange themed pull-to-refresh (`SwipeRefreshLayout`).
- **Offline Fallback UI**: Graceful offline detection with a "Retry Connection" button when internet connectivity is lost.
- **Avatar Upload Support**: Native Android file picker integration (`onShowFileChooser`) allowing users to easily choose photos for their profile picture.
- **Smart Back Navigation**: Back button navigates through anime pages history; double-tap back within 2 seconds confirms exiting the app.
- **GodAnime Theme**: Status bar and navigation bar stay dark (`#0C0D10`), perfectly matching GodAnime's Black + Orange design.

---

## How to Build the APK

### Prerequisites
- [Android Studio Iguana+](https://developer.android.com/studio) or command-line Gradle with Android SDK 34 installed.
- JDK 17 or higher.

### Option 1: Using Android Studio (Recommended)
1. Open Android Studio.
2. Select **File > Open...** and choose the `android/` directory in this repository.
3. Allow Gradle to sync.
4. Connect an Android phone with USB debugging enabled, or launch an Android emulator.
5. Click **Run > Run 'app'** (or press `Shift + F10`).
6. To generate a signed release APK: **Build > Generate Signed Bundle / APK... > APK**.

### Option 2: Using the Command Line
```bash
cd android

# Build debug APK
./gradlew assembleDebug

# Output APK path:
# android/app/build/outputs/apk/debug/app-debug.apk

# Build release APK
./gradlew assembleRelease

# Output APK path:
# android/app/build/outputs/apk/release/app-release-unsigned.apk
```

---

## Changing the Target URL
The default URL is set to `https://godanime2.vercel.app/home`.  
To change this, open `android/app/src/main/java/com/godanime/app/MainActivity.java` and modify:
```java
public static final String DEFAULT_URL = "https://godanime2.vercel.app/home";
```
