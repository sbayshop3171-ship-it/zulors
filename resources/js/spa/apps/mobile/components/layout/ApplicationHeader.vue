<template>
	<header class="sticky top-0 z-50 bg-bg-pr transition-all duration-300"
	v-bind:class="[state.hideHeader ? '-translate-y-full' : '']">
		
		<div class="relative h-14">
			<div class="absolute inset-0 flex items-center justify-center pointer-events-none">
				<h1 class="zulors-wordmark">
					Zulors
				</h1>
			</div>
			<div class="relative z-10 flex items-center justify-between px-4 h-full cursor-pointer">
				<div v-on:click="state.mainMenu.open" class="shrink-0 inline-flex items-center gap-0.5">
					<h4 class="text-title-3 font-medium text-lab-pr text-center truncate">
						{{ $t('labels.home') }}
					</h4>
					<div class="size-icon-small shrink-0 text-lab-pr3">
						<SvgIcon name="chevron-down"></SvgIcon>
					</div>
				</div>
				<div class="ml-auto flex gap-2">
					<NotificationsButton></NotificationsButton>
				</div>
			</div>
		</div>
		<Soundbar></Soundbar>
	</header>

	<Teleport to="body">
		<HeaderMenu v-if="state.mainMenu.status" v-on:close="state.mainMenu.close"></HeaderMenu>
	</Teleport>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted, reactive, computed } from 'vue';
	import { useAudioStore } from '@M/store/audio/audio.store.js';

	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

import NotificationsButton from '@M/components/layout/parts/NotificationsButton.vue';
import Soundbar from '@M/components/soundbar/Soundbar.vue';
import HeaderMenu from '@M/components/layout/parts/HeaderMenu.vue';
	
	export default defineComponent({
		setup: function () {
			const audioStore = useAudioStore();
			const state = reactive({
				hideHeader: false,
				scrollPosition: 0,
				mainMenu: useMenu()
			});

			const fixed = computed(() => {
				return audioStore.audioData !== null;
			});

			const handleScroll = () => {
				if(! fixed.value) {
					const current = window.scrollY
					state.hideHeader = current > state.scrollPosition && current > 50;
					state.scrollPosition = current;
				}
				else {
					state.hideHeader = false;
				}
			}

			onMounted(() => {
				window.addEventListener('scroll', handleScroll);
			});

			onUnmounted(() => {
				window.removeEventListener('scroll', handleScroll);
			});

			return {
				state: state
			};
		},
		components: {
			NotificationsButton: NotificationsButton,
			Soundbar: Soundbar,
			HeaderMenu: HeaderMenu
		}
	});
</script>
