<template>
	<div class="px-6 pb-4 border-b border-b-bord-pr">
		<h5 class="text-lab-sc text-par-m mb-2">
			{{ $t('labels.leave_reaction') }}
		</h5>
		<div class="flex hidden-scroll gap-4">
			<div v-for="reactionItem in reactionsList" v-bind:key="reactionItem.unified"
				v-bind:title="reactionItem.unified" 
				v-on:click="addReaction(reactionItem)" 
			class="cursor-pointer size-9 shrink-0">
				<img class="w-full" v-bind:src="$asset(reactionItem.url)" loading="lazy" alt="Reaction">
			</div>
		</div>
	</div>
</template>

<script>
    import { defineComponent, ref, onMounted } from 'vue';

    export default defineComponent({
        emits: ['add'],
        setup: function(props, context) {
            var reactionsList = ref([]);

			onMounted(() => {
				reactionsList.value = embedder('assets.emojis.animated');
			});

            return {
                reactionsList: reactionsList,
                getEmojiUrl: (image) => {
                    return embedder('links.assets.emoji') + "/" + image;
                },
                addReaction: (emojiItem) => {
                    context.emit('add', emojiItem.unified);
                }
            }
        }
    });
</script>