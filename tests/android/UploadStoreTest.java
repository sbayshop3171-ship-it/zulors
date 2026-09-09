package com.zulors.app;

import android.content.Context;
import android.net.Uri;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import org.json.JSONArray;
import org.json.JSONObject;
import org.junit.After;
import org.junit.Before;
import org.junit.Test;
import org.junit.runner.RunWith;
import org.robolectric.RobolectricTestRunner;
import org.robolectric.RuntimeEnvironment;
import org.robolectric.annotation.Config;
import java.io.FileOutputStream;
import java.util.Collections;
import java.util.Map;
import java.util.UUID;
import static org.junit.Assert.*;

@RunWith(RobolectricTestRunner.class)
@Config(manifest = Config.NONE, sdk = 28)
public class UploadStoreTest {
    private Context context;
    private UploadStore store;
    private final String owner = "17";

    @Before public void setup() {
        context = RuntimeEnvironment.getApplication();
        context.deleteDatabase("media-publications.db");
        store = new UploadStore(context);
    }

    @After public void close() { store.close(); }

    private JSONObject file() throws Exception {
        String handle = UUID.randomUUID().toString();
        try (FileOutputStream output = new FileOutputStream(store.path(handle))) { output.write(new byte[] {0, 1, 2, 3, 4, 5, 6, 7}); output.getFD().sync(); }
        JSONObject item = new JSONObject().put("native_file_id", handle).put("client_uid", UUID.randomUUID().toString())
            .put("type", "image").put("name", "photo.png").put("mime", "image/png").put("size", 8)
            .put("preview_url", UploadPolicy.origin(BuildConfig.APP_URL) + UploadPolicy.PREVIEW + handle);
        store.addFile(owner, item); return item;
    }

    private JSONObject descriptor(JSONObject... items) throws Exception {
        JSONArray array = new JSONArray(); for (JSONObject item : items) array.put(item);
        return new JSONObject().put("client_uid", UUID.randomUUID().toString()).put("kind", "post").put("content", "test").put("privacy", "all").put("items", array);
    }

    @Test public void durableEnqueueAndPartCheckpointsSurviveReopen() throws Exception {
        JSONObject item = file(), input = descriptor(item);
        String uid = input.getString("client_uid"), itemId = item.getString("client_uid");
        store.enqueue(owner, input); store.part(uid, itemId, 2, 1, "\"etag\"");
        store.close(); store = new UploadStore(context);
        assertEquals(uid, store.list(owner).getJSONObject(0).getString("client_uid"));
        assertTrue(store.path(item.getString("native_file_id")).isFile());
        assertEquals("\"etag\"", store.parts(uid, itemId, 2).getJSONObject(0).getString("etag"));
        assertEquals(0, store.parts(uid, itemId, 3).length());
    }

    @Test public void duplicateEnqueueCreatesOnlyOneDurablePublication() throws Exception {
        JSONObject input = descriptor(file());
        store.enqueue(owner, input); store.enqueue(owner, input);
        assertEquals(1, store.list(owner).length());
    }

    @Test public void fileHandlesAndOutboxesAreAccountBound() throws Exception {
        JSONObject item = file(), input = descriptor(item);
        assertThrows(IllegalArgumentException.class, () -> store.enqueue("another-user", input));
        store.enqueue(owner, input);
        assertEquals(0, store.list("another-user").length());
        assertThrows(IllegalArgumentException.class, () -> store.file("another-user", item.getString("native_file_id")));
    }

    @Test public void failedEnqueueRollsBackAllClaims() throws Exception {
        JSONObject first = file(), second = file(), duplicate = new JSONObject(second.toString()).put("client_uid", first.getString("client_uid"));
        assertThrows(IllegalArgumentException.class, () -> store.enqueue(owner, descriptor(first, duplicate)));
        assertEquals(0, store.list(owner).length());
        store.enqueue(owner, descriptor(first, second));
        assertEquals(1, store.list(owner).length());
    }

    @Test public void handleCannotBeClaimedByAnotherPublication() throws Exception {
        JSONObject item = file(); store.enqueue(owner, descriptor(item));
        assertThrows(IllegalArgumentException.class, () -> store.enqueue(owner, descriptor(item)));
        assertEquals(1, store.list(owner).length());
    }

    @Test public void cancellationTombstoneSurvivesLostCreateResponse() throws Exception {
        JSONObject input = descriptor(file()); String uid = input.getString("client_uid"); store.enqueue(owner, input);
        store.flag(uid, "create_started", true); store.flag(uid, "cancel_requested", true); store.state(uid, "cancelling", null, 0);
        store.close(); store = new UploadStore(context);
        JSONObject row = store.get(owner, uid);
        assertEquals(1, row.getInt("create_started")); assertEquals(1, row.getInt("cancel_requested")); assertEquals("cancelling", row.getString("status"));
    }

