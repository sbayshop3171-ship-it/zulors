@php
    $host = request()->getHost();
    $isLoopbackHost = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);

    $shouldRegisterPwa = config('app.pwa_enabled')
        && ! app()->environment('local')
        && ! $isLoopbackHost
        && ! file_exists(public_path('hot'));
@endphp

@if ($shouldRegisterPwa)
	<link rel="manifest" href="{{ asset('pwa/manifest.json') }}">

	<script>
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', () => {
				const swVersion = @json((string) ($buildNumber ?? '1'));
				const swUrl = `/pwa/service-worker.js?v=${encodeURIComponent(swVersion)}`;

				navigator.serviceWorker.register(swUrl).then((reg) => {
					reg.update().catch(() => {});
					console.log('Service worker registered');
				}).catch((err) => {
					console.error('Service worker error', err);
				});
			});
		}
	</script>
@else
	<script>
		if ('serviceWorker' in navigator) {
			(async () => {
				const hadController = Boolean(navigator.serviceWorker.controller);
				const registrations = await navigator.serviceWorker.getRegistrations();

				await Promise.all(registrations.map((registration) => registration.unregister()));

				if ('caches' in window) {
					const cacheNames = await caches.keys();
					await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
				}

				if (hadController && ! sessionStorage.getItem('pwa-local-reset-complete')) {
					sessionStorage.setItem('pwa-local-reset-complete', 'true');
					window.location.reload();
					return;
				}

				sessionStorage.removeItem('pwa-local-reset-complete');
			})().catch((error) => {
				console.error('Failed to disable local service worker', error);
			});
		}
	</script>
@endif
