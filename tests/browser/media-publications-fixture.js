import { createApp, h, ref, nextTick } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';
import { publicationManager, publicationRows } from '/resources/js/spa/kernel/services/media/publications/index.js';
import { PublicationDatabase } from '/resources/js/spa/kernel/services/media/publications/database.js';
import PublicationOutbox from '/resources/js/spa/kernel/vue/components/media/publications/PublicationOutbox.vue';
import SvgIcon from '/resources/js/spa/kernel/vue/components/icons/SvgIcon.vue';
import '/resources/css/spa/apps/mobile/main.css';

const params = new URLSearchParams(location.search);
const platform = params.get('platform') || 'mobile';
const kind = params.get('kind') || 'post';
const pinia = createPinia();
setActivePinia(pinia);
const authModule = await import(/* @vite-ignore */ `/resources/js/spa/apps/${platform}/store/auth/auth.store.js`);
const auth = authModule.useAuthStore();
auth.user = { id: 1, name: 'Uploader', username: 'uploader', is_author: true };
await nextTick();
publicationManager.wake = () => {};
await publicationManager.setAccount(1);
const paths = {
    mobile: { post: 'views/editors/post/PostEditor.vue', story: 'views/editors/stories/StoriesEditor.vue', chat: 'views/messenger/children/chat/parts/ChatEditor.vue' },
    desktop: { post: 'components/timeline/editor/PublicationEditor.vue', story: 'components/stories/editor/StoriesEditor.vue', chat: 'views/messenger/children/chat/parts/ChatForm.vue' },
};
const Editor = (await import(/* @vite-ignore */ `/resources/js/spa/apps/${platform}/${paths[platform][kind]}`)).default;
const passthrough = { setup: (_, { slots }) => () => h('div', slots.default?.()) };
for (const name of ['MentionsPicker', 'AvatarSmall', 'DropdownMenu', 'DropdownMenuItem', 'DropdownButton', 'RichMenu', 'RichMenuItem', 'Toolbar', 'ToastNotification', 'StoryEditorHeader', 'EmojisPickerButton']) {
    if (Editor.components?.[name]) Editor.components[name] = passthrough;
}
let store;
if (kind === 'story') {
    store = (await import(/* @vite-ignore */ `/resources/js/spa/apps/${platform}/store/stories/editor.store.js`)).useStoriesEditorStore();
    store.storyMedia = { type: 'image', source_url: '', preview_url: '' };
} else if (kind === 'chat') {
    store = (await import(/* @vite-ignore */ `/resources/js/spa/apps/${platform}/store/chats/chat.store.js`)).useChatStore();
    store.chatId = '7ec727ba-ed57-4b9f-a2ce-7822a86597cd';
} else {
    store = (await import(/* @vite-ignore */ `/resources/js/spa/apps/${platform}/store/timeline/editor.store.js`)).usePostEditorStore();
}
const vm = ref(null);
const showEditor = ref(!params.has('outbox'));
const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/', name: 'home_index', component: passthrough },
    { path: '/editor', name: 'story_editor', component: passthrough },
] });
await router.push('/editor');
const app = createApp({ setup: () => () => [
    showEditor.value ? h(Editor, { ref: vm }) : null,
    kind !== 'chat' || !showEditor.value ? h(PublicationOutbox, { chatId: kind === 'chat' ? store.chatId : null }) : null,
] });
app.use(pinia); app.use(router);
app.config.errorHandler = error => { window.fixtureError = error.stack; console.error(error.stack); };
app.component('SvgIcon', SvgIcon);
app.component('Border', { render: () => h('hr') });
app.directive('outside-click', {});
Object.assign(app.config.globalProperties, {
    $t: window.__t, $isStandalone: () => false, $getRoute: () => '/', $asset: value => value,
    $embedder: window.embedder, $filters: { mediaDuration: () => '0:04' },
});
app.mount('#app');
window.publicationTest = {
    manager: publicationManager, rows: publicationRows, db: publicationManager.db, PublicationDatabase, store,
    get vm() { return vm.value; },
    async selectStory(bytes, mime = 'image/png') {
        await store.uploadMedia(new File([new Uint8Array(bytes)], mime.startsWith('video/') ? 'video.mp4' : 'photo.png', { type: mime }));
    },
    async submit() { await vm.value.submitForm({ preventDefault() {}, shiftKey: false }); },
    close() { showEditor.value = false; },
};
