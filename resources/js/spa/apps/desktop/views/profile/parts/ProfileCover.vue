<template>
	<div class="relative overflow-hidden cursor-pointer min-h-[180px] bg-fill-fv">
		<img v-on:click.self="lightboxCover" class="w-full" v-bind:src="profileData.cover_url" alt="Cover">
		<button v-on:click="goBack" class="cursor-pointer absolute top-4 outline-none left-4 backdrop-blur-3xl bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
			<SvgIcon name="arrow-left" classes="size-icon-small text-white/90"></SvgIcon>
		</button>
	</div>
</template>

<script>
    import { defineComponent, inject } from 'vue';
	import { useLightboxStore } from '@D/store/lightbox/lightbox.store.js';
	import { useRouter } from 'vue-router';

	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const lightboxStore = useLightboxStore();
			const router = useRouter();
			
			return {
				profileData: profileData,
				lightboxCover: function() {
					lightboxStore.add({
						albumName: `${profileData.value.name} ${profileData.value.caption}`,
						images: [profileData.value.cover_url]
					});
				},
				goBack: function() {
					router.go(-1);
				}
			}
		},
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
    });
</script>