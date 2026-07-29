<template>
	<div class="flex items-center text-lab-pr2 pr-4 leading-none h-14 border-t border-t-fill-pr px-3">
		<span class="shrink-0 size-6">
			<SvgIcon name="volume-max" type="line"></SvgIcon>
		</span>
		<span class="ml-2 text-par-m">
			{{ $t('notifs.notifications_sound') }}
		</span>
		<span class="shrink-0 ml-auto">
			<SecondarySwitcher v-model="isSoundEnabled"></SecondarySwitcher>
		</span>
	</div>
</template>

<script>
	import { defineComponent, ref, onMounted, watch } from 'vue';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import SecondarySwitcher from '@D/components/inter-ui/switchers/SecondarySwitcher.vue';

	export default defineComponent({
		props: {
			
		},
		setup: function(props, context) {
			const isSoundEnabled = ref(true);

			onMounted(() => {
				isSoundEnabled.value = colibriSounds.isNotificationsSoundEnabled();
			});

			watch(isSoundEnabled, (newValue) => {
				colibriSounds.setNotificationsSoundEnabled(newValue);
			});

			return {
				isSoundEnabled: isSoundEnabled
			};
		},
		components: {
			SecondarySwitcher: SecondarySwitcher
		}
	});
</script>
