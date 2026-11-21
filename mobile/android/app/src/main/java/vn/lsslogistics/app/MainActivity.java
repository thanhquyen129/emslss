package vn.lsslogistics.app;

import android.os.Bundle;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;
import com.onesignal.Continue;
import com.onesignal.OneSignal;
import com.onesignal.debug.LogLevel;
import com.onesignal.notifications.INotificationClickEvent;
import com.onesignal.notifications.INotificationClickListener;
import org.json.JSONObject;

public class MainActivity extends BridgeActivity {
    private String pendingOpenUrl = null;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(EmsPushPlugin.class);
        super.onCreate(savedInstanceState);

        String appId = getString(R.string.onesignal_app_id);
        if (appId != null && !appId.isEmpty() && !appId.startsWith("REPLACE")) {
            OneSignal.getDebug().setLogLevel(LogLevel.WARN);
            OneSignal.initWithContext(this, appId);
            OneSignal.getNotifications().requestPermission(true, Continue.none());
            OneSignal.getNotifications().addClickListener(new INotificationClickListener() {
                @Override
                public void onClick(INotificationClickEvent event) {
                    String target = resolveOpenUrl(event);
                    if (target != null && !target.isEmpty()) {
                        openInApp(target);
                    }
                }
            });
        }
    }

    @Override
    public void onResume() {
        super.onResume();
        if (pendingOpenUrl != null) {
            openInApp(pendingOpenUrl);
        }
    }

    private String resolveOpenUrl(INotificationClickEvent event) {
        try {
            JSONObject data = event.getNotification().getAdditionalData();
            if (data == null) {
                return defaultOrdersUrl();
            }
            String openUrl = data.optString("open_url", "");
            if (openUrl != null && !openUrl.isEmpty()) {
                return openUrl;
            }
            String path = data.optString("open_path", "");
            if (path != null && !path.isEmpty()) {
                if (path.startsWith("http://") || path.startsWith("https://")) {
                    return path;
                }
                String base = getString(R.string.app_web_base_url);
                if (!path.startsWith("/")) {
                    path = "/" + path;
                }
                return base + path;
            }
            String type = data.optString("type", "");
            if ("new_order".equals(type)) {
                return defaultOrdersUrl();
            }
        } catch (Exception ignored) {
        }
        return defaultOrdersUrl();
    }

    private String defaultOrdersUrl() {
        return getString(R.string.app_web_base_url) + "/modules/admin/admin_orders.php";
    }

    private void openInApp(String url) {
        runOnUiThread(() -> {
            try {
                if (getBridge() != null) {
                    WebView webView = getBridge().getWebView();
                    if (webView != null) {
                        webView.loadUrl(url);
                        pendingOpenUrl = null;
                        return;
                    }
                }
            } catch (Exception ignored) {
            }
            pendingOpenUrl = url;
        });
    }
}
