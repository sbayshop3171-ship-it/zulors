<template>
	<div class="flex">
		<div class="shrink-0 overflow-hidden">
			<div class="flex relative items-stretch overflow-hidden">
				<div class="w-[3px] shrink-0 relative z-10 rounded-xs" v-bind:style="{ backgroundColor: messageColor }"></div>
				<div class="cursor-pointer pl-2 py-0.5 relative z-10 overflow-hidden">
					<h3 class="text-cap-l font-semibold leading-none mb-0.5" v-bind:style="{ color: messageColor }">
						{{ replyData.user.name }}
					</h3>
                    <template v-if="replyData.relations?.link_snapshot">
                        <div class="mt-2 max-w-full">
                            <LinkSnapshot v-bind:linkSnapshot="replyData.relations.link_snapshot"></LinkSnapshot>
                        </div>
                    </template>
                    <template v-else>
                    <template v-if="['video_circle', 'video'].includes(replyData.type) && replyData.relations.media">
                        <div class="flex mt-2 items-center gap-2">
                            <div class="shrink-0">
                                <AvatarExtraSmall v-bind:avatarSrc="replyData.relations.media.thumbnail_url"></AvatarExtraSmall>
                            </div>
                            <p class="text-par-m truncate text-lab-sc max-w-content">
                                {{ $t('labels.video_message') }}
                            </p>
                        </div>
                    </template>
                    <template v-else-if="replyData.type === 'audio'">
                        <p class="text-par-m truncate text-lab-sc max-w-content">
                            {{ $t('labels.audio_message') }}
                        </p>
                    </template>
                    <template v-else-if="replyData.type === 'image'">
                        <div class="flex mt-2 items-center gap-2">
                            <div class="shrink-0">
                                <img v-bind:src="replyData.relations.media.source_url" v-bind:alt="replyData.relations.media.name" class="size-6 object-cover rounded-sm">
                            </div>
                            <p class="text-par-m truncate text-lab-sc max-w-content">
                                {{ $t('labels.image_message') }}
                            </p>
                        </div>
                    </template>
                    <template v-else-if="replyData.type === 'document'">
                        <p class="text-par-m truncate text-lab-sc max-w-content">{{ $t('labels.document') }}</p>
                    </template>
                    <template v-else-if="replyData.type === 'location'">
                        <p class="text-par-m truncate text-lab-sc max-w-content">{{ $t('labels.location') }}</p>
                    </template>
                    <template v-else>
                        <p class="text-par-m text-lab-sc break-words" v-html="replyData.content"></p>
                    </template>
                    </template>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
    import AvatarExtraSmall from '@D/components/general/avatars/AvatarExtraSmall.vue';
    import LinkSnapshot from '@D/components/media/links/LinkSnapshot.vue';

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
