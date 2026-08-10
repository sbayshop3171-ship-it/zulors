<template>
	<div class="px-4 py-0.5" v-on:dblclick.stop="reply" v-bind:title="$t('chat.double_click_to_reply')">
		<div class="flex-1 group">
			<div class="flex gap-2.5 items-end" v-bind:class="{ 'flex-row-reverse': isSender }">
				<div v-if="! isSender" class="shrink-0">
					<Avatar2ExtraSmall v-bind:avatarSrc="messageUser.avatar_url"></Avatar2ExtraSmall>
				</div>
				<div class="overflow-hidden">
					<div class="flex" v-bind:class="{ 'flex-row-reverse': isSender }">
						<div class="overflow-hidden flex-1 rounded-xl p-1 w-fit min-w-3/12"
						v-bind:class="[isSender ? 'bg-brand-900 text-white rounded-br-md' : 'bg-fill-qt border border-bord-pr rounded-bl-md', hasMedia || hasLinkSnapshot || isLocationMessage ? 'max-w-[78vw] sm:max-w-96' : 'max-w-[78vw] sm:max-w-80']">

							<template v-if="hasLinkSnapshot && isNotDeleted">
								<div class="w-full mb-1">
									<LinkSnapshot v-bind:linkSnapshot="messageData.relations.link_snapshot"></LinkSnapshot>
								</div>
							</template>

                            <template v-if="hasMedia && isNotDeleted">
                                <div class="w-full mb-1">
                                    <template v-if="messageData.type === 'image'">
                                        <MessageImage v-bind:mediaData="{ mediaItem: messageData.relations.media, userName: messageUser.name, date: messageData.date.iso }"></MessageImage>
                                    </template>
	                                    <template v-else-if="messageData.type === 'video_circle'">
	                                        <CircleVideoPlayer
	                                            v-bind:thumbnailUrl="messageData.relations.media.thumbnail_url"
	                                            v-bind:duration="messageData.relations.media.metadata.duration"
	                                        v-bind:videoUrl="messageData.relations.media.preview_url || messageData.relations.media.source_url"></CircleVideoPlayer>
	                                    </template>
	                                    <template v-else-if="messageData.type === 'video'">
	                                        <div class="w-[min(68vw,256px)] overflow-hidden rounded-xl bg-black">
	                                            <VideoPlayer
	                                                v-bind:thumbnailUrl="messageData.relations.media.thumbnail_url"
	                                                v-bind:duration="messageData.relations.media.metadata.duration"
	                                            v-bind:videoUrl="messageData.relations.media.preview_url || messageData.relations.media.source_url"></VideoPlayer>
	                                        </div>
	                                    </template>

	                                    <template v-else-if="messageData.type === 'audio'">
                                        <div class="p-1">
                                            <AudioPlayer
                                                v-bind:mediaItem="messageData.relations.media"
                                            v-bind:label="messageUser.name"></AudioPlayer>
                                        </div>
                                    </template>
                                    <template v-else-if="messageData.type === 'document'">
                                        <MessageDocument v-bind:mediaData="messageData.relations.media"></MessageDocument>
                                    </template>
                                </div>
                            </template>

                            <template v-if="isLocationMessage && isNotDeleted">
                                <div class="w-full mb-1">
                                    <MessageLocation v-bind:locationUrl="messageData.content"></MessageLocation>
                                </div>
                            </template>

							<template v-if="messageData.has_parent">
								<div class="mb-1">
									<ChatMessageReply v-bind:replyData="replyData"></ChatMessageReply>
								</div>
							</template>
							<div class="px-1">
								<p class="text-par-l break-words" v-bind:class="[isSender ? 'text-white' : 'text-lab-pr']">
									<template v-if="isNotDeleted && messageData.content && ! isLocationMessage">
										<span v-html="$mdInline(messageData.content)"></span>
									</template>
									<template v-else-if="! isNotDeleted">
										<span class="text-par-m" v-bind:class="[isSender ? 'text-white opacity-80' : 'text-lab-sc']">
											{{ $t('chat.message_is_deleted') }}
										</span>
									</template>
								</p>
							</div>
							<div class="leading-none flex items-center justify-end px-1">
								<time class="text-cap-l mr-1" v-bind:class="[isSender ? 'text-white opacity-70' : 'text-lab-sc']">{{ messageData.date.time_ago }}</time>
								<span v-if="isSender && isMessageSeen" class="size-4 text-sky-300">
									<SvgIcon type="line" name="message-double-check"></SvgIcon>
								</span>
							</div>
						</div>
					</div>
				</div>
				<div v-if="isNotDeleted" class="shrink-0 w-x-small-avatar self-center">
					<DropdownButton v-on:click="state.mainMenu.open"></DropdownButton>
				</div>
			</div>
			<div class="block mt-1" v-if="isNotDeleted && hasReactions">
				<div class="flex" v-bind:class="[isSender ? 'flex-row-reverse' : '']">
					<div class="ml-2x-small-avatar pl-2.5">
						<ReactionsViewer v-on:add="addReaction" v-bind:reactions="messageData.relations.reactions"></ReactionsViewer>
					</div>
				</div>
			</div>
		</div>
	</div>

	<ActionSheet v-if="isNotDeleted && state.mainMenu.status" v-on:close="state.mainMenu.close" v-bind:isMuted="true">
		<div v-on:click.stop="state.mainMenu.close">
			<div class="mb-4">
				<ActionSheetReactions v-on:add="addReaction"></ActionSheetReactions>
			</div>
			<ActionSheetGroup>
				<ActionSheetItem v-on:click="state.reactionMenu.open" iconName="heart-rounded" v-bind:textLabel="$t('dd.add_reaction')"></ActionSheetItem>

				<ActionSheetItem v-on:click="reply" iconName="pencil-line" v-bind:textLabel="$t('dd.message.reply', { name: messageUser.name })"></ActionSheetItem>

				<ActionSheetItem v-on:click="deleteMessage" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.message.delete_message')"></ActionSheetItem>

				<ActionSheetItem v-on:click="copyContent" iconName="type-01" v-bind:textLabel="$t('dd.copy_text')"></ActionSheetItem>
			</ActionSheetGroup>
		</div>
	</ActionSheet>

	<ReactionsPicker v-if="state.reactionMenu.status" v-on:add="addReaction" v-on:close="state.reactionMenu.close"></ReactionsPicker>
