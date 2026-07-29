<template>
	<div class="flex px-4 py-3 items-center shrink-0">
		<div class="shrink-0">
			<AvatarExtraSmall v-bind:avatarSrc="userData.avatar_url"></AvatarExtraSmall>
		</div>
		<div class="flex-1 ml-1">
			<h4 class="text-par-n font-medium text-lab-pr2 truncate">
				{{ userData.name }}
				<VerificationBadge v-if="userData.verification.status" size="xs"></VerificationBadge>
			</h4>
		</div>
		<div class="shrink-0">
			<button v-on:click="closeEditor" class="text-par-s text-brand-900 cursor-pointer">{{ $t('labels.close') }}</button>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref } from 'vue';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { useStoriesEditorStore } from '@D/store/stories/editor.store.js';

	import AvatarExtraSmall from '@D/components/general/avatars/AvatarExtraSmall.vue';

	export default defineComponent({
		setup: function() {
			const storiesEditorStore = useStoriesEditorStore();
			const authStore = useAuthStore();
			const userData = ref(authStore.userData);

			return {
				userData: userData,
				closeEditor: () => {
					storiesEditorStore.closeEditor();
				}
			};
		},
		components: {
			AvatarExtraSmall: AvatarExtraSmall
		}
	});
</script>