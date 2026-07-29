<template>
	<div class="flex overflow-hidden">
		<div class="flex-1 flex relative items-stretch rounded-tl-md rounded-bl-md rounded-tr-lg rounded-br-lg overflow-hidden">
			<div class="absolute inset-0 opacity-15" v-bind:style="{ backgroundColor: messageColor }"></div>
			<div class="w-1 shrink-0 relative z-10" v-bind:style="{ backgroundColor: messageColor }"></div>
			<div class="cursor-pointer pl-2 pb-1 pt-2 pr-3 mb-1 relative z-10">
				<h3 class="text-cap-l font-semibold leading-none mb-0.5" v-bind:style="{ color: messageColor }">
					{{ replyData.user.name }}
				</h3>
				<template v-if="replyData.relations?.link_snapshot">
					<div class="mt-2 w-full">
						<LinkSnapshot v-bind:linkSnapshot="replyData.relations.link_snapshot"></LinkSnapshot>
					</div>
				</template>
				<template v-else-if="replyData.type === 'image' && replyData.relations.media">
					<div class="mt-1 flex items-center gap-2">
						<img v-bind:src="replyData.relations.media.source_url" v-bind:alt="replyData.relations.media.name" class="size-6 object-cover rounded-sm">
						<p class="text-par-s truncate text-lab-sc max-w-content">
							{{ $t('labels.image_message') }}
						</p>
					</div>
				</template>
				<template v-else-if="['video_circle', 'video'].includes(replyData.type) && replyData.relations.media">
					<div class="mt-1 flex items-center gap-2">
						<img v-bind:src="replyData.relations.media.thumbnail_url" v-bind:alt="replyData.relations.media.name" class="size-6 object-cover rounded-sm">
						<p class="text-par-s truncate text-lab-sc max-w-content">
							{{ $t('labels.video_message') }}
						</p>
					</div>
				</template>
				<template v-else-if="replyData.type === 'audio' && replyData.relations.media">
					<div class="mt-1 flex items-center gap-2">
						<div class="shrink-0">
							<AvatarExtraSmall v-bind:avatarSrc="replyData.user.avatar_url"></AvatarExtraSmall>
						</div>
						<p class="text-par-s truncate text-lab-sc max-w-content">
							{{ $t('labels.audio_message') }}
						</p>
					</div>
				</template>
				<template v-else-if="replyData.type === 'document'">
					<p class="text-par-s truncate text-lab-sc max-w-content">{{ $t('labels.document') }}</p>
				</template>
				<template v-else-if="replyData.type === 'location'">
					<p class="text-par-s truncate text-lab-sc max-w-content">{{ $t('labels.location') }}</p>
				</template>
				<p v-else class="text-par-s text-lab-pr2 break-words" v-html="replyData.content"></p>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

    import AvatarExtraSmall from '@M/components/general/avatars/AvatarExtraSmall.vue';
    import LinkSnapshot from '@M/components/media/links/LinkSnapshot.vue';

	export default defineComponent({
		props: {
			replyData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			return {
				messageColor: computed(() => {
					return props.replyData.participant.color;
				})
			}
		},
		components: {
			AvatarExtraSmall: AvatarExtraSmall,
			LinkSnapshot: LinkSnapshot
		}
	});
</script>
