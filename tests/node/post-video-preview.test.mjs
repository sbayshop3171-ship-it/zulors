import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { setTimeout as delay } from 'node:timers/promises';
import test from 'node:test';
import { parse, babelParse } from '@vue/compiler-sfc';
import * as vue from 'vue';
import * as postTypes from '../../resources/js/spa/kernel/enums/post/post.type.js';
import * as previews from '../../resources/js/spa/kernel/services/media/post-media-preview.js';
import * as progress from '../../resources/js/spa/kernel/services/media/multipart-upload-progress.js';
import * as previewSizing from '../../resources/js/spa/kernel/services/media/media-preview-sizing.js';

const paths = {
    mobile: 'resources/js/spa/apps/mobile/views/editors/post/PostEditor.vue',
    desktop: 'resources/js/spa/apps/desktop/components/timeline/editor/PublicationEditor.vue',
};
const previewPaths = {
    mobile: 'resources/js/spa/apps/mobile/views/editors/post/parts/preview/PostVideoPreview.vue',
    desktop: 'resources/js/spa/apps/desktop/components/timeline/editor/preview/video/PostVideoPreview.vue',
};
const waitFor = async predicate => {
    for(let attempt = 0; attempt < 100; attempt++) {
        if(predicate()) return;
        await delay(5);
    }
    assert.fail('Editor did not reach the expected state');
};

