/**
 * Bridge OneSignal trong Capacitor WebView.
 * Gọi EmsPush.identify(userId, role) + đăng ký về server.
 */
(function () {
  var cfg = window.EMSLSS_PUSH || {};
  if (!cfg.userId) return;

  function isNative() {
    try {
      return !!(window.Capacitor && Capacitor.isNativePlatform && Capacitor.isNativePlatform());
    } catch (e) {
      return false;
    }
  }

  function getPlugin() {
    try {
      if (window.Capacitor && Capacitor.Plugins && Capacitor.Plugins.EmsPush) {
        return Capacitor.Plugins.EmsPush;
      }
    } catch (e) {}
    return null;
  }

  function registerServer(subscriptionId) {
    var url = cfg.registerUrl || '/modules/push_register.php';
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        subscription_id: subscriptionId || '',
        platform: (cfg.platform || 'android')
      })
    }).catch(function () {});
  }

  function run() {
    if (!isNative()) return;
    var plugin = getPlugin();
    if (!plugin) {
      registerServer('');
      return;
    }
    var payload = {
      userId: String(cfg.userId),
      role: String(cfg.role || '')
    };
    Promise.resolve(plugin.identify(payload))
      .then(function () {
        if (plugin.requestPermission) {
          return plugin.requestPermission({});
        }
      })
      .then(function () {
        if (plugin.getSubscriptionId) {
          return plugin.getSubscriptionId({});
        }
      })
      .then(function (res) {
        var sid = res && (res.subscriptionId || res.value) ? (res.subscriptionId || res.value) : '';
        registerServer(sid);
      })
      .catch(function () {
        registerServer('');
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
