package com.zulors.app;

import android.annotation.SuppressLint;
import android.Manifest;
import android.app.Activity;
import android.app.NotificationManager;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.ActivityInfo;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.Insets;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.media.AudioAttributes;
import android.media.AudioDeviceCallback;
import android.media.AudioDeviceInfo;
import android.media.AudioFocusRequest;
import android.media.AudioManager;
import android.media.Ringtone;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.CancellationSignal;
import android.os.Handler;
import android.os.Looper;
import android.os.PowerManager;
import android.os.SystemClock;
import android.util.Log;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.WindowInsets;
import android.view.WindowManager;
import android.window.OnBackInvokedCallback;
import android.window.OnBackInvokedDispatcher;
import android.webkit.CookieManager;
import android.webkit.GeolocationPermissions;
import android.webkit.JavascriptInterface;
import android.webkit.JsResult;
import android.webkit.PermissionRequest;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.Toast;

import androidx.credentials.Credential;
import androidx.credentials.CredentialManager;
import androidx.credentials.CredentialManagerCallback;
import androidx.credentials.CustomCredential;
import androidx.credentials.GetCredentialRequest;
import androidx.credentials.GetCredentialResponse;
import androidx.credentials.exceptions.GetCredentialException;
import androidx.core.splashscreen.SplashScreen;

import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption;
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential;
import com.google.android.play.core.appupdate.AppUpdateInfo;
import com.google.android.play.core.appupdate.AppUpdateManager;
import com.google.android.play.core.appupdate.AppUpdateManagerFactory;
import com.google.android.play.core.appupdate.AppUpdateOptions;
import com.google.android.play.core.install.InstallState;
import com.google.android.play.core.install.InstallStateUpdatedListener;
import com.google.android.play.core.install.model.AppUpdateType;
import com.google.android.play.core.install.model.InstallStatus;
import com.google.android.play.core.install.model.UpdateAvailability;

import org.json.JSONObject;
import org.json.JSONArray;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.Executor;

public class MainActivity extends Activity {
    public static final String EXTRA_PUSH_URL = "zulors_url";
    private static final String TAG = "ZulorsApp";
    private static final String LIGHT_CHROME_COLOR = "#FFFFFF";
    private static final String DARK_CHROME_COLOR = "#111111";

    private static final int FILE_CHOOSER_REQUEST = 42;
    private static final int WEB_PERMISSION_REQUEST = 43;
    private static final int GEOLOCATION_PERMISSION_REQUEST = 44;
    private static final int NOTIFICATION_PERMISSION_REQUEST = 45;
    private static final int FLEXIBLE_UPDATE_REQUEST = 46;
    private static final String AUDIO_ROUTE_EARPIECE = "earpiece";
    private static final String AUDIO_ROUTE_SPEAKER = "speaker";
    private static final String AUDIO_ROUTE_WIRED = "wired";
    private static final String AUDIO_ROUTE_BLUETOOTH = "bluetooth";
    private static final String AUDIO_ROUTE_UNKNOWN = "unknown";
    private static final String APP_UPDATE_PREFS = "zulors_play_updates";
    private static final String PREF_LAST_FLEXIBLE_UPDATE_PROMPT_AT = "last_flexible_update_prompt_at";
    private static final String PREF_LAST_FLEXIBLE_UPDATE_VERSION = "last_flexible_update_version";
    private static final long STARTUP_SPLASH_MAX_HOLD_MS = 1200L;
    private static final long DEFERRED_STARTUP_TASK_DELAY_MS = 180L;
    private static final long FLEXIBLE_UPDATE_CHECK_DELAY_MS = 1800L;
    private static final long FLEXIBLE_UPDATE_PROMPT_COOLDOWN_MS = 6L * 60L * 60L * 1000L;
    private static final long FLEXIBLE_UPDATE_INSTALL_DELAY_MS = 1200L;
    private static final long DUPLICATE_NOTIFICATION_LAUNCH_WINDOW_MS = 1800L;
    private static final int FILE_CHOOSER_FLAGS =
        Intent.FLAG_GRANT_READ_URI_PERMISSION |
        Intent.FLAG_GRANT_WRITE_URI_PERMISSION |
        Intent.FLAG_GRANT_PERSISTABLE_URI_PERMISSION;

    private WebView webView;
    private ValueCallback<Uri[]> filePathCallback;
    private PermissionRequest pendingPermissionRequest;
    private String[] pendingPermissionResources;
    private String pendingGeolocationOrigin;
    private GeolocationPermissions.Callback pendingGeolocationCallback;
    private View customView;
    private WebChromeClient.CustomViewCallback customViewCallback;
    private FrameLayout rootLayout;
    private boolean backNavigationPending = false;
    private boolean nativeGoogleSignInInProgress = false;
    private String nativeGoogleSignInSource = "google_button";
    private String nativeGoogleServerClientId = "";
    private String nativeGoogleSignInProvider = "credential_manager";
    private OnBackInvokedCallback backInvokedCallback;
    private AudioManager audioManager;
    private AudioFocusRequest callAudioFocusRequest;
    private AudioFocusRequest ringtoneAudioFocusRequest;
    private Ringtone activeCallRingtone;
    private boolean callAudioModeActive = false;
    private int previousAudioMode = AudioManager.MODE_NORMAL;
    private boolean previousSpeakerphoneOn = false;
    private boolean currentSpeakerphoneOn = false;
    private String currentCommunicationAudioRoute = AUDIO_ROUTE_EARPIECE;
    private boolean legacyBluetoothScoActive = false;
    private Runnable pendingSpeakerRouteRunnable;
    private CredentialManager credentialManager;
    private Handler mainHandler;
    private Executor mainExecutor;
    private ZulorsCallSessionManager callSessionManager;
    private AudioDeviceCallback communicationAudioDeviceCallback;
    private boolean communicationAudioDeviceCallbackRegistered = false;
    private PowerManager.WakeLock proximityWakeLock;
    private AppUpdateManager appUpdateManager;
    private InstallStateUpdatedListener flexibleUpdateListener;
    private boolean flexibleUpdateFlowStarted = false;
    private boolean flexibleUpdateReadyToInstall = false;
    private boolean flexibleUpdateDeferredToastShown = false;
    private boolean appInForeground = false;
    private boolean startupSplashVisible = true;
    private boolean startupSplashReleased = false;
    private boolean startupFirstPageCommitted = false;
    private boolean startupAppShellReady = false;
    private boolean deferredStartupTasksStarted = false;
    private Runnable deferredStartupTasksRunnable;
    private long startupStartedAtMs = 0L;
    private String startupSplashReleaseReason = "pending";
    private String lastNotificationLaunchUrl = null;
    private long lastNotificationLaunchAtMs = 0L;
    private String lastGoogleCallbackUrl = null;
    private long lastGoogleCallbackAtMs = 0L;
    private final ZulorsCallSessionManager.EventSink nativeCallEventSink =
        new ZulorsCallSessionManager.EventSink() {
            @Override
            public void onNativeCallEvent(String type, JSONObject payload) {
                dispatchNativeCallEvent(type, payload);
            }
        };
    private final AudioManager.OnAudioFocusChangeListener callAudioFocusChangeListener =
        new AudioManager.OnAudioFocusChangeListener() {
            @Override
            public void onAudioFocusChange(int focusChange) {
                // WebRTC keeps its own media state; Android focus only improves routing.
            }
        };
    private final AudioManager.OnAudioFocusChangeListener ringtoneAudioFocusChangeListener =
        new AudioManager.OnAudioFocusChangeListener() {
            @Override
            public void onAudioFocusChange(int focusChange) {
                if (focusChange == AudioManager.AUDIOFOCUS_LOSS) {
                    stopNativeCallRingtone();
                }
            }
        };
    private static final class SafeAreaCssInsets {
        final int left;
        final int top;
        final int right;
        final int bottom;

        SafeAreaCssInsets(int left, int top, int right, int bottom) {
            this.left = left;
            this.top = top;
            this.right = right;
            this.bottom = bottom;
        }
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        startupStartedAtMs = SystemClock.elapsedRealtime();
        SplashScreen splashScreen = SplashScreen.Companion.installSplashScreen(this);
        super.onCreate(savedInstanceState);
        setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_PORTRAIT);
        splashScreen.setKeepOnScreenCondition(() -> startupSplashVisible);
        recordStartupEvent("launch_started");

        WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG_WEBVIEW);
        audioManager = (AudioManager) getSystemService(Context.AUDIO_SERVICE);
        credentialManager = CredentialManager.create(this);
        mainHandler = new Handler(Looper.getMainLooper());
        mainExecutor = new Executor() {
            @Override
            public void execute(Runnable command) {
                mainHandler.post(command);
            }
        };
        callSessionManager = ZulorsCallSessionManager.getInstance(getApplicationContext());
        callSessionManager.attachEventSink(nativeCallEventSink);
        callSessionManager.setAppVisibility(true);
        currentSpeakerphoneOn = callSessionManager.isSpeakerEnabled();
        currentCommunicationAudioRoute = callSessionManager.getAudioRoute();
        configureSystemChrome();

        rootLayout = new FrameLayout(this);
        rootLayout.setFitsSystemWindows(false);
        rootLayout.setBackgroundColor(Color.parseColor(LIGHT_CHROME_COLOR));
        rootLayout.setPadding(0, 0, 0, 0);
        rootLayout.setClipChildren(false);
        rootLayout.setClipToPadding(false);
        rootLayout.setOnApplyWindowInsetsListener(new View.OnApplyWindowInsetsListener() {
            @Override
            public WindowInsets onApplyWindowInsets(View view, WindowInsets insets) {
                installAndroidViewportGuards(webView);

                return insets;
            }
        });

        webView = new WebView(this);
        webView.setFitsSystemWindows(false);
        webView.setBackgroundColor(Color.parseColor(LIGHT_CHROME_COLOR));
        webView.setPadding(0, 0, 0, 0);
        webView.setClipChildren(false);
        webView.setClipToPadding(false);
        webView.setHorizontalScrollBarEnabled(false);
        webView.setVerticalScrollBarEnabled(false);

        FrameLayout.LayoutParams webViewLayoutParams = new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        );
        webViewLayoutParams.setMargins(0, 0, 0, 0);
        rootLayout.addView(webView, webViewLayoutParams);

        setContentView(rootLayout);
        rootLayout.requestApplyInsets();
        applySystemChrome(false);
        configureWebView();
        seedCookies();
        prepareFreshPreview();
        configureBackNavigation();
        scheduleStartupSplashTimeout();
        String launchUrl = resolveLaunchUrl(getIntent());

        if (hasLaunchUrl(getIntent())) {
            Uri launchUri = Uri.parse(launchUrl);

            if (isGoogleCallbackUrl(launchUri)) {
                shouldLoadGoogleCallback(launchUrl);
            }
            else {
                rememberNotificationLaunchUrl(launchUrl);
            }
        }

        webView.loadUrl(launchUrl, noCacheHeaders());
        recordStartupEvent("webview_load_requested");
    }

    private void scheduleStartupSplashTimeout() {
        if (mainHandler == null) {
            return;
        }

        mainHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                releaseStartupSplash("timeout");
            }
        }, STARTUP_SPLASH_MAX_HOLD_MS);
    }

    private void releaseStartupSplash(String reason) {
        if (startupSplashReleased) {
            return;
        }

        startupSplashReleased = true;
        startupSplashVisible = false;
        startupSplashReleaseReason = reason == null ? "unknown" : reason;
        recordStartupEvent("splash_released", startupSplashReleaseReason);
        scheduleDeferredStartupTasks(DEFERRED_STARTUP_TASK_DELAY_MS);
    }

    private void scheduleDeferredStartupTasks(long delayMs) {
        if (deferredStartupTasksStarted) {
            return;
        }

        deferredStartupTasksStarted = true;

        if (mainHandler == null) {
            runDeferredStartupTasks();
            return;
        }

        deferredStartupTasksRunnable = new Runnable() {
            @Override
            public void run() {
                deferredStartupTasksRunnable = null;
                runDeferredStartupTasks();
            }
        };

        mainHandler.postDelayed(deferredStartupTasksRunnable, Math.max(0L, delayMs));
    }

    private void runDeferredStartupTasks() {
        recordStartupEvent("deferred_startup_tasks_started");
        requestNotificationPermission();
        ZulorsTelecomCallManager.registerPhoneAccount(this);
        configureFlexibleAppUpdates();
        clearZulorsNotifications();
        PushTokenBridge.start(this, webView);
        PushTokenBridge.syncLatestToken(this, webView);
    }

    private void recordStartupEvent(String name) {
        recordStartupEvent(name, null);
    }

    private void recordStartupEvent(String name, String detail) {
        if (name == null || name.trim().isEmpty()) {
            return;
        }

        long elapsedMs = startupStartedAtMs <= 0L
            ? 0L
            : Math.max(0L, SystemClock.elapsedRealtime() - startupStartedAtMs);
        String suffix = detail == null || detail.trim().isEmpty() ? "" : " [" + detail + "]";

        Log.d(TAG, "startup:" + name + " @" + elapsedMs + "ms" + suffix);
    }

    private void configureSystemChrome() {
        Window window = getWindow();

        window.setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            window.setDecorFitsSystemWindows(false);
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            window.setStatusBarContrastEnforced(false);
            window.setNavigationBarContrastEnforced(false);
        }

        applySystemChrome(false);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            WindowManager.LayoutParams layoutParams = window.getAttributes();
            layoutParams.layoutInDisplayCutoutMode = WindowManager.LayoutParams.LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES;
            window.setAttributes(layoutParams);
        }
    }

    private void applySystemChrome(boolean isDarkTheme) {
        Window window = getWindow();
        View decorView = window.getDecorView();
        int fallbackChromeColor = Color.parseColor(isDarkTheme ? DARK_CHROME_COLOR : LIGHT_CHROME_COLOR);
        int flags = View.SYSTEM_UI_FLAG_LAYOUT_STABLE |
            View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN |
            View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION;

        if (!isDarkTheme && Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flags |= View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR;
        }

        if (!isDarkTheme && Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            flags |= View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR;
        }

        decorView.setSystemUiVisibility(flags);
        window.setBackgroundDrawable(new ColorDrawable(fallbackChromeColor));
        window.setStatusBarColor(Color.TRANSPARENT);
        window.setNavigationBarColor(Color.TRANSPARENT);

        if (rootLayout != null) {
            rootLayout.setBackgroundColor(fallbackChromeColor);
        }

        if (webView != null) {
            webView.setBackgroundColor(fallbackChromeColor);
        }
    }

    private void seedCookies() {
        CookieManager cookieManager = CookieManager.getInstance();

        cookieManager.setAcceptCookie(true);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(webView, true);
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.flush();
        }
    }

    private void prepareFreshPreview() {
        if (!BuildConfig.NO_CACHE) {
            return;
        }

        webView.clearCache(true);
        webView.clearHistory();
        webView.clearFormData();
    }

    private Map<String, String> noCacheHeaders() {
        Map<String, String> headers = new HashMap<>();

        if (BuildConfig.NO_CACHE) {
            headers.put("Cache-Control", "no-cache, no-store, must-revalidate");
            headers.put("Pragma", "no-cache");
        }

        return headers;
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void configureWebView() {
        WebSettings settings = webView.getSettings();

        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setTextZoom(100);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setLoadsImagesAutomatically(true);
        settings.setBlockNetworkImage(false);
        settings.setCacheMode(BuildConfig.NO_CACHE ? WebSettings.LOAD_NO_CACHE : WebSettings.LOAD_DEFAULT);
        settings.setGeolocationEnabled(true);
        settings.setMixedContentMode(BuildConfig.ALLOW_MIXED_CONTENT ? WebSettings.MIXED_CONTENT_ALWAYS_ALLOW : WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setUserAgentString(settings.getUserAgentString() + " " + BuildConfig.USER_AGENT_SUFFIX);
        webView.setInitialScale(100);
        webView.addJavascriptInterface(new CallAudioBridge(), "ZulorsCallAudio");
        webView.addJavascriptInterface(new StartupBridge(), "ZulorsStartup");
        webView.addJavascriptInterface(new NativeAuthBridge(), "ZulorsNativeAuth");

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            settings.setOffscreenPreRaster(true);
        }

        webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            webView.setRendererPriorityPolicy(WebView.RENDERER_PRIORITY_IMPORTANT, true);
        }

        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        webView.setScrollBarStyle(View.SCROLLBARS_INSIDE_OVERLAY);

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri = request.getUrl();

                if (shouldStartNativeGoogleSignIn(uri)) {
                    startNativeGoogleSignIn("webview");
                    return true;
                }

                if (isTrustedWebUrl(uri)) {
                    return false;
                }

                openExternalUrl(uri);

                return true;
            }

            @SuppressWarnings("deprecation")
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                Uri uri = Uri.parse(url);

                if (shouldStartNativeGoogleSignIn(uri)) {
                    startNativeGoogleSignIn("webview");
                    return true;
                }

                if (isTrustedWebUrl(uri)) {
                    return false;
                }

                openExternalUrl(uri);

                return true;
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                recordStartupEvent("page_started", url);
            }

            @Override
            public void onPageCommitVisible(WebView view, String url) {
                super.onPageCommitVisible(view, url);
                startupFirstPageCommitted = true;
                recordStartupEvent("page_commit_visible", url);
                releaseStartupSplash("page_commit_visible");
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                recordStartupEvent("page_finished", url);
                installAndroidViewportGuards(view);
                syncSystemChromeWithPage(view);

                if (!startupFirstPageCommitted) {
                    releaseStartupSplash("page_finished");
                }

                if (deferredStartupTasksStarted) {
                    PushTokenBridge.syncLatestToken(MainActivity.this, view);
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onJsAlert(WebView view, String url, String message, JsResult result) {
                if (isRateLimitAlert(message)) {
                    Toast.makeText(MainActivity.this, "Please wait a moment and try again.", Toast.LENGTH_SHORT).show();
                    result.confirm();

                    return true;
                }

                return super.onJsAlert(view, url, message, result);
            }

            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> filePath, FileChooserParams params) {
                if (filePathCallback != null) {
                    filePathCallback.onReceiveValue(null);
                }

                filePathCallback = filePath;

                Intent intent = params.createIntent();
                prepareFileChooserIntent(intent, params);

                try {
                    startActivityForResult(intent, FILE_CHOOSER_REQUEST);
                }
                catch (ActivityNotFoundException exception) {
                    try {
                        startActivityForResult(createFallbackFileChooserIntent(params), FILE_CHOOSER_REQUEST);
                    }
                    catch (ActivityNotFoundException fallbackException) {
                        filePathCallback = null;
                        return false;
                    }
                }

                return true;
            }

            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) {
                    return;
                }

                runOnUiThread(new Runnable() {
                    @Override
                    public void run() {
                        handleWebPermissionRequest(request);
                    }
                });
            }

            @Override
            public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
                if (!BuildConfig.ENABLE_GEOLOCATION) {
                    callback.invoke(origin, false, false);
                    return;
                }

                if (hasAnyPermission(new String[] {
                    Manifest.permission.ACCESS_FINE_LOCATION,
                    Manifest.permission.ACCESS_COARSE_LOCATION
                })) {
                    callback.invoke(origin, true, false);
                    return;
                }

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    pendingGeolocationOrigin = origin;
                    pendingGeolocationCallback = callback;
                    requestPermissions(new String[] {
                        Manifest.permission.ACCESS_FINE_LOCATION,
                        Manifest.permission.ACCESS_COARSE_LOCATION
                    }, GEOLOCATION_PERMISSION_REQUEST);
                    return;
                }

                callback.invoke(origin, false, false);
            }

            @Override
            public void onShowCustomView(View view, CustomViewCallback callback) {
                if (customView != null) {
                    callback.onCustomViewHidden();
                    return;
                }

                customView = view;
                customViewCallback = callback;
                rootLayout.addView(customView, new FrameLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT,
                    ViewGroup.LayoutParams.MATCH_PARENT
                ));
                webView.setVisibility(View.GONE);
            }

            @Override
            public void onHideCustomView() {
                hideCustomView();
            }
        });
    }

    private boolean isRateLimitAlert(String message) {
        if (message == null) {
            return false;
        }

        String normalizedMessage = message.toLowerCase();

        return normalizedMessage.contains("too many attempts") ||
            normalizedMessage.contains("rate limit") ||
            normalizedMessage.contains("please wait a moment");
    }

    private void configureBackNavigation() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return;
        }

        backInvokedCallback = new OnBackInvokedCallback() {
            @Override
            public void onBackInvoked() {
                handleBackNavigation();
            }
        };

        getOnBackInvokedDispatcher().registerOnBackInvokedCallback(
            OnBackInvokedDispatcher.PRIORITY_DEFAULT,
            backInvokedCallback
        );
    }

    private String resolveLaunchUrl(Intent intent) {
        if (intent == null) {
            return BuildConfig.APP_URL;
        }

        String url = intent.getStringExtra(EXTRA_PUSH_URL);

        if (url == null) {
            url = intent.getStringExtra("url");
        }

        if (url == null && intent.getData() != null) {
            url = intent.getDataString();
        }

        if (url != null) {
            try {
                Uri uri = Uri.parse(url);

                if (isTrustedWebUrl(uri)) {
                    return url;
                }
            }
            catch (Exception exception) {
                return BuildConfig.APP_URL;
            }
        }

        return BuildConfig.APP_URL;
    }

    private boolean hasLaunchUrl(Intent intent) {
        return intent != null && (intent.hasExtra(EXTRA_PUSH_URL) || intent.hasExtra("url") || intent.getData() != null);
    }

    private boolean isTrustedWebUrl(Uri uri) {
        if (uri == null || uri.getScheme() == null || uri.getHost() == null) {
            return false;
        }

        String scheme = uri.getScheme().toLowerCase();
        String host = uri.getHost().toLowerCase();
        String trustedHost = BuildConfig.TRUSTED_HOST.toLowerCase();

        if (!scheme.equals("https") && !BuildConfig.ALLOW_HTTP_APP_URL) {
            return false;
        }

        if (BuildConfig.ALLOW_HTTP_APP_URL && scheme.equals("http") && host.equals("127.0.0.1")) {
            return true;
        }

        return host.equals(trustedHost) || host.endsWith("." + trustedHost);
    }

    private boolean shouldStartNativeGoogleSignIn(Uri uri) {
        if (!BuildConfig.NATIVE_GOOGLE_AUTH_ENABLED || uri == null || !isTrustedWebUrl(uri)) {
            return false;
        }

        return "/social-login/auth/google".equals(uri.getPath());
    }

    private boolean isGoogleCallbackUrl(Uri uri) {
        return uri != null
            && isTrustedWebUrl(uri)
            && "/social-login/callback/google".equals(uri.getPath())
            && !uri.getQueryParameterNames().isEmpty();
    }

    private boolean shouldLoadGoogleCallback(String url) {
        long now = SystemClock.elapsedRealtime();

        if (url != null && url.equals(lastGoogleCallbackUrl)
            && now - lastGoogleCallbackAtMs < DUPLICATE_NOTIFICATION_LAUNCH_WINDOW_MS) {
            return false;
        }

        lastGoogleCallbackUrl = url;
        lastGoogleCallbackAtMs = now;

        return true;
    }

    private String resolveNativeGoogleServerClientId(String requestedServerClientId) {
        String candidate = requestedServerClientId == null ? "" : requestedServerClientId.trim();

        if (!candidate.isEmpty()) {
            return candidate;
        }

        String configuredClientId = BuildConfig.GOOGLE_WEB_CLIENT_ID == null
            ? ""
            : BuildConfig.GOOGLE_WEB_CLIENT_ID.trim();

        if (!configuredClientId.isEmpty()) {
            return configuredClientId;
        }

        return resolveGoogleServicesWebClientId();
    }

    private String resolveGoogleServicesWebClientId() {
        try {
            int resourceId = getResources().getIdentifier("default_web_client_id", "string", getPackageName());

            if (resourceId == 0) {
                return "";
            }

            String clientId = getString(resourceId);

            return clientId == null ? "" : clientId.trim();
        }
        catch (Exception exception) {
            return "";
        }
    }

    private boolean isNativeGoogleSignInSupported(String requestedServerClientId) {
        String serverClientId = resolveNativeGoogleServerClientId(requestedServerClientId);

        return BuildConfig.NATIVE_GOOGLE_AUTH_ENABLED
            && !serverClientId.isEmpty();
    }

    private boolean startNativeGoogleSignIn(String source) {
        return startNativeGoogleSignIn(source, null);
    }

    private boolean startNativeGoogleSignIn(String source, String requestedServerClientId) {
        if (nativeGoogleSignInInProgress) {
            return false;
        }

        String serverClientId = resolveNativeGoogleServerClientId(requestedServerClientId);

        if (!isNativeGoogleSignInSupported(serverClientId)) {
            finishNativeGoogleSignInWithFailure("Google sign in is not available right now. Please try again later.");
            return false;
        }

        nativeGoogleSignInInProgress = true;
        nativeGoogleSignInSource = source == null || source.trim().isEmpty() ? "google_button" : source;
        nativeGoogleServerClientId = serverClientId;
        nativeGoogleSignInProvider = "credential_manager";
        dispatchNativeGoogleAuthEvent("started", nativeGoogleSignInSource, null);

        requestNativeGoogleCredential();

        return true;
    }

    private void requestNativeGoogleCredential() {
        if (!nativeGoogleSignInInProgress) {
            return;
        }

        if (credentialManager == null) {
            finishNativeGoogleSignInWithFailure("Google sign in is not available on this device. Please use email login.");
            return;
        }

        final GetCredentialRequest credentialRequest = buildNativeGoogleCredentialRequest(nativeGoogleServerClientId);

        runOnUiThread(new Runnable() {
            @Override
            public void run() {
                Toast.makeText(MainActivity.this, "Signing in with Google...", Toast.LENGTH_SHORT).show();

                credentialManager.getCredentialAsync(
                    MainActivity.this,
                    credentialRequest,
                    new CancellationSignal(),
                    mainExecutor,
                    new CredentialManagerCallback<GetCredentialResponse, GetCredentialException>() {
                        @Override
                        public void onResult(GetCredentialResponse result) {
                            handleNativeGoogleCredential(result);
                        }

                        @Override
                        public void onError(GetCredentialException exception) {
                            handleNativeGoogleCredentialError(exception);
                        }
                    }
                );
            }
        });
    }

    private GetCredentialRequest buildNativeGoogleCredentialRequest(String serverClientId) {
        GetCredentialRequest.Builder requestBuilder = new GetCredentialRequest.Builder();

        GetSignInWithGoogleOption googleOption = new GetSignInWithGoogleOption.Builder(serverClientId)
            .build();

        return requestBuilder.addCredentialOption(googleOption).build();
    }

    private void handleNativeGoogleCredentialError(GetCredentialException exception) {
        String exceptionName = exception == null ? "" : exception.getClass().getSimpleName();
        String exceptionMessage = exception == null ? "" : String.valueOf(exception.getMessage());
        String normalizedMessage = exceptionMessage == null ? "" : exceptionMessage.toLowerCase();

        Log.w(TAG, "Native Google sign in failed: " + exceptionName + " " + exceptionMessage);

        if (normalizedMessage.contains("reauth failed")) {
            finishNativeGoogleSignInWithFailure("Google account verification failed on this device. Please update Google Play Services or try the account again.");
            return;
        }

        if (exceptionName.contains("Cancellation")) {
            finishNativeGoogleSignInCancelled("Google sign in was cancelled.");
            return;
        }

        if (exceptionName.contains("NoCredential")) {
            finishNativeGoogleSignInWithFailure("No Google account is available. Add an account or use email login.");
            return;
        }

        if (exceptionName.contains("Unsupported") || exceptionName.contains("ProviderConfiguration")) {
            finishNativeGoogleSignInWithFailure("Google sign in is not available on this device. Please use email login.");
            return;
        }

        finishNativeGoogleSignInWithFailure("Google sign in could not start. Please try again.");
    }

    private void handleNativeGoogleCredential(GetCredentialResponse response) {
        if (response == null) {
            finishNativeGoogleSignInWithFailure("Google sign in failed. Please try again.");
            return;
        }

        Credential credential = response.getCredential();

        if (!(credential instanceof CustomCredential)) {
            finishNativeGoogleSignInWithFailure("Google sign in failed. Please try again.");
            return;
        }

        CustomCredential customCredential = (CustomCredential) credential;

        if (!GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL.equals(customCredential.getType())) {
            finishNativeGoogleSignInWithFailure("Google sign in failed. Please try again.");
            return;
        }

        try {
            GoogleIdTokenCredential googleIdTokenCredential = GoogleIdTokenCredential.createFrom(customCredential.getData());
            String idToken = googleIdTokenCredential.getIdToken();

            if (idToken == null || idToken.trim().isEmpty()) {
                finishNativeGoogleSignInWithFailure("Google sign in did not return a usable token.");
                return;
            }

            sendNativeGoogleTokenToServer(idToken);
        }
        catch (Exception exception) {
            finishNativeGoogleSignInWithFailure("Google sign in failed. Please try again.");
        }
    }

    private void sendNativeGoogleTokenToServer(final String idToken) {
        new Thread(new Runnable() {
            @Override
            public void run() {
                HttpURLConnection connection = null;

                try {
                    URL url = new URL(resolveAppUrl("/api/auth/google"));
                    connection = (HttpURLConnection) url.openConnection();
                    connection.setRequestMethod("POST");
                    connection.setConnectTimeout(10000);
                    connection.setReadTimeout(15000);
                    connection.setDoOutput(true);
                    connection.setRequestProperty("Accept", "application/json");
                    connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
                    connection.setRequestProperty("User-Agent", System.getProperty("http.agent", "") + " " + BuildConfig.USER_AGENT_SUFFIX);

                    JSONObject payload = new JSONObject();
                    payload.put("id_token", idToken);

                    byte[] body = payload.toString().getBytes(StandardCharsets.UTF_8);
                    connection.setFixedLengthStreamingMode(body.length);

                    try (OutputStream outputStream = connection.getOutputStream()) {
                        outputStream.write(body);
                    }

                    int statusCode = connection.getResponseCode();
                    InputStream responseStream = statusCode >= 200 && statusCode < 300
                        ? connection.getInputStream()
                        : connection.getErrorStream();
                    String responseBody = readStream(responseStream);

                    if (statusCode < 200 || statusCode >= 300) {
                        throw new IllegalStateException(extractNativeGoogleErrorMessage(responseBody, "Google sign in failed. Please try again."));
                    }

                    JSONObject responseJson = new JSONObject(responseBody);
                    String redirectUrl = responseJson.optString("redirect_url", "");

                    if (redirectUrl.isEmpty()) {
                        throw new IllegalStateException("Native Google auth did not return a handoff URL.");
                    }

                    Uri redirectUri = Uri.parse(redirectUrl);

                    if (!isTrustedWebUrl(redirectUri)) {
                        throw new IllegalStateException("Google sign in returned an untrusted handoff URL.");
                    }

                    finishNativeGoogleSignInWithSuccess(redirectUrl);
                }
                catch (Exception exception) {
                    String message = exception == null || exception.getMessage() == null || exception.getMessage().trim().isEmpty()
                        ? "Google sign in failed. Please try again."
                        : exception.getMessage().trim();
                    finishNativeGoogleSignInWithFailure(message);
                }
                finally {
                    if (connection != null) {
                        connection.disconnect();
                    }
                }
            }
        }).start();
    }

    private String readStream(InputStream inputStream) throws Exception {
        if (inputStream == null) {
            return "";
        }

        StringBuilder builder = new StringBuilder();

        try (BufferedReader reader = new BufferedReader(new InputStreamReader(inputStream, StandardCharsets.UTF_8))) {
            String line;

            while ((line = reader.readLine()) != null) {
                builder.append(line);
            }
        }

        return builder.toString();
    }

    private String extractNativeGoogleErrorMessage(String responseBody, String fallbackMessage) {
        if (responseBody == null || responseBody.trim().isEmpty()) {
            return fallbackMessage;
        }

        try {
            JSONObject responseJson = new JSONObject(responseBody);
            JSONObject errors = responseJson.optJSONObject("errors");

            if (errors != null) {
                JSONArray googleErrors = errors.optJSONArray("google");

                if (googleErrors != null && googleErrors.length() > 0) {
                    String googleMessage = googleErrors.optString(0, "").trim();

                    if (!googleMessage.isEmpty()) {
                        return googleMessage;
                    }
                }
            }

            String message = responseJson.optString("message", "").trim();

            if (!message.isEmpty()) {
                return message;
            }
        }
        catch (Exception ignored) {}

        return fallbackMessage;
    }

    private void dispatchNativeGoogleAuthEvent(String state, String source, String message) {
        JSONObject payload = new JSONObject();

        try {
            payload.put("state", state == null ? "unknown" : state);

            if (source != null && !source.trim().isEmpty()) {
                payload.put("source", source);
            }

            if (message != null && !message.trim().isEmpty()) {
                payload.put("message", message);
            }
        }
        catch (Throwable ignored) {}

        dispatchJavascriptEvent("zulors:native-google-auth", payload.toString());
    }

    private void finishNativeGoogleSignInWithSuccess(final String redirectUrl) {
        runOnUiThread(new Runnable() {
            @Override
            public void run() {
                nativeGoogleSignInInProgress = false;
                dispatchNativeGoogleAuthEvent("success", "handoff", null);
                webView.loadUrl(redirectUrl, noCacheHeaders());
            }
        });
    }

    private void finishNativeGoogleSignInCancelled(final String message) {
        runOnUiThread(new Runnable() {
            @Override
            public void run() {
                nativeGoogleSignInInProgress = false;
                dispatchNativeGoogleAuthEvent("cancelled", nativeGoogleSignInProvider, message);

                if (message != null && !message.trim().isEmpty()) {
                    Toast.makeText(MainActivity.this, message, Toast.LENGTH_SHORT).show();
                }
            }
        });
    }

    private void finishNativeGoogleSignInWithFailure(final String message) {
        runOnUiThread(new Runnable() {
            @Override
            public void run() {
                nativeGoogleSignInInProgress = false;
                dispatchNativeGoogleAuthEvent("failed", nativeGoogleSignInProvider, message);
                Toast.makeText(MainActivity.this, message, Toast.LENGTH_SHORT).show();
            }
        });
    }

    private String resolveAppUrl(String path) {
        Uri baseUri = Uri.parse(BuildConfig.APP_URL);

        return baseUri.buildUpon()
            .encodedPath(path)
            .encodedQuery(null)
            .fragment(null)
            .build()
            .toString();
    }

    private void openExternalUrl(Uri uri) {
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        }
        catch (ActivityNotFoundException exception) {
            // Keep users inside the app if Android cannot resolve the external URL.
        }
    }

    private void requestNotificationPermission() {
        if (!BuildConfig.ENABLE_FIREBASE_MESSAGING || Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return;
        }

        if (checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[] {
                Manifest.permission.POST_NOTIFICATIONS
            }, NOTIFICATION_PERMISSION_REQUEST);
        }
    }

    private void configureFlexibleAppUpdates() {
        if (!BuildConfig.ENABLE_PLAY_FLEXIBLE_UPDATES) {
            return;
        }

        appUpdateManager = AppUpdateManagerFactory.create(this);
        flexibleUpdateListener = new InstallStateUpdatedListener() {
            @Override
            public void onStateUpdate(InstallState state) {
                handleFlexibleUpdateState(state);
            }
        };

        appUpdateManager.registerListener(flexibleUpdateListener);
        scheduleFlexibleUpdateCheck(FLEXIBLE_UPDATE_CHECK_DELAY_MS);
    }

    private void scheduleFlexibleUpdateCheck(long delayMs) {
        if (!BuildConfig.ENABLE_PLAY_FLEXIBLE_UPDATES || appUpdateManager == null || mainHandler == null) {
            return;
        }

        mainHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                checkForFlexibleAppUpdate();
            }
        }, Math.max(0L, delayMs));
    }

    private void checkForFlexibleAppUpdate() {
        if (!BuildConfig.ENABLE_PLAY_FLEXIBLE_UPDATES || appUpdateManager == null || flexibleUpdateFlowStarted) {
            return;
        }

        appUpdateManager.getAppUpdateInfo()
            .addOnSuccessListener(appUpdateInfo -> {
                if (appUpdateInfo.installStatus() == InstallStatus.DOWNLOADED) {
                    handleFlexibleUpdateDownloaded();
                    return;
                }

                if (appUpdateInfo.updateAvailability() != UpdateAvailability.UPDATE_AVAILABLE
                    || !appUpdateInfo.isUpdateTypeAllowed(AppUpdateType.FLEXIBLE)
                    || !shouldPromptForFlexibleUpdate(appUpdateInfo)) {
                    return;
                }

                startFlexibleUpdate(appUpdateInfo);
            })
            .addOnFailureListener(exception -> Log.w(TAG, "Unable to check Play flexible update", exception));
    }

    private boolean shouldPromptForFlexibleUpdate(AppUpdateInfo appUpdateInfo) {
        SharedPreferences preferences = getSharedPreferences(APP_UPDATE_PREFS, MODE_PRIVATE);
        int availableVersionCode = appUpdateInfo.availableVersionCode();
        int lastVersionCode = preferences.getInt(PREF_LAST_FLEXIBLE_UPDATE_VERSION, -1);
        long lastPromptAt = preferences.getLong(PREF_LAST_FLEXIBLE_UPDATE_PROMPT_AT, 0L);

        return availableVersionCode != lastVersionCode
            || System.currentTimeMillis() - lastPromptAt >= FLEXIBLE_UPDATE_PROMPT_COOLDOWN_MS;
    }

    private void recordFlexibleUpdatePrompt(AppUpdateInfo appUpdateInfo) {
        getSharedPreferences(APP_UPDATE_PREFS, MODE_PRIVATE)
            .edit()
            .putInt(PREF_LAST_FLEXIBLE_UPDATE_VERSION, appUpdateInfo.availableVersionCode())
            .putLong(PREF_LAST_FLEXIBLE_UPDATE_PROMPT_AT, System.currentTimeMillis())
            .apply();
    }

    private void startFlexibleUpdate(AppUpdateInfo appUpdateInfo) {
        if (appUpdateManager == null) {
            return;
        }

        AppUpdateOptions options = AppUpdateOptions.newBuilder(AppUpdateType.FLEXIBLE).build();

        try {
            flexibleUpdateFlowStarted = appUpdateManager.startUpdateFlowForResult(
                appUpdateInfo,
                this,
                options,
                FLEXIBLE_UPDATE_REQUEST
            );

            if (flexibleUpdateFlowStarted) {
                recordFlexibleUpdatePrompt(appUpdateInfo);
            }
        }
        catch (Exception exception) {
            flexibleUpdateFlowStarted = false;
            Log.w(TAG, "Unable to start Play flexible update", exception);
        }
    }

    private void handleFlexibleUpdateState(InstallState state) {
        if (state.installStatus() == InstallStatus.DOWNLOADED) {
            handleFlexibleUpdateDownloaded();
        }
        else if (state.installStatus() == InstallStatus.INSTALLED) {
            flexibleUpdateReadyToInstall = false;
            flexibleUpdateFlowStarted = false;
            unregisterFlexibleUpdateListener();
        }
        else if (state.installStatus() == InstallStatus.FAILED
            || state.installStatus() == InstallStatus.CANCELED) {
            flexibleUpdateReadyToInstall = false;
            flexibleUpdateFlowStarted = false;
            scheduleFlexibleUpdateCheck(FLEXIBLE_UPDATE_PROMPT_COOLDOWN_MS);
        }
    }

    private void handleFlexibleUpdateDownloaded() {
        flexibleUpdateReadyToInstall = true;
        flexibleUpdateDeferredToastShown = false;
        flexibleUpdateFlowStarted = false;
        completeFlexibleUpdateWhenSafe();
    }

    private void completeFlexibleUpdateWhenSafe() {
        if (!BuildConfig.ENABLE_PLAY_FLEXIBLE_UPDATES || appUpdateManager == null || !flexibleUpdateReadyToInstall) {
            return;
        }

        if (callAudioModeActive) {
            if (!flexibleUpdateDeferredToastShown) {
                flexibleUpdateDeferredToastShown = true;
                Toast.makeText(this, "Update downloaded. It will install after your call.", Toast.LENGTH_LONG).show();
            }

            mainHandler.postDelayed(new Runnable() {
                @Override
                public void run() {
                    completeFlexibleUpdateWhenSafe();
                }
            }, 30000L);
            return;
        }

        flexibleUpdateReadyToInstall = false;
        flexibleUpdateDeferredToastShown = false;

        if (appInForeground) {
            Toast.makeText(this, "Update downloaded. Restarting Zulors to install.", Toast.LENGTH_LONG).show();
        }

        mainHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                if (appUpdateManager == null) {
                    return;
                }

                appUpdateManager.completeUpdate()
                    .addOnFailureListener(exception -> {
                        flexibleUpdateReadyToInstall = true;
                        Log.w(TAG, "Unable to complete Play flexible update", exception);
                    });
            }
        }, appInForeground ? FLEXIBLE_UPDATE_INSTALL_DELAY_MS : 0L);
    }

    private void unregisterFlexibleUpdateListener() {
        if (appUpdateManager != null && flexibleUpdateListener != null) {
            appUpdateManager.unregisterListener(flexibleUpdateListener);
            flexibleUpdateListener = null;
        }
    }

    private void handleWebPermissionRequest(PermissionRequest request) {
        String[] resources = request.getResources();
        String[] androidPermissions = androidPermissionsForWebResources(resources);

        if (androidPermissions.length == 0 || hasAllPermissions(androidPermissions)) {
            request.grant(resources);

            if (hasWebResource(resources, PermissionRequest.RESOURCE_AUDIO_CAPTURE)) {
                scheduleEnterCommunicationAudioMode();
            }

            return;
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            pendingPermissionRequest = request;
            pendingPermissionResources = resources;
            requestPermissions(androidPermissions, WEB_PERMISSION_REQUEST);
            return;
        }

        request.deny();
    }

    private String[] androidPermissionsForWebResources(String[] resources) {
        Set<String> permissions = new HashSet<>();

        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP || resources == null) {
            return new String[0];
        }

        for (String resource : resources) {
            if (PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(resource)) {
                permissions.add(Manifest.permission.CAMERA);
            }
            else if (PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resource)) {
                permissions.add(Manifest.permission.RECORD_AUDIO);
            }
        }

        return permissions.toArray(new String[0]);
    }

    private boolean hasWebResource(String[] resources, String expectedResource) {
        if (resources == null || expectedResource == null) {
            return false;
        }

        for (String resource : resources) {
            if (expectedResource.equals(resource)) {
                return true;
            }
        }

        return false;
    }

    private void enterCommunicationAudioMode() {
        if (audioManager == null) {
            return;
        }

        stopNativeCallRingtone();

        if (!callAudioModeActive) {
            previousAudioMode = audioManager.getMode();
            previousSpeakerphoneOn = audioManager.isSpeakerphoneOn();
            currentSpeakerphoneOn = false;
            callAudioModeActive = true;
            requestCallAudioFocus();
        }

        audioManager.setMode(AudioManager.MODE_IN_COMMUNICATION);
        audioManager.setSpeakerphoneOn(currentSpeakerphoneOn);
        registerCommunicationAudioDeviceCallback();
        routeCommunicationAudio(currentSpeakerphoneOn);
        startNativeCallGuards();
    }

    private void scheduleEnterCommunicationAudioMode() {
        if (mainHandler == null) {
            enterCommunicationAudioMode();

            return;
        }

        mainHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                enterCommunicationAudioMode();
            }
        }, 80);
    }

    private void setSpeakerEnabledNative(boolean enabled) {
        currentSpeakerphoneOn = enabled;

        if (!callAudioModeActive) {
            currentCommunicationAudioRoute = enabled ? AUDIO_ROUTE_SPEAKER : AUDIO_ROUTE_EARPIECE;
        }

        if (audioManager == null) {
            return;
        }

        if (callAudioModeActive) {
            audioManager.setMode(AudioManager.MODE_IN_COMMUNICATION);
            audioManager.setSpeakerphoneOn(enabled);
            routeCommunicationAudio(enabled);
            scheduleCommunicationAudioRoute(enabled);
        }
    }

    private void exitCommunicationAudioMode() {
        stopNativeCallRingtone();
        stopNativeCallGuards();

        if (audioManager == null) {
            completeFlexibleUpdateWhenSafe();
            return;
        }

        cancelPendingSpeakerRoute();
        unregisterCommunicationAudioDeviceCallback();
        stopLegacyBluetoothSco();

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            audioManager.clearCommunicationDevice();
        }

        audioManager.setSpeakerphoneOn(previousSpeakerphoneOn);
        audioManager.setMode(previousAudioMode);
        abandonCallAudioFocus();
        callAudioModeActive = false;
        previousAudioMode = AudioManager.MODE_NORMAL;
        previousSpeakerphoneOn = false;
        currentSpeakerphoneOn = false;
        currentCommunicationAudioRoute = AUDIO_ROUTE_EARPIECE;
        completeFlexibleUpdateWhenSafe();
    }

    private void startNativeCallGuards() {
        startCallForegroundService();
        syncProximityWakeLock();
    }

    private void stopNativeCallGuards() {
        releaseProximityWakeLock();
        stopCallForegroundService();
    }

    private void startCallForegroundService() {
        if (callSessionManager != null) {
            callSessionManager.startForegroundCallService();
            return;
        }
    }

    private void stopCallForegroundService() {
        if (callSessionManager != null) {
            callSessionManager.stopForegroundCallService();
        }
    }

    private void acquireProximityWakeLock() {
        if (proximityWakeLock != null && proximityWakeLock.isHeld()) {
            return;
        }

        try {
            PowerManager powerManager = (PowerManager) getSystemService(Context.POWER_SERVICE);

            if (
                powerManager == null
                || !powerManager.isWakeLockLevelSupported(PowerManager.PROXIMITY_SCREEN_OFF_WAKE_LOCK)
            ) {
                return;
            }

            proximityWakeLock = powerManager.newWakeLock(
                PowerManager.PROXIMITY_SCREEN_OFF_WAKE_LOCK,
                "Zulors:ProximityWakeLock"
            );
            proximityWakeLock.setReferenceCounted(false);
            proximityWakeLock.acquire();
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to acquire proximity wake lock.", exception);
        }
    }

    private void syncProximityWakeLock() {
        if (callAudioModeActive && AUDIO_ROUTE_EARPIECE.equals(currentCommunicationAudioRoute)) {
            acquireProximityWakeLock();
            return;
        }

        releaseProximityWakeLock();
    }

    private void releaseProximityWakeLock() {
        try {
            if (proximityWakeLock != null && proximityWakeLock.isHeld()) {
                proximityWakeLock.release();
            }
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to release proximity wake lock.", exception);
        }

        proximityWakeLock = null;
    }

    private boolean hasRecordAudioPermission() {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.M
            || checkSelfPermission(Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED;
    }

    private void routeCommunicationAudio(boolean speakerEnabled) {
        if (audioManager == null) {
            return;
        }

        audioManager.setMode(AudioManager.MODE_IN_COMMUNICATION);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            stopLegacyBluetoothSco();

            AudioDeviceInfo communicationDevice = findPreferredCommunicationDevice(speakerEnabled);

            if (communicationDevice != null && audioManager.setCommunicationDevice(communicationDevice)) {
                String routeName = routeNameForAudioDevice(communicationDevice);
                audioManager.setSpeakerphoneOn(AUDIO_ROUTE_SPEAKER.equals(routeName));
                syncNativeAudioRoute(routeName);
                return;
            }

            audioManager.clearCommunicationDevice();
            audioManager.setSpeakerphoneOn(speakerEnabled);
        }

        if (!speakerEnabled && shouldPreferLegacyBluetoothRoute()) {
            startLegacyBluetoothSco();
            audioManager.setSpeakerphoneOn(false);
            syncNativeAudioRoute(AUDIO_ROUTE_BLUETOOTH);

            return;
        }

        stopLegacyBluetoothSco();
        audioManager.setSpeakerphoneOn(speakerEnabled);
        syncNativeAudioRoute(resolveLegacyRouteName(speakerEnabled));
    }

    private void scheduleCommunicationAudioRoute(final boolean speakerEnabled) {
        if (mainHandler == null) {
            routeCommunicationAudio(speakerEnabled);

            return;
        }

        cancelPendingSpeakerRoute();

        pendingSpeakerRouteRunnable = new Runnable() {
            @Override
            public void run() {
                pendingSpeakerRouteRunnable = null;
                routeCommunicationAudio(speakerEnabled);
            }
        };

        mainHandler.postDelayed(pendingSpeakerRouteRunnable, 140);
    }

    private void cancelPendingSpeakerRoute() {
        if (mainHandler != null && pendingSpeakerRouteRunnable != null) {
            mainHandler.removeCallbacks(pendingSpeakerRouteRunnable);
        }

        pendingSpeakerRouteRunnable = null;
    }

    private AudioDeviceInfo findCommunicationDevice(int deviceType) {
        if (audioManager == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
            return null;
        }

        for (AudioDeviceInfo device : audioManager.getAvailableCommunicationDevices()) {
            if (device.getType() == deviceType) {
                return device;
            }
        }

        return null;
    }

    private AudioDeviceInfo findPreferredCommunicationDevice(boolean speakerEnabled) {
        if (audioManager == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
            return null;
        }

        if (speakerEnabled) {
            AudioDeviceInfo speakerDevice = findCommunicationDevice(AudioDeviceInfo.TYPE_BUILTIN_SPEAKER);

            if (speakerDevice != null) {
                return speakerDevice;
            }
        }
        else {
            AudioDeviceInfo wiredDevice = firstCommunicationDeviceByType(
                AudioDeviceInfo.TYPE_WIRED_HEADSET,
                AudioDeviceInfo.TYPE_WIRED_HEADPHONES,
                AudioDeviceInfo.TYPE_USB_HEADSET,
                AudioDeviceInfo.TYPE_USB_DEVICE
            );

            if (wiredDevice != null) {
                return wiredDevice;
            }

            AudioDeviceInfo bluetoothDevice = firstCommunicationDeviceByType(
                AudioDeviceInfo.TYPE_BLUETOOTH_SCO,
                AudioDeviceInfo.TYPE_BLE_HEADSET
            );

            if (bluetoothDevice != null) {
                return bluetoothDevice;
            }

            AudioDeviceInfo earpieceDevice = findCommunicationDevice(AudioDeviceInfo.TYPE_BUILTIN_EARPIECE);

            if (earpieceDevice != null) {
                return earpieceDevice;
            }
        }

        return findCommunicationDevice(AudioDeviceInfo.TYPE_BUILTIN_SPEAKER);
    }

    private AudioDeviceInfo firstCommunicationDeviceByType(int... deviceTypes) {
        if (audioManager == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
            return null;
        }

        for (AudioDeviceInfo deviceTypeMatch : audioManager.getAvailableCommunicationDevices()) {
            for (int deviceType : deviceTypes) {
                if (deviceTypeMatch.getType() == deviceType) {
                    return deviceTypeMatch;
                }
            }
        }

        return null;
    }

    private void registerCommunicationAudioDeviceCallback() {
        if (
            audioManager == null
            || communicationAudioDeviceCallbackRegistered
            || Build.VERSION.SDK_INT < Build.VERSION_CODES.M
        ) {
            return;
        }

        if (communicationAudioDeviceCallback == null) {
            communicationAudioDeviceCallback = new AudioDeviceCallback() {
                @Override
                public void onAudioDevicesAdded(AudioDeviceInfo[] addedDevices) {
                    handleCommunicationDeviceInventoryChanged();
                }

                @Override
                public void onAudioDevicesRemoved(AudioDeviceInfo[] removedDevices) {
                    handleCommunicationDeviceInventoryChanged();
                }
            };
        }

        audioManager.registerAudioDeviceCallback(communicationAudioDeviceCallback, mainHandler);
        communicationAudioDeviceCallbackRegistered = true;
    }

    private void unregisterCommunicationAudioDeviceCallback() {
        if (
            audioManager == null
            || !communicationAudioDeviceCallbackRegistered
            || communicationAudioDeviceCallback == null
            || Build.VERSION.SDK_INT < Build.VERSION_CODES.M
        ) {
            communicationAudioDeviceCallbackRegistered = false;

            return;
        }

        audioManager.unregisterAudioDeviceCallback(communicationAudioDeviceCallback);
        communicationAudioDeviceCallbackRegistered = false;
    }

    private void handleCommunicationDeviceInventoryChanged() {
        if (!callAudioModeActive) {
            return;
        }

        scheduleCommunicationAudioRoute(currentSpeakerphoneOn);
    }

    private boolean shouldPreferLegacyBluetoothRoute() {
        return hasOutputDevice(AudioDeviceInfo.TYPE_BLUETOOTH_SCO, AudioDeviceInfo.TYPE_BLE_HEADSET);
    }

    private boolean hasWiredOutputDevice() {
        return hasOutputDevice(
            AudioDeviceInfo.TYPE_WIRED_HEADSET,
            AudioDeviceInfo.TYPE_WIRED_HEADPHONES,
            AudioDeviceInfo.TYPE_USB_HEADSET,
            AudioDeviceInfo.TYPE_USB_DEVICE
        );
    }

    private boolean hasOutputDevice(int... deviceTypes) {
        if (audioManager == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
            return false;
        }

        AudioDeviceInfo[] devices = audioManager.getDevices(AudioManager.GET_DEVICES_OUTPUTS);

        for (AudioDeviceInfo device : devices) {
            for (int deviceType : deviceTypes) {
                if (device.getType() == deviceType) {
                    return true;
                }
            }
        }

        return false;
    }

    private void startLegacyBluetoothSco() {
        if (audioManager == null || legacyBluetoothScoActive) {
            return;
        }

        try {
            audioManager.startBluetoothSco();
            audioManager.setBluetoothScoOn(true);
            legacyBluetoothScoActive = true;
        }
        catch (Throwable ignored) {}
    }

    private void stopLegacyBluetoothSco() {
        if (audioManager == null || !legacyBluetoothScoActive) {
            legacyBluetoothScoActive = false;

            return;
        }

        try {
            audioManager.setBluetoothScoOn(false);
            audioManager.stopBluetoothSco();
        }
        catch (Throwable ignored) {}

        legacyBluetoothScoActive = false;
    }

    private String resolveLegacyRouteName(boolean speakerEnabled) {
        if (speakerEnabled) {
            return AUDIO_ROUTE_SPEAKER;
        }

        if (hasWiredOutputDevice()) {
            return AUDIO_ROUTE_WIRED;
        }

        if (shouldPreferLegacyBluetoothRoute()) {
            return AUDIO_ROUTE_BLUETOOTH;
        }

        return AUDIO_ROUTE_EARPIECE;
    }

    private String routeNameForAudioDevice(AudioDeviceInfo device) {
        if (device == null) {
            return AUDIO_ROUTE_UNKNOWN;
        }

        int deviceType = device.getType();

        if (deviceType == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER) {
            return AUDIO_ROUTE_SPEAKER;
        }

        if (deviceType == AudioDeviceInfo.TYPE_BUILTIN_EARPIECE) {
            return AUDIO_ROUTE_EARPIECE;
        }

        if (
            deviceType == AudioDeviceInfo.TYPE_WIRED_HEADSET
            || deviceType == AudioDeviceInfo.TYPE_WIRED_HEADPHONES
            || deviceType == AudioDeviceInfo.TYPE_USB_HEADSET
            || deviceType == AudioDeviceInfo.TYPE_USB_DEVICE
        ) {
            return AUDIO_ROUTE_WIRED;
        }

        if (deviceType == AudioDeviceInfo.TYPE_BLUETOOTH_SCO || deviceType == AudioDeviceInfo.TYPE_BLE_HEADSET) {
            return AUDIO_ROUTE_BLUETOOTH;
        }

        return AUDIO_ROUTE_UNKNOWN;
    }

    private String normalizeAudioRouteName(String routeName) {
        if (routeName == null) {
            return AUDIO_ROUTE_EARPIECE;
        }

        String normalizedRoute = routeName.trim().toLowerCase();

        if (
            AUDIO_ROUTE_SPEAKER.equals(normalizedRoute)
            || AUDIO_ROUTE_WIRED.equals(normalizedRoute)
            || AUDIO_ROUTE_BLUETOOTH.equals(normalizedRoute)
            || AUDIO_ROUTE_EARPIECE.equals(normalizedRoute)
        ) {
            return normalizedRoute;
        }

        return AUDIO_ROUTE_EARPIECE;
    }

    private void syncNativeAudioRoute(String routeName) {
        String normalizedRoute = normalizeAudioRouteName(routeName);
        currentCommunicationAudioRoute = normalizedRoute;
        syncProximityWakeLock();

        if (callSessionManager != null) {
            callSessionManager.rememberAudioRoute(normalizedRoute);
        }

        dispatchNativeRouteEvent(normalizedRoute);
    }

    private void requestCallAudioFocus() {
        if (audioManager == null) {
            return;
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            AudioAttributes attributes = new AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_VOICE_COMMUNICATION)
                .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                .build();

            callAudioFocusRequest = new AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN)
                .setAudioAttributes(attributes)
                .setOnAudioFocusChangeListener(callAudioFocusChangeListener)
                .build();

            audioManager.requestAudioFocus(callAudioFocusRequest);
            return;
        }

        audioManager.requestAudioFocus(
            callAudioFocusChangeListener,
            AudioManager.STREAM_VOICE_CALL,
            AudioManager.AUDIOFOCUS_GAIN
        );
    }

    private void abandonCallAudioFocus() {
        if (audioManager == null) {
            return;
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && callAudioFocusRequest != null) {
            audioManager.abandonAudioFocusRequest(callAudioFocusRequest);
            callAudioFocusRequest = null;
            return;
        }

        audioManager.abandonAudioFocus(callAudioFocusChangeListener);
    }

    private boolean startNativeCallRingtone() {
        if (audioManager == null) {
            return false;
        }

        stopNativeCallRingtone();
        requestRingtoneAudioFocus();

        Uri ringtoneUri = defaultCallRingtoneUri();

        if (ringtoneUri == null) {
            abandonRingtoneAudioFocus();

            return false;
        }

        try {
            Ringtone ringtone = RingtoneManager.getRingtone(getApplicationContext(), ringtoneUri);

            if (ringtone == null) {
                abandonRingtoneAudioFocus();

                return false;
            }

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                ringtone.setAudioAttributes(new AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build());
            }

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                ringtone.setLooping(true);
            }

            activeCallRingtone = ringtone;
            activeCallRingtone.play();

            return true;
        }
        catch (Throwable exception) {
            activeCallRingtone = null;
            abandonRingtoneAudioFocus();

            return false;
        }
    }

    private void stopNativeCallRingtone() {
        Ringtone ringtone = activeCallRingtone;

        activeCallRingtone = null;

        try {
            if (ringtone != null && ringtone.isPlaying()) {
                ringtone.stop();
            }
        }
        catch (Throwable ignored) {}

        abandonRingtoneAudioFocus();
    }

    private void requestRingtoneAudioFocus() {
        if (audioManager == null) {
            return;
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            AudioAttributes attributes = new AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build();

            ringtoneAudioFocusRequest = new AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN_TRANSIENT)
                .setAudioAttributes(attributes)
                .setOnAudioFocusChangeListener(ringtoneAudioFocusChangeListener)
                .build();

            audioManager.requestAudioFocus(ringtoneAudioFocusRequest);
            return;
        }

        audioManager.requestAudioFocus(
            ringtoneAudioFocusChangeListener,
            AudioManager.STREAM_RING,
            AudioManager.AUDIOFOCUS_GAIN_TRANSIENT
        );
    }

    private void abandonRingtoneAudioFocus() {
        if (audioManager == null) {
            return;
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && ringtoneAudioFocusRequest != null) {
            audioManager.abandonAudioFocusRequest(ringtoneAudioFocusRequest);
            ringtoneAudioFocusRequest = null;
            return;
        }

        audioManager.abandonAudioFocus(ringtoneAudioFocusChangeListener);
    }

    private Uri defaultCallRingtoneUri() {
        Uri ringtoneUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE);

        if (ringtoneUri != null) {
            return ringtoneUri;
        }

        return RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);
    }

    private final class StartupBridge {
        @JavascriptInterface
        public void appShellReady(final String detailJson) {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    startupAppShellReady = true;
                    recordStartupEvent("app_shell_ready", detailJson);
                    releaseStartupSplash("app_shell_ready");
                }
            });
        }
    }

    private final class NativeAuthBridge {
        @JavascriptInterface
        public boolean isGoogleSignInAvailable() {
            return isNativeGoogleSignInSupported(null);
        }

        @JavascriptInterface
        public boolean startGoogleSignIn() {
            return startNativeGoogleSignIn("bridge");
        }

        @JavascriptInterface
        public boolean startGoogleSignInWithClientId(String serverClientId) {
            return startNativeGoogleSignIn("bridge", serverClientId);
        }
    }

    private final class CallAudioBridge {
        @JavascriptInterface
        public boolean hasAudioPermission() {
            return hasPermission(Manifest.permission.RECORD_AUDIO);
        }

        @JavascriptInterface
        public boolean requestAudioPermission() {
            if (hasPermission(Manifest.permission.RECORD_AUDIO)) {
                return true;
            }

            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
                return false;
            }

            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    requestPermissions(new String[] {
                        Manifest.permission.RECORD_AUDIO
                    }, WEB_PERMISSION_REQUEST);
                }
            });

            return false;
        }

        @JavascriptInterface
        public boolean nativeRtcSupported() {
            return callSessionManager != null && callSessionManager.isNativeRtcSupported();
        }

        @JavascriptInterface
        public void enterCall() {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    enterCommunicationAudioMode();
                }
            });
        }

        @JavascriptInterface
        public void leaveCall() {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    exitCommunicationAudioMode();
                }
            });
        }

        @JavascriptInterface
        public boolean startNativeAgoraCall(final String sessionJson) {
            if (callSessionManager == null || sessionJson == null || sessionJson.trim().isEmpty()) {
                return false;
            }

            if (!hasRecordAudioPermission()) {
                Log.w(TAG, "Native Agora call start was blocked because RECORD_AUDIO is missing.");
                dispatchAudioPermissionResult(false);
                return false;
            }

            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    enterCommunicationAudioMode();
                    callSessionManager.rememberSpeakerEnabled(currentSpeakerphoneOn);
                    callSessionManager.rememberAudioRoute(currentCommunicationAudioRoute);

                    if (!callSessionManager.startNativeCall(sessionJson)) {
                        dispatchNativeCallError("Unable to start native audio engine.");
                    }
                }
            });

            return true;
        }

        @JavascriptInterface
        public void endNativeAgoraCall() {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    if (callSessionManager != null) {
                        callSessionManager.endNativeCall();
                    }

                    exitCommunicationAudioMode();
                }
            });
        }

        @JavascriptInterface
        public void setMutedNative(final boolean muted) {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    if (callSessionManager != null) {
                        callSessionManager.setMuted(muted);
                    }
                }
            });
        }

        @JavascriptInterface
        public void updateNativeAgoraToken(final String token) {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    if (callSessionManager != null) {
                        callSessionManager.updateToken(token);
                    }
                }
            });
        }

        @JavascriptInterface
        public boolean refreshNativeAgoraCall() {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    if (callSessionManager != null) {
                        callSessionManager.refreshState();
                    }
                }
            });

            return true;
        }

        @JavascriptInterface
        public void setSpeakerEnabled(final boolean enabled) {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    setSpeakerEnabledNative(enabled);

                    if (callSessionManager != null) {
                        callSessionManager.setSpeakerEnabled(enabled);
                        callSessionManager.setAudioRoute(currentCommunicationAudioRoute);
                    }
                }
            });
        }

        @JavascriptInterface
        public boolean startRingtone(final String direction) {
            if (direction == null || !"incoming".equalsIgnoreCase(direction.trim())) {
                return false;
            }

            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    startNativeCallRingtone();
                }
            });

            return true;
        }

        @JavascriptInterface
        public void stopRingtone() {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    stopNativeCallRingtone();
                }
            });
        }
    }

    private boolean hasAllPermissions(String[] permissions) {
        for (String permission : permissions) {
            if (!hasPermission(permission)) {
                return false;
            }
        }

        return true;
    }

    private boolean hasAnyPermission(String[] permissions) {
        for (String permission : permissions) {
            if (hasPermission(permission)) {
                return true;
            }
        }

        return false;
    }

    private boolean hasPermission(String permission) {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.M || checkSelfPermission(permission) == PackageManager.PERMISSION_GRANTED;
    }

    private void dispatchAudioPermissionResult(boolean granted) {
        dispatchJavascriptEvent(
            "zulors:audio-permission",
            "{granted:" + (granted ? "true" : "false") + "}"
        );
    }

    private void dispatchNativeCallError(String message) {
        JSONObject payload = new JSONObject();

        try {
            payload.put("message", message);
        }
        catch (Throwable ignored) {}

        dispatchNativeCallEvent("error", payload);
    }

    private void dispatchNativeRouteEvent(String routeName) {
        JSONObject payload = new JSONObject();

        try {
            payload.put("route", routeName);
        }
        catch (Throwable ignored) {}

        dispatchNativeCallEvent("route", payload);
    }

    private void dispatchNativeCallEvent(String type, JSONObject payload) {
        JSONObject eventPayload = payload == null ? new JSONObject() : payload;

        try {
            eventPayload.put("type", type);
        }
        catch (Throwable ignored) {}

        dispatchJavascriptEvent("zulors:native-call", eventPayload.toString());
    }

    private void dispatchJavascriptEvent(String eventName, String detailJson) {
        if (webView == null || eventName == null || eventName.trim().isEmpty()) {
            return;
        }

        final String safeEventName = escapeJavascriptString(eventName);
        final String payload = detailJson != null && !detailJson.trim().isEmpty() ? detailJson : "{}";

        webView.post(new Runnable() {
            @Override
            public void run() {
                if (webView == null) {
                    return;
                }

                webView.evaluateJavascript(
                    "(function(){window.dispatchEvent(new CustomEvent('" + safeEventName + "',{detail:" + payload + "}));})();",
                    null
                );
            }
        });
    }

    private String escapeJavascriptString(String value) {
        if (value == null) {
            return "";
        }

        return value
            .replace("\\", "\\\\")
            .replace("'", "\\'")
            .replace("\n", "\\n")
            .replace("\r", "\\r");
    }

    private void prepareFileChooserIntent(Intent intent, WebChromeClient.FileChooserParams params) {
        intent.addFlags(FILE_CHOOSER_FLAGS);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN_MR2 &&
            params.getMode() == WebChromeClient.FileChooserParams.MODE_OPEN_MULTIPLE) {
            intent.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
        }
    }

    private Intent createFallbackFileChooserIntent(WebChromeClient.FileChooserParams params) {
        Intent fallbackIntent = new Intent(
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.KITKAT ?
                Intent.ACTION_OPEN_DOCUMENT :
                Intent.ACTION_GET_CONTENT
        );

        fallbackIntent.addCategory(Intent.CATEGORY_OPENABLE);
        fallbackIntent.setType(resolveAcceptMimeType(params.getAcceptTypes()));
        prepareFileChooserIntent(fallbackIntent, params);

        return Intent.createChooser(fallbackIntent, "Select file");
    }

    private String resolveAcceptMimeType(String[] acceptTypes) {
        if (acceptTypes == null || acceptTypes.length == 0) {
            return "*/*";
        }

        String mimeType = null;

        for (String acceptType : acceptTypes) {
            if (acceptType == null || acceptType.trim().isEmpty()) {
                continue;
            }

            String cleanType = acceptType.trim();

            if (cleanType.contains(",")) {
                return "*/*";
            }

            if (mimeType == null) {
                mimeType = cleanType;
                continue;
            }

            if (!mimeType.equals(cleanType)) {
                return "*/*";
            }
        }

        return mimeType == null ? "*/*" : mimeType;
    }

    private void hideCustomView() {
        if (customView == null) {
            return;
        }

        rootLayout.removeView(customView);
        customView = null;
        webView.setVisibility(View.VISIBLE);

        if (customViewCallback != null) {
            customViewCallback.onCustomViewHidden();
            customViewCallback = null;
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);

        if (requestCode == FLEXIBLE_UPDATE_REQUEST) {
            flexibleUpdateFlowStarted = false;

            if (resultCode != Activity.RESULT_OK) {
                scheduleFlexibleUpdateCheck(FLEXIBLE_UPDATE_PROMPT_COOLDOWN_MS);
            }

            return;
        }

        if (requestCode == FILE_CHOOSER_REQUEST && filePathCallback != null) {
            Uri[] results = collectFileChooserResults(resultCode, data);
            filePathCallback.onReceiveValue(results);
            filePathCallback = null;
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);

        if (requestCode == WEB_PERMISSION_REQUEST) {
            boolean granted = grantResults.length > 0;
            boolean audioPermissionRequested = false;

            for (String permission : permissions) {
                if (Manifest.permission.RECORD_AUDIO.equals(permission)) {
                    audioPermissionRequested = true;
                    break;
                }
            }

            for (int result : grantResults) {
                if (result != PackageManager.PERMISSION_GRANTED) {
                    granted = false;
                    break;
                }
            }

            if (pendingPermissionRequest != null) {
                if (granted && pendingPermissionResources != null) {
                    pendingPermissionRequest.grant(pendingPermissionResources);

                    if (hasWebResource(pendingPermissionResources, PermissionRequest.RESOURCE_AUDIO_CAPTURE)) {
                        scheduleEnterCommunicationAudioMode();
                    }
                }
                else {
                    pendingPermissionRequest.deny();
                }
            }

            if (audioPermissionRequested) {
                dispatchAudioPermissionResult(granted && hasPermission(Manifest.permission.RECORD_AUDIO));
            }

            pendingPermissionRequest = null;
            pendingPermissionResources = null;
            return;
        }

        if (requestCode == GEOLOCATION_PERMISSION_REQUEST) {
            boolean granted = false;

            for (int result : grantResults) {
                if (result == PackageManager.PERMISSION_GRANTED) {
                    granted = true;
                    break;
                }
            }

            if (pendingGeolocationCallback != null) {
                pendingGeolocationCallback.invoke(pendingGeolocationOrigin, granted, false);
            }

            pendingGeolocationOrigin = null;
            pendingGeolocationCallback = null;
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        recordStartupEvent("new_intent_received");

        if (webView != null && hasLaunchUrl(intent)) {
            String launchUrl = resolveLaunchUrl(intent);
            Uri launchUri = Uri.parse(launchUrl);

            if (isGoogleCallbackUrl(launchUri) && shouldLoadGoogleCallback(launchUrl)) {
                webView.loadUrl(launchUrl, noCacheHeaders());
            }
            else if (shouldHandleNotificationLaunch(launchUrl)) {
                webView.loadUrl(launchUrl, noCacheHeaders());
            }
        }

        clearZulorsNotifications();
        PushTokenBridge.syncLatestToken(this, webView);
    }

    private boolean shouldHandleNotificationLaunch(String url) {
        if (url == null || url.trim().isEmpty()) {
            return false;
        }

        long now = SystemClock.elapsedRealtime();

        if (url.equals(lastNotificationLaunchUrl) && (now - lastNotificationLaunchAtMs) < DUPLICATE_NOTIFICATION_LAUNCH_WINDOW_MS) {
            return false;
        }

        rememberNotificationLaunchUrl(url);

        return true;
    }

    private void rememberNotificationLaunchUrl(String url) {
        if (url == null || url.trim().isEmpty()) {
            return;
        }

        lastNotificationLaunchUrl = url;
        lastNotificationLaunchAtMs = SystemClock.elapsedRealtime();
    }

    @Override
    protected void onResume() {
        super.onResume();
        appInForeground = true;
        recordStartupEvent("activity_resumed", startupSplashReleaseReason);
        completeFlexibleUpdateWhenSafe();
        scheduleFlexibleUpdateCheck(FLEXIBLE_UPDATE_CHECK_DELAY_MS);

        if (callSessionManager != null && callSessionManager.hasActiveCall()) {
            callSessionManager.setAppVisibility(true);
            callSessionManager.refreshState();
        }

        if (webView == null) {
            return;
        }

        webView.onResume();
        clearZulorsNotifications();
        webView.post(new Runnable() {
            @Override
            public void run() {
                syncSystemChromeWithPage(webView);
                webView.evaluateJavascript(
                    "window.dispatchEvent(new Event('focus'));" +
                    "document.dispatchEvent(new Event('visibilitychange'));" +
                    "window.dispatchEvent(new CustomEvent('zulors:app-resume'));",
                    null
                );
                installAndroidViewportGuards(webView);
                PushTokenBridge.syncLatestToken(MainActivity.this, webView);
            }
        });
    }

    private void clearZulorsNotifications() {
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);

        if (manager != null) {
            manager.cancelAll();
        }
    }

    private SafeAreaCssInsets getSafeAreaCssInsets() {
        int leftInset = 0;
        int topInset = 0;
        int rightInset = 0;
        int bottomInset = 0;
        View insetsView = rootLayout != null ? rootLayout : getWindow().getDecorView();

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && insetsView != null) {
            WindowInsets insets = insetsView.getRootWindowInsets();

            if (insets != null) {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                    Insets systemInsets = insets.getInsets(WindowInsets.Type.systemBars() | WindowInsets.Type.displayCutout());

                    leftInset = Math.max(leftInset, systemInsets.left);
                    topInset = Math.max(topInset, systemInsets.top);
                    rightInset = Math.max(rightInset, systemInsets.right);
                    bottomInset = Math.max(bottomInset, systemInsets.bottom);
                }
                else {
                    leftInset = Math.max(leftInset, insets.getSystemWindowInsetLeft());
                    topInset = Math.max(topInset, insets.getSystemWindowInsetTop());
                    rightInset = Math.max(rightInset, insets.getSystemWindowInsetRight());
                    bottomInset = Math.max(bottomInset, insets.getSystemWindowInsetBottom());

                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P && insets.getDisplayCutout() != null) {
                        leftInset = Math.max(leftInset, insets.getDisplayCutout().getSafeInsetLeft());
                        topInset = Math.max(topInset, insets.getDisplayCutout().getSafeInsetTop());
                        rightInset = Math.max(rightInset, insets.getDisplayCutout().getSafeInsetRight());
                        bottomInset = Math.max(bottomInset, insets.getDisplayCutout().getSafeInsetBottom());
                    }
                }
            }
        }

        topInset = Math.max(topInset, getSystemDimensionPixelSize("status_bar_height"));
        bottomInset = Math.max(bottomInset, getSystemDimensionPixelSize("navigation_bar_height"));

        return new SafeAreaCssInsets(
            toCssPixel(leftInset),
            toCssPixel(topInset),
            toCssPixel(rightInset),
            toCssPixel(bottomInset)
        );
    }

    private int getSystemDimensionPixelSize(String name) {
        int resourceId = getResources().getIdentifier(name, "dimen", "android");

        if (resourceId <= 0) {
            return 0;
        }

        return getResources().getDimensionPixelSize(resourceId);
    }

    private int toCssPixel(int pixelValue) {
        if (pixelValue <= 0) {
            return 0;
        }

        float density = Math.max(1f, getResources().getDisplayMetrics().density);

        return Math.max(1, Math.round(pixelValue / density));
    }

    private void installAndroidViewportGuards(WebView view) {
        if (view == null) {
            return;
        }

        SafeAreaCssInsets safeArea = getSafeAreaCssInsets();
        String safeLeftCss = safeArea.left + "px";
        String safeTopCss = safeArea.top + "px";
        String safeRightCss = safeArea.right + "px";
        String safeBottomCss = safeArea.bottom + "px";

        view.evaluateJavascript(
            "(function(){" +
                "var safeLeft='" + safeLeftCss + "',safeTop='" + safeTopCss + "',safeRight='" + safeRightCss + "',safeBottom='" + safeBottomCss + "';" +
                "var d=document,r=d.documentElement,b=d.body;" +
                "if(!r){return;}" +
                "r.classList.add('zulors-android-app');" +
                "var meta=d.querySelector('meta[name=\"viewport\"]');" +
                "if(!meta){meta=d.createElement('meta');meta.name='viewport';(d.head||r).appendChild(meta);}" +
                "meta.setAttribute('content','width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content, user-scalable=no');" +
                "var applyInsets=function(){" +
                    "r.style.setProperty('--zulors-android-safe-left',safeLeft);" +
                    "r.style.setProperty('--zulors-android-safe-top',safeTop);" +
                    "r.style.setProperty('--zulors-android-safe-right',safeRight);" +
                    "r.style.setProperty('--zulors-android-safe-bottom',safeBottom);" +
                    "if(b){" +
                        "b.style.setProperty('--zulors-android-safe-left',safeLeft);" +
                        "b.style.setProperty('--zulors-android-safe-top',safeTop);" +
                        "b.style.setProperty('--zulors-android-safe-right',safeRight);" +
                        "b.style.setProperty('--zulors-android-safe-bottom',safeBottom);" +
                    "}" +
                "};" +
                "var applyViewport=function(){" +
                    "var vv=window.visualViewport;" +
                    "var width=Math.round(vv&&vv.width?vv.width:(window.innerWidth||r.clientWidth||0));" +
                    "var height=Math.round(vv&&vv.height?vv.height:(window.innerHeight||r.clientHeight||0));" +
                    "if(!width){width=Math.max(1,r.clientWidth||0);}" +
                    "if(!height){height=Math.max(1,r.clientHeight||0);}" +
                    "var widthCss=width+'px',heightCss=height+'px';" +
                    "r.style.setProperty('--zulors-android-viewport-width',widthCss);" +
                    "r.style.setProperty('--zulors-android-viewport-height',heightCss);" +
                    "if(b){" +
                        "b.style.setProperty('--zulors-android-viewport-width',widthCss);" +
                        "b.style.setProperty('--zulors-android-viewport-height',heightCss);" +
                    "}" +
                "};" +
                "window.__zulorsAndroidViewportGuardsApply=function(){applyInsets();applyViewport();};" +
                "if(!window.__zulorsAndroidViewportGuardsBound){" +
                    "var rafId=0;" +
                    "var schedule=function(){" +
                        "if(rafId){window.cancelAnimationFrame(rafId);}" +
                        "rafId=window.requestAnimationFrame(function(){" +
                            "rafId=0;" +
                            "if(window.__zulorsAndroidViewportGuardsApply){window.__zulorsAndroidViewportGuardsApply();}" +
                        "});" +
                    "};" +
                    "window.addEventListener('resize',schedule,{passive:true});" +
                    "window.addEventListener('orientationchange',schedule,{passive:true});" +
                    "if(window.visualViewport){" +
                        "window.visualViewport.addEventListener('resize',schedule,{passive:true});" +
                        "window.visualViewport.addEventListener('scroll',schedule,{passive:true});" +
                    "}" +
                    "window.__zulorsAndroidViewportGuardsBound=true;" +
                "}" +
                "window.__zulorsAndroidViewportGuardsApply();" +
                "var css='html.zulors-android-app{--zulors-android-safe-left:'+safeLeft+';--zulors-android-safe-top:'+safeTop+';--zulors-android-safe-right:'+safeRight+';--zulors-android-safe-bottom:'+safeBottom+';--mobile-safe-left:max(env(safe-area-inset-left,0px),var(--zulors-android-safe-left));--mobile-safe-top:max(env(safe-area-inset-top,0px),var(--zulors-android-safe-top));--mobile-safe-right:max(env(safe-area-inset-right,0px),var(--zulors-android-safe-right));--mobile-safe-bottom:max(env(safe-area-inset-bottom,0px),var(--zulors-android-safe-bottom));}'+" +
                    "'html.zulors-android-app,html.zulors-android-app body,html.zulors-android-app #zulors-mobile-app,html.zulors-android-app .mobile-app-content,html.zulors-android-app .mobile-app-stage,html.zulors-android-app .mobile-route-view,html.zulors-android-app .base-publication,html.zulors-android-app .zulors-action-sheet-overlay,html.zulors-android-app .zulors-action-sheet{left:0!important;right:0!important;width:var(--zulors-android-viewport-width,100vw)!important;min-width:var(--zulors-android-viewport-width,100vw)!important;max-width:var(--zulors-android-viewport-width,100vw)!important;margin-left:0!important;margin-right:0!important;padding-left:0!important;padding-right:0!important;border-left:0!important;border-right:0!important;border-radius:0!important;box-shadow:none!important;filter:none!important;overflow-x:hidden!important;}'+" +
                    "'html.zulors-android-app .zulors-action-sheet-overlay{inset:0!important;height:var(--zulors-android-viewport-height,100vh)!important;min-height:var(--zulors-android-viewport-height,100vh)!important;padding:0!important;}'+" +
                    "'html.zulors-android-app .zulors-action-sheet-group{width:100%!important;max-width:none!important;margin-left:0!important;margin-right:0!important;border-radius:0!important;box-shadow:none!important;filter:none!important;}'+" +
                    "'html.zulors-android-app .zulors-action-sheet>.flex-1{margin-bottom:0!important;padding-bottom:var(--mobile-safe-bottom)!important;}'+" +
                    "'html.zulors-android-app .mobile-safe-header{padding-top:var(--mobile-safe-top)!important;padding-left:var(--mobile-safe-left)!important;padding-right:var(--mobile-safe-right)!important;}'+" +
                    "'html.zulors-android-app .mobile-safe-sticky-top{top:var(--mobile-safe-top)!important;}'+" +
                    "'html.zulors-android-app .mobile-safe-navbar{left:0!important;right:0!important;width:var(--zulors-android-viewport-width,100vw)!important;max-width:var(--zulors-android-viewport-width,100vw)!important;padding-bottom:var(--mobile-safe-bottom)!important;padding-left:var(--mobile-safe-left)!important;padding-right:var(--mobile-safe-right)!important;}'+" +
                    "'html.zulors-android-app body.auth-page{min-height:var(--zulors-android-viewport-height,100vh)!important;}'+" +
                    "'html.zulors-android-app body.auth-page .auth-page-shell{min-height:calc(var(--zulors-android-viewport-height,100vh) - var(--auth-header-height))!important;}'+" +
                    "'html.zulors-android-app .base-publication video,html.zulors-android-app .base-publication canvas,html.zulors-android-app .base-publication img{max-width:100vw!important;}';" +
                "var style=d.getElementById('zulors-android-edge-to-edge-guard');" +
                "if(!style){style=d.createElement('style');style.id='zulors-android-edge-to-edge-guard';(d.head||r).appendChild(style);}" +
                "style.textContent=css;" +
                "r.style.margin='0';r.style.padding='0';r.style.width='100%';r.style.maxWidth='100%';r.style.minWidth='100%';r.style.overflowX='hidden';" +
                "if(b){b.style.margin='0';b.style.padding='0';b.style.width='100%';b.style.maxWidth='100%';b.style.minWidth='100%';b.style.overflowX='hidden';}" +
            "})();",
            null
        );
    }

    private void syncSystemChromeWithPage(WebView view) {
        if (view == null) {
            return;
        }

        view.evaluateJavascript(
            "(function(){return document.documentElement.getAttribute('data-theme') || document.body.getAttribute('data-theme') || 'light';})();",
            value -> applySystemChrome("dark".equalsIgnoreCase(unwrapJavascriptString(value)))
        );
    }

    private String unwrapJavascriptString(String value) {
        if (value == null) {
            return "";
        }

        String normalized = value.trim();

        if ("null".equals(normalized)) {
            return "";
        }

        if (normalized.length() >= 2 && normalized.startsWith("\"") && normalized.endsWith("\"")) {
            normalized = normalized.substring(1, normalized.length() - 1);
        }

        return normalized
            .replace("\\u003C", "<")
            .replace("\\n", "\n")
            .replace("\\\"", "\"")
            .replace("\\/", "/")
            .replace("\\\\", "\\");
    }

    @Override
    protected void onPause() {
        appInForeground = false;

        if (callSessionManager != null) {
            callSessionManager.setAppVisibility(false);
        }

        if (webView != null) {
            webView.onPause();
        }

        super.onPause();
    }

    private Uri[] collectFileChooserResults(int resultCode, Intent data) {
        if (resultCode != Activity.RESULT_OK || data == null) {
            return null;
        }

        List<Uri> selectedUris = new ArrayList<>();
        ClipData clipData = data.getClipData();

        if (clipData != null) {
            for (int index = 0; index < clipData.getItemCount(); index++) {
                Uri itemUri = clipData.getItemAt(index).getUri();

                if (itemUri != null) {
                    persistUriReadAccess(data, itemUri);
                    selectedUris.add(itemUri);
                }
            }
        }
        else if (data.getData() != null) {
            Uri itemUri = data.getData();

            persistUriReadAccess(data, itemUri);
            selectedUris.add(itemUri);
        }

        if (!selectedUris.isEmpty()) {
            return selectedUris.toArray(new Uri[0]);
        }

        return WebChromeClient.FileChooserParams.parseResult(resultCode, data);
    }

    private void persistUriReadAccess(Intent data, Uri uri) {
        try {
            int flags = data.getFlags() & Intent.FLAG_GRANT_READ_URI_PERMISSION;

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.KITKAT && flags != 0) {
                getContentResolver().takePersistableUriPermission(uri, flags);
            }
        }
        catch (SecurityException exception) {
            // Some providers grant one-time access only. The WebView can still read it.
        }
    }

    @Override
    public void onBackPressed() {
        handleBackNavigation();
    }

    private void handleBackNavigation() {
        if (customView != null) {
            hideCustomView();
            return;
        }

        if (backNavigationPending || webView == null) {
            return;
        }

        backNavigationPending = true;
        webView.evaluateJavascript(
            "(function(){try{return !!(window.ZulorsNativeBack && window.ZulorsNativeBack.handle && window.ZulorsNativeBack.handle());}catch(error){return false;}})();",
            value -> {
                backNavigationPending = false;

                if ("true".equals(value)) {
                    return;
                }

                fallbackBackNavigation();
            }
        );
    }

    private void fallbackBackNavigation() {
        if (webView.canGoBack()) {
            webView.goBack();
            return;
        }

        moveTaskToBack(true);
    }

    @Override
    protected void onDestroy() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU && backInvokedCallback != null) {
            getOnBackInvokedDispatcher().unregisterOnBackInvokedCallback(backInvokedCallback);
            backInvokedCallback = null;
        }

        if (mainHandler != null && deferredStartupTasksRunnable != null) {
            mainHandler.removeCallbacks(deferredStartupTasksRunnable);
            deferredStartupTasksRunnable = null;
        }

        unregisterFlexibleUpdateListener();

        if (webView != null) {
            webView.destroy();
            webView = null;
        }

        if (callSessionManager != null) {
            callSessionManager.detachEventSink(nativeCallEventSink);
        }

        if (callSessionManager != null && callSessionManager.hasActiveCall()) {
            stopNativeCallRingtone();
            cancelPendingSpeakerRoute();
            unregisterCommunicationAudioDeviceCallback();
            releaseProximityWakeLock();
        }
        else {
            exitCommunicationAudioMode();
        }

        super.onDestroy();
    }
}
