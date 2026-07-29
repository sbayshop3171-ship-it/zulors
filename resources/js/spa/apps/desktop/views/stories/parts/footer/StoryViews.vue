<template>
    <div class="flex items- gap-4 px-3 pr-4 leading-none">
        <div class="inline-flex items-center flex-1 gap-1 opacity-70 hover:opacity-100">
            <PrimaryIconButton
                v-on:click="showViews"
                iconName="eye"
                buttonColor="text-white"
            hoverText="hover:text-white"></PrimaryIconButton>
            
            <span class="text-par-s text-white/90">
                {{ $t('story.views_number', { n: storyViewsCount.formatted }, storyViewsCount.raw) }}
            </span>
        </div>
        <div class="shrink-0">
            <StoryShareButton></StoryShareButton>
        </div>
    </div>
</template>

<script>
    import { computed, defineComponent, inject } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import StoryShareButton from '@D/views/stories/parts/StoryShareButton.vue';

    export default defineComponent({
        setup: function() {
            const playerState = inject('playerState');

            return {
                playerState: playerState,
                showViews: () => {
                    colibriEventBus.emit('story:show-views');
                },
                storyViewsCount: computed(() => {
                    return playerState.frameData.views_count;
                }),
                storyViews: computed(() => {
                    return playerState.frameData.relations.views;
                }),
                hasViews: computed(() => {
                    return playerState.frameData.views_count.raw > 0;
                })
            }
        },
        components: {
            StoryShareButton: StoryShareButton,
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>