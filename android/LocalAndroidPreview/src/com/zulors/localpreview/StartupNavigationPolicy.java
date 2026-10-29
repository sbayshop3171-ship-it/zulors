package com.zulors.app;

import java.net.URI;
import java.net.URISyntaxException;
import java.util.Locale;

final class StartupNavigationPolicy {
    static final class LaunchPlan {
        final String initialUrl;
        final boolean homeFallbackAvailable;

        LaunchPlan(String initialUrl, boolean homeFallbackAvailable) {
            this.initialUrl = initialUrl;
            this.homeFallbackAvailable = homeFallbackAvailable;
        }
    }

    private StartupNavigationPolicy() {}

    static LaunchPlan forColdStart(String homeUrl, String requestedUrl, boolean googleCallback) {
        if (requestedUrl == null || requestedUrl.trim().isEmpty()) {
            return new LaunchPlan(homeUrl, false);
        }

        return new LaunchPlan(
            requestedUrl,
            !googleCallback && hasHomeFallback(homeUrl, requestedUrl)
        );
    }

    static boolean hasHomeFallback(String homeUrl, String targetUrl) {
        return !isHomeUrl(homeUrl, targetUrl);
    }

    static boolean shouldLoadHomeFallback(String homeUrl, String currentUrl, boolean fallbackAvailable) {
        return fallbackAvailable && !isHomeUrl(homeUrl, currentUrl);
    }

    private static boolean isHomeUrl(String homeUrl, String candidateUrl) {
        if (homeUrl == null || candidateUrl == null) {
            return false;
        }

        try {
            URI home = new URI(homeUrl.trim());
            URI candidate = new URI(candidateUrl.trim());
            String homePath = home.getPath();
            String candidatePath = candidate.getPath();

            return normalize(home.getScheme()).equals(normalize(candidate.getScheme()))
                && normalize(home.getHost()).equals(normalize(candidate.getHost()))
                && effectivePort(home) == effectivePort(candidate)
                && isRootPath(homePath)
                && isRootPath(candidatePath)
                && candidate.getFragment() == null;
        }
        catch (URISyntaxException exception) {
            return false;
        }
    }

    private static String normalize(String value) {
        return value == null ? "" : value.toLowerCase(Locale.ROOT);
    }

    private static int effectivePort(URI uri) {
        if (uri.getPort() >= 0) {
            return uri.getPort();
        }

        String scheme = normalize(uri.getScheme());
        if ("https".equals(scheme)) {
            return 443;
        }
        if ("http".equals(scheme)) {
            return 80;
        }

        return -1;
    }

    private static boolean isRootPath(String path) {
        return path == null || path.isEmpty() || "/".equals(path);
    }
}
