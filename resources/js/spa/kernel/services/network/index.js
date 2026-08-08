const listeners = new Set();

let isSubscribed = false;
let connectionChangeHandler = null;

const connectionProfile = () => {
	if(typeof navigator === 'undefined') {
		return null;
	}

	return navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;
};

const buildProfilePolicy = (profile, connection) => {
	const effectiveType = String(connection?.effectiveType || '');
	const downlinkMbps = Number(connection?.downlink || 0);
	const saveData = Boolean(connection?.saveData);
	const offline = typeof navigator !== 'undefined' && navigator.onLine === false;

	switch(profile) {
		case 'offline':
			return {
				profile,
				offline: true,
				saveData,
				effectiveType: effectiveType || 'offline',
				downlinkMbps: 0,
				allowVideoPrefetch: false,
				reelsNearRadius: 0,
				activeVideoPreload: 'metadata',
				reelsMinBufferSeconds: 1.4,
				stallRecoveryDelayMs: 1800
			};
		case 'very_slow':
			return {
				profile,
				offline,
				saveData,
				effectiveType,
				downlinkMbps,
				allowVideoPrefetch: false,
				reelsNearRadius: 0,
				activeVideoPreload: 'metadata',
				reelsMinBufferSeconds: 1.35,
				stallRecoveryDelayMs: 1600
			};
		case 'slow':
			return {
				profile,
				offline,
				saveData,
				effectiveType,
				downlinkMbps,
				allowVideoPrefetch: false,
				reelsNearRadius: 1,
				activeVideoPreload: 'auto',
				reelsMinBufferSeconds: 1.0,
				stallRecoveryDelayMs: 1400
			};
			case 'normal':
				return {
				profile,
				offline,
				saveData,
				effectiveType,
				downlinkMbps,
				allowVideoPrefetch: true,
					reelsNearRadius: 2,
					activeVideoPreload: 'auto',
					reelsMinBufferSeconds: 0.65,
					stallRecoveryDelayMs: 1100
			};
		default:
				return {
				profile: 'fast',
				offline,
				saveData,
				effectiveType,
				downlinkMbps,
				allowVideoPrefetch: true,
					reelsNearRadius: 2,
					activeVideoPreload: 'auto',
					reelsMinBufferSeconds: 0.35,
					stallRecoveryDelayMs: 900
			};
	}
};

const resolveProfileName = (connection) => {
	if(typeof navigator !== 'undefined' && navigator.onLine === false) {
		return 'offline';
	}

	if(! connection) {
		return 'normal';
	}

	const effectiveType = String(connection.effectiveType || '').toLowerCase();
	const downlinkMbps = Number(connection.downlink || 0);
	const saveData = Boolean(connection.saveData);

	if(saveData || effectiveType === 'slow-2g' || (downlinkMbps > 0 && downlinkMbps < 0.5)) {
		return 'very_slow';
	}

	if(effectiveType === '2g' || (downlinkMbps > 0 && downlinkMbps < 1.2)) {
		return 'slow';
	}

	if(effectiveType === '3g' || (downlinkMbps > 0 && downlinkMbps < 4)) {
		return 'normal';
	}

	return 'fast';
};

const emitProfile = () => {
	const snapshot = getNetworkProfileSnapshot();

	listeners.forEach((listener) => {
		try {
			listener(snapshot);
		}
		catch(error) {
			// Network state listeners should never block app behavior.
		}
	});
};

const subscribeRuntimeListeners = () => {
	if(isSubscribed || typeof window === 'undefined') {
		return;
	}

	isSubscribed = true;
	connectionChangeHandler = () => {
		emitProfile();
	};

	window.addEventListener('online', connectionChangeHandler);
	window.addEventListener('offline', connectionChangeHandler);
	connectionProfile()?.addEventListener?.('change', connectionChangeHandler);
};

const unsubscribeRuntimeListeners = () => {
	if(! isSubscribed || typeof window === 'undefined' || listeners.size > 0) {
		return;
	}

	window.removeEventListener('online', connectionChangeHandler);
	window.removeEventListener('offline', connectionChangeHandler);
	connectionProfile()?.removeEventListener?.('change', connectionChangeHandler);
	connectionChangeHandler = null;
	isSubscribed = false;
};

function getNetworkProfileSnapshot() {
	const connection = connectionProfile();

	return buildProfilePolicy(resolveProfileName(connection), connection);
}

function subscribeNetworkProfile(listener) {
	if(typeof listener !== 'function') {
		return () => {};
	}

	listeners.add(listener);
	subscribeRuntimeListeners();
	listener(getNetworkProfileSnapshot());

	return () => {
		listeners.delete(listener);
		unsubscribeRuntimeListeners();
	};
}

function isSlowNetworkProfile(profileSnapshot) {
	const profile = typeof profileSnapshot === 'string'
		? profileSnapshot
		: String(profileSnapshot?.profile || '');

	return ['offline', 'very_slow', 'slow'].includes(profile);
}

export {
	getNetworkProfileSnapshot,
	subscribeNetworkProfile,
	isSlowNetworkProfile
};
