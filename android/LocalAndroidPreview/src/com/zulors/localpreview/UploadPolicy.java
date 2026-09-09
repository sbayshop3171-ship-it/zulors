package com.zulors.app;

import java.net.URI;
import java.util.Locale;
import java.util.UUID;

final class UploadPolicy {
    static final String API = "/api/media-publications";
    static final String PREVIEW = "/__zulors_native_media__/";

    static String origin(String value) {
        try {
            URI uri = new URI(value);
            String scheme = uri.getScheme().toLowerCase(Locale.US);
            String host = uri.getHost().toLowerCase(Locale.US);
            if ((!scheme.equals("https") && !scheme.equals("http")) || uri.getUserInfo() != null) return "";
            int port = uri.getPort();
            return scheme + "://" + host + ((port < 0 || port == (scheme.equals("https") ? 443 : 80)) ? "" : ":" + port);
        } catch (Exception error) { return ""; }
    }

    static boolean trusted(String value, String appUrl) {
        String expected = origin(appUrl);
        return !expected.isEmpty() && expected.equals(origin(value));
    }

    static String uuid(String value) {
        String canonical = UUID.fromString(value).toString();
        if (!canonical.equalsIgnoreCase(value)) throw new IllegalArgumentException("invalid_id");
        return canonical;
    }

    static boolean terminal(String status) {
        return "published".equals(status) || "cancelled".equals(status);
    }

    static boolean uploaded(String status) {
        return "uploaded".equals(status) || "processing".equals(status) || "processed".equals(status)
            || "ready".equals(status) || "published".equals(status);
    }

    static boolean safeUploadHeader(String name) {
        String key = name.toLowerCase(Locale.US);
        return key.equals("content-type") || key.equals("content-md5") || key.equals("cache-control")
            || key.equals("content-disposition") || key.startsWith("x-amz-");
    }

    static void validateUploadUrl(String value, String appUrl) {
        try {
            URI uri = new URI(value);
            if (!"https".equals(uri.getScheme()) || uri.getHost() == null || uri.getUserInfo() != null
                || uri.getFragment() != null || trusted(value, appUrl)) throw new IllegalArgumentException();
        } catch (Exception error) { throw new IllegalArgumentException("invalid_upload_url"); }
    }

    // HTTP ranges are inclusive. The signed multipart descriptor's end is exclusive (Blob.slice).
    static long[] range(String header, long size) {
        if (size <= 0) throw new IllegalArgumentException("invalid_range");
        if (header == null || header.isEmpty()) return new long[] {0, size - 1};
        if (!header.matches("bytes=(\\d+-\\d*|-\\d+)")) throw new IllegalArgumentException("invalid_range");
        String[] bounds = header.substring(6).split("-", -1);
        long start = bounds[0].isEmpty() ? Math.max(0, size - Long.parseLong(bounds[1])) : Long.parseLong(bounds[0]);
        long end = bounds[0].isEmpty() || bounds[1].isEmpty() ? size - 1 : Math.min(size - 1, Long.parseLong(bounds[1]));
        if (start < 0 || start >= size || end < start) throw new IllegalArgumentException("invalid_range");
        return new long[] {start, end};
    }
}
