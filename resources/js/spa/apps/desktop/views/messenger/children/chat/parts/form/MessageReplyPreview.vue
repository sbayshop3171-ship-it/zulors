<template>
	<div class="block">
		<div class="flex overflow-hidden">
			<div class="size-small-avatar shrink-0">
				<div v-bind:style="{ backgroundColor: messageColor }" class="transform -scale-x-100 size-small-avatar inline-flex-center overflow-hidden rounded-full">
					<SvgIcon name="share-06" type="line" classes="size-icon-small text-white"></SvgIcon>
				</div>
			</div>
			<div class="flex-1 overflow-hidden ml-2">
				<div class="overflow-hidden">
					<h3 class="text-par-s font-semibold leading-none" v-bind:style="{ color: messageColor }">
						{{ $t('labels.reply_to') }} {{ messageData.relations.user.name }}

						<span class="text-par-s text-lab-sc leading-none ml-1 font-normal">
							{{ messageData.date.time_ago }}
						</span>
					</h3>
                    <template v-if="messageData.relations?.link_snapshot">
                        <div class="mt-2 max-w-full">
                            <LinkSnapshot v-bind:linkSnapshot="messageData.relations.link_snapshot"></LinkSnapshot>
                        </div>
                    </template>
                    <template v-else>
                    <template v-if="['video_circle', 'video'].includes(messageData.type)">
                        <div class="flex mt-2 items-center gap-2">
                            <div class="shrink-0">
                                <AvatarSmall v-bind:avatarSrc="messageData.relations.media.thumbnail_url"></AvatarSmall>
                            </div>
                            <p class="text-par-m truncate text-lab-sc max-w-content">
                                {{ $t('labels.video_message') }}
                            </p>
                        </div>
                    </template>
                    <template v-else-if="messageData.type === 'audio'">
                        <div class="flex mt-2 items-center gap-2">
                            <div class="shrink-0">
                                <AvatarSmall v-bind:avatarSrc="messageData.relations.user.avatar_url"></AvatarSmall>
                            </div>
                            <p class="text-par-m truncate text-lab-sc max-w-content">
                                {{ $t('labels.audio_message') }}
                            </p>
                        </div>
                    </template>
                    <template v-else-if="messageData.type === 'image'">
                        <div class="flex mt-2 items-center gap-2">
                            <div class="shrink-0">
                                <img v-bind:src="messageData.relations.media.source_url" v-bind:alt="messageData.relations.media.name" class="size-6 object-cover rounded-sm">
                            </div>
                            <p class="text-par-m truncate text-lab-sc max-w-content">
                                {{ $t('labels.image_message') }}
                            </p>
                        </div>
                    </template>
                    <template v-else-if="messageData.type === 'document'">
                        <p class="text-par-m truncate text-lab-sc max-w-content">{{ $t('labels.document') }}</p>
                    </template>
                    <template v-else-if="messageData.type === 'location'">
                        <p class="text-par-m truncate text-lab-sc max-w-content">{{ $t('labels.location') }}</p>
                    </template>
                    <template v-else>
                        <p class="text-par-m truncate text-lab-pr2 max-w-content" v-html="messageData.content"></p>
                    </template>
                    </template>
				</div>
			</div>
			<div class="size-small-avatar shrink-0">
				<PrimaryIconButton v-on:click="cancelMessageReply" name="x-close"></PrimaryIconButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';

	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
    import LinkSnapshot from '@D/components/media/links/LinkSnapshot.vue';

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
            AvatarSmall: AvatarSmall,
            LinkSnapshot: LinkSnapshot
		}
	});
</script>
