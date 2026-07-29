<template>
	<div class="flex items-center gap-3 px-4 py-3 hover:bg-fill-qt">
		<button type="button" class="flex items-center gap-3 flex-1 min-w-0 text-left" v-on:click="$emit('select', userData)">
			<span class="shrink-0 relative">
				<AvatarNormal v-bind:avatarSrc="userData.avatar_url"></AvatarNormal>
				<span v-if="presence?.is_online" class="absolute right-0 bottom-0 size-3 rounded-full bg-green-500 ring-2 ring-bg-pr"></span>
				<span v-else-if="presence?.recent" class="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-green-600 px-1.5 py-0.5 text-[10px] leading-none font-semibold text-white ring-2 ring-bg-pr">
					{{ presence.short_label }}
				</span>
			</span>
			<span class="block flex-1 min-w-0">
				<strong class="block text-par-m font-semibold text-lab-pr2 truncate">
					{{ userData.name }}
					<VerificationBadge v-if="userData.verified" size="xs"></VerificationBadge>
				</strong>
				<span class="block text-par-s text-lab-sc truncate">
					@{{ userData.username }}<template v-if="userData.caption"> - {{ userData.caption }}</template>
				</span>
			</span>
		</button>
		<button v-if="removable" type="button" class="inline-flex-center size-small-avatar rounded-full hover:bg-fill-pr text-lab-tr shrink-0" v-on:click.stop="$emit('remove', userData)">
			<span class="size-icon-small">
				<SvgIcon name="x"></SvgIcon>
			</span>
		</button>
	</div>
</template>

<script>
	import { computed, defineComponent } from 'vue';

	import AvatarNormal from '@D/components/general/avatars/AvatarNormal.vue';

	export default defineComponent({
		props: {
			userData: {
				type: Object,
				required: true
			},
			removable: {
				type: Boolean,
				default: false
			}
		},
		emits: ['select', 'remove'],
		setup: function(props) {
			return {
				presence: computed(() => {
					return props.userData.presence || {};
				})
			};
		},
		components: {
			AvatarNormal: AvatarNormal
		}
	});
</script>
