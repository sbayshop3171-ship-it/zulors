@php
    $bootVariant = $variant ?? 'mobile';
    $bootCacheKey = $bootVariant === 'desktop'
        ? 'colibri.desktop.bootstrap.v1'
        : 'colibri.mobile.bootstrap.v1';
    $bootCacheTtl = 1000 * 60 * 15;
    $bootBootstrapUrl = url('/api/bootstrap/bootstrap');
@endphp

<link rel="preload" href="{{ $logotypeUrl }}" as="image" fetchpriority="high">
<link rel="preload" href="{{ $bootBootstrapUrl }}" as="fetch" crossorigin="use-credentials" fetchpriority="high">

<script>
    (function() {
        var variant = @json($bootVariant);
        var cacheKey = @json($bootCacheKey);
        var cacheTtl = @json($bootCacheTtl);
        var bootstrapUrl = @json($bootBootstrapUrl);
        var bootState = window.__zulorsBoot = window.__zulorsBoot || {};

        bootState.variant = variant;
        bootState.cacheKey = cacheKey;
        bootState.cacheTtl = cacheTtl;

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
                }
            }
        } catch (error) {
            //
        }

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
                data: payload
            };
        }).catch(function(error) {
            if (bootState.bootstrapRequest) {
                bootState.bootstrapRequest = null;
            }

            throw error;
        });
    })();
</script>
