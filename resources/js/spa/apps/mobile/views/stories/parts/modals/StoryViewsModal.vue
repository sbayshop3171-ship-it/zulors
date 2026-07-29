<template>
    <ActionSheet v-on:close="$emit('hide')">
        <div class="pb-2 px-4">
            <SheetTitle v-bind:title="`${$t('story.who_watched_story')} ${views.length}`"></SheetTitle>
        </div>
        <Border></Border>
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
    </ActionSheet>
</template>
<script>
    import { defineComponent, ref, onMounted, reactive, inject } from 'vue';
    import { useStoriesStore } from '@M/store/stories/stories.store.js';

    import ViewItem from '@M/views/stories/parts/modals/parts/views/ViewItem.vue';
    import ViewItemSkeleton from '@M/views/stories/parts/modals/parts/views/ViewItemSkeleton.vue';

    import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
    import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';

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
            ViewItemSkeleton: ViewItemSkeleton,
            ViewItem: ViewItem,
            ActionSheet: ActionSheet,
            ActionSheetGroup: ActionSheetGroup,
            SheetTitle: SheetTitle
        }
    });
</script>