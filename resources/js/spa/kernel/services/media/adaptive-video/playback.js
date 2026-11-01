import Hls from 'hls.js';
import { getNetworkProfileSnapshot, isSlowNetworkProfile } from '../../network/index.js';
import { canVideoElementPlayNatively, HLS_CONTENT_TYPE } from './index.js';

const hlsOptionsFor = (networkProfile) => ({
	autoStartLoad: false,
	enableWorker: true,
	capLevelToPlayerSize: true,
	lowLatencyMode: false,
	backBufferLength: networkProfile?.reelsBackBufferLength ?? 6,
	startLevel: isSlowNetworkProfile(networkProfile) || networkProfile?.saveData ? 0 : -1,
	maxBufferLength: networkProfile?.reelsMaxBufferLength ?? 12,
	maxMaxBufferLength: networkProfile?.reelsMaxMaxBufferLength ?? 18
});

export function createAdaptiveVideoPlaybackController(videoElement, options = {}) {
	const HlsClass = options.HlsClass || Hls;
	const getNetworkProfile = options.getNetworkProfile || getNetworkProfileSnapshot;
	let hlsInstance = null;
	let source = null;
	let fallbackSource = null;
	let networkRetries = 0;
	let mediaRetries = 0;
	let loadRequested = false;
	let destroyed = false;
	let resumePosition = 0;
	let nativeErrorHandler = null;

	const releaseHls = () => {
		if(! hlsInstance) {
			return;
		}

		try {
			hlsInstance.destroy();
		}
		catch(error) {}

		hlsInstance = null;
	};

	const clearVideoSource = () => {
		if(! videoElement) {
			return;
		}

		videoElement.pause?.();
		if(nativeErrorHandler) {
			videoElement.removeEventListener?.('error', nativeErrorHandler);
			nativeErrorHandler = null;
		}
		videoElement.removeAttribute?.('src');
		videoElement.load?.();
	};

	const attach = (nextSource, nextFallback = null) => {
		if(destroyed || ! videoElement || ! nextSource?.url) {
			return false;
		}

		releaseHls();
		clearVideoSource();
		source = nextSource;
		fallbackSource = nextFallback?.url && nextFallback.url !== nextSource.url ? nextFallback : null;
		networkRetries = 0;
		mediaRetries = 0;

		const canUseNativeHls = nextSource.transport === 'hls'
			&& canVideoElementPlayNatively(videoElement, nextSource.type || HLS_CONTENT_TYPE);
		const shouldUseHlsJs = nextSource.transport === 'hls'
			&& ! canUseNativeHls
			&& Boolean(HlsClass?.isSupported?.());

		if(nextSource.transport === 'hls' && ! canUseNativeHls && ! shouldUseHlsJs) {
			if(! fallbackSource) {
				options.onFatalError?.({ type: 'unsupported_hls', fatal: true });
				return false;
			}

			const directSource = fallbackSource;
			fallbackSource = null;
			options.onFallback?.();

			return attach(directSource);
		}

		if(! shouldUseHlsJs) {
			videoElement.preload = 'metadata';
			if(canUseNativeHls && fallbackSource) {
				nativeErrorHandler = () => {
					const directSource = fallbackSource;
					if(! directSource) {
						return;
					}
					fallbackSource = null;
					options.onFallback?.();
					attach(directSource);
				};
				videoElement.addEventListener?.('error', nativeErrorHandler, { once: true });
			}
			videoElement.src = nextSource.url;
			videoElement.load?.();

			return true;
		}

		const instance = new HlsClass(hlsOptionsFor(getNetworkProfile()));
		hlsInstance = instance;

		const useFallback = () => {
			if(hlsInstance !== instance || ! fallbackSource?.url) {
				return false;
			}

			const directSource = fallbackSource;
			fallbackSource = null;
			releaseHls();
			options.onFallback?.();

			return attach(directSource);
		};

		instance.on(HlsClass.Events.ERROR, (event, data = {}) => {
			if(hlsInstance !== instance || ! data.fatal) {
				return;
			}

			if(data.type === HlsClass.ErrorTypes.NETWORK_ERROR && networkRetries < 1) {
				networkRetries += 1;
				try {
					instance.startLoad(Number(videoElement.currentTime || 0));
					return;
				}
				catch(error) {}
			}

			if(data.type === HlsClass.ErrorTypes.MEDIA_ERROR && mediaRetries < 1) {
				mediaRetries += 1;
				try {
					instance.recoverMediaError();
					return;
				}
				catch(error) {}
			}

			if(! useFallback()) {
				options.onFatalError?.(data);
			}
		});

		if(HlsClass.Events.LEVEL_SWITCHED) {
			instance.on(HlsClass.Events.LEVEL_SWITCHED, (event, data = {}) => {
				const height = instance.levels?.[data.level]?.height || 0;
				if(height > 0) {
					options.onRendition?.(height);
				}
			});
		}

		instance.on(HlsClass.Events.MEDIA_ATTACHED, () => {
			if(hlsInstance !== instance) {
				return;
			}

			instance.loadSource(nextSource.url);
			if(loadRequested) {
				instance.startLoad(resumePosition || Number(videoElement.currentTime || 0));
			}
		});
		instance.attachMedia(videoElement);

		return true;
	};

	return {
		setSource(nextSource, nextFallback = null) {
			source = nextSource?.url ? nextSource : null;
			fallbackSource = nextFallback?.url && nextFallback.url !== nextSource?.url ? nextFallback : null;
		},
		attach(nextSource, nextFallback = null) {
			loadRequested = true;
			return attach(nextSource, nextFallback);
		},
		startLoad(startPosition = Number(videoElement?.currentTime || 0)) {
			loadRequested = true;
			try {
				hlsInstance?.startLoad(startPosition);
			}
			catch(error) {}
		},
		stopLoad() {
			loadRequested = false;
			try {
				hlsInstance?.stopLoad();
			}
			catch(error) {}
		},
		suspend(startPosition = Number(videoElement?.currentTime || 0)) {
			resumePosition = Number.isFinite(startPosition) ? Math.max(0, startPosition) : 0;
			loadRequested = false;
			releaseHls();
			clearVideoSource();
		},
		resume() {
			if(! source?.url || destroyed) {
				return false;
			}

			loadRequested = true;
			const resumed = attach(source, fallbackSource);
			if(resumed && resumePosition > 0) {
				const seekToResumePosition = () => {
					try {
						videoElement.currentTime = resumePosition;
					}
					catch(error) {}
				};
				videoElement.addEventListener?.('loadedmetadata', seekToResumePosition, { once: true });
			}
			return resumed;
		},
		destroy() {
			destroyed = true;
			loadRequested = false;
			source = null;
			fallbackSource = null;
			releaseHls();
			clearVideoSource();
		}
	};
}
