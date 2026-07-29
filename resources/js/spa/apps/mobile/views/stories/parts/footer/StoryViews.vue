<template>
    <div class="px-4 cursor-pointer" v-on:click="showViews">
        <div class="inline-flex items-center flex-1 gap-1 opacity-70 hover:opacity-100">
            <PrimaryIconButton
                iconName="eye"
                buttonColor="text-white"
            hoverText="hover:text-white"></PrimaryIconButton>
            
            <span class="text-par-s text-white/90">
                {{ $t('story.views_number', { n: storyViewsCount.formatted }, storyViewsCount.raw) }}
            </span>
        </div>
    </div>
</template>

<script>
    import { computed, defineComponent, inject } from 'vue';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

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
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>