<template>
    <div class="w-full px-6 h-full overflow-y-auto pb-4">
        <template v-if="fusedReactions.length">
            <div class="pb-4">
                <h5 class="text-lab-sc text-par-m">
                    {{ $t('labels.fused_reactions') }}
                </h5>
            </div>
            <div class="grid grid-cols-8 gap-5 pb-4">
                <div v-bind:title="reactionItem.unified" v-on:click="addReaction(reactionItem)" class="cursor-pointer hover:scale-110 transition-all ease-linear" v-for="reactionItem in fusedReactions" v-bind:key="reactionItem.unified">
                    <img class="w-full" v-bind:src="getEmojiUrl(reactionItem.image)" loading="lazy" alt="Reaction">
                </div>
            </div>
        </template>
        <div class="pb-4">
            <h5 class="text-lab-sc text-par-m">
                {{ $t('labels.reactions') }}
            </h5>
        </div>
        <div class="grid grid-cols-8 gap-5">
            <div v-bind:title="emojiItem.unified" v-on:click="addReaction(emojiItem)" class="cursor-pointer hover:scale-110 transition-all ease-linear" v-for="emojiItem in reactionsList" v-bind:key="emojiItem.unified">
                <img class="w-full" v-bind:src="getEmojiUrl(emojiItem.image)" loading="lazy" alt="Reaction">
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, onMounted, ref } from 'vue';
    import { smileysAndEmotionEmojis } from '@/assets/emojis/apple/categories/smileys-and-emotions.js';

    export default defineComponent({
        emits: ['add'],
        setup: function(props, context) {
            var reactionsList = ref([]);

            const fusedReactions = ref([]);

            onMounted(() => {
                reactionsList.value = smileysAndEmotionEmojis.emojis;

                const fusedReactionsStorage = localStorage.getItem('FUSED_REACTIONS');

                if(fusedReactionsStorage) {
                    fusedReactions.value = JSON.parse(fusedReactionsStorage);
                }
            });

            const pushReactionToFused = (reactionItem) => {
                const duplicateReactionItemIndex = fusedReactions.value.findIndex((item) => {
                    return item.unified == reactionItem.unified;
                });

                if(duplicateReactionItemIndex == -1) {
                    fusedReactions.value.unshift(reactionItem);
                }
                else {
                    // Push the reaction to the top of the list
                
                    fusedReactions.value.splice(duplicateReactionItemIndex, 1);
                    fusedReactions.value.unshift(reactionItem);
                }
                
                if(fusedReactions.value.length > 40) {
                    fusedReactions.value = fusedReactions.value.splice(0, 40);
                }

                localStorage.setItem('FUSED_REACTIONS', JSON.stringify(fusedReactions.value)); 
            }

            return {
                reactionsList: reactionsList,
                fusedReactions: fusedReactions,
                getEmojiUrl: (image) => {
                    return embedder('links.assets.emoji') + "/" + image;
                },
                addReaction: (emojiItem) => {
                    context.emit('add', emojiItem.unified);

                    pushReactionToFused(emojiItem);
                }
            }
        }
    });
</script>