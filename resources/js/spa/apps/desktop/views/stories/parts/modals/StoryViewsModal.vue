<template>
        <ModalBackdrop>
        <ModalContent>
            <ModalHeader v-bind:title="$t('story.who_watched_story')" v-bind:buttonText="$t('labels.close')" v-on:cancel="$emit('hide')"></ModalHeader>
            <div v-if="! state.isLoading" class="px-3 pb-2 flex items-center gap-2">
                <div v-if="meta.reactions_summary.length" class="inline-flex items-center -space-x-1">
                    <img
                        v-for="reactionItem in meta.reactions_summary.slice(0, 3)"
                        v-bind:key="reactionItem.unified_id"
                        class="size-4 rounded-full ring-1 ring-fill-sc bg-bg-pr"
                        v-bind:src="reactionItem.image_url"
                        alt="Reaction"
                    >
                </div>
                <p class="text-lab-sc text-par-s">
                    {{ $t('story.reactions_number', { n: meta.reactions_count.formatted }, meta.reactions_count.raw) }}
                </p>
            </div>
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
            const meta = ref({
                reactions_count: {
                    raw: 0,
                    formatted: 0
                },
                reactions_summary: [],
                likes_count: {
                    raw: 0,
                    formatted: 0
                }
            });

            onMounted(async () => {
                const response = await storiesStore.fetchAndReturnStoryViews(playerState.frameData.id);

                views.value = response.data;
                meta.value = response.meta;

                state.isLoading = false;
            });

            return {
                state: state,
                views: views,
                meta: meta
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
