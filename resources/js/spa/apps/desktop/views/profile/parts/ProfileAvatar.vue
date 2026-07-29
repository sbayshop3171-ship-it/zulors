<template>
	<div v-on:click="lightboxAvatar" class="cursor-pointer relative z-10 border-4 border-bg-pr rounded-full">
		<AvatarLarge v-bind:avatarSrc="profileData.avatar_url"></AvatarLarge>
	</div>
</template>

<script>
    import { defineComponent, inject } from 'vue';
	import { useLightboxStore } from '@D/store/lightbox/lightbox.store.js';

	import AvatarLarge from '@D/components/general/avatars/AvatarLarge.vue';

    export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const lightboxStore = useLightboxStore();

			return {
				profileData: profileData,
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