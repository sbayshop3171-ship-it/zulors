package com.zulors.app;

import android.content.Context;
import android.database.Cursor;
import android.graphics.BitmapFactory;
import android.media.MediaMetadataRetriever;
import android.net.Uri;
import android.provider.OpenableColumns;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import org.json.JSONObject;
import java.io.ByteArrayInputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.RandomAccessFile;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

final class UploadFiles {
    private static final long MAX_FILE_BYTES = 8L * 1024 * 1024 * 1024;

    static JSONObject importFile(Context context, UploadStore store, String account, Uri uri) throws Exception {
        if (!"content".equals(uri.getScheme())) throw new IllegalArgumentException("invalid_picker_uri");
        String mime = context.getContentResolver().getType(uri);
        if (mime == null || !(mime.equals("image/jpeg") || mime.equals("image/png") || mime.equals("image/webp") || mime.startsWith("video/"))) throw new IllegalArgumentException("unsupported_media");
        String handle = UUID.randomUUID().toString();
        String name = mime.startsWith("video/") ? "video" : "image";
        try (Cursor cursor = context.getContentResolver().query(uri, new String[] {OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE}, null, null, null)) {
            if (cursor != null && cursor.moveToFirst()) {
                name = cursor.getString(0);
                if (!cursor.isNull(1) && cursor.getLong(1) > MAX_FILE_BYTES) throw new IllegalArgumentException("file_too_large");
            }
        }
        File target = store.path(handle), temporary = new File(store.directory, handle + ".import");
        boolean committed = false;
        try {
            long size = 0;
            try (InputStream input = context.getContentResolver().openInputStream(uri); FileOutputStream output = new FileOutputStream(temporary)) {
                if (input == null) throw new IllegalArgumentException("file_unavailable");
                byte[] buffer = new byte[64 * 1024];
                int count;
                while ((count = input.read(buffer)) != -1) {
                    size += count;
                    if (size > MAX_FILE_BYTES) throw new IllegalArgumentException("file_too_large");
                    output.write(buffer, 0, count);
                }
                output.getFD().sync();
            }
            if (size == 0 || !temporary.renameTo(target)) throw new IllegalStateException("storage_unavailable");
            java.io.FileDescriptor directory = android.system.Os.open(store.directory.getAbsolutePath(), android.system.OsConstants.O_RDONLY, 0);
            try { android.system.Os.fsync(directory); } finally { android.system.Os.close(directory); }
            JSONObject metadata = new JSONObject().put("native_file_id", handle).put("name", name == null ? "media" : name)
                .put("size", size).put("mime", mime).put("type", mime.startsWith("video/") ? "video" : "image")
                .put("preview_url", UploadPolicy.origin(BuildConfig.APP_URL) + UploadPolicy.PREVIEW + handle);
            if (mime.startsWith("video/")) {
                MediaMetadataRetriever retriever = new MediaMetadataRetriever();
                try {
                    retriever.setDataSource(target.getAbsolutePath());
                    metadata.put("duration_seconds", Double.parseDouble(retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_DURATION)) / 1000);
                    int width = Integer.parseInt(retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_WIDTH));
                    int height = Integer.parseInt(retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_HEIGHT));
                    String rotation = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_VIDEO_ROTATION);
                    boolean rotated = "90".equals(rotation) || "270".equals(rotation);
                    metadata.put("width", rotated ? height : width).put("height", rotated ? width : height);
                } catch (Exception ignored) { /* Metadata is optional; the server validates the media. */ }
                finally { retriever.release(); }
            } else {
                BitmapFactory.Options options = new BitmapFactory.Options(); options.inJustDecodeBounds = true;
                BitmapFactory.decodeFile(target.getAbsolutePath(), options);
                if (options.outWidth > 0) metadata.put("width", options.outWidth).put("height", options.outHeight);
            }
            store.addFile(account, metadata);
            committed = true;
            return metadata;
        } finally {
            temporary.delete();
            if (!committed) target.delete();
        }
    }

    static WebResourceResponse preview(UploadStore store, String account, String topUrl, WebResourceRequest request) {
        Uri uri = request.getUrl();
        if (uri.getPath() == null || !uri.getPath().startsWith(UploadPolicy.PREVIEW)) return null;
        Map<String, String> headers = new HashMap<>();
        headers.put("Cache-Control", "no-store"); headers.put("X-Content-Type-Options", "nosniff");
        headers.put("Cross-Origin-Resource-Policy", "same-origin"); headers.put("Content-Security-Policy", "default-src 'none'; sandbox");
        try {
            if (account.isEmpty() || !UploadPolicy.trusted(topUrl, BuildConfig.APP_URL) || !UploadPolicy.trusted(uri.toString(), BuildConfig.APP_URL)
                || request.isForMainFrame() || !("GET".equals(request.getMethod()) || "HEAD".equals(request.getMethod())) || uri.getQuery() != null) throw new IllegalArgumentException();
            String referer = header(request.getRequestHeaders(), "Referer");
            if (referer != null && !UploadPolicy.trusted(referer, BuildConfig.APP_URL)) throw new IllegalArgumentException();
            String handle = uri.getPath().substring(UploadPolicy.PREVIEW.length());
            JSONObject metadata = store.file(account, handle);
            File file = store.path(handle);
            String range = header(request.getRequestHeaders(), "Range");
            long[] bounds;
            try { bounds = UploadPolicy.range(range, file.length()); }
            catch (IllegalArgumentException error) {
                headers.put("Content-Range", "bytes */" + file.length());
                return new WebResourceResponse("text/plain", "UTF-8", 416, "Range Not Satisfiable", headers, new ByteArrayInputStream(new byte[0]));
            }
            long length = bounds[1] - bounds[0] + 1;
            headers.put("Accept-Ranges", "bytes"); headers.put("Content-Length", String.valueOf(length));
            if (range != null) headers.put("Content-Range", "bytes " + bounds[0] + "-" + bounds[1] + "/" + file.length());
            InputStream input;
            if ("HEAD".equals(request.getMethod())) input = new ByteArrayInputStream(new byte[0]);
            else {
                RandomAccessFile stream = new RandomAccessFile(file, "r"); stream.seek(bounds[0]);
                input = new InputStream() {
                    long remaining = length;
                    @Override public int read() throws java.io.IOException { byte[] one = new byte[1]; return read(one, 0, 1) < 0 ? -1 : one[0] & 255; }
                    @Override public int read(byte[] buffer, int offset, int count) throws java.io.IOException {
                        if (count == 0) return 0;
                        if (remaining <= 0) return -1;
                        int read = stream.read(buffer, offset, (int) Math.min(count, remaining));
                        if (read > 0) remaining -= read;
                        return read;
                    }
                    @Override public void close() throws java.io.IOException { stream.close(); }
                };
            }
            return new WebResourceResponse(metadata.getString("mime"), null, range == null ? 200 : 206, range == null ? "OK" : "Partial Content", headers, input);
        } catch (Exception error) {
            return new WebResourceResponse("text/plain", "UTF-8", 404, "Not Found", headers, new ByteArrayInputStream(new byte[0]));
        }
    }

    private static String header(Map<String, String> headers, String name) {
        for (Map.Entry<String, String> entry : headers.entrySet()) if (entry.getKey().equalsIgnoreCase(name)) return entry.getValue();
        return null;
    }
}
