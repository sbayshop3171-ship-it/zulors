<template>
    <div v-if="state.mainMenu.status" class="w-80 block">
        <div class="popup-background-tr shadow-2xl rounded-2xl overflow-hidden leading-normal">
            <PanelHeader v-bind:title="$t('labels.leave_reaction')"></PanelHeader>
            <div class="grid grid-cols-6 gap-2 p-4 pt-0 max-h-72 overflow-y-auto">
                <div v-bind:title="emojiItem.unified" v-on:click="addReaction(emojiItem.unified)" class="cursor-pointer hover:scale-110 transition-all ease-linear" v-for="emojiItem in reactionsList" v-bind:key="emojiItem.unified">
                    <img class="w-full" v-bind:src="getEmojiUrl(emojiItem.image)" loading="lazy" alt="Reaction">
                </div>
            </div>
        </div>
    </div>
    <div v-else class="popup-background-tr flex gap-2 py-2 px-2.5 border border-fill-qt max-h-72 overflow-y-auto shadow-2xl rounded-full overflow-hidden leading-none">
        <div class="flex-1 overflow-hidden w-80">
            <swiper-container slides-per-view="8" autoplay-disable-on-interaction="true" autoplay-delay="1000" space-between="10" v-bind:autoplay="true" speed="200" mousewheel="true" grab-cursor="true" class="w-full">
                <swiper-slide v-for="reactionItem in animatedReactionsList" v-bind:key="reactionItem.unified">
                    <div v-bind:title="reactionItem.unified"
                        v-on:click="addReaction(reactionItem.unified)"
                    class="cursor-pointer size-8 shrink-0">
                        <img class="w-full" v-bind:src="$asset(reactionItem.url)" loading="lazy" alt="Reaction">
                    </div>
                </swiper-slide>
            </swiper-container>
        </div>
        <div class="shrink-0">
            <PrimaryIconButton v-on:click.stop="state.mainMenu.open" iconName="plus" buttonColor="text-lab-sc"></PrimaryIconButton>
        </div>
    </div>
</template>

<script>
    import { defineComponent, onMounted, reactive, ref } from 'vue';
    import { smileysAndEmotionEmojis } from '@/assets/emojis/apple/categories/smileys-and-emotions.js';
    import { useMenu } from '@/kernel/vue/composables/menu/index.js';
    import { register  } from 'swiper/element/bundle';

    register();
    
    import PanelHeader from '@D/components/inter-ui/popups/parts/PanelHeader.vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        emits: ['add'],
        setup: function(_, context) {
            const state = reactive({
                mainMenu: useMenu()
            });

            var reactionsList = ref([]);
            var animatedReactionsList = embedder('assets.emojis.animated');

            onMounted(() => {
                reactionsList.value = smileysAndEmotionEmojis.emojis;
            });

            return {
                state: state,
                reactionsList: reactionsList,
                animatedReactionsList: animatedReactionsList,
                getEmojiUrl: (image) => {
                    return embedder('links.assets.emoji') + "/" + image;
                },
                addReaction: (reactionId) => {
                    context.emit('add', reactionId);
                }
            }
        },
        components: {
            PanelHeader: PanelHeader,
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>