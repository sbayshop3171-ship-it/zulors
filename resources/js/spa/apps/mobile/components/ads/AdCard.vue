<template>
	<template v-if="! state.isLoading">
		<component
			v-if="adData"
			v-bind:is="adData.click_url ? 'a' : 'div'"
			v-bind:href="adData.click_url || undefined"
			v-bind:target="adData.click_url ? '_blank' : undefined"
			v-bind:rel="adData.click_url ? 'noopener' : undefined"
			class="w-full block"
		>
			<div class="overflow-hidden relative">
				<video
					v-if="adData.media_type === 'video' && adData.video_url"
					class="w-full"
					v-bind:src="adData.video_url"
					v-bind:poster="adData.thumbnail_url || adData.preview_image_url"
					muted
					loop
					playsinline
					autoplay></video>
				<img v-else class="w-full" v-bind:src="adData.preview_image_url" alt="Ad Creative">
				<span class="absolute top-3 bg-black/20 leading-none text-white left-3 backdrop-blur-xs px-2 py-1.5 rounded-full text-cap-s">
					{{ $t('labels.ad') }} &middot; Sponsored
				</span>
			</div>
			<div class="p-4">
				<h4 class="font-semibold text-par-l text-lab-pr mb-1">
					{{ adData.headline || adData.title }}
				</h4>
				<p class="text-lab-sc text-par-s mb-2">
					{{ adData.primary_text || adData.content }}
				</p>
				<div v-if="adData.cta_enabled && adData.click_url" class="block">
					<PrimaryPillButton v-bind:buttonText="adData.cta_text" v-bind:buttonFluid="true" buttonSize="md"></PrimaryPillButton>	
				</div>
			</div>
		</component>
	</template>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted, reactive, computed, ref } from 'vue';
	import { useAdStore } from '@M/store/ad/ad.store.js';

	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const adStore = useAdStore();
			const state = reactive({
				isLoading: true
			});

			const adData = ref(null);

			onMounted(async function() {
				adData.value = await adStore.fetchAd();

				state.isLoading = false;
			});

			return {
				adData: adData,
				state: state
			}
		},
		components: {
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
