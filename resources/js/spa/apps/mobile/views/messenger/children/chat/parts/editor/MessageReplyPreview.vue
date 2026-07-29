<template>
	<div class="px-4 pt-3 border-t border-t-bord-tr">
		<div class="flex overflow-hidden items-center gap-2.5">
			<div class="size-2x-small-avatar shrink-0">
				<div v-bind:style="{ backgroundColor: messageColor }" class="size-2x-small-avatar inline-flex-center overflow-hidden rounded-full">
					<SvgIcon name="share-06" type="line" classes="size-icon-x-small text-white"></SvgIcon>
				</div>
			</div>
			<div class="flex-1 overflow-hidden">
				<h5 class="text-par-s font-medium leading-none">
					{{ messageData.relations.user.name }} <span class="text-par-xs text-lab-sc leading-none ml-1 font-normal">{{ messageData.date.time_ago }}</span>
				</h5>
                <template v-if="messageData.relations?.link_snapshot">
                    <div class="mt-2 max-w-full">
                        <LinkSnapshot v-bind:linkSnapshot="messageData.relations.link_snapshot"></LinkSnapshot>
                    </div>
                </template>
                <template v-else>
                <template v-if="messageData.type === 'image'">
                    <div class="mt-1 flex items-center gap-2">
                        <img v-bind:src="messageData.relations.media.source_url" v-bind:alt="messageData.relations.media.name" class="size-6 object-cover rounded-sm">
                        <p class="text-par-m truncate text-lab-sc max-w-content">
                            {{ $t('labels.image_message') }}
                        </p>
                    </div>
                </template>
                <template v-else-if="['video_circle', 'video'].includes(messageData.type)">
                    <div class="mt-1 flex items-center gap-2">
                        <img v-bind:src="messageData.relations.media.thumbnail_url" v-bind:alt="messageData.relations.media.name" class="size-6 object-cover rounded-sm">
                        <p class="text-par-s truncate text-lab-sc max-w-content">
                            {{ $t('labels.video_message') }}
                        </p>
                    </div>
                </template>
                <template v-else-if="messageData.type === 'audio'">
                    <div class="mt-2 flex items-center gap-2">
                        <div class="shrink-0">
                            <AvatarExtraSmall v-bind:avatarSrc="messageData.relations.user.avatar_url"></AvatarExtraSmall>
                        </div>
                        <p class="text-par-s truncate text-lab-sc max-w-content">
                            {{ $t('labels.audio_message') }}
                        </p>
                    </div>
                </template>
                <template v-else-if="messageData.type === 'document'">
                    <p class="text-par-m truncate text-lab-sc max-w-content">{{ $t('labels.document') }}</p>
                </template>
                <template v-else-if="messageData.type === 'location'">
                    <p class="text-par-m truncate text-lab-sc max-w-content">{{ $t('labels.location') }}</p>
                </template>
				<p v-else class="text-par-n truncate text-lab-pr3 mt-0.5" v-html="messageData.content"></p>
                </template>
			</div>
			<div class="shrink-0">
				<PrimaryIconButton v-on:click="cancelMessageReply"></PrimaryIconButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
    import AvatarExtraSmall from '@M/components/general/avatars/AvatarExtraSmall.vue';
    import LinkSnapshot from '@M/components/media/links/LinkSnapshot.vue';

	export default defineComponent({
		props: {
			messageData: {
				type: Object,
				required: true
			}
		},
		emits: ['cancel'],
		setup: function(props, context) {
			const messageData = ref(props.messageData);

			return {
				messageData: messageData,
				cancelMessageReply: () => {
					context.emit('cancel');
				},
				messageColor: computed(() => {
					return messageData.value.relations.participant.color;
				})
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
            AvatarExtraSmall: AvatarExtraSmall,
            LinkSnapshot: LinkSnapshot,
		}
	});
</script>
