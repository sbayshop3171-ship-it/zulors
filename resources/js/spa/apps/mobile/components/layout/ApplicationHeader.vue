<template>
	<header class="mobile-safe-header sticky top-0 z-[80] bg-bg-pr transition-all duration-300 will-change-transform"
	v-bind:class="[isHeaderHidden ? '-translate-y-full' : '']">
		
		<div class="mobile-safe-header__bar relative h-[3.5rem]">
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
	import { defineComponent, reactive, computed } from 'vue';
	import { useAudioStore } from '@M/store/audio/audio.store.js';

	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
	import { useAutoHideHeader } from '@M/core/services/auto-hide-header.js';

import NotificationsButton from '@M/components/layout/parts/NotificationsButton.vue';
import Soundbar from '@M/components/soundbar/Soundbar.vue';
import HeaderMenu from '@M/components/layout/parts/HeaderMenu.vue';
	
	export default defineComponent({
		setup: function () {
			const audioStore = useAudioStore();
			const state = reactive({
				mainMenu: useMenu()
			});

			const isPinned = computed(() => {
				return audioStore.audioData !== null;
			});

			const { isHeaderHidden } = useAutoHideHeader({
				isPinned: isPinned,
				isMenuOpen: state.mainMenu.status
			});

			return {
				state: state,
				isHeaderHidden: isHeaderHidden
			};
		},
		components: {
			NotificationsButton: NotificationsButton,
			Soundbar: Soundbar,
			HeaderMenu: HeaderMenu
		}
	});
</script>
