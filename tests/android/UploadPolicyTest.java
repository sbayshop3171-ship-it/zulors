package com.zulors.app;

import org.junit.Test;
import static org.junit.Assert.*;
import okhttp3.Authenticator;
import okhttp3.CookieJar;
import okhttp3.OkHttpClient;

public class UploadPolicyTest {
    @Test public void originRequiresExactSchemeHostAndPort() {
        assertTrue(UploadPolicy.trusted("https://zulors.com:443/editor", "https://zulors.com/"));
        for (String value : new String[] {"https://zulors.com.evil.test", "https://sub.zulors.com", "http://zulors.com", "https://zulors.com:444", "https://user@zulors.com", "file:///zulors.com", "not-a-url"}) {
            assertFalse(value, UploadPolicy.trusted(value, "https://zulors.com"));
        }
    }

    @Test public void uploadDestinationsCannotReachAuthenticatedOrigin() {
        UploadPolicy.validateUploadUrl("https://bucket.r2.cloudflarestorage.com/file?signature=test", "https://zulors.com/");
        for (String value : new String[] {"https://zulors.com/api/upload", "http://bucket.r2.cloudflarestorage.com/file", "https://name:password@bucket.test/file", "file:///private/file"}) {
            assertThrows(IllegalArgumentException.class, () -> UploadPolicy.validateUploadUrl(value, "https://zulors.com/"));
        }
    }

    @Test public void onlyStorageHeadersCanReachUploadRequests() {
        for (String value : new String[] {"Authorization", "Cookie", "Cookie2", "X-XSRF-TOKEN", "X-CSRF-TOKEN", "Proxy-Authorization", "Origin", "Host", "Referer", "Content-Length", "Connection"}) assertFalse(value, UploadPolicy.safeUploadHeader(value));
        assertTrue(UploadPolicy.safeUploadHeader("Content-Type"));
        assertTrue(UploadPolicy.safeUploadHeader("x-amz-checksum-sha256"));
    }

    @Test public void httpClientHasNoAmbientCredentialsOrRedirects() {
        OkHttpClient client = UploadHttp.isolatedClient();
        assertSame(CookieJar.NO_COOKIES, client.cookieJar());
        assertSame(Authenticator.NONE, client.authenticator());
        assertSame(Authenticator.NONE, client.proxyAuthenticator());
        assertFalse(client.followRedirects());
        assertFalse(client.followSslRedirects());
        assertTrue(client.interceptors().isEmpty());
    }

    @Test public void previewRangesSupportSeekingAndSuffixes() {
        assertArrayEquals(new long[] {0, 99}, UploadPolicy.range(null, 100));
        assertArrayEquals(new long[] {10, 19}, UploadPolicy.range("bytes=10-19", 100));
        assertArrayEquals(new long[] {90, 99}, UploadPolicy.range("bytes=90-", 100));
        assertArrayEquals(new long[] {90, 99}, UploadPolicy.range("bytes=-10", 100));
        assertArrayEquals(new long[] {0, 99}, UploadPolicy.range("bytes=0-200", 100));
    }

    @Test public void previewRejectsInvalidOrMultipartRanges() {
        for (String value : new String[] {"bytes=100-", "bytes=20-10", "bytes=-0", "bytes=0-2,5-7", "other=0-1", "bytes=--1"}) assertThrows(value, IllegalArgumentException.class, () -> UploadPolicy.range(value, 100));
    }

    @Test public void handlesCannotAddressPaths() {
        assertEquals("00000000-0000-0000-0000-000000000001", UploadPolicy.uuid("00000000-0000-0000-0000-000000000001"));
        for (String value : new String[] {"../file", "/data/private", "1-1-1-1-1", "%2e%2e", "00000000-0000-0000-0000-000000000001/next"}) assertThrows(IllegalArgumentException.class, () -> UploadPolicy.uuid(value));
    }
}
