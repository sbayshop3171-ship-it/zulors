<template>
	<div>
		<div v-if="storyMusic" class="mx-3 mb-2 inline-flex max-w-[calc(100%-1.5rem)] items-center gap-2 rounded-full bg-black/45 px-3 py-1.5 text-white backdrop-blur-md">
			<SvgIcon name="music-note-01" type="line" classes="size-4 shrink-0"></SvgIcon>
			<span class="truncate text-cap-l font-semibold">{{ storyMusic.title }}</span>
			<span v-if="storyMusic.artist" class="shrink-0 text-cap-l text-white/60">&middot; {{ storyMusic.artist }}</span>
		</div>
		<div v-if="hasContent" class="text-par-s text-white px-3 cursor-pointer mb-2" v-on:click="showContent">
			{{ storyContent.substr(0, 90) }}...
		</div>
	</div>
</template>

<script>
	import { defineComponent, inject, computed } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	export default defineComponent({
		setup: function() {
			const playerState = inject('playerState');

			return {
				hasContent: computed(() => {
					return playerState.frameData.content.length;
				}),
				storyContent: computed(() => {
					return playerState.frameData.content;
				}),
				storyMusic: computed(() => {
					return playerState.frameData.story_music;
				}),
				showContent: () => {
					colibriEventBus.emit('story:show-content');
				}
			};
		}
	});
</script>
