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
			<div class="border border-bord-pr rounded-2xl overflow-hidden shadow-xs transition-shadow hover:shadow-md">
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
					<span class="absolute top-3 left-3 bg-black/30 leading-none text-white backdrop-blur-xs px-2.5 py-1.5 rounded-full text-cap-s font-semibold">
						{{ $t('labels.ad') }} · Sponsored
					</span>
				</div>
				<div class="p-4">
					<h4 class="font-semibold text-par-l text-lab-pr mb-1">
						{{ adData.title }}
					</h4>
					<p class="text-lab-sc text-par-s mb-2">
						{{ adData.content }}
					</p>
					<div v-if="adData.cta_enabled && adData.click_url" class="block">
						<PrimaryPillButton v-bind:buttonText="adData.cta_text || 'Learn more'" v-bind:buttonFluid="true" buttonSize="lm"></PrimaryPillButton>
					</div>
				</div>
			</div>
		</component>
	</template>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted, reactive, computed } from 'vue';
	import { useAdStore } from '@D/store/ad/ad.store.js';

	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const adStore = useAdStore();
			const state = reactive({
				isLoading: true
			});

			let adInterval = null;

			const adData = computed(() => {
				return adStore.ad;
			});

			const fetchAd = async function() {
				await adStore.fetchAd('sidebar');
			}

			onMounted(async function() {
				await fetchAd();

				adInterval = setInterval(fetchAd, (1000 * 60 * 5));

				state.isLoading = false;
			});

			onUnmounted(function() {
				clearInterval(adInterval);
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