// Execute each real SFC setup with isolated API/browser dependencies and Vue reactivity.
async function editorHarness(platform, { fallback = false, fail = false } = {}) {
    const source = (await readFile(new URL(`../../${paths[platform]}`, import.meta.url))).toString();
    const script = parse(source).descriptor.script.content;
    const ast = babelParse(script, { sourceType: 'module' });
    const unmount = [];
    const revoked = [];
    const requests = [];
    const calls = [];
    const timeline = [];
    let storedMedia = null;
    const pendingMedia = () => ({ id: 101, type: 'video', status: 'processing', source_url: null, metadata: { duration: 100, upload_state: 'uploaded', processing_state: 'queued' } });
    const store = vue.reactive({
        draftPost: { type: 'text', content: 'Unsaved caption', relations: { media: [] } },
        videoUploadActive: false, isEditingPost: false,
        setVideoUploadActive(value) { this.videoUploadActive = value; },
        finishEditing() { this.draftPost = { type: 'text', content: '', relations: {} }; },
        async fetchDraftPost() {
            this.draftPost = { type: storedMedia ? 'video' : 'text', content: 'Unsaved caption', relations: { media: storedMedia ? [structuredClone(storedMedia)] : [] } };
        },
    });
    function api() {
        let payload;
        const client = {
            postEditor() { return this; },
            with(value) { payload = value; return this; },
            withHeaders() { return this; }, params() { return this; }, uploadProgress() { return this; },
            async sendTo(endpoint) {
                calls.push({ endpoint, payload });
                if(endpoint.endsWith('/create')) {
                    return { data: { data: fallback ? { direct_upload: false } : {
                        direct_upload: true, upload_type: 'multipart', uid: 'test-uid', upload_id: 'test-upload',
                        media: pendingMedia(), part_size: 10, upload_concurrency: 2,
                        parts: [1, 2].map(n => ({ part_number: n, start: (n - 1) * 10, end: n * 10, upload_url: `https://r2.invalid/${n}` })),
                    } } };
                }
                if(endpoint.endsWith('/complete') || endpoint === 'media/video/upload') {
                    if(fail) throw new Error('Upload verification failed');
                    storedMedia = pendingMedia();
                    return { data: { data: { media: structuredClone(storedMedia) } } };
                }
                return { data: { data: {} } };
            },
            async delete(endpoint) {
                calls.push({ endpoint, payload });
                storedMedia = null;
                return { data: {} };
            },
        };
        return client;
    }
    class XHR {
        upload = {};
        status = 200;
        open() {} setRequestHeader() {} abort() {}
        getResponseHeader() { return '"test-etag"'; }
        send(body) { this.body = body; requests.push(this); }
        progress(bytes) { this.upload.onprogress({ loaded: bytes, total: this.body.size, lengthComputable: true }); }
        complete() { this.onload(); }
    }
    const imports = new Map([
        ['vue', { ...vue, onMounted: () => {}, onBeforeUnmount: fn => unmount.push(fn), defineAsyncComponent: () => ({}) }],
        ['vue-router', { useRouter: () => ({}) }],
        ['@/kernel/enums/post/post.type.js', postTypes],
        ['@/kernel/services/media/post-media-preview.js', previews],
        ['@/kernel/services/media/multipart-upload-progress.js', progress],
        ['@/kernel/services/media/media-preview-sizing.js', previewSizing],
        ['@/kernel/vue/composables/media-preview-bounds.js', {
            useMediaPreviewBounds: () => ({ bounds: vue.ref({ width: 390, height: 600 }), refresh() {} }),
        }],
        ['@/kernel/vue/composables/media-publication/index.js', {
            useMediaPublication: () => ({
                selections: vue.ref([]),
                selecting: vue.ref(false),
                select: async () => false,
                pick: async () => null,
                remove() {},
                clear() {},
                enqueue: async () => {},
            }),
        }],
        ['@/kernel/services/media/video-metadata.js', {
            readVideoFileMetadata: async () => ({ duration_seconds: 100, duration: 100, dimensions: { width: 640, height: 360 } }),
            buildVideoPresentationMetadata: (width, height, durationSeconds = 0) => {
                const result = {};
                if(width > 0 && height > 0) {
                    result.dimensions = { width, height };
                    result.aspect_ratio = width / height;
                    result.is_portrait = width < height;
                }
                if(durationSeconds > 0) result.duration_seconds = Math.round(durationSeconds);
                return result;
            },
            applyVideoPresentationMetadata: (item, metadata) => { Object.assign(item.metadata, metadata); },
        }],
        ['@/kernel/services/api-client/native/index.js', { colibriAPI: api }],
        ['@/kernel/events/bus/index.js', { colibriEventBus: { emit() {} } }],
        ['@/kernel/services/sounds/index.js', { colibriSounds: { uiFeedback() {} } }],
        ['@/kernel/vue/composables/input/index.js', { useInputHandlers: () => ({ autoResize() {} }) }],
        ['@/kernel/vue/composables/menu/index.js', { useMenu: () => ({}) }],
        ['@D/core/composables/cheat-sheet/index.js', { useCheatSheet: () => ({}) }],
    ]);
    for(const prefix of ['@M', '@D']) {
        imports.set(`${prefix}/store/timeline/editor.store.js`, { usePostEditorStore: () => store });
        imports.set(`${prefix}/store/auth/auth.store.js`, { useAuthStore: () => ({ userData: { id: 1 } }) });
        imports.set(`${prefix}/store/timeline/timeline.store.js`, { useTimelineStore: () => ({ setPostMedia: item => timeline.push(item), prependPost() {} }) });
    }
    const replacements = ast.program.body.flatMap(node => {
        if(node.type === 'ImportDeclaration') {
            return [{ start: node.start, end: node.end, text: node.specifiers.map(spec => {
                const name = spec.type === 'ImportDefaultSpecifier' ? 'default' : spec.imported.name;
                return `const ${spec.local.name} = imports.get(${JSON.stringify(node.source.value)})?.[${JSON.stringify(name)}] || {};`;
            }).join('\n') }];
        }
        if(node.type === 'ExportDefaultDeclaration') return [{ start: node.start, end: node.declaration.start, text: 'return ' }];
        return [];
    });
    let body = script;
    for(const edit of replacements.reverse()) body = body.slice(0, edit.start) + edit.text + body.slice(edit.end);
    const component = new Function('imports', 'URL', 'XMLHttpRequest', 'setTimeout', 'debounce', 'toastError', 'navigator', body)(
        imports, { createObjectURL: () => 'blob:device-video', revokeObjectURL: url => revoked.push(url) }, XHR,
        fn => { queueMicrotask(fn); return 0; }, () => {}, () => {}, { vibrate() {} },
    );
    const editor = component.setup({}, {});
    return { editor, store, revoked, requests, calls, timeline, unmount };
}

