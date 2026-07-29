<template>
	<div v-if="isTyping" class="flex items-center px-6 bg-green-100 py-2">
		<div class="shrink-0">
			<p class="text-par-s text-black font-medium">{{ $t('chat.user_is_typing', { name: typingUser.user.name }) }}</p>
		</div>
		<div class="shrink-0 ml-6">
			<div class="colibri-primary-animation"></div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
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