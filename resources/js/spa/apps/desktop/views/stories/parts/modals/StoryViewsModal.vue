<template>
    <ModalBackdrop>
        <ModalContent>
            <ModalHeader v-bind:title="$t('story.who_watched_story')" v-bind:buttonText="$t('labels.close')" v-on:cancel="$emit('hide')"></ModalHeader>
            <div v-if="state.isLoading" class="block">
                <ViewItemSkeleton v-for="i in 5" v-bind:key="i"></ViewItemSkeleton>
            </div>
            <div v-else class="block max-h-80 overflow-y-auto">
                <template v-if="views.length">
                    <ViewItem v-for="viewItem in views" v-bind:viewItem="viewItem" v-bind:key="viewItem.id"></ViewItem>
                </template>
                <template v-else>
                    <div class="py-16">
                        <p class="text-lab-pr2 text-par-s text-center">{{ $t('story.no_view_yet') }}</p>
                    </div>
                </template>
            </div>
        </ModalContent>
    </ModalBackdrop>
</template>
<script>
    import { defineComponent, ref, onMounted, reactive, inject } from 'vue';
    import { useStoriesStore } from '@D/store/stories/stories.store.js';

    import ModalBackdrop from '@D/views/stories/parts/modals/parts/ModalBackdrop.vue';
    import ModalContent from '@D/views/stories/parts/modals/parts/ModalContent.vue';
    import ModalHeader from '@D/views/stories/parts/modals/parts/ModalHeader.vue';
    import ViewItem from '@D/views/stories/parts/modals/parts/views/ViewItem.vue';
    import ViewItemSkeleton from '@D/views/stories/parts/modals/parts/views/ViewItemSkeleton.vue';

    export default defineComponent({
        emits: ['hide'],
        setup: function() {
            const storiesStore = useStoriesStore();
            const playerState = inject('playerState');
            const state = reactive({
                isLoading: true
            });

            const views = ref([]);

            onMounted(async () => {
                views.value = await storiesStore.fetchAndReturnStoryViews(playerState.frameData.id);

                state.isLoading = false;
            });

            return {
                state: state,
                views: views
            }
        },
        components: {
            ModalBackdrop: ModalBackdrop,
            ModalContent: ModalContent,
            ModalHeader: ModalHeader,
            ViewItemSkeleton: ViewItemSkeleton,
            ViewItem: ViewItem
        }
    });
</script>