async function videoPreviewHarness(platform, play = async () => {}) {
    const source = (await readFile(new URL(`../../${previewPaths[platform]}`, import.meta.url))).toString();
    const descriptor = parse(source).descriptor;
    const ast = babelParse(descriptor.script.content, { sourceType: 'module' });
    const imports = new Map([
        ['vue', vue],
        ['@/kernel/services/media/media-preview-sizing.js', previewSizing],
        ['@/kernel/vue/composables/media-preview-bounds.js', {
            useMediaPreviewBounds: () => ({ bounds: vue.ref({ width: 390, height: 600 }), refresh() {} }),
        }],
        ['@/kernel/services/media/video-metadata.js', {
            applyVideoPresentationMetadata: (item, metadata) => { item.metadata = { ...item.metadata, ...metadata }; },
            buildVideoPresentationMetadata: () => ({}),
            isVideoPortrait: () => true,
            videoFrameAspectStyle: () => ({}),
        }],
        ['@M/views/editors/post/parts/buttons/MediaDeleteButton.vue', { default: {} }],
        ['@D/components/timeline/editor/animations/MediaBlurOverlay.vue', { default: {} }],
        ['@D/components/timeline/editor/buttons/MediaDeleteButton.vue', { default: {} }],
        ['@/kernel/vue/components/media/video/VideoDurationTime.vue', { default: {} }],
    ]);
    const replacements = ast.program.body.flatMap(node => {
        if(node.type === 'ImportDeclaration') {
            return [{ start: node.start, end: node.end, text: node.specifiers.map(spec => {
                const name = spec.type === 'ImportDefaultSpecifier' ? 'default' : spec.imported.name;
                return `const ${spec.local.name} = imports.get(${JSON.stringify(node.source.value)})?.[${JSON.stringify(name)}] || {};`;
            }).join('\n') }];
        }
        if(node.type === 'ExportDefaultDeclaration') return [{ start: node.start, end: node.declaration.start, text: 'return ' }];
        return [];
    });
    let body = descriptor.script.content;
    for(const edit of replacements.reverse()) body = body.slice(0, edit.start) + edit.text + body.slice(edit.end);
    const component = new Function('imports', body)(imports);
    const item = { id: 'video-preview', preview_url: 'blob:video-preview', type: 'video', metadata: {} };
    const props = platform === 'mobile' ? { postMedia: [item], canDelete: false } : { mediaItem: item, canDelete: false };
    const preview = component.setup(props, { emit() {} });
    const video = {
        paused: true,
        playbackRate: 1,
        play: async () => { await play(); video.paused = false; },
        pause: () => { video.paused = true; },
    };
    const event = { stopPropagation() {}, currentTarget: { closest: selector => {
        assert.equal(selector, '.post-video-preview-frame');
        return { querySelector: query => { assert.equal(query, 'video'); return video; } };
    } } };
    const videoEvent = { currentTarget: video };
    return { descriptor, preview, item, video, event, videoEvent };
}

