<template>
	<button v-if="isLoading" type="button" class="skeleton-circle size-5 inline-block outline-none cursor-pointer"></button>
	<button v-else type="button" class="inline-block outline-none group cursor-pointer size-5">
		<img v-bind:src="emojiUrl" alt="Emojis" class="size-full group-hover:scale-130 transition-all duration-300">
	</button>
</template>

<script>
	import { defineComponent, ref, computed, onMounted, onUnmounted } from 'vue';
	
	export default defineComponent({
		setup: function() {
			const isLoading = ref(true);
			const emojiUrl = ref(null);
			const animatedEmojis = embedder('assets.emojis.animated');

			let interval = null;

			const setRandomEmoji = () => {
				emojiUrl.value =  animatedEmojis[Math.floor(Math.random() * animatedEmojis.length)].url;
			}

			onMounted(() => {
				isLoading.value = false;

				setRandomEmoji();

				interval = setInterval(() => {
					setRandomEmoji();
				}, 3500);
			});

			onUnmounted(() => {
				clearInterval(interval);
			});

			return {
				isLoading: isLoading,
				emojiUrl: emojiUrl
			}
		}
	});
</script>