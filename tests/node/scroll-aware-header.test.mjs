import test from 'node:test';
import assert from 'node:assert/strict';

import {
    createScrollAwareHeaderState,
    resolveScrollAwareHeaderState,
    resetScrollAwareHeaderState
} from '../../resources/js/spa/kernel/vue/composables/scroll-aware-header/index.js';

test('header stays visible near the top and hides only after meaningful downward scroll', () => {
    let state = createScrollAwareHeaderState(0);

    state = resolveScrollAwareHeaderState(state, 18);
    assert.equal(state.isVisible, true);

    state = resolveScrollAwareHeaderState(state, 42);
    assert.equal(state.isVisible, true);

    state = resolveScrollAwareHeaderState(state, 70);
    assert.equal(state.isVisible, false);
});

test('header reveals after upward scroll while hidden', () => {
    let state = createScrollAwareHeaderState(0);

    state = resolveScrollAwareHeaderState(state, 70);
    assert.equal(state.isVisible, false);

    state = resolveScrollAwareHeaderState(state, 62);
    assert.equal(state.isVisible, false);

    state = resolveScrollAwareHeaderState(state, 54);
    assert.equal(state.isVisible, true);
});

test('disabled mode and reset force the header back to visible', () => {
    let state = createScrollAwareHeaderState(0);

    state = resolveScrollAwareHeaderState(state, 80);
    assert.equal(state.isVisible, false);

    state = resolveScrollAwareHeaderState(state, 120, {}, {
        disabled: true
    });
    assert.equal(state.isVisible, true);
    assert.equal(state.anchorScrollY, 120);

    state = resetScrollAwareHeaderState(32);
    assert.equal(state.isVisible, true);
    assert.equal(state.lastScrollY, 32);
});