for(const platform of Object.keys(previewPaths)) {
    test(`${platform}: visible preview play button starts video playback`, async () => {
        let playCalls = 0;
        const h = await videoPreviewHarness(platform, async () => { playCalls++; });
        assert.match(h.descriptor.template.content, /v-on:click="playPreview/);
        if(platform === 'mobile') await h.preview.playPreview(h.item, h.event);
        else await h.preview.playPreview(h.event);
        assert.equal(playCalls, 1);
        assert.equal(platform === 'mobile' ? h.preview.isPreviewPlaying(h.item) : h.preview.isPreviewPlaying.value, true);
    });

    test(`${platform}: unsupported preview playback shows retry/error state`, async () => {
        const h = await videoPreviewHarness(platform, async () => { throw new Error('unsupported codec'); });
        if(platform === 'mobile') await h.preview.playPreview(h.item, h.event);
        else await h.preview.playPreview(h.event);
        assert.equal(platform === 'mobile' ? h.preview.playbackError(h.item) : h.preview.playbackError.value, true);
        assert.equal(platform === 'mobile' ? h.preview.isPreviewPlaying(h.item) : h.preview.isPreviewPlaying.value, false);
    });

    test(`${platform}: tapping video toggles playback and side controls change speed up to 3x`, async () => {
        const h = await videoPreviewHarness(platform);
        const toggle = () => platform === 'mobile'
            ? h.preview.togglePreviewPlayback(h.item, h.videoEvent)
            : h.preview.togglePreviewPlayback(h.videoEvent);
        const canChange = direction => platform === 'mobile'
            ? h.preview.canChangePlaybackSpeed(h.item, direction)
            : h.preview.canChangePlaybackSpeed(direction);
        const changeSpeed = direction => platform === 'mobile'
            ? h.preview.changePlaybackSpeed(h.item, direction, h.event)
            : h.preview.changePlaybackSpeed(direction, h.event);

        await toggle();
        assert.equal(h.video.paused, false);
        assert.equal(platform === 'mobile' ? h.preview.isPreviewPlaying(h.item) : h.preview.isPreviewPlaying.value, true);
        await toggle();
        assert.equal(h.video.paused, true);
        assert.equal(platform === 'mobile' ? h.preview.isPreviewPlaying(h.item) : h.preview.isPreviewPlaying.value, false);

        changeSpeed(1);
        assert.equal(h.video.playbackRate, 1.25);
        changeSpeed(-1);
        assert.equal(h.video.playbackRate, 1);
        while(canChange(1)) changeSpeed(1);
        assert.equal(h.video.playbackRate, 3);
        assert.equal(canChange(1), false);
        while(canChange(-1)) changeSpeed(-1);
        assert.equal(h.video.playbackRate, 0.5);
        assert.equal(canChange(-1), false);
    });
}

for(const platform of Object.keys(paths)) {
    test(`${platform}: multipart completion preserves local preview, duration and server deletion identity`, async () => {
            const h = await editorHarness(platform);
            const file = new File(['01234567890123456789'], 'video.mp4', { type: 'video/mp4' });
            h.editor.onVideoSelect({ target: { files: [file] } });
            await waitFor(() => h.requests.length === 2);
            assert.equal(h.editor.submitButtonStatus.value, true);
            h.editor.deletePostMedia(h.editor.postMedia.value[0]);
            assert.equal(h.revoked.length, 0, 'cannot remove an active upload');
            h.requests[0].progress(8);
            h.requests[1].progress(5);
            h.requests[0].progress(10);
            h.requests[0].complete();
            await delay(0);
            const value = platform === 'mobile' ? h.editor.state.uploadProgress : h.editor.state.postMediaUploadProgress;
            assert.equal(value, 75, 'part acknowledgement must include the other active part');
            h.requests[1].progress(10);
            h.requests[1].complete();
            await waitFor(() => ! h.store.videoUploadActive);
            assert.equal(h.editor.state.videoUploadFailed, false);
            assert.equal(h.editor.submitButtonStatus.value, false, 'uploaded media can publish before processing');
            assert.equal(h.editor.postMedia.value.length, 1);
            const item = h.editor.postMedia.value[0];
            assert.equal(item.id, 101);
            assert.equal(item.source_url, null, 'the server must not expose originals');
            assert.equal(item.preview_url, 'blob:device-video');
            assert.equal(item.metadata.duration, 100);
            assert.equal(item.preview_key, h.editor.state.localMediaPreviews[0].id);
            assert.deepEqual(h.revoked, [], 'completion must not revoke the playable URL');
            assert.equal(h.store.draftPost.relations.media[0].preview_url, undefined);
            assert.equal(h.timeline.some(media => media.preview_url), false, 'local URLs never enter the public store');
            await h.store.fetchDraftPost();
            assert.equal(h.editor.postMedia.value[0].preview_url, 'blob:device-video');
            h.editor.deletePostMedia(h.editor.postMedia.value[0]);
            await waitFor(() => h.editor.postMedia.value.length === 0);
            assert.deepEqual(h.calls.find(call => call.endpoint === 'media/delete').payload, { id: 101 });
            assert.deepEqual(h.revoked, ['blob:device-video']);
    });

    test(`${platform}: direct completion failure disables publish and unmount releases preview`, async () => {
            const h = await editorHarness(platform, { fail: true });
            h.editor.onVideoSelect({ target: { files: [new File(['01234567890123456789'], 'video.mp4', { type: 'video/mp4' })] } });
            await waitFor(() => h.requests.length === 2);
            for(const request of h.requests) { request.progress(10); request.complete(); }
            await waitFor(() => ! h.store.videoUploadActive);
            assert.equal(h.editor.state.videoUploadFailed, true);
            assert.equal(h.editor.submitButtonStatus.value, true);
            await h.editor.submitForm();
            assert.equal(h.calls.some(call => call.endpoint === 'create'), false);
            assert.equal(h.editor.postMedia.value[0].preview_url, 'blob:device-video');
            for(const cleanup of h.unmount) cleanup();
            assert.deepEqual(h.revoked, ['blob:device-video']);
    });

    test(`${platform}: unavailable direct storage never proxies video bytes through Laravel`, async () => {
        const h = await editorHarness(platform, { fallback: true });
        h.editor.onVideoSelect({ target: { files: [new File(['01234567890123456789'], 'video.mp4', { type: 'video/mp4' })] } });
        await waitFor(() => h.calls.some(call => call.endpoint === 'media/video/direct/create'));
        await waitFor(() => ! h.store.videoUploadActive);
        assert.equal(h.editor.state.videoUploadFailed, true);
        assert.equal(h.editor.submitButtonStatus.value, true, 'an unuploaded video cannot be published');
        assert.equal(h.requests.length, 0);
        assert.equal(h.calls.some(call => call.endpoint === 'media/video/upload'), false);
        assert.equal(h.calls.some(call => call.endpoint === 'media/video/direct/part'), false);
    });
}

test('preview merge does not duplicate associated video or mutate server data; unrelated images survive', () => {
    const server = [{ id: 1, type: 'image' }, { id: 2, type: 'video', source_url: null }];
    const local = [{ id: 'local-video', media_id: '2', preview_url: 'blob:video', type: 'video' }, { id: 'local-image', type: 'image' }];
    const result = previews.mergePostMediaPreviews(server, local);
    assert.equal(result.length, 3);
    assert.equal(result[1].id, 2);
    assert.equal(result[1].preview_url, 'blob:video');
    assert.equal(server[1].preview_url, undefined);
    assert.deepEqual(previews.mergePostMediaPreviews(server, []), server);
});

test('multipart progress is cumulative across acknowledgements and retries', () => {
    const values = [];
    const update = progress.createMultipartUploadProgress(200, value => values.push(value));
    update(1, 80, 100);
    update(2, 60, 100);
    update(1, 100, 100);
    update(2, 0, 100);
    update(2, 20, 100);
    update(2, 100, 100);
    update(2, 100, 100);
    assert.deepEqual(values, [40, 70, 80, 80, 80, 100, 100]);
});

test('desktop publishing clears the retained preview even if the editor stays mounted', async () => {
    const h = await editorHarness('desktop');
    h.editor.onVideoSelect({ target: { files: [new File(['video'], 'video.mp4', { type: 'video/mp4' })] } });
    await waitFor(() => h.requests.length === 2);
    for(const request of h.requests) request.complete();
    await waitFor(() => ! h.store.videoUploadActive);
    await h.editor.submitForm();
    assert.equal(h.calls.some(call => call.endpoint === 'create'), true);
    assert.equal(h.editor.postMedia.value.length, 0);
    assert.deepEqual(h.revoked, ['blob:device-video']);
});
