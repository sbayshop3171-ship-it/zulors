import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

import { compileTemplate, parse } from '@vue/compiler-sfc';

const componentPath = 'resources/js/spa/apps/mobile/views/messenger/children/chat/MessengerChat.vue';

test('mobile MessengerChat forward dialog only binds values exposed by setup', () => {
	const componentSource = fs.readFileSync(componentPath, 'utf8');
	const { descriptor, errors: parseErrors } = parse(componentSource, { filename: componentPath });

	assert.deepEqual(parseErrors, []);

	const compiledTemplate = compileTemplate({
		source: descriptor.template.content,
		filename: componentPath,
		id: 'mobile-messenger-chat',
	});

	assert.deepEqual(compiledTemplate.errors, []);
	assert.match(compiledTemplate.code, /_ctx\.chatsHistory/);
	assert.doesNotMatch(compiledTemplate.code, /_ctx\.chatStore/);
	assert.match(descriptor.script.content, /chatsHistory:\s*chatsHistory/);
});
