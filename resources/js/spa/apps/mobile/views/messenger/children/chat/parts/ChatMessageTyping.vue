<template>
	<div v-if="isTyping" class="flex px-4 gap-2">
		<Avatar2ExtraSmall v-bind:avatarSrc="typingUser.user.avatar_url"></Avatar2ExtraSmall>
		<div class="w-12 flex items-center justify-center">
			<div class="colibri-primary-animation"></div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
	import Avatar2ExtraSmall from '@M/components/general/avatars/Avatar2ExtraSmall.vue';

	export default defineComponent({
		components: {
			Avatar2ExtraSmall: Avatar2ExtraSmall
		},
		props: {
			typingUser: {
				type: Object,
				default: {
					is_typing: false,
					user: null
				}
			}
		},
		setup: function (props) {
			return {
				isTyping: computed(() => {
					return props.typingUser.is_typing === true;
				})
			};
		}
	});
</script>