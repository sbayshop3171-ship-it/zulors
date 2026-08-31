@php
    $bootVariant = $variant ?? 'mobile';
    $bootIsAuthenticated = auth_check();
    $bootAuthUserId = $bootIsAuthenticated ? me()?->id : null;
    $bootCacheKey = $bootVariant === 'desktop'
        ? 'colibri.desktop.bootstrap.v1'
        : 'colibri.mobile.bootstrap.v1';
    $sharedFeedCacheKey = $bootVariant === 'desktop'
        ? 'colibri.desktop.timeline.public_feed.first_page.shared.v1'
        : 'colibri.mobile.timeline.public_feed.first_page.shared.v1';
    $bootCacheTtl = 1000 * 60 * 15;
    $bootCacheMaxAge = 1000 * 60 * 60 * 24 * 7;
    $sharedFeedCacheTtl = 1000 * 60 * 20;
    $sharedFeedCacheMaxAge = 1000 * 60 * 60 * 24 * 7;
    $bootBootstrapUrl = url('/api/bootstrap/bootstrap');
    $sharedFeedUrl = url('/api/bootstrap/home-feed-seed');
    $runtimeReverb = config('realtime.reverb', []);
    $runtimeReverbScheme = $runtimeReverb['scheme'] ?: (request()->isSecure() ? 'https' : 'http');
    $runtimeReverbPort = $runtimeReverb['port'] ?: ($runtimeReverbScheme === 'https' ? 443 : 80);
@endphp

<link rel="preload" href="{{ $logotypeUrl }}" as="image" fetchpriority="high">
<link rel="preload" href="{{ $bootBootstrapUrl }}" as="fetch" crossorigin="use-credentials" fetchpriority="high">
@unless($bootIsAuthenticated)
    <link rel="preload" href="{{ $sharedFeedUrl }}" as="fetch" crossorigin="anonymous" fetchpriority="high">
@endunless

