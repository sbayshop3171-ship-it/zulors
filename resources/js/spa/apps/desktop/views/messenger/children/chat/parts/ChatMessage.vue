<template>
	<div v-on:dblclick.stop="replyToMessage" v-bind:title="$t('chat.double_click_to_reply')" class="group px-4 sm:px-6" v-bind:class="[displayMessageControls ? 'bg-brand-900/5' : 'hover:bg-fill-fv', isCompacted ? 'py-0.5' : 'py-1.5']" v-on:contextmenu.prevent="toggleMainDropdown">
		<div class="flex items-end gap-2" v-bind:class="[isSender ? 'justify-end' : 'justify-start']">
			<div v-if="! isSender" class="shrink-0 w-small-avatar">
				<AvatarSmall v-if="! isCompacted" v-bind:avatarSrc="messageUser.avatar_url"></AvatarSmall>
			</div>

			<div class="min-w-0 flex flex-col" v-bind:class="[isSender ? 'items-end' : 'items-start', hasMedia || hasLinkSnapshot || isLocationMessage ? 'max-w-[78%] xl:max-w-xl' : 'max-w-[78%] xl:max-w-lg']">
				<div v-if="! isSender && ! isCompacted" class="leading-none mb-1 px-1">
					<strong class="text-par-n font-semibold" v-bind:style="{ color: messageColor }">
						{{ messageUser.name }} <VerificationBadge v-if="messageUser.verified" size="xs"></VerificationBadge>
					</strong>
				</div>

				<div class="overflow-hidden rounded-2xl" v-bind:class="[isSender ? 'bg-brand-900 text-white rounded-br-md' : 'bg-fill-qt border border-bord-pr rounded-bl-md', hasMedia || hasLinkSnapshot || isLocationMessage ? 'p-1' : 'px-3 py-2']">
					<template v-if="messageData.has_parent">
						<div class="mb-1">
							<ChatMessageReply v-bind:replyData="replyData"></ChatMessageReply>
						</div>
					</template>

					<div v-if="isTranslatable && state.isTranslated" class="leading-none mb-1">
						<TextTranslateButton v-on:click="cancelTranslation" v-bind:buttonText="$t('labels.show_untranslated')"></TextTranslateButton>
					</div>

					<div v-if="isNotDeleted && messageContent && ! isLocationMessage" class="text-par-l markdown-text leading-snug font-medium break-words" v-bind:class="[isSender ? 'text-white' : 'text-lab-pr2']" v-html="$mdInline(messageContent)"></div>
					<div v-else-if="! isNotDeleted" class="flex">
						<p class="text-par-m leading-snug" v-bind:class="[isSender ? 'text-white opacity-80' : 'text-lab-sc']">
							{{ $t('chat.message_is_deleted') }}
						</p>
					</div>

					<div v-if="isTranslatable && isNotDeleted && state.isTranslated" class="mt-2">
						<TranslationService></TranslationService>
					</div>

                    <template v-if="isLocationMessage && isNotDeleted">
                        <div class="mt-1">
                            <MessageLocation v-bind:locationUrl="messageData.content"></MessageLocation>
                        </div>
                    </template>

					<template v-if="hasLinkSnapshot && isNotDeleted">
						<div class="mt-1 w-full min-w-64 max-w-md">
							<LinkSnapshot v-bind:linkSnapshot="messageData.relations.link_snapshot"></LinkSnapshot>
						</div>
					</template>

						<template v-if="hasMedia && isNotDeleted">
							<template v-if="messageData.type === 'video_circle'">
								<div class="mt-1">
									<CircleVideoPlayer
										v-bind:thumbnailUrl="messageData.relations.media.thumbnail_url"
										v-bind:duration="messageData.relations.media.metadata.duration"
									v-bind:videoUrl="messageData.relations.media.preview_url || messageData.relations.media.source_url"></CircleVideoPlayer>
								</div>
							</template>
							<template v-else-if="messageData.type === 'video'">
								<div class="mt-1 w-[min(68vw,288px)] overflow-hidden rounded-xl bg-black">
									<VideoPlayer
										v-bind:thumbnailUrl="messageData.relations.media.thumbnail_url"
										v-bind:duration="messageData.relations.media.metadata.duration"
									v-bind:videoUrl="messageData.relations.media.preview_url || messageData.relations.media.source_url"></VideoPlayer>
								</div>
							</template>
							<template v-else-if="messageData.type === 'audio'">
								<div class="mt-1 min-w-64 max-w-md">
									<AudioPlayer
									v-bind:mediaItem="messageData.relations.media"
								v-bind:label="messageUser.name"></AudioPlayer>
							</div>
						</template>
						<template v-else-if="messageData.type === 'image'">
							<div class="mt-1 min-w-48 max-w-md">
								<MessageImage v-bind:mediaData="{ mediaItem: messageData.relations.media, userName: messageUser.name, date: messageData.date.iso }"></MessageImage>
							</div>
						</template>
                        <template v-else-if="messageData.type === 'document'">
                            <div class="mt-1 min-w-64 max-w-md">
                                <MessageDocument v-bind:mediaData="messageData.relations.media"></MessageDocument>
                            </div>
                        </template>
					</template>
				</div>

				<div class="block mt-1" v-if="isNotDeleted && hasReactions">
					<ReactionsViewer v-on:add="addReaction" v-bind:reactions="messageData.relations.reactions"></ReactionsViewer>
				</div>

				<div class="flex items-center gap-1 mt-0.5 px-1" v-bind:class="[isSender ? 'justify-end' : 'justify-start']">
					<time class="text-cap-l text-lab-sc">{{ messageData.date.time_ago }}</time>
					<span v-if="isSender && isMessageSeen" class="size-icon-x-small text-brand-900">
						<SvgIcon type="line" name="message-double-check"></SvgIcon>
					</span>
				</div>
			</div>

			<div v-if="isNotDeleted" class="shrink-0 inline-flex items-center leading-none self-center" v-bind:class="[isSender ? 'order-first' : '']">
				<div class="inline-flex items-center" v-bind:class="[displayMessageControls ? 'visible' : 'invisible group-hover:visible']">
					<div class="shrink-0 transform -scale-x-100">
						<PrimaryIconButton v-on:click.stop="replyToMessage" iconName="share-06" iconSize="icon-small" iconType="line"></PrimaryIconButton>
					</div>
					<div class="shrink-0 relative">
						<PrimaryIconButton v-on:click.stop="openReactionsPicker" iconName="heart-rounded" iconSize="icon-small" iconType="line"></PrimaryIconButton>
						<PrimaryTransition>
							<div class="absolute right-0 top-8 origin-top-left z-20">
								<ReactionsPicker
									v-if="state.isReactionPickerOpen"
									v-on:add="addReaction"
								v-outside-click="closeReactionsPicker"></ReactionsPicker>
							</div>
						</PrimaryTransition>
					</div>
				</div>
				<div class="shrink-0 relative">
					<div class="opacity-80 hover:opacity-100">
						<DropdownButton v-on:click.stop="toggleMainDropdown"></DropdownButton>
					</div>
					<div class="absolute top-10 right-0 z-50" v-if="state.isDropdownOpen">
						<DropdownMenu v-outside-click="toggleMainDropdown" v-on:click="toggleMainDropdown">
							<DropdownReactions v-on:add="addReaction"></DropdownReactions>
							<DropdownMenuItem v-on:click="openReactionsPicker" iconName="heart-rounded" v-bind:textLabel="$t('dd.add_reaction')"></DropdownMenuItem>

							<template v-if="isTranslatable">
								<DropdownMenuItem v-if="state.isTranslated" v-on:click="cancelTranslation" iconName="translate-01" v-bind:textLabel="$t('dd.show_untranslated')"></DropdownMenuItem>
								<DropdownMenuItem v-else v-on:click="translate" iconName="translate-01" v-bind:textLabel="$t('dd.translate')"></DropdownMenuItem>
							</template>

							<DropdownMenuItem v-on:click="replyToMessage" iconName="pencil-line" v-bind:textLabel="$t('dd.message.reply', { name: messageUser.name })"></DropdownMenuItem>
							<DropdownMenuItem v-on:click="copyMessageText" iconName="type-01" v-bind:textLabel="$t('dd.copy_text')"></DropdownMenuItem>
							<Border/>
							<DropdownMenuItem v-on:click="deleteMessage" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.message.delete_message')"></DropdownMenuItem>
						</DropdownMenu>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, toRef, computed, reactive, defineAsyncComponent } from 'vue';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { colibriTranslator } from '@/kernel/services/translator/index.js';

	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';
	import DropdownReactions from '@D/components/general/dropdowns/parts/DropdownReactions.vue';
	import ChatMessageReply from '@D/views/messenger/children/chat/parts/ChatMessageReply.vue';
	import TranslationService from '@D/components/general/TranslationService.vue';
	import TextTranslateButton from '@D/components/inter-ui/buttons/TextTranslateButton.vue';
    import CircleVideoPlayer from '@/kernel/vue/components/players/CircleVideoPlayer.vue';
    import AudioPlayer from '@D/components/players/audio/AudioPlayer.vue';
    import VideoPlayer from '@D/components/players/video/VideoPlayer.vue';

	export default defineComponent({
		props: {
			messageData: {
				type: Object,
				required: true
			},
			isCompacted: {
				type: Boolean,
				required: true
			}
		},
		emits: ['delete', 'reply', 'copy'],
		setup: function (props, context) {
			const state = reactive({
				isDropdownOpen: false,
				isReactionPickerOpen: false,
				isTranslating: false,
				isTranslated: false
			});

			const messageTranslatedContent = ref('');
			const authStore = useAuthStore();
			const chatStore = useChatStore();
			const userData = ref(authStore.userData);
			const messageData = toRef(props, 'messageData');

			const openReactionsPicker = function() {
				state.isDropdownOpen = false;

				if (isNotDeleted.value) {
					debounce(() => {
						state.isReactionPickerOpen = true;
					}, 50);
				}
            }

            const closeReactionsPicker = function() {
                state.isReactionPickerOpen = false;
            }

			const isNotDeleted = computed(() => {
				return !messageData.value.meta.is_deleted;
			});

			const toggleMainDropdown = () => {
				closeReactionsPicker();

				if (isNotDeleted.value) {
					state.isDropdownOpen = !state.isDropdownOpen;
				}
			}

			const isSender = computed(() => {
				return userData.value.id == messageData.value.user_id;
			});

			return {
				state: state,
				isSender: isSender,
				closeReactionsPicker: closeReactionsPicker,
				openReactionsPicker: openReactionsPicker,
				messageData: messageData,
				messageUser: computed(() => {
					return messageData.value.relations.user;
				}),
				messageColor: computed(() => {
					return messageData.value.relations.participant.color;
				}),
				replyData: computed(() => {
					return messageData.value.relations.parent;
				}),
				messageContent: computed(() => {
					return state.isTranslated ? messageTranslatedContent.value : messageData.value.content;
				}),
				toggleMainDropdown: toggleMainDropdown,
				addReaction: (reactionId) => {
                    closeReactionsPicker();

					if (isNotDeleted.value) {
						chatStore.addReaction(reactionId, messageData.value.id);
					}
                },
				displayMessageControls: computed(() => {
					return state.isDropdownOpen || state.isReactionPickerOpen;
				}),
				hasReactions: computed(() => {
                    return messageData.value.relations.reactions.length;
                }),
				hasLinkSnapshot: computed(() => {
					return messageData.value.relations?.link_snapshot;
				}),
                hasMedia: computed(() => {
                    let mediaData = messageData.value.relations?.media;

                    return (mediaData && ! Array.isArray(mediaData) && Object.keys(mediaData).length > 0);
                }),
				isNotDeleted: isNotDeleted,
                isLocationMessage: computed(() => {
                    return messageData.value.type === 'location';
                }),
				isTranslatable: computed(() => {
					return messageData.value.meta.is_translatable;
				}),
				deleteMessage: () => {
					context.emit('delete', {
						messageId: messageData.value.id,
						isSender: isSender.value
					});
                },
				replyToMessage: () => {
					context.emit('reply', messageData.value);
				},
				copyMessageText: () => {
					context.emit('copy', messageData.value);
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
				translate: async () => {
                    if (state.isTranslating || state.isTranslated) {
                        return false;
                    }

                    state.isTranslating = true;
                    const translatedText = await colibriTranslator().translate(messageData.value.content);

                    if (translatedText) {
                        messageTranslatedContent.value = translatedText;
                        state.isTranslated = true;
                    }

                    state.isTranslating = false;
                },
				cancelTranslation: () => {
                    state.isTranslated = false;
                    messageTranslatedContent.value = '';
                },
			};
		},
		components: {
			AvatarSmall: AvatarSmall,
			DropdownButton: DropdownButton,
			DropdownMenu: DropdownMenu,
			DropdownMenuItem: DropdownMenuItem,
			PrimaryIconButton: PrimaryIconButton,
			ChatMessageReply: ChatMessageReply,
			TranslationService: TranslationService,
			TextTranslateButton: TextTranslateButton,
			DropdownReactions: DropdownReactions,
            CircleVideoPlayer: CircleVideoPlayer,
            VideoPlayer: VideoPlayer,
            AudioPlayer: AudioPlayer,
			ReactionsPicker: defineAsyncComponent(() => {
                return import('@D/components/reactions/ReactionsPicker.vue');
            }),
			ReactionsViewer: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/reactions/ReactionsViewer.vue');
            }),
			LinkSnapshot: defineAsyncComponent(() => {
                return import('@D/components/media/links/LinkSnapshot.vue');
            }),
            MessageImage: defineAsyncComponent(() => {
                return import('@D/views/messenger/children/chat/parts/media/MessageImage.vue');
            }),
            MessageDocument: defineAsyncComponent(() => {
                return import('@D/views/messenger/children/chat/parts/media/MessageDocument.vue');
            }),
            MessageLocation: defineAsyncComponent(() => {
                return import('@D/views/messenger/children/chat/parts/media/MessageLocation.vue');
            })
		}
	});
</script>
