<template>
    <div class="relative overflow-hidden cursor-pointer h-36 bg-fill-fv mb-4">
        <img class="block h-full w-full object-cover" v-on:error="onCoverError" v-bind:src="coverSrc" alt="Cover">
    </div>
	<div class="flex items-center gap-3 px-4">
		<div v-on:click="lightboxAvatar" class="cursor-pointer">
			<AvatarLarge v-bind:avatarSrc="profileData.avatar_url"></AvatarLarge>
		</div>

		<div class="block">
			<h1 class="text-title-2 font-semibold leading-none text-lab-pr2 mb-1">
				{{ profileData.name }} <VerificationBadge size="sm" v-if="profileData.verified"></VerificationBadge>
			</h1>
			<p class="text-par-n text-lab-sc">
				<span class="inline-flex items-center gap-1">
					<span>{{ profileData.caption }}</span>
				</span>
			</p>
		</div>
	</div>
</template>

<script>
    import { defineComponent, inject, ref, watch } from 'vue';
	import { useLightboxStore } from '@M/store/lightbox/lightbox.store.js';

	import AvatarLarge from '@M/components/general/avatars/AvatarLarge.vue';

    export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const lightboxStore = useLightboxStore();
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
				lightboxAvatar: function() {
					lightboxStore.add({
						albumName: `${profileData.value.name} ${profileData.value.caption}`,
						images: [profileData.value.avatar_url]
					});
				}
			}
		},
		components: {
			AvatarLarge: AvatarLarge
		}
    });
</script>