    @Test public void processingOneItemDoesNotStrandRemainingTransfers() throws Exception {
        JSONObject input = descriptor(file(), file()); String uid = input.getString("client_uid"); store.enqueue(owner, input);
        JSONObject remote = new JSONObject(input.toString()).put("id", "server-id").put("status", "processing");
        remote.getJSONArray("items").getJSONObject(0).put("status", "processing");
        remote.getJSONArray("items").getJSONObject(1).put("status", "pending");
        store.server(owner, uid, remote);
        assertTrue(UploadStore.needsTransfer(store.get(owner, uid)));
        remote.getJSONArray("items").getJSONObject(1).put("status", "uploaded"); store.server(owner, uid, remote);
        assertFalse(UploadStore.needsTransfer(store.get(owner, uid)));
    }

    @Test public void completionReleasesBytesAndCannotRegressOnStaleSync() throws Exception {
        JSONObject item = file(), input = descriptor(item); String uid = input.getString("client_uid"); store.enqueue(owner, input);
        JSONObject remote = new JSONObject(input.toString()).put("id", "server-id").put("status", "published");
        store.server(owner, uid, remote);
        assertFalse(store.path(item.getString("native_file_id")).exists());
        store.server(owner, uid, remote.put("status", "processing"));
        assertEquals("published", store.get(owner, uid).getString("status"));
    }

    @Test public void serverExpiryRetainsFilesAndRestartAtomicallyMovesOwnership() throws Exception {
        JSONObject item = file(), input = descriptor(item); String uid = input.getString("client_uid"); store.enqueue(owner, input);
        JSONObject remote = new JSONObject(input.toString()).put("id", "expired-id").put("status", "cancelled").put("error", "Upload expired. Start a new publication.");
        store.server(owner, uid, remote);
        assertTrue(store.path(item.getString("native_file_id")).exists());
        assertEquals("failed", store.get(owner, uid).getString("status"));
        assertTrue(store.view(store.get(owner, uid)).getBoolean("restart_required"));
        JSONObject replacement = store.restart(owner, uid);
        assertNotEquals(uid, replacement.getString("client_uid"));
        assertEquals("queued", replacement.getString("status"));
        assertEquals("cancelled", store.get(owner, uid).getString("status"));
        assertTrue(store.path(item.getString("native_file_id")).exists());
        store.releaseFiles(uid);
        assertTrue(store.path(item.getString("native_file_id")).exists());
    }

    @Test public void onlyExplicitCancellationDiscardsCancelledPublicationFiles() throws Exception {
        JSONObject item = file(), input = descriptor(item); String uid = input.getString("client_uid"); store.enqueue(owner, input);
        store.flag(uid, "cancel_requested", true);
        store.server(owner, uid, new JSONObject(input.toString()).put("id", "cancelled-id").put("status", "cancelled"));
        assertFalse(store.path(item.getString("native_file_id")).exists());
        assertEquals("cancelled", store.get(owner, uid).getString("status"));
    }

    @Test public void rangedPreviewStreamsOnlyRequestedBytesAndRejectsOtherAccounts() throws Exception {
        JSONObject item = file(); Uri uri = Uri.parse(item.getString("preview_url"));
        WebResourceRequest request = new WebResourceRequest() {
            public Uri getUrl() { return uri; }
            public boolean isForMainFrame() { return false; }
            public boolean isRedirect() { return false; }
            public boolean hasGesture() { return false; }
            public String getMethod() { return "GET"; }
            public Map<String, String> getRequestHeaders() { return Collections.singletonMap("Range", "bytes=2-4"); }
        };
        WebResourceResponse response = UploadFiles.preview(store, owner, BuildConfig.APP_URL, request);
        assertEquals(206, response.getStatusCode());
        assertEquals("bytes 2-4/8", response.getResponseHeaders().get("Content-Range"));
        try (java.io.InputStream bytes = response.getData()) { assertEquals(2, bytes.read()); assertEquals(3, bytes.read()); assertEquals(4, bytes.read()); assertEquals(-1, bytes.read()); }
        assertEquals(404, UploadFiles.preview(store, "another-user", BuildConfig.APP_URL, request).getStatusCode());
        assertEquals(404, UploadFiles.preview(store, owner, "https://evil.test/", request).getStatusCode());
    }
}
