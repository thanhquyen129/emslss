package vn.lsslogistics.app;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.onesignal.Continue;
import com.onesignal.OneSignal;
import com.onesignal.user.subscriptions.IPushSubscription;

@CapacitorPlugin(name = "EmsPush")
public class EmsPushPlugin extends Plugin {

    @PluginMethod
    public void identify(PluginCall call) {
        String userId = call.getString("userId", "");
        String role = call.getString("role", "");

        if (userId != null && !userId.isEmpty()) {
            OneSignal.login(userId);
        }
        if (role != null && !role.isEmpty()) {
            OneSignal.getUser().addTag("role", role);
        }

        JSObject ret = new JSObject();
        ret.put("userId", userId);
        ret.put("role", role);
        call.resolve(ret);
    }

    @PluginMethod
    public void requestPermission(PluginCall call) {
        try {
            OneSignal.getNotifications().requestPermission(true, Continue.with(result -> {
                JSObject ret = new JSObject();
                boolean accepted = result.isSuccess() && Boolean.TRUE.equals(result.getData());
                ret.put("accepted", accepted);
                call.resolve(ret);
            }));
        } catch (Exception e) {
            JSObject ret = new JSObject();
            ret.put("accepted", false);
            call.resolve(ret);
        }
    }

    @PluginMethod
    public void getSubscriptionId(PluginCall call) {
        String id = "";
        try {
            IPushSubscription sub = OneSignal.getUser().getPushSubscription();
            if (sub != null && sub.getId() != null) {
                id = sub.getId();
            }
        } catch (Exception ignored) {
        }
        JSObject ret = new JSObject();
        ret.put("subscriptionId", id);
        call.resolve(ret);
    }

    @PluginMethod
    public void logout(PluginCall call) {
        try {
            OneSignal.logout();
        } catch (Exception ignored) {
        }
        call.resolve();
    }
}