<script>
    (function() {
        var variant = @json($bootVariant);
        var isAuthenticated = @json($bootIsAuthenticated);
        var authUserId = @json($bootAuthUserId);
        var cacheKey = @json($bootCacheKey);
        var sharedFeedCacheKey = @json($sharedFeedCacheKey);
        var cacheTtl = @json($bootCacheTtl);
        var cacheMaxAge = @json($bootCacheMaxAge);
        var sharedFeedCacheTtl = @json($sharedFeedCacheTtl);
        var sharedFeedCacheMaxAge = @json($sharedFeedCacheMaxAge);
        var bootstrapUrl = @json($bootBootstrapUrl);
        var sharedFeedUrl = @json($sharedFeedUrl);
        var bootState = window.__zulorsBoot = window.__zulorsBoot || {};
        var startupState = window.__zulorsStartup = window.__zulorsStartup || {
            launchedAt: Date.now(),
            perfStartedAt: (window.performance && typeof window.performance.now === 'function') ? Math.round(window.performance.now()) : Date.now(),
            marks: {},
            nativeReadySent: false
        };
        window.__zulorsRealtime = {
            reverb: {
                enabled: @json((bool) ($runtimeReverb['enabled'] ?? false) && filled($runtimeReverb['app_key'] ?? null)),
                app_key: @json($runtimeReverb['app_key'] ?? null),
                host: @json(($runtimeReverb['host'] ?? null) ?: request()->getHost()),
                port: @json((int) $runtimeReverbPort),
                scheme: @json($runtimeReverbScheme)
            }
        };

        bootState.variant = variant;
        bootState.isAuthenticated = Boolean(isAuthenticated);
        bootState.authUserId = authUserId;
        bootState.cacheKey = cacheKey;
        bootState.cacheTtl = cacheTtl;
        bootState.cacheMaxAge = cacheMaxAge;
        bootState.sharedFeedCacheKey = sharedFeedCacheKey;
        bootState.sharedFeedCacheTtl = sharedFeedCacheTtl;
        bootState.sharedFeedCacheMaxAge = sharedFeedCacheMaxAge;
        startupState.variant = variant;
        startupState.cacheHit = false;

        var writeSharedFeedCache = function(posts) {
            if (bootState.isAuthenticated || !Array.isArray(posts) || !posts.length) {
                return;
            }

            try {
                window.localStorage.setItem(sharedFeedCacheKey, JSON.stringify({
                    data: posts,
                    timestamp: Date.now()
                }));
            } catch (error) {
                //
            }
        };

        var rememberSharedFeedPayload = function(payload) {
            if (bootState.isAuthenticated) {
                bootState.sharedFeed = null;
                return;
            }

            var posts = payload && Array.isArray(payload.posts) ? payload.posts : [];

            if (!posts.length) {
                return;
            }

            bootState.sharedFeed = payload;
            writeSharedFeedCache(posts);
        };

        var applyBootstrapPayload = function(payload) {
            if (!payload || typeof payload !== 'object') {
                return;
            }

            bootState.cachedBootstrap = payload;
            bootState.isAuthenticated = Boolean(payload.auth && payload.auth.user);
            bootState.authUserId = payload.auth && payload.auth.user ? payload.auth.user.id : null;

            if (bootState.isAuthenticated) {
                bootState.sharedFeed = null;
                return;
            }

            rememberSharedFeedPayload(payload.home_feed || null);
        };

        try {
            var cacheEntry = window.localStorage.getItem(cacheKey);

            if (cacheEntry) {
                cacheEntry = JSON.parse(cacheEntry);

                if (
                    cacheEntry &&
                    cacheEntry.timestamp &&
                    (Date.now() - Number(cacheEntry.timestamp)) <= cacheMaxAge &&
                    cacheEntry.data &&
                    cacheEntry.data.auth &&
                    cacheEntry.data.auth.user
                ) {
                    document.documentElement.dataset.zulorsBootCache = 'hit';
                    startupState.cacheHit = true;
                    applyBootstrapPayload(cacheEntry.data);
                }
            }
        } catch (error) {
            //
        }

        if (!bootState.isAuthenticated) {
            try {
                var sharedFeedEntry = window.localStorage.getItem(sharedFeedCacheKey);

                if (sharedFeedEntry) {
                    sharedFeedEntry = JSON.parse(sharedFeedEntry);

                    if (
                        sharedFeedEntry &&
                        sharedFeedEntry.timestamp &&
                        (Date.now() - Number(sharedFeedEntry.timestamp)) <= sharedFeedCacheMaxAge &&
                        Array.isArray(sharedFeedEntry.data) &&
                        sharedFeedEntry.data.length
                    ) {
                        bootState.sharedFeed = {
                            type: 'for_you',
                            session_id: 'shared-cache',
                            refresh_reason: 'seed',
                            posts: sharedFeedEntry.data,
                            meta: {
                                feed: {
                                    type: 'for_you',
                                    strategy: 'shared_cache',
                                    scored: false
                                }
                            }
                        };
                    }
                }
            } catch (error) {
                //
            }
        }

        var cachedBootstrap = bootState.cachedBootstrap || null;
        var cachedHomeFeed = cachedBootstrap && cachedBootstrap.home_feed ? cachedBootstrap.home_feed : null;
        bootState.skipSharedFeedRequest = Boolean(
            bootState.isAuthenticated ||
            cachedBootstrap &&
            cachedBootstrap.auth &&
            cachedBootstrap.auth.user &&
            cachedHomeFeed &&
            Array.isArray(cachedHomeFeed.posts) &&
            cachedHomeFeed.posts.length
        );

        if (!window.fetch || bootState.bootstrapRequest) {
            return;
        }

        bootState.bootstrapRequest = window.fetch(bootstrapUrl, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(async function(response) {
            var payload = null;
            var contentType = String(response.headers.get('content-type') || '').toLowerCase();

            if (contentType.indexOf('application/json') !== -1) {
                payload = await response.json().catch(function() {
                    return null;
                });
            }

            if (!response.ok) {
                var error = new Error('Bootstrap request failed with status ' + response.status);

                error.response = {
                    status: response.status,
                    data: payload
                };

                throw error;
            }

            return {
                status: response.status,
                data: payload,
                headers: {
                    'server-timing': response.headers.get('server-timing'),
                    'x-zulors-home-feed-cache': response.headers.get('x-zulors-home-feed-cache')
                }
            };
        }).then(function(response) {
            applyBootstrapPayload(response && response.data ? response.data.data : null);

            return response;
        }).catch(function(error) {
            if (bootState.bootstrapRequest) {
                bootState.bootstrapRequest = null;
            }

            throw error;
        });

        if (!bootState.sharedFeedRequest && !bootState.skipSharedFeedRequest) {
            bootState.sharedFeedRequest = window.fetch(sharedFeedUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(async function(response) {
                var payload = null;
                var contentType = String(response.headers.get('content-type') || '').toLowerCase();

                if (contentType.indexOf('application/json') !== -1) {
                    payload = await response.json().catch(function() {
                        return null;
                    });
                }

                if (!response.ok) {
                    return null;
                }

                return {
                    status: response.status,
                    data: payload,
                    headers: {
                        'server-timing': response.headers.get('server-timing'),
                        'x-zulors-home-feed-cache': response.headers.get('x-zulors-home-feed-cache')
                    }
                };
            }).then(function(response) {
                rememberSharedFeedPayload(response && response.data ? response.data.data : null);

                return response;
            }).catch(function() {
                return null;
            });
        }
    })();
</script>
