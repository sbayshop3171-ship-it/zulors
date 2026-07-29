<template>
	<div class="px-4 py-2 relative after:content-[''] after:absolute after:bottom-0 after:left-16 after:right-0 after:border-b after:border-bord-pr last:after:border-none">
		<div class="flex items-center">
			<div class="flex items-center gap-2.5">
				<div class="shrink-0">
					<AvatarSmall v-bind:avatarSrc="viewItem.relations.user.avatar_url"></AvatarSmall>
				</div>
				<Name v-bind:name="viewItem.relations.user.name" v-bind:isVerified="viewItem.relations.user.verified || true"></Name>
			</div>
			<span class="text-lab-sc text-par-n ml-auto lowercase">
				{{ viewItem.date.is_today ? $t('labels.today') : $t('labels.yesterday') }}
			</span>
			<span class="text-lab-sc text-par-n w-12 text-right">
				{{ viewItem.date.time }}
			</span>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	import AvatarSmall from '@M/components/general/avatars/AvatarSmall.vue';
	import Name from '@M/components/people/Name.vue';

	export default defineComponent({
		props: {
			viewItem: {
				type: Object,
				required: true
			}
		},
		components: {
			AvatarSmall: AvatarSmall,
			Name: Name
		}
	});
</script>