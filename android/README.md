# GodAnime Android App (GodAnime.apk)

Official lightweight, high-performance native Android Web App for **GodAnime** (`https://godanime2.vercel.app`).

---

## 🚀 How to Download the GodAnime APK

### Method 1: Instant Download from GitHub (Easiest - No Android Studio Needed)
Because we have automated GitHub Actions set up on this repository:
1. Go to your repository on GitHub: **[https://github.com/GodxD69/Anipaca](https://github.com/GodxD69/Anipaca)** (or your fork).
2. Click on the **Actions** tab at the top.
3. Click on the latest workflow run named **"Build GodAnime APK"**.
4. Scroll down to the **Artifacts** section at the bottom of the page.
5. Click **GodAnime-APK** to download `GodAnime.zip`.
6. Extract the zip file on your phone or PC — inside you will find **`GodAnime.apk`**!
7. Tap `GodAnime.apk` on your Android phone to install.

---

### Method 2: Build Locally using Android Studio
1. Open [Android Studio](https://developer.android.com/studio).
2. Select **File > Open...** and navigate to the `android/` directory inside this repository.
3. Wait a moment for Gradle to sync.
4. Plug in your Android phone with USB Debugging enabled, or start an Android Emulator.
5. Click **Run > Run 'app'** (or press `Shift + F10`).
6. To export the APK file directly:
   - Go to **Build > Build Bundle(s) / APK(s) > Build APK(s)**.
   - When finished, click **locate** in the popup notification to get your `GodAnime.apk`.

---

### Method 3: Build via Command Line
```bash
cd android

# Linux / Mac
chmod +x gradlew
./gradlew assembleDebug

# Windows (Command Prompt / PowerShell)
gradlew.bat assembleDebug
```
The compiled APK will be located at:
`android/app/build/outputs/apk/debug/GodAnime.apk`

---

## ✨ Features Included

- **Custom GodAnime App Icon**: Pre-configured with the official GodAnime brand logo across all Android screen densities.
- **HTML5 Fullscreen Video Engine**: Anime player seamlessly rotates to immersive fullscreen landscape upon tapping the fullscreen button.
- **Native Android Download Manager**: Streaming downloads save straight into your Android device's `Downloads` folder with real-time download notification progress.
- **Edge-to-Edge Dark Interface**: Matching GodAnime's Black + Orange theme (`#0C0D10` and `#FF6B00`).
- **Swipe-to-Refresh**: Native pull-down to refresh with GodAnime orange accent.
- **Offline Recovery Screen**: Shows a clean retry button if you temporarily lose internet connection.
