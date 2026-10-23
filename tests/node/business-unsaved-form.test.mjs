import test from 'node:test';
import assert from 'node:assert/strict';

import { hasBusinessFormChanges } from '../../resources/js/business/unsaved-form.js';

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
