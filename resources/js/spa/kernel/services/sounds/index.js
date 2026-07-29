import { Howl, Howler } from 'howler';

const soundInstances = new Map();
let unlockListenersAttached = false;

function getStoredPreference(key) {
	if(typeof window === 'undefined' || ! window.localStorage) {
		return null;
	}

	try {
		return window.localStorage.getItem(key);
	}
	catch(error) {
		return null;
	}
}

function setStoredPreference(key, value) {
	if(typeof window === 'undefined' || ! window.localStorage) {
		return;
	}

	try {
		window.localStorage.setItem(key, value);
	}
	catch(error) {}
}

const colibriSounds = {
	sounds: embedder('config.sounds'),
	activeChatMessageReceived: function() {
		colibriSounds.playSound(colibriSounds.sounds.chat.active_chat_message_received, {
			volume: 0.8
		});
	},
	backgroundChatMessageReceived: function() {
		colibriSounds.playSound(colibriSounds.sounds.chat.background_chat_message_received, {
			volume: 1
		});
	},
	chatMessageSent: function() {
		colibriSounds.playSound(colibriSounds.sounds.chat.chat_message_sent, {
			volume: 0.5
		});
	},
	notificationReceived: function() {
		colibriSounds.playSound(colibriSounds.sounds.notification.received, {
			volume: 1
		});
	},
	uiFeedback: function() {
		colibriSounds.playSound(colibriSounds.sounds.notification.ui_feedback, {
			volume: 0.6
		});
	},
	isNotificationsSoundEnabled: function() {
		return getStoredPreference('notificationsSound') !== '0';
	},
	setNotificationsSoundEnabled: function(isEnabled) {
		setStoredPreference('notificationsSound', isEnabled ? '1' : '0');
	},
	unlockAudio: function() {
		if(Howler.ctx && Howler.ctx.state === 'suspended') {
			Howler.ctx.resume().catch(() => {});
		}
	},
	attachUnlockListeners: function() {
		if(unlockListenersAttached || typeof window === 'undefined') {
			return;
		}

		let unlock = () => {
			colibriSounds.unlockAudio();
			colibriSounds.preloadNotificationSounds();
		};

		window.addEventListener('pointerdown', unlock, { passive: true });
		window.addEventListener('touchstart', unlock, { passive: true });
		window.addEventListener('keydown', unlock);
		unlockListenersAttached = true;
	},
	preloadNotificationSounds: function() {
		if(! colibriSounds.isNotificationsSoundEnabled()) {
			return;
		}

		colibriSounds.getSound(colibriSounds.sounds.chat.background_chat_message_received, 1);
		colibriSounds.getSound(colibriSounds.sounds.notification.received, 1);
	},
	getSound: function(soundSourceUrl, volume) {
		if(! soundInstances.has(soundSourceUrl)) {
			soundInstances.set(soundSourceUrl, new Howl({
				src: [soundSourceUrl],
				volume: volume,
				preload: true
			}));
		}

		let sound = soundInstances.get(soundSourceUrl);

		sound.volume(volume);

		return sound;
	},
	playSound: function(soundSourceUrl, options = {}) {
		if(! soundSourceUrl) {
			return;
		}

		let volume = Number.isFinite(options.volume) ? options.volume : 0.5;
		let sound = colibriSounds.getSound(soundSourceUrl, volume);

		colibriSounds.unlockAudio();
		sound.play();
	}
};

colibriSounds.attachUnlockListeners();

export { colibriSounds };