</template>

<script>
	import { defineComponent, toRef, computed, reactive, defineAsyncComponent } from 'vue';
	import { useChatStore } from '@M/store/chats/chat.store';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import Avatar2ExtraSmall from '@M/components/general/avatars/Avatar2ExtraSmall.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import DropdownButton from '@M/components/general/dropdowns/DropdownButton.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import ActionSheetReactions from '@M/components/general/sheets/ActionSheetReactions.vue';
	import ReactionsViewer from '@/kernel/vue/components/reactions/ReactionsViewer.vue';
	import ReactionsPicker from '@M/views/messenger/children/chat/parts/ReactionsPicker.vue';
	import ChatMessageReply from '@M/views/messenger/children/chat/parts/ChatMessageReply.vue';
    import CircleVideoPlayer from '@/kernel/vue/components/players/CircleVideoPlayer.vue';
    import AudioPlayer from '@M/components/players/audio/AudioPlayer.vue';
    import VideoPlayer from '@M/components/players/video/VideoPlayer.vue';

	export default defineComponent({
		props: {
			messageData: {
				type: Object,
				required: true
			}
		},
		emits: ['delete', 'reply', 'copy'],
		setup: function (props, context) {
			const chatStore = useChatStore();
			const authStore = useAuthStore();

			const userData = computed(() => {
				return authStore.userData;
			});

			const messageData = toRef(props, 'messageData');
			const state = reactive({
				mainMenu: useMenu(),
				reactionMenu: useMenu()
			});

			const isSender = computed(() => {
				return userData.value.id == messageData.value.user_id;
			});

			const isNotDeleted = computed(() => {
				return ! messageData.value.meta.is_deleted;
			});

			return {
				isSender: isSender,
				state: state,
				isNotDeleted: isNotDeleted,
                isLocationMessage: computed(() => {
                    return messageData.value.type === 'location';
                }),
				messageUser: computed(() => {
					return messageData.value.relations.user;
				}),
                hasMedia: computed(() => {
                    let mediaData = messageData.value.relations?.media;

                    return (mediaData && ! Array.isArray(mediaData) && Object.keys(mediaData).length > 0);
                }),
				replyData: computed(() => {
					return messageData.value.relations.parent;
				}),
				messageColor: computed(() => {
					return messageData.value.relations.participant.color;
				}),
				reply: function() {
					colibriEventBus.emit('messenger-message:reply', {
						messageData: messageData.value
					});
				},
				copyContent: function() {
					context.emit('copy', messageData.value);
				},
				addReaction: (reactionId) => {
					if (isNotDeleted.value) {
						state.reactionMenu.close();
						chatStore.addReaction(reactionId, messageData.value.id);
					}
                },
				deleteMessage: function() {
					context.emit('delete', {
						messageId: messageData.value.id,
						isSender: isSender.value
					});
				},
				isMessageSeen: computed(() => {
					if(! isSender.value || ! chatStore.otherParticipants?.length) {
						return false;
					}

					if(chatStore.chatData?.is_group) {
						return chatStore.otherParticipants.every(function(p) {
							return p.last_read_message_id >= messageData.value.id;
						});
					}

					return chatStore.otherParticipants.some(function(p) {
						return p.last_read_message_id >= messageData.value.id;
					});
				}),
				hasReactions: computed(() => {
                    return messageData.value.relations.reactions.length;
                }),
				hasLinkSnapshot: computed(() => {
					return messageData.value.relations?.link_snapshot;
				})
			};
		},
		components: {
			Avatar2ExtraSmall: Avatar2ExtraSmall,
			PrimaryIconButton: PrimaryIconButton,
			DropdownButton: DropdownButton,
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			ActionSheetGroup: ActionSheetGroup,
			ActionSheetReactions: ActionSheetReactions,
			ReactionsViewer: ReactionsViewer,
			ReactionsPicker: ReactionsPicker,
            CircleVideoPlayer: CircleVideoPlayer,
            VideoPlayer: VideoPlayer,
			ChatMessageReply: ChatMessageReply,
			LinkSnapshot: defineAsyncComponent(() => {
                return import('@M/components/media/links/LinkSnapshot.vue');
            }),
            MessageImage: defineAsyncComponent(() => {
                return import('@M/views/messenger/children/chat/parts/media/MessageImage.vue');
            }),
            MessageDocument: defineAsyncComponent(() => {
                return import('@M/views/messenger/children/chat/parts/media/MessageDocument.vue');
            }),
            MessageLocation: defineAsyncComponent(() => {
                return import('@M/views/messenger/children/chat/parts/media/MessageLocation.vue');
            }),
            AudioPlayer: AudioPlayer,
		}
	});
</script>
