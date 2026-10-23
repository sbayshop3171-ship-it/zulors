import test from 'node:test';
import assert from 'node:assert/strict';

import {
  afterBusinessLivewireInitialization,
  hasBusinessFormChanges,
} from '../../resources/js/business/unsaved-form.js';

function field(fieldKey, value, differsFromDefault = false) {
  return { componentId: 'product-form', fieldKey, value, differsFromDefault };
}

test('restoring a radio group to its original selection clears the unsaved state', () => {
  const baselines = new Map([['product-form', new Map([
    ['type:radio:physical', true],
    ['type:radio:digital', false],
  ])]]);

  assert.equal(hasBusinessFormChanges([
    field('type:radio:physical', false),
    field('type:radio:digital', true),
  ], baselines), true);

  assert.equal(hasBusinessFormChanges([
    field('type:radio:physical', true),
    field('type:radio:digital', false),
  ], baselines), false);
});

test('a changed form value remains unsaved until it matches the captured baseline', () => {
  const baselines = new Map([['product-form', new Map([['formData.title:text:', '']])]]);

  assert.equal(hasBusinessFormChanges([field('formData.title:text:', 'New listing')], baselines), true);
  assert.equal(hasBusinessFormChanges([field('formData.title:text:', '')], baselines), false);
});

test('new optional controls only count when their value differs from the rendered default', () => {
  const baselines = new Map([['product-form', new Map([['formData.with_discount:radio:no', true]])]]);

  assert.equal(hasBusinessFormChanges([field('formData.discount:number:', '', false)], baselines), false);
  assert.equal(hasBusinessFormChanges([field('formData.discount:number:', '15', true)], baselines), true);
});

test('initial form baseline waits for Livewire to apply its model values', () => {
  let readyListener;
  let captured = false;
  const document = {
    addEventListener(name, callback, options) {
      assert.equal(name, 'livewire:initialized');
      assert.equal(options.once, true);
      readyListener = callback;
    },
  };

  afterBusinessLivewireInitialization(document, undefined, () => { captured = true; });
  assert.equal(captured, false);

  readyListener();
  assert.equal(captured, true);
});

test('already initialized Livewire captures the baseline immediately', () => {
  let captured = false;

  afterBusinessLivewireInitialization({}, { initialRenderIsFinished: true }, () => { captured = true; });
  assert.equal(captured, true);
});

test('baseline capture handles the Livewire event firing just before the module loads', () => {
  let readyListener;
  let pendingTimer;
  let captured = false;
  const document = {
    defaultView: {
      Livewire: { initialRenderIsFinished: false },
      setTimeout(callback, delay) {
        assert.equal(delay, 0);
        pendingTimer = callback;
      },
    },
    addEventListener(name, callback) {
      assert.equal(name, 'livewire:initialized');
      readyListener = callback;
    },
  };

  afterBusinessLivewireInitialization(document, document.defaultView.Livewire, () => { captured = true; });
  document.defaultView.Livewire.initialRenderIsFinished = true;
  pendingTimer();
  readyListener();

  assert.equal(captured, true);
});
