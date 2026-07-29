<template>
	<template v-if="! state.isLoading">
		<a v-if="adData" v-bind:href="adData.click_url || adData.target_url" target="_blank" class="w-full block">
			<div class="border border-bord-pr rounded-2xl overflow-hidden">
				<div class="overflow-hidden relative">
					<img class="w-full" v-bind:src="adData.preview_image_url" alt="Ad Creative">
					<span class="absolute top-3 bg-black/20 leading-none text-white left-3 backdrop-blur-xs px-2 py-1.5 rounded-full text-cap-s">
						{{ $t('labels.ad') }} &middot; 16+
					</span>
				</div>
				<div class="p-4">
					<h4 class="font-semibold text-par-l text-lab-pr mb-1">
						{{ adData.title }}
					</h4>
					<p class="text-lab-sc text-par-s mb-2">
						{{ adData.content }}
					</p>
					<span class="block text-lab-sc text-par-s mb-4">
						{{ adData.target_url }}
					</span>
					<div class="block">
						<PrimaryPillButton v-bind:buttonText="adData.cta_text" v-bind:buttonFluid="true" buttonSize="lm"></PrimaryPillButton>	
					</div>
				</div>
			</div>
		</a>
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
				await adStore.fetchAd();
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
