import test from 'node:test';
import assert from 'node:assert/strict';

import {
    useInputHandlers,
    buildViewportMetaContent,
    getSafeViewportHeight,
    syncViewportHeight,
    installViewportKeyboardGuard,
    CHAT_SAFE_BOTTOM_VARIABLE,
} from '../../resources/js/spa/kernel/vue/composables/input/index.js';

test('preserveInputFocus clears a textarea and keeps it focused without collapsing the keyboard state', () => {
    const api = useInputHandlers();
    const previousRequestAnimationFrame = globalThis.requestAnimationFrame;

    globalThis.requestAnimationFrame = (callback) => {
        callback();
        return 1;
    };

    const field = {
        value: 'hello world',
        style: {},
        selectionStart: 5,
        selectionEnd: 5,
        focused: false,
        preventScroll: false,
        focus({ preventScroll = true } = {}) {
            this.focused = true;
            this.preventScroll = preventScroll;
        },
        setSelectionRange(start, end) {
            this.selectionStart = start;
            this.selectionEnd = end;
        },
    };

    const wasFocused = api.preserveInputFocus(field, '');

    assert.equal(wasFocused, true);
    assert.equal(field.value, '');
    assert.equal(field.focused, true);
    assert.equal(field.preventScroll, true);
    assert.equal(field.style.height, 'auto');

    globalThis.requestAnimationFrame = previousRequestAnimationFrame;
});

test('buildViewportMetaContent keeps the mobile viewport keyboard-safe and resize-aware', () => {
    const content = buildViewportMetaContent();

    assert.match(content, /viewport-fit=cover/);
    assert.match(content, /interactive-widget=resizes-content/);
    assert.match(content, /user-scalable=no/);
});

test('viewport sizing keeps the app height locked so keyboard resize does not pan the whole screen', () => {
    const previousWindow = globalThis.window;
    const previousDocument = globalThis.document;

    const element = {
        style: {
            setProperty: (name, value) => {
                globalThis.__lastViewportValue = { name, value };
            },
        },
    };

    globalThis.window = {
        visualViewport: { height: 640 },
        innerHeight: 800,
        addEventListener: () => {},
        removeEventListener: () => {},
    };

    globalThis.document = {
        documentElement: {
            clientHeight: 760,
            ...element,
        },
    };

    const viewportHeight = getSafeViewportHeight({ fallback: 0 });
    assert.equal(viewportHeight, 640);

    const renderedHeight = syncViewportHeight({
        element: globalThis.document.documentElement,
        variableName: '--app-viewport-height',
    });

    assert.equal(renderedHeight, '640px');
    assert.equal(globalThis.__lastViewportValue.name, '--app-viewport-height');
    assert.equal(globalThis.__lastViewportValue.value, '640px');

    globalThis.window = previousWindow;
    globalThis.document = previousDocument;
});

test('viewport guard handles keyboard resize and recalibrates safe insets after orientation changes', () => {
    const previousWindow = globalThis.window;
    const previousDocument = globalThis.document;
    const windowListeners = new Map();
    const visualViewportListeners = new Map();
    const windowTimers = new Map();
    const styleValues = new Map();
    let nextTimerId = 0;
    const visualViewport = {
        height: 800,
        addEventListener: (name, listener) => visualViewportListeners.set(name, listener),
        removeEventListener: (name) => visualViewportListeners.delete(name),
    };
    const element = {
        style: {
            setProperty: (name, value) => styleValues.set(name, value),
        },
    };

    globalThis.window = {
        innerHeight: 800,
        visualViewport,
        addEventListener: (name, listener) => windowListeners.set(name, listener),
        removeEventListener: (name) => windowListeners.delete(name),
        setTimeout: (callback) => {
            const timerId = ++nextTimerId;
            windowTimers.set(timerId, callback);
            return timerId;
        },
        clearTimeout: (timerId) => windowTimers.delete(timerId),
    };
    globalThis.document = {
        documentElement: {
            clientHeight: 800,
        },
    };

    try {
        const removeGuard = installViewportKeyboardGuard({ element });

        assert.equal(styleValues.get('--app-viewport-height'), '800px');
        assert.equal(styleValues.get(CHAT_SAFE_BOTTOM_VARIABLE), 'var(--mobile-safe-bottom, env(safe-area-inset-bottom, 0px))');

        visualViewport.height = 500;
        visualViewportListeners.get('resize')();

        assert.equal(styleValues.get('--app-viewport-height'), '500px');
        assert.equal(styleValues.get(CHAT_SAFE_BOTTOM_VARIABLE), '0px');

        visualViewport.height = 800;
        windowListeners.get('resize')();

        assert.equal(styleValues.get('--app-viewport-height'), '800px');
        assert.equal(styleValues.get(CHAT_SAFE_BOTTOM_VARIABLE), 'var(--mobile-safe-bottom, env(safe-area-inset-bottom, 0px))');

        globalThis.window.innerHeight = 400;
        visualViewport.height = 400;
        globalThis.document.documentElement.clientHeight = 400;
        windowListeners.get('orientationchange')();
        const [orientationTimerId, orientationTimer] = [...windowTimers.entries()][0];
        windowTimers.delete(orientationTimerId);
        orientationTimer();

        assert.equal(styleValues.get('--app-viewport-height'), '400px');
        assert.equal(styleValues.get(CHAT_SAFE_BOTTOM_VARIABLE), 'var(--mobile-safe-bottom, env(safe-area-inset-bottom, 0px))');

        visualViewport.height = 200;
        visualViewportListeners.get('resize')();
        assert.equal(styleValues.get(CHAT_SAFE_BOTTOM_VARIABLE), '0px');

        visualViewport.height = 400;
        visualViewportListeners.get('resize')();
        assert.equal(styleValues.get(CHAT_SAFE_BOTTOM_VARIABLE), 'var(--mobile-safe-bottom, env(safe-area-inset-bottom, 0px))');

        removeGuard();
        assert.equal(windowListeners.size, 0);
        assert.equal(visualViewportListeners.size, 0);
        assert.equal(windowTimers.size, 0);
    }
    finally {
        globalThis.window = previousWindow;
        globalThis.document = previousDocument;
    }
});

test('autoResize clamps the composer to the WhatsApp-style 1-to-4-line range without leaving a blank gap at the keyboard', async () => {
    const api = useInputHandlers();
    const field = {
        style: {
            height: 'auto',
            overflowY: 'hidden',
        },
        scrollHeight: 220,
    };

    api.autoResize(field);
    await Promise.resolve();

    assert.equal(field.style.height, '112px');
    assert.equal(field.style.overflowY, 'auto');
});
