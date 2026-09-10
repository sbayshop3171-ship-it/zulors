# Native Media Publications

Version: `0.5.0-production`. The server's `MEDIA_PUBLICATIONS_ENABLED` flag controls new admission. Shipping this APK does not enable the feature. Already-admitted uploads continue when the flag is disabled. The existing HTML file chooser and legacy media endpoints are unchanged.

## Bridge Protocol 1

The bridge exists only when the installed WebView supports AndroidX `WEB_MESSAGE_LISTENER`. Older APKs and unsupported WebViews have no `window.ZulorsUploadBridge`; the browser must treat a missing object or failed capability handshake as unsupported.

Only the exact configured `APP_URL` origin is allowed (scheme, host, port). There are no wildcard subdomains. Native code additionally verifies the sender is the main frame and the current top-level page is trusted. Navigation invalidates reply proxies. No upload interface uses `addJavascriptInterface`.

```javascript
window.ZulorsUploadBridge.onmessage = ({ data }) => {
    const response = JSON.parse(data);
    // Response: {id, result, error}; event: {event: 'uploadsChanged', items: [...]}
};
window.ZulorsUploadBridge.postMessage(JSON.stringify({
    id: crypto.randomUUID(), method: 'capabilities', payload: {}
}));
```

Every reply has `{id, result, error}`. Success has `error: null`. Failure has `result: null` and `error: {code, message}`. `id` can be a string or number. Malformed envelopes, non-main-frame messages, and untrusted origins are ignored. Envelopes are limited to 256 KiB. No file data crosses the bridge.

| Method | Payload | Result |
| --- | --- | --- |
| `capabilities` | `{}` | Server capabilities plus `supported: true`, `protocol_version: 1`, `native_picker: true`, `upload_concurrency: 2` |
| `setAccount` | `{user_id: string-or-number}` | Verified same-user server capabilities |
| `setAccount` | `{user_id: null}` | `{user_id: null, enabled: false}`; stops old-account requests and removes their notifications |
| `pick` | `{type: 'image'|'video'|'media', kinds: ['image','video'], multiple: false}` | `{items: [...]}`; cancelling the picker returns an empty array |
| `enqueue` | The API publication descriptor, each item also including `native_file_id` | Durable local publication; resolves after SQLite commit, before network admission |
| `list` | `{}` | Array of local publications for the active account |
| `retry` | `{client_uid}` or `{native_id}` or `{id}` | Updated local publication |
| `cancel` | `{client_uid}` or `{native_id}` or `{id}` | Updated publication; cancellation intent is durable before acknowledgement |

Call `setAccount` after capability verification and on each account change/logout. Picker `types` is accepted as an alias for `kinds`. `type` is used when neither array exists. Images are restricted to JPEG, PNG and WebP. `video/*` is available for server validation. The picker allows at most 30 items and 8 GiB per imported item; the browser and server impose their own narrower publication limits. Selection only copies bytes into app-private storage. It performs no API creation or R2 upload.

Picked items have `{native_file_id, name, size, mime, type, preview_url}` plus optional `duration_seconds`, `width`, and `height`. `native_file_id` is an opaque random UUID, never a filesystem or provider path. Preview URLs have the form `https://zulors.com/__zulors_native_media__/<handle>` (the configured app origin is used in local builds). Assign the URL directly to an image/video element. Do not fetch and convert to Blob/base64 for upload. The private loader supports HTTP byte ranges and seeking, returns `no-store`, serves only owned handles, and rejects navigation to the media URL. A preview's source bytes are removed after publication/cancellation; abandoned selections expire after 24 hours.

Only confirmed publication or explicit user cancellation releases an outbox's source files. Server-side expiry/cancellation retains them and exposes local `status: 'failed'`, the server error, and `restart_required: true`. Native `retry` then atomically creates a replacement publication with a new `client_uid` and new item UUIDs, moving the existing file claims to it. This new admission requires the feature to be enabled. The original row remains a cancelled tombstone.

Local publications include the API publication fields plus `native_id` (stable client UUID), `user_id`, `created_at` (milliseconds), `next_attempt_at` (milliseconds), and aggregate `progress` (0-100). Before admission, `id` equals `client_uid`; after admission it is the server id. Always use `client_uid` or `native_id` as the stable UI key. Local states include `queued`, `waiting`, `auth_required`, `paused`, and `cancelling` as well as the server states. `uploadsChanged.items` is the same array as `list` and is scoped to the current account. `enqueue` retries with the same `client_uid` return the already-committed row.

## Transfer And Recovery

The SQLite database stores publication descriptors, cancellation/retry intent, server identity, and generation-scoped part ETags. Selected files are streamed into the app's no-backup directory, fsynced, then atomically renamed before a handle is issued. SQLite uses WAL and synchronous FULL. No credentials or signed URLs are stored in the database.

