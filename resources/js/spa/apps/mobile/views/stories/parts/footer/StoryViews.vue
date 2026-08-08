<template>
    <div class="px-4 flex items-center gap-4">
        <div class="inline-flex items-center flex-1 gap-1 opacity-70 hover:opacity-100 cursor-pointer" v-on:click="showViews">
            <PrimaryIconButton
                iconName="eye"
                buttonColor="text-white"
            hoverText="hover:text-white"></PrimaryIconButton>
            
            <span class="text-par-s text-white/90">
                {{ $t('story.views_number', { n: storyViewsCount.formatted }, storyViewsCount.raw) }}
            </span>
        </div>
        <div class="inline-flex items-center gap-1 opacity-70">
            <div v-if="storyReactionsSummary.length" class="inline-flex items-center -space-x-1">
                <img
                    v-for="reactionItem in storyReactionsSummary"
                    v-bind:key="reactionItem.unified_id"
                    class="size-4 rounded-full ring-1 ring-black/30 bg-black/10"
                    v-bind:src="reactionItem.image_url"
                    alt="Reaction"
                >
            </div>
            <PrimaryIconButton
                v-else
                iconName="heart-rounded"
                buttonColor="text-red-900"
                hoverBg=""
                hoverText=""
            ></PrimaryIconButton>

            <span class="text-par-s text-white/90">
                {{ $t('story.reactions_number', { n: storyReactionsCount.formatted }, storyReactionsCount.raw) }}
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
                storyReactionsCount: computed(() => {
                    return playerState.frameData.reactions_count || playerState.frameData.likes_count;
                }),
                storyReactionsSummary: computed(() => {
                    return (playerState.frameData.reactions_summary || []).slice(0, 3);
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
