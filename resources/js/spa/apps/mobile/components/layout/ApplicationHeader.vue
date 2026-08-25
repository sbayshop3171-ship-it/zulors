<template>
	<header class="mobile-safe-header sticky top-0 z-[80] bg-bg-pr transition-all duration-300"
	v-bind:class="[state.hideHeader ? '-translate-y-full' : '']">
		
		<div class="mobile-safe-header__bar relative">
			<div class="pointer-events-none absolute inset-x-0 top-0 bottom-0 flex items-center justify-center px-24">
				<h1 class="zulors-wordmark mobile-safe-header__wordmark">
					Zulors
				</h1>
			</div>
			<div class="relative z-10 flex items-center justify-between px-3 h-full">
				<button type="button" v-on:click="state.mainMenu.open" class="mobile-safe-action shrink-0 inline-flex items-center gap-0.5 rounded-full px-1.5 outline-hidden active:bg-fill-tr">
					<h4 class="text-title-3 font-medium text-lab-pr text-center truncate">
						{{ $t('labels.home') }}
					</h4>
					<div class="size-icon-small shrink-0 text-lab-pr3">
						<SvgIcon name="chevron-down"></SvgIcon>
					</div>
				</button>
				<div class="ml-auto flex min-h-11 items-center gap-1">
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
