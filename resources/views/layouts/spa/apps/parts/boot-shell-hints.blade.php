@php
    $bootVariant = $variant ?? 'mobile';
    $bootCacheKey = $bootVariant === 'desktop'
        ? 'colibri.desktop.bootstrap.v1'
        : 'colibri.mobile.bootstrap.v1';
    $sharedFeedCacheKey = $bootVariant === 'desktop'
        ? 'colibri.desktop.timeline.public_feed.first_page.shared.v1'
        : 'colibri.mobile.timeline.public_feed.first_page.shared.v1';
    $bootCacheTtl = 1000 * 60 * 15;
    $sharedFeedCacheTtl = 1000 * 60 * 20;
    $bootBootstrapUrl = url('/api/bootstrap/bootstrap');
    $sharedFeedUrl = url('/api/bootstrap/home-feed-seed');
    $runtimeReverb = config('realtime.reverb', []);
    $runtimeReverbScheme = $runtimeReverb['scheme'] ?: (request()->isSecure() ? 'https' : 'http');
    $runtimeReverbPort = $runtimeReverb['port'] ?: ($runtimeReverbScheme === 'https' ? 443 : 80);
@endphp

<link rel="preload" href="{{ $logotypeUrl }}" as="image" fetchpriority="high">
<link rel="preload" href="{{ $bootBootstrapUrl }}" as="fetch" crossorigin="use-credentials" fetchpriority="high">
<link rel="preload" href="{{ $sharedFeedUrl }}" as="fetch" crossorigin="anonymous" fetchpriority="high">

<script>
    (function() {
        var variant = @json($bootVariant);
        var cacheKey = @json($bootCacheKey);
        var sharedFeedCacheKey = @json($sharedFeedCacheKey);
        var cacheTtl = @json($bootCacheTtl);
        var sharedFeedCacheTtl = @json($sharedFeedCacheTtl);
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
        bootState.cacheKey = cacheKey;
        bootState.cacheTtl = cacheTtl;
        bootState.sharedFeedCacheKey = sharedFeedCacheKey;
        bootState.sharedFeedCacheTtl = sharedFeedCacheTtl;
        startupState.variant = variant;
        startupState.cacheHit = false;

        var writeSharedFeedCache = function(posts) {
            if (!Array.isArray(posts) || !posts.length) {
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
            var posts = payload && Array.isArray(payload.posts) ? payload.posts : [];

            if (!posts.length) {
                return;
            }

            bootState.sharedFeed = payload;
            writeSharedFeedCache(posts);
        };

        try {
            var cacheEntry = window.localStorage.getItem(cacheKey);

            if (cacheEntry) {
                cacheEntry = JSON.parse(cacheEntry);

                if (
                    cacheEntry &&
                    cacheEntry.timestamp &&
                    (Date.now() - Number(cacheEntry.timestamp)) <= cacheTtl &&
                    cacheEntry.data &&
                    cacheEntry.data.auth &&
                    cacheEntry.data.auth.user
                ) {
                    document.documentElement.dataset.zulorsBootCache = 'hit';
                    bootState.cachedBootstrap = cacheEntry.data;
                    startupState.cacheHit = true;
                    rememberSharedFeedPayload(cacheEntry.data.home_feed || null);
                }
            }
        } catch (error) {
            //
        }

        try {
            var sharedFeedEntry = window.localStorage.getItem(sharedFeedCacheKey);

            if (sharedFeedEntry) {
                sharedFeedEntry = JSON.parse(sharedFeedEntry);

                if (
                    sharedFeedEntry &&
                    sharedFeedEntry.timestamp &&
                    (Date.now() - Number(sharedFeedEntry.timestamp)) <= sharedFeedCacheTtl &&
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

        var cachedBootstrap = bootState.cachedBootstrap || null;
        var cachedHomeFeed = cachedBootstrap && cachedBootstrap.home_feed ? cachedBootstrap.home_feed : null;
        bootState.skipSharedFeedRequest = Boolean(
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
            rememberSharedFeedPayload(response && response.data && response.data.data ? response.data.data.home_feed : null);

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
