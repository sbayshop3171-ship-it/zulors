import test from 'node:test';
import assert from 'node:assert/strict';

import {
    useInputHandlers,
    buildViewportMetaContent,
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
