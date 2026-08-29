package com.zulors.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import android.util.Log;
import android.webkit.CookieManager;
import android.webkit.WebView;

import com.google.android.gms.tasks.OnCompleteListener;
import com.google.android.gms.tasks.Task;
import com.google.firebase.messaging.FirebaseMessaging;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public final class PushTokenBridge {
    private static final String TAG = "ZulorsPush";
    private static final String PREFS = "zulors_push";
    private static final String KEY_TOKEN = "fcm_token";
    private static final String KEY_SYNC_SIGNATURE = "sync_signature";
    private static final String KEY_LAST_ATTEMPT_MS = "last_attempt_ms";
    private static final long RETRY_INTERVAL_MS = 15000;
    private static final Object SYNC_LOCK = new Object();
    private static final ExecutorService EXECUTOR = Executors.newSingleThreadExecutor();
    private static boolean syncInFlight = false;

    private PushTokenBridge() {
    }

    public static void start(final Context context) {
        start(context, null);
    }

    public static void start(final Context context, final WebView webView) {
        if (!BuildConfig.ENABLE_FIREBASE_MESSAGING) {
            return;
        }

        FirebaseMessaging.getInstance().getToken().addOnCompleteListener(new OnCompleteListener<String>() {
            @Override
            public void onComplete(Task<String> task) {
                if (!task.isSuccessful() || isBlank(task.getResult())) {
                    Log.w(TAG, "FCM token is not available yet.");
                    return;
                }

                saveToken(context, task.getResult());
                syncLatestToken(context, webView);
            }
        });
    }

    public static void saveToken(Context context, String token) {
        if (isBlank(token)) {
            return;
        }

        prefs(context).edit().putString(KEY_TOKEN, token.trim()).apply();
    }

    public static void syncLatestToken(Context context) {
        // WebView session cookies are HttpOnly, so token registration is done from the page context.
    }

    public static void syncLatestToken(Context context, WebView webView) {
        if (!BuildConfig.ENABLE_FIREBASE_MESSAGING) {
            return;
        }

        if (webView == null || !isTrustedWebUrl(webView.getUrl())) {
            return;
        }

        final SharedPreferences prefs = prefs(context.getApplicationContext());
        final String token = prefs.getString(KEY_TOKEN, null);

        if (isBlank(token)) {
            return;
        }

        long now = System.currentTimeMillis();

        if ((now - prefs.getLong(KEY_LAST_ATTEMPT_MS, 0)) < RETRY_INTERVAL_MS) {
            return;
        }

        synchronized (SYNC_LOCK) {
            if (syncInFlight) {
                return;
            }

            syncInFlight = true;
        }

        prefs.edit().putLong(KEY_LAST_ATTEMPT_MS, now).apply();

        final String syncSignature = sha256(token + "|" + BuildConfig.VERSION_NAME);
        final String script = "(function(){try{" +
            "var token=" + jsString(token) + ";" +
            "var signature=" + jsString(syncSignature) + ";" +
            "if(window.localStorage&&window.localStorage.getItem('zulors_push_sync_signature')===signature){return;}" +
            "var csrfMatch=document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);" +
            "var csrf=csrfMatch?decodeURIComponent(csrfMatch[1]):'';" +
            "if(!csrf){return;}" +
            "fetch('/api/settings/devices/push-token',{" +
                "method:'POST'," +
                "credentials:'same-origin'," +
                "headers:{" +
                    "'Accept':'application/json'," +
                    "'Content-Type':'application/json'," +
                    "'X-Requested-With':'XMLHttpRequest'," +
                    "'X-XSRF-TOKEN':csrf" +
                "}," +
                "body:JSON.stringify({" +
                    "token:token," +
                    "provider:'fcm'," +
                    "platform:'android'," +
                    "device_id:" + jsString(deviceId(context)) + "," +
                    "device_name:" + jsString(deviceName()) + "," +
                    "app_version:" + jsString(BuildConfig.VERSION_NAME) +
                "})" +
            "}).then(function(response){" +
                "if(response.ok&&window.localStorage){window.localStorage.setItem('zulors_push_sync_signature',signature);}" +
            "}).catch(function(){});" +
        "}catch(error){}})();";

        webView.post(new Runnable() {
            @Override
            public void run() {
                try {
                    webView.evaluateJavascript(script, null);
                    if (prefs.getString(KEY_SYNC_SIGNATURE, null) == null) {
                        prefs.edit().putString(KEY_SYNC_SIGNATURE, syncSignature).apply();
                    }
                }
                finally {
                    synchronized (SYNC_LOCK) {
                        syncInFlight = false;
                    }
                }
            }
        });
    }

    private static boolean isTrustedWebUrl(String url) {
        if (isBlank(url)) {
            return false;
        }

        try {
            Uri uri = Uri.parse(url);
            String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase(Locale.US);
            String host = uri.getHost() == null ? "" : uri.getHost().toLowerCase(Locale.US);
            String trustedHost = BuildConfig.TRUSTED_HOST.toLowerCase(Locale.US);

            return ("https".equals(scheme) || (BuildConfig.ALLOW_HTTP_APP_URL && "http".equals(scheme)))
                && (host.equals(trustedHost) || host.endsWith("." + trustedHost) || (BuildConfig.ALLOW_HTTP_APP_URL && "127.0.0.1".equals(host)));
        }
        catch (Exception exception) {
            return false;
        }
    }

    private static String jsString(String value) {
        return JSONObject.quote(value == null ? "" : value);
    }

    private static boolean postToken(Context context, String token, String cookies) {
        HttpURLConnection connection = null;

        try {
            URL url = new URL(apiUrl("/api/settings/devices/push-token"));
            connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("POST");
            connection.setConnectTimeout(10000);
            connection.setReadTimeout(10000);
            connection.setDoOutput(true);
            connection.setRequestProperty("Accept", "application/json");
            connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            connection.setRequestProperty("X-Requested-With", "XMLHttpRequest");
            connection.setRequestProperty("User-Agent", BuildConfig.USER_AGENT_SUFFIX);
            connection.setRequestProperty("Cookie", cookies);

            String csrfToken = cookieValue(cookies, "XSRF-TOKEN");

            if (!isBlank(csrfToken)) {
                connection.setRequestProperty("X-XSRF-TOKEN", csrfToken);
            }

            JSONObject payload = new JSONObject();
            payload.put("token", token);
            payload.put("provider", "fcm");
            payload.put("platform", "android");
            payload.put("device_id", deviceId(context));
            payload.put("device_name", deviceName());
            payload.put("app_version", BuildConfig.VERSION_NAME);

            byte[] body = payload.toString().getBytes(StandardCharsets.UTF_8);
            connection.setFixedLengthStreamingMode(body.length);

            try (OutputStream outputStream = connection.getOutputStream()) {
                outputStream.write(body);
            }

            int responseCode = connection.getResponseCode();
            consumeResponse(connection);

            if (responseCode >= 200 && responseCode < 300) {
                Log.i(TAG, "FCM token synced.");
                return true;
            }

            Log.w(TAG, "FCM token sync failed with HTTP " + responseCode + ".");
        }
        catch (Exception exception) {
            Log.w(TAG, "FCM token sync failed: " + exception.getClass().getName() + ": " + exception.getMessage());
        }
        finally {
            if (connection != null) {
                connection.disconnect();
            }
        }

        return false;
    }

    private static String apiUrl(String path) {
        String baseUrl = BuildConfig.APP_URL;

        if (baseUrl.endsWith("/")) {
            baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
        }

        return baseUrl + path;
    }

    private static String cookieValue(String cookies, String name) {
        String[] pairs = cookies.split(";");

        for (String pair : pairs) {
            String[] parts = pair.trim().split("=", 2);

            if (parts.length == 2 && name.equals(parts[0])) {
                try {
                    return URLDecoder.decode(parts[1], "UTF-8");
                }
                catch (Exception exception) {
                    return parts[1];
                }
            }
        }

        return null;
    }

    private static String deviceId(Context context) {
        try {
            String androidId = Settings.Secure.getString(context.getContentResolver(), Settings.Secure.ANDROID_ID);
            return isBlank(androidId) ? null : androidId;
        }
        catch (Exception exception) {
            return null;
        }
    }

    private static String deviceName() {
        return String.format(Locale.US, "%s %s", Build.MANUFACTURER, Build.MODEL).trim();
    }

    private static SharedPreferences prefs(Context context) {
        return context.getApplicationContext().getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    private static String sha256(String value) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(value.getBytes(StandardCharsets.UTF_8));
            StringBuilder builder = new StringBuilder();

            for (byte item : hash) {
                builder.append(String.format(Locale.US, "%02x", item));
            }

            return builder.toString();
        }
        catch (Exception exception) {
            return String.valueOf(value.hashCode());
        }
    }

    private static void consumeResponse(HttpURLConnection connection) {
        InputStream stream = null;

        try {
            stream = connection.getResponseCode() >= 400 ? connection.getErrorStream() : connection.getInputStream();

            if (stream == null) {
                return;
            }

            BufferedReader reader = new BufferedReader(new InputStreamReader(stream, StandardCharsets.UTF_8));

            while (reader.readLine() != null) {
                // Consume the response so Android can reuse the connection.
            }
        }
        catch (Exception exception) {
            // Response body is not needed for token sync decisions.
        }
        finally {
            if (stream != null) {
                try {
                    stream.close();
                }
                catch (Exception exception) {
                    // Nothing else to release.
                }
            }
        }
    }

    private static boolean isBlank(String value) {
        return value == null || value.trim().isEmpty();
    }
}
