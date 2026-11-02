import test from 'node:test';
import assert from 'node:assert/strict';

import { createAgoraAudioCallPeer } from '../../resources/js/spa/kernel/services/calls/agora-audio-call.js';

class FakeMediaStream {
    constructor(tracks = []) {
        this.tracks = tracks;
    }

    getTracks() {
        return this.tracks;
    }

    getAudioTracks() {
        return this.tracks.filter((track) => track.kind === 'audio');
    }

    getVideoTracks() {
        return this.tracks.filter((track) => track.kind === 'video');
    }
}

class FakeEventClient {
    constructor() {
        this.handlers = new Map();
        this.remoteUsers = [];
        this.subscribeCalls = 0;
    }

    on(name, callback) {
        const listeners = this.handlers.get(name) || [];
        listeners.push(callback);
        this.handlers.set(name, listeners);
    }

    async emit(name, ...args) {
        for(const callback of this.handlers.get(name) || []) {
            await callback(...args);
        }
    }

    join(appId, channel, token, uid, options) {
        this.joinOptions = options;

        return Promise.resolve(uid || 10);
    }

    publish() {
        return Promise.resolve();
    }

    subscribe() {
        this.subscribeCalls += 1;

        return Promise.resolve();
    }

    leave() {
        return Promise.resolve();
    }
}

const makeTrack = (id) => {
    const mediaTrack = {
        id: id,
        kind: 'audio',
        enabled: true,
    };
    const track = {
        stopped: 0,
        volume: null,
        getMediaStreamTrack: () => mediaTrack,
        setVolume(volume) {
            this.volume = volume;
        },
        setMuted: () => Promise.resolve(),
        stop() {
            this.stopped += 1;
        },
        close: () => {},
    };

    return track;
};

const setupBrowserAudio = ({ playback, callbacks = {}, AgoraRTCOverrides = {} } = {}) => {
    const previousWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');
    const previousNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    const previousMediaStream = Object.getOwnPropertyDescriptor(globalThis, 'MediaStream');
    const client = new FakeEventClient();
    const localTrack = makeTrack('local-audio-track');
    const AgoraRTC = {
        createClient: () => client,
        createMicrophoneAudioTrack: () => Promise.resolve(localTrack),
        ...AgoraRTCOverrides,
    };
    const window = {
        ZulorsCallAudio: {},
        setTimeout: globalThis.setTimeout,
        clearTimeout: globalThis.clearTimeout,
        setInterval: globalThis.setInterval,
        clearInterval: globalThis.clearInterval,
        requestAnimationFrame: (callback) => globalThis.setTimeout(callback, 0),
        addEventListener: () => {},
        removeEventListener: () => {},
    };
    const navigator = {
        userAgent: 'Android',
        vendor: '',
        maxTouchPoints: 0,
        mediaDevices: {
            getUserMedia: () => Promise.resolve(new FakeMediaStream()),
        },
    };

    Object.defineProperty(globalThis, 'window', {
        configurable: true,
        writable: true,
        value: window,
    });
    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        writable: true,
        value: navigator,
    });
    Object.defineProperty(globalThis, 'MediaStream', {
        configurable: true,
        writable: true,
        value: FakeMediaStream,
    });

    const restore = () => {
        for(const [key, descriptor] of [
            ['window', previousWindow],
            ['navigator', previousNavigator],
            ['MediaStream', previousMediaStream],
        ]) {
            if(descriptor) {
                Object.defineProperty(globalThis, key, descriptor);
            }
            else {
                delete globalThis[key];
            }
        }
    };

    const outputElement = {
        srcObject: null,
        volume: 1,
        muted: false,
        paused: false,
        readyState: 4,
        playCalls: 0,
        play() {
            this.playCalls += 1;

            return playback
                ? playback(this.playCalls)
                : Promise.resolve();
        },
    };
    const peer = createAgoraAudioCallPeer(callbacks, {
        AgoraRTC: AgoraRTC,
        mediaSession: {
            provider: 'agora',
            app_id: 'test-app',
            channel: 'test-channel',
            uid: 10,
        },
    });
    peer.attachRemoteOutputElement(outputElement);

    return {
        client,
        localTrack,
        outputElement,
        peer,
        restore,
    };
};

const publishRemoteAudioAfterJoin = async (client, remoteTrack) => {
    const remoteUser = {
        uid: 20,
        hasAudio: true,
        audioTrack: null,
    };
    client.remoteUsers = [remoteUser];

    await client.emit('user-published', remoteUser, 'audio');
    assert.equal(client.subscribeCalls, 0);

    remoteUser.audioTrack = remoteTrack;
    await client.emit('user-info-updated');

    return remoteUser;
};

test('web Agora uses auto receive without a duplicate manual subscription or player', async () => {
    const browser = setupBrowserAudio();

    try {
        await browser.peer.ensurePeerConnection();
        assert.equal(browser.client.joinOptions.autoReceiveAndPlayAudio, true);

        const remoteTrack = makeTrack('remote-audio-track');
        await publishRemoteAudioAfterJoin(browser.client, remoteTrack);

        assert.equal(browser.client.subscribeCalls, 0);
        assert.equal(browser.outputElement.playCalls, 1);
        assert.equal(remoteTrack.volume, 100);
        assert.ok(remoteTrack.stopped >= 1);
    }
    finally {
        browser.peer.close();
        browser.restore();
    }
});

test('web Agora reports blocked autoplay and resumes after a user gesture', async () => {
    const callbackEvents = [];
    const browser = setupBrowserAudio({
        callbacks: {
            onAudioPlaybackBlocked: () => callbackEvents.push('blocked'),
            onAudioPlaybackResumed: () => callbackEvents.push('resumed'),
        },
        AgoraRTCOverrides: {
            resumeAudioContext: () => Promise.resolve(),
        },
        playback: (playCall) => {
            if(playCall === 1) {
                const error = new Error('Autoplay is not allowed.');
                error.name = 'NotAllowedError';

                return Promise.reject(error);
            }

            return Promise.resolve();
        },
    });

    try {
        browser.peer.attachRemoteOutputElement(browser.outputElement);
        await browser.peer.ensurePeerConnection();
        await publishRemoteAudioAfterJoin(browser.client, makeTrack('remote-audio-track'));
        await new Promise((resolve) => setImmediate(resolve));

        assert.deepEqual(callbackEvents, ['blocked']);
        assert.equal(await browser.peer.resumeAudioPlayback(), true);
        assert.equal(browser.outputElement.playCalls, 2);
        assert.deepEqual(callbackEvents, ['blocked', 'resumed']);
    }
    finally {
        browser.peer.close();
        browser.restore();
    }
});
