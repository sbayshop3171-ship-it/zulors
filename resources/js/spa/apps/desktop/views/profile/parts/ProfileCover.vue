<template>
	<div class="relative overflow-hidden cursor-pointer h-[220px] bg-fill-fv">
		<img v-on:click.self="lightboxCover" v-on:error="onCoverError" class="block h-full w-full object-cover" v-bind:src="coverSrc" alt="Cover">
		<button v-on:click="goBack" class="cursor-pointer absolute top-4 outline-none left-4 backdrop-blur-3xl bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
			<SvgIcon name="arrow-left" classes="size-icon-small text-white/90"></SvgIcon>
		</button>
	</div>
</template>

<script>
    import { defineComponent, inject, ref, watch } from 'vue';
	import { useLightboxStore } from '@D/store/lightbox/lightbox.store.js';
	import { useRouter } from 'vue-router';

	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const lightboxStore = useLightboxStore();
			const router = useRouter();
			const fallbackCoverUrl = config('user.default_cover', '');
			const coverSrc = ref(profileData.value.cover_url || fallbackCoverUrl);

			watch(() => {
				return profileData.value.cover_url;
			}, (coverUrl) => {
				coverSrc.value = coverUrl || fallbackCoverUrl;
			});
			
			return {
				profileData: profileData,
				coverSrc: coverSrc,
				onCoverError: function() {
					if(coverSrc.value !== fallbackCoverUrl) {
						coverSrc.value = fallbackCoverUrl;
					}
				},
				lightboxCover: function() {
					lightboxStore.add({
						albumName: `${profileData.value.name} ${profileData.value.caption}`,
						images: [coverSrc.value || fallbackCoverUrl]
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
