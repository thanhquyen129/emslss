package vn.lsslogistics.app;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;
import com.onesignal.OneSignal;
import com.onesignal.debug.LogLevel;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(EmsPushPlugin.class);
        super.onCreate(savedInstanceState);

        String appId = getString(R.string.onesignal_app_id);
        if (appId != null && !appId.isEmpty() && !appId.startsWith("REPLACE")) {
            OneSignal.getDebug().setLogLevel(LogLevel.WARN);
            OneSignal.initWithContext(this, appId);
        }
    }
}
