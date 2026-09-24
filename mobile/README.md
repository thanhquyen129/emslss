# EMS-LSS Mobile (Capacitor WebView + OneSignal)

URL: `https://lsslogistics.vn`  
Package: `vn.lsslogistics.app`

## OneSignal — bắt buộc điền key

Dashboard login bị reCAPTCHA nên agent không lấy được key tự động. Bạn làm tay:

1. Vào [OneSignal Dashboard](https://dashboard.onesignal.com/) → tạo/chọn app **EMS-LSS** (Android).
2. Cấu hình **Firebase (FCM)** theo hướng dẫn OneSignal (upload service account).
3. **Settings → Keys & IDs** lấy:
   - **OneSignal App ID**
   - **REST API Key**
4. Điền:
   - App: `android/app/src/main/res/values/strings.xml` → `onesignal_app_id`
   - Server: env `EMSLSS_ONESIGNAL_APP_ID` + `EMSLSS_ONESIGNAL_REST_API_KEY`  
     hoặc sửa fallback trong `config/system.php` → `onesignal`

## Luồng push đơn mới

1. Admin mở app → login web → bridge gọi `EmsPush.identify(userId, role=admin)`.
2. EMS gọi `api/ems_push_order.php` → sau khi tạo đơn, server gửi OneSignal tới external_id admin + tag `role=admin`.

## Build APK

```bash
cd mobile
npm install
npx cap sync android
export ANDROID_HOME=$HOME/Android/Sdk
export JAVA_HOME=$(dirname $(dirname $(readlink -f $(which java))))  # hoặc path JDK
cd android && ./gradlew assembleDebug
```

APK: `android/app/build/outputs/apk/debug/app-debug.apk`
