<template>
    <ContentModal v-if="state.isOpen" v-on:close="closeEditor">
        <PublicationEditor></PublicationEditor>
    </ContentModal>
</template>

<script>
    import { defineComponent, computed, reactive, onMounted, onUnmounted } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import { usePostEditorStore } from '@D/store/timeline/editor.store.js';

    import ContentModal from '@D/components/general/modals/ContentModal.vue';
    import PublicationEditor from '@D/components/timeline/editor/PublicationEditor.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isOpen: false
            });

            const postEditorStore = usePostEditorStore();

            const openEditor = (data) => {
                state.isOpen = true;
                
                if(data) {
                    if(data.editPost) {
                        postEditorStore.startEditingPost(data.editPost);

                        return;
                    }

                    if(data.initialType) {
                        postEditorStore.initialType = data.initialType;
                    }
    
                    if(data.mentionName) {
                        postEditorStore.mentionName = data.mentionName;
                    }
    
                    if(data.quotePostId) {
                        postEditorStore.quotePostId = data.quotePostId;
                    }
                }
            };

            const closeEditor = (data) => {
                if(postEditorStore.videoUploadActive) {
                    toastError('Please wait until the video upload finishes.');
                    return;
                }

                state.isOpen = false;

                postEditorStore.finishEditing();
            };

            onMounted(() => {
                colibriEventBus.on('post-editor:open', openEditor);
                colibriEventBus.on('post-editor:close', closeEditor);
            });

            onUnmounted(() => {
                colibriEventBus.off('post-editor:open', openEditor);
                colibriEventBus.off('post-editor:close', closeEditor);
            });

            return {
                state: state,
                closeEditor: closeEditor
            };
        },
        components: {
            ContentModal: ContentModal,
            PublicationEditor: PublicationEditor
        }
    });
</script>
