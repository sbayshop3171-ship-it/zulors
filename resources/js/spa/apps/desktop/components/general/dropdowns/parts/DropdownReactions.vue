<template>
	<div class="px-4 py-4 border-b border-b-bord-pr">
		<h5 class="text-lab-sc text-par-n mb-4">
			{{ $t('labels.leave_reaction') }}
		</h5>
		<swiper-container slides-per-view="7" autoplay-disable-on-interaction="true" autoplay-delay="1000" space-between="16" v-bind:autoplay="true" speed="200" mousewheel="true" grab-cursor="true" class="w-full">
			<swiper-slide v-for="reactionItem in reactionsList" v-bind:key="reactionItem.unified">
				<div v-bind:title="reactionItem.unified" v-on:click="addReaction(reactionItem)" class="cursor-pointer shrink-0">
					<img class="w-full" v-bind:src="$asset(reactionItem.url)" loading="lazy" alt="Reaction">
				</div>
			</swiper-slide>
		</swiper-container>
	</div>
</template>

<script>
    import { defineComponent, ref, onMounted } from 'vue';
	import { register  } from 'swiper/element/bundle';

	register();

    export default defineComponent({
        emits: ['add'],
        setup: function(props, context) {
            var reactionsList = ref([]);

			onMounted(() => {
				reactionsList.value = embedder('assets.emojis.animated');
			});

            return {
                reactionsList: reactionsList,
                addReaction: (emojiItem) => {
                    context.emit('add', emojiItem.unified);
                }
            }
        }
    });
</script>