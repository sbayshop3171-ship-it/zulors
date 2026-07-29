<template>
	<div class="py-3 border-b border-bord-tr z-50 flex items-center popup-background-pr sticky top-0 cursor-pointer px-4">
		<div class="flex items-center justify-center shrink-0 ml-4">
			<div v-for="postData in postsPreviews" class="-ml-4">
				<AvatarSmall v-bind:avatarSrc="postData.relations.user.avatar_url"></AvatarSmall>
			</div>
		</div>
		<div class="flex-1 flex items-center justify-end">
			<h5 class="text-lab-sc font-semibold text-par-n">
				{{ $t('labels.new_posts') }} <BadgeCounter color="bg-fill-pr !text-lab-sc" v-bind:count="posts.length"></BadgeCounter>
			</h5>
		</div>
	</div>
</template>

<script>
    import { defineComponent, onMounted, computed, onUnmounted } from 'vue';

	import AvatarSmall from '@M/components/general/avatars/AvatarSmall.vue';
	import BadgeCounter from '@M/components/general/counters/BadgeCounter.vue';

	export default defineComponent({
		props: {
			posts: {
				type: Array,
				default: []
			}
		},
        setup: function(props) {
			return {
				postsPreviews: computed(() => {
					return [...new Set(props.posts.map(item => item.relations.user.id))]
						.map(id => props.posts.find(item => item.relations.user.id === id)).splice(0, 7);
				})
			}
		},
		components: {
			AvatarSmall: AvatarSmall,
			BadgeCounter: BadgeCounter
		}
	});
</script>