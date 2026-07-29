import { onMounted, onUnmounted, watch } from 'vue';

function useInstantRevalidation(callback, options = {}) {
	const {
		routeKey = null,
		interval = 0,
		minDelay = 1500,
		immediate = false
	} = options;

	let refreshInProgress = false;
	let lastRefreshAt = 0;
	let deferredRefreshId = null;
	let intervalId = null;

	const run = async (reason = 'manual', force = false) => {
		if(refreshInProgress) {
			return false;
		}

		const elapsed = Date.now() - lastRefreshAt;

		if(! force && elapsed < minDelay) {
			clearTimeout(deferredRefreshId);

			deferredRefreshId = setTimeout(() => {
				run(reason, true);
			}, Math.max(minDelay - elapsed, 0));

			return false;
		}

		refreshInProgress = true;
		lastRefreshAt = Date.now();

		try {
			await callback(reason);
		}
		catch (error) {
			console.log(error);
		}
		finally {
			refreshInProgress = false;
		}
	};

	const handleVisibilityChange = () => {
		if(! document.hidden) {
			run('visibility');
		}
	};

	const handleWSStatus = (event) => {
		if(event.detail?.connected) {
			run('websocket');
		}
	};

	onMounted(() => {
		document.addEventListener('visibilitychange', handleVisibilityChange);
		window.addEventListener('focus', run);
		window.addEventListener('pageshow', run);
		window.addEventListener('online', run);
		window.addEventListener('colibri:ws-status', handleWSStatus);

		if(interval) {
			intervalId = setInterval(() => {
				run('interval');
			}, interval);
		}

		if(immediate) {
			run('mounted', true);
		}
	});

	onUnmounted(() => {
		clearTimeout(deferredRefreshId);

		if(intervalId) {
			clearInterval(intervalId);
		}

		document.removeEventListener('visibilitychange', handleVisibilityChange);
		window.removeEventListener('focus', run);
		window.removeEventListener('pageshow', run);
		window.removeEventListener('online', run);
		window.removeEventListener('colibri:ws-status', handleWSStatus);
	});

	if(routeKey) {
		watch(routeKey, () => {
			run('route', true);
		});
	}

	return {
		revalidate: run
	};
}

export { useInstantRevalidation };