One queue runner transfers items serially, with at most two parallel R2 parts. Part boundaries use an exclusive `end`, matching `Blob.slice(start, end)`. Raw payload fields are read from the upload root and raw `/complete` sends `parts: []`. Resume `uploaded` with a null upload is acknowledged through `/complete`; processed items never upload again. A publication entering `processing` after its first item does not stop remaining item uploads. The server is the only source of `published` status.

The API adapter takes a WebView cookie snapshot and sends the existing `X-XSRF-TOKEN`, `Origin`, `Referer`, and `X-Requested-With` headers only to the exact app origin. It verifies the server capability `user_id` before operating on an account's rows. The independent R2 client has no cookie jar, authenticators, interceptors, or redirects; only storage headers from the signed descriptor are accepted. R2 requests always stream PUT bodies. There is no server-proxy fallback.

Network failures and 429 responses retain the queue and reschedule with at least 60 seconds backoff; `Retry-After` is honored. Expired URLs use resume; a 410 session sets a durable retry intent before resuming. API 401/419 pauses work as `auth_required`. Account changes cancel active requests and hide the old account's rows. Cancel persists a tombstone before aborting calls; server cancellation can be retried, including resolving a lost create response with the same idempotency key.

Android 14+ uses one persisted UIDT `JobService`; earlier versions use a unique WorkManager foreground worker with the dedicated `dataSync` service type. Neither path starts the call/microphone service. Progress notifications are throttled to one second. Transfer jobs end after upload acknowledgement, leaving separate processing notifications. Denied notification permission never deletes pending rows. Android can defer or stop work; force-stop recovery and foreground scheduling restrictions require a subsequent permitted run or app reopen.

The UIDT job is reserved while Publish is still a visible user action. The runner drains subsequent publications from the same SQLite queue. Before ending, the JobService checks both pending rows and Publish requests still committing; it calls `jobFinished(..., true)` for that continuation, retaining Android's rescheduling authorization even when the Activity is no longer visible. The official UIDT constraint list supports both `setPersisted()` and exponential `setBackoffCriteria()`; an Android 14 Robolectric test builds and inspects this exact combination.

FCM data messages use `{type:'media_publication', publication_id, client_uid, status:'published', user_id, url}`, all strings. The handler drops another account's messages before display and updates the stable `media_publication:<client_uid>` notification tag. Status sync also runs on app reopen and in a short WorkManager task. Processing does not hold a foreground service or poll indefinitely.

## Build And Verification

The build pins AndroidX WebKit `1.12.1`, WorkManager `2.10.3`, and OkHttp `4.12.0`. Official references:

- [WebKit 1.12.1 release](https://developer.android.com/jetpack/androidx/releases/webkit#1.12.1)
- [Main-frame WebMessage listener parameters](https://developer.android.com/reference/androidx/webkit/WebViewCompat.WebMessageListener)
- [User-initiated data transfers and backward compatibility](https://developer.android.com/develop/background-work/background-tasks/uidt)
- [WorkManager long-running workers and foreground service types](https://developer.android.com/develop/background-work/background-tasks/persistent/how-to/long-running)
- [WorkManager 2.10.3 release](https://developer.android.com/jetpack/androidx/releases/work#2.10.3)

Use the existing signing environment and keystore. The build reads release passwords from environment variables; generated Gradle files do not contain them. Set `CREATE_RELEASE_KEYSTORE=false`. Never generate a replacement upload key. Play Store upload builds should be signed AABs; the matching signed APK is only for same-signature device sanity testing.

```sh
APP_MODE=production BUILD_TYPE=release ARTIFACT_TYPE=bundle RUN_UPLOAD_TESTS=true \
CREATE_RELEASE_KEYSTORE=false bash android/LocalAndroidPreview/build-local.sh
```

`VERSION_CODE` defaults to the build's Unix timestamp; check the resulting AAB is higher than the previous release. `RUN_UPLOAD_TESTS=true` runs the Android tests under `tests/android` before packaging. `LOCAL_MAVEN_CACHE` optionally supplies a local Maven repository when the environment cannot reach Maven through the JVM. The final Play upload file is `build/latest/zulors-production-release.aab`.

For the full Play helper that builds both the AAB and a matching install-test APK, run:

```sh
android/LocalAndroidPreview/build-play-production.command
```

Device acceptance still requires Android 14+ and an earlier Android device: upload while backgrounded, seek a local video preview, terminate/reopen during multipart and after create, toggle airplane mode, expire a signed URL, deny notifications, switch accounts, cancel, and receive completion FCM. No device installation is part of the build script.
