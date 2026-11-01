import test from 'node:test';
import assert from 'node:assert/strict';

import { requestNextReelsPage } from '../../resources/js/spa/kernel/services/feed-session/reels-pagination.js';

test('a failed page request leaves the committed page unchanged for retry', async () => {
	let currentPage = 4;
	let requestedPage = 0;

	await assert.rejects(requestNextReelsPage(currentPage, async (page) => {
		requestedPage = page;
		throw new Error('network timeout');
	}, (page) => {
		currentPage = page;
	}));

	assert.equal(requestedPage, 5);
	assert.equal(currentPage, 4);
});

test('a successful page request commits its page only after the response arrives', async () => {
	let currentPage = 1;
	const response = { data: { data: [{ id: 22 }] } };

	const result = await requestNextReelsPage(currentPage, async (page) => {
		assert.equal(currentPage, 1);
		assert.equal(page, 2);
		return response;
	}, (page) => {
		currentPage = page;
	});

	assert.equal(currentPage, 2);
	assert.equal(result, response);
});
