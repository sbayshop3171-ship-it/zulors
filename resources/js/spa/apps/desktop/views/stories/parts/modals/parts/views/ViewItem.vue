<template>
	<div class="px-3 py-2 border-b border-fill-sc">
		<div class="flex items-center">
			<div class="shrink-0">
				<AvatarSmall v-bind:avatarSrc="viewItem.relations.user.avatar_url"></AvatarSmall>
			</div>
			<span class="text-lab-pr ml-2 font-medium text-par-n">
				{{ viewItem.relations.user.name }} <VerificationBadge v-if="viewItem.relations.user.verified" size="xs"></VerificationBadge>
			</span>
			<span class="text-lab-sc text-par-n ml-auto">
				{{ viewItem.date.time_ago }}
			</span>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue'
	export default defineComponent({
		props: {
			viewItem: {
				type: Object,
				required: true
			}
		},
		components: {
			AvatarSmall: AvatarSmall
		}
	});
</script>
