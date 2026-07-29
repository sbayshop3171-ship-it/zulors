<template>
    <div class="flex flex-wrap">
        <button type="button" role="button"
            v-for="reactionItem in reactionsList" v-bind:key="reactionItem.unified_id"
            v-on:click="makeReaction(reactionItem.unified_id)"
            v-bind:class="[reactionItem.has_reacted ? 'bg-fill-pr text-brand-900' : 'bg-fill-sc text-lab-sc']"
        class="rounded-full cursor-pointer last:mr-0 outline-hidden font-medium text-par-m px-1 py-1 mr-1.5 mb-1.5 inline-flex items-center">
            <img class="size-icon-normal md:size-icon-small" v-bind:src="reactionItem.image_url" loading="lazy" alt="Reaction">
            <span class="ml-1 text-par-m md:text-par-n leading-zero mr-1.5 font-mono">{{ reactionItem.total }}</span>
        </button>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    export default defineComponent({
        props: {
            isReply: false,
            reactions: {
                type: Array,
                default: []
            }
        },
        emits: ['add'],
        setup: function(props, context) {
            return {
                makeReaction: (unifiedId, button) => {
                    context.emit('add', unifiedId);
                },
                reactionsList: computed(() => {
                    return props.reactions;
                })
            }
        }
    });
</script>