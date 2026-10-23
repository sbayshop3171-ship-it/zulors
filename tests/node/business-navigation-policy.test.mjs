import test from 'node:test';
import assert from 'node:assert/strict';

import {
  BusinessPageCache,
  getBusinessNavigationTarget,
  isBusinessUnsavedFormPath,
  isOneShotBusinessPath,
  shouldInterceptBusinessClick,
} from '../../resources/js/business/navigation-policy.js';

test('business navigation accepts only same-origin business pages and the in-app help center', () => {
  const origin = 'https://zulors.example';

  assert.equal(getBusinessNavigationTarget('/business/market?status=active', origin), '/business/market?status=active');
  assert.equal(getBusinessNavigationTarget('/document/help-center', origin), '/document/help-center');
  assert.equal(getBusinessNavigationTarget('https://zulors.example/business/jobs/create', origin), '/business/jobs/create');
  assert.equal(getBusinessNavigationTarget('/businesses', origin), null);
  assert.equal(getBusinessNavigationTarget('/logout', origin), null);
  assert.equal(getBusinessNavigationTarget('https://guide.example/account', origin), null);
});

test('business navigation leaves modified, download, external-target and non-primary clicks to the browser', () => {
  const anchor = {
    getAttribute(name) {
      return ({ target: '_self', rel: '' })[name] ?? null;
    },
    hasAttribute() {
      return false;
    },
  };

  assert.equal(shouldInterceptBusinessClick({ button: 0 }, anchor), true);
  assert.equal(shouldInterceptBusinessClick({ button: 1 }, anchor), false);
  assert.equal(shouldInterceptBusinessClick({ button: 0, metaKey: true }, anchor), false);
  assert.equal(shouldInterceptBusinessClick({ button: 0, ctrlKey: true }, anchor), false);
  assert.equal(shouldInterceptBusinessClick({ button: 0, shiftKey: true }, anchor), false);
  assert.equal(shouldInterceptBusinessClick({ button: 0, altKey: true }, anchor), false);
  assert.equal(shouldInterceptBusinessClick({ button: 0 }, { ...anchor, getAttribute: name => name === 'target' ? '_blank' : '' }), false);
  assert.equal(shouldInterceptBusinessClick({ button: 0 }, { ...anchor, hasAttribute: name => name === 'download' }), false);
  assert.equal(shouldInterceptBusinessClick({ button: 0 }, { ...anchor, getAttribute: name => name === 'rel' ? 'external' : '_self' }), false);
});

test('create and edit forms are one-use cache entries while read pages can be stale-revalidated', () => {
  assert.equal(isOneShotBusinessPath('/business/market/create'), true);
  assert.equal(isOneShotBusinessPath('/business/jobs/create'), true);
  assert.equal(isOneShotBusinessPath('/business/market/42/edit'), true);
  assert.equal(isOneShotBusinessPath('/business/jobs/42/show'), false);
  assert.equal(isOneShotBusinessPath('/business/wallet/cashouts/new'), true);
  assert.equal(isOneShotBusinessPath('/business/settings/edit'), true);

  const cache = new BusinessPageCache({ freshTtl: 100, staleTtl: 500 });
  cache.set('/business/market', { content: 'products' }, { now: 0 });
  cache.set('/business/market/create', { content: 'form' }, { oneShot: true, now: 0 });

  assert.equal(cache.get('/business/market', 50).fresh, true);
  assert.equal(cache.get('/business/market', 200).fresh, false);
  assert.equal(cache.get('/business/market', 600), null);
  assert.equal(cache.get('/business/market/create', 50).page.content, 'form');
  assert.equal(cache.get('/business/market/create', 50), null);
});

test('unsaved-form confirmation is limited to product and job creation pages', () => {
  assert.equal(isBusinessUnsavedFormPath('/business/market/create'), true);
  assert.equal(isBusinessUnsavedFormPath('/business/jobs/create'), true);
  assert.equal(isBusinessUnsavedFormPath('/business/market/42/edit'), false);
  assert.equal(isBusinessUnsavedFormPath('/business/settings/edit'), false);
});
