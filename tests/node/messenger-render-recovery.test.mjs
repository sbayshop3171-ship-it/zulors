import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

import { compileTemplate, parse } from '@vue/compiler-sfc';

const compileSfc = (filename) => {
	const source = fs.readFileSync(filename, 'utf8');
	const { descriptor, errors } = parse(source, { filename });

	assert.deepEqual(errors, [], `${filename} should parse`);

	const template = compileTemplate({
		source: descriptor.template.content,
		filename,
		id: filename.replace(/[^a-z0-9]/gi, '-'),
	});

	assert.deepEqual(template.errors, [], `${filename} template should compile`);

	return { descriptor, template };
};

test('mobile and desktop Messenger layouts catch child render errors and show recovery actions', () => {
	const layoutPaths = [
		'resources/js/spa/apps/mobile/layouts/MessengerLayout.vue',
		'resources/js/spa/apps/desktop/layouts/ApplicationMessengerLayout.vue',
	];

	for(const filename of layoutPaths) {
		const { descriptor, template } = compileSfc(filename);

		assert.match(descriptor.script.content, /onErrorCaptured\(/, `${filename} should guard child render errors`);
		assert.match(template.code, /MessengerRenderError/, `${filename} should render the shared recovery view`);
		assert.match(template.code, /retryMessengerView/, `${filename} should expose retry`);
		assert.match(template.code, /returnToInbox/, `${filename} should expose back to inbox`);
	}
});

test('shared Messenger recovery view compiles with an alert and both actions', () => {
	const { descriptor, template } = compileSfc('resources/js/spa/kernel/vue/components/chat/MessengerRenderError.vue');

	assert.match(template.code, /role:\s*"alert"/);
	assert.match(template.code, /\$emit\('retry'\)/);
	assert.match(template.code, /\$emit\('back'\)/);
	assert.match(descriptor.script.content, /emits:\s*\['retry', 'back'\]/);
});
