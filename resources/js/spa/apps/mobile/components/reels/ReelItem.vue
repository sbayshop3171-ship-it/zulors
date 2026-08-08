<template>
	<section
		ref="reelRootRef"
		class="relative h-full snap-start snap-always overflow-hidden bg-black text-white"
	>
		<ReelVideoPlayer
			v-if="canPlayImmediately"
			v-bind:mediaItem="mediaItem"
			v-bind:postData="postData"
			v-bind:active="active"
			v-bind:isNear="isNear"
			v-bind:distanceFromActive="distanceFromActive"
			v-bind:blocked="isPlaybackBlocked"
			v-bind:feedSessionId="feedSessionId"
			v-bind:position="position"
			v-on:double-tap="handleDoubleTap"
		></ReelVideoPlayer>

		<div v-else class="absolute inset-0 bg-black inline-flex-center">
			<div class="max-w-56 px-5 text-center">
				<div class="mx-auto mb-4 size-12 rounded-full bg-white/10 inline-flex-center text-white/80">
					<SvgIcon name="video-recorder" type="line" classes="size-6"></SvgIcon>
				</div>
				<p class="text-par-s text-white/75">
					{{ fallbackText }}
				</p>
			</div>
		</div>

		<button
			v-if="isPlaybackBlocked"
			type="button"
			v-on:click.stop="revealSensitiveContent"
			class="absolute inset-0 z-20 bg-black/75 backdrop-blur-xl flex flex-col px-8 py-8 text-white"
		>
			<div class="mt-auto mb-3 inline-flex justify-center text-white/90">
				<SvgIcon name="eye-off" type="line" classes="size-11"></SvgIcon>
			</div>
			<h3 class="text-par-l font-bold text-center">
				{{ $t('labels.sensitive_content.title') }}
			</h3>
			<p class="text-par-s text-white/80 text-center mt-2">
				{{ $t('labels.sensitive_content.description') }}
			</p>
			<div class="mt-auto border-t border-white/25 pt-4 text-cap-l font-semibold text-center">
				{{ $t('labels.sensitive_content.button') }}
			</div>
		</button>

		<div v-if="state.showHeartBurst" class="pointer-events-none absolute inset-0 z-30 inline-flex-center">
			<SvgIcon name="heart-rounded" type="line" classes="reel-heart-burst text-white"></SvgIcon>
		</div>

		<div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-1/2 bg-gradient-to-t from-black/85 via-black/35 to-transparent"></div>

		<div class="absolute left-4 right-20 bottom-5 z-20">
			<RouterLink v-bind:to="{ name: 'profile_index', params: { id: postUser.username } }" class="inline-flex max-w-full items-center gap-2 mb-3">
				<img v-bind:src="postUser.avatar_url" v-bind:alt="postUser.username" class="size-9 rounded-full object-cover border border-white/30">
				<span class="min-w-0 truncate text-par-m font-bold text-white">
					{{ postUser.username }}
				</span>
				<VerificationBadge v-if="postUser.verified" size="xs"></VerificationBadge>
			</RouterLink>

			<div v-if="postContent" class="text-par-s leading-5 text-white/95 line-clamp-3 content-text" v-html="$mdInline(postContent)"></div>

			<div class="mt-3 inline-flex max-w-full items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-cap-l text-white/80 backdrop-blur">
				<SvgIcon name="music-note-01" type="line" classes="size-4 shrink-0"></SvgIcon>
				<span class="truncate">{{ postUser.name }}</span>
			</div>
		</div>

		<div class="absolute right-3 bottom-20 z-20 flex flex-col items-center gap-4">
			<button type="button" v-on:click.stop="openReactions" class="inline-flex flex-col items-center gap-1 text-white">
				<span v-bind:class="hasReacted ? 'text-red-900' : 'text-white'" class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center">
					<SvgIcon name="heart-rounded" type="line" classes="size-7"></SvgIcon>
				</span>
				<span class="text-cap-l font-semibold leading-none">{{ reactionCountLabel }}</span>
			</button>

			<button type="button" v-on:click.stop="openComments" class="inline-flex flex-col items-center gap-1 text-white">
				<span class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center">
					<SvgIcon name="message-circle-02" type="line" classes="size-7"></SvgIcon>
				</span>
				<span class="text-cap-l font-semibold leading-none">{{ postData.comments_count.formatted || 0 }}</span>
			</button>

			<button type="button" v-on:click.stop="sharePost" class="inline-flex flex-col items-center gap-1 text-white">
				<span class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center">
					<SvgIcon name="share-06" type="line" classes="size-7"></SvgIcon>
				</span>
				<span class="text-cap-l font-semibold leading-none">{{ postData.shares_count.formatted || 0 }}</span>
			</button>

			<button type="button" v-on:click.stop="bookmarkPost" class="inline-flex flex-col items-center gap-1 text-white">
				<span class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center">
					<SvgIcon v-bind:name="postData.meta.activity.bookmarked ? 'bookmark-minus' : 'bookmark'" type="line" classes="size-7"></SvgIcon>
				</span>
				<span class="text-cap-l font-semibold leading-none">{{ postData.bookmarks_count.formatted || 0 }}</span>
			</button>

			<button type="button" v-on:click.stop="state.mainMenu.open" class="size-11 rounded-full bg-black/30 backdrop-blur inline-flex-center text-white">
				<SvgIcon name="dots-horizontal" type="solid" classes="size-7"></SvgIcon>
			</button>
		</div>
	</section>

	<PublicationReactions
		v-if="state.reactionMenu.status"
		v-on:add="addReaction"
		v-on:close="state.reactionMenu.close"
	></PublicationReactions>

	<PublicationShare
		v-if="state.shareMenu.status"
		v-bind:postLink="postLink"
		v-on:close="state.shareMenu.close"
	></PublicationShare>

	<PublicationComments
		v-if="state.commentsMenu.status"
		v-bind:postData="postData"
		v-on:close="state.commentsMenu.close"
	></PublicationComments>

	<ActionSheet v-if="state.mainMenu.status" v-on:close="state.mainMenu.close" v-bind:isMuted="true">
		<div v-on:click.stop="state.mainMenu.close" class="h-full overflow-y-auto">
			<div class="mb-4">
				<ActionSheetGroup>
					<RouterLink v-bind:to="{ name: 'publication_index', params: { hash_id: postData.hash_id }}">
						<ActionSheetItem v-bind:notLast="true" iconName="arrow-up-right" v-bind:textLabel="$t('dd.post.open_post')"></ActionSheetItem>
					</RouterLink>
					<ActionSheetItem v-on:click="copyLink" iconName="copy-06" v-bind:textLabel="$t('dd.post.copy_link')"></ActionSheetItem>
					<ActionSheetItem v-if="postContent" v-on:click="copyContent" iconName="type-01" v-bind:textLabel="$t('dd.copy_text')"></ActionSheetItem>
				</ActionSheetGroup>
			</div>
			<ActionSheetGroup>
				<ActionSheetItem
					v-on:click="markNotInterested"
					v-bind:loading="state.isApplyingFeedback"
					iconName="minus-circle"
					iconType="solid"
					v-bind:textLabel="$t('dd.post.not_interested')"
				></ActionSheetItem>
				<ActionSheetItem
					v-on:click="hideReel"
					v-bind:loading="state.isApplyingFeedback"
					v-bind:notLast="canReportPost"
					iconName="eye-off"
					v-bind:textLabel="$t('dd.post.hide_reel')"
				></ActionSheetItem>
				<ActionSheetItem v-if="canReportPost" v-on:click="reportPost" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.post.report_post')"></ActionSheetItem>
			</ActionSheetGroup>
		</div>
	</ActionSheet>
</template>

<script>
	import { computed, defineAsyncComponent, defineComponent, reactive } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { applyOptimisticReaction } from '@/kernel/services/reactions/optimistic.js';
	import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';
	import { sendFeedFeedbackEvent } from '@/kernel/services/timeline-feedback/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
	import { useExploreReelsStore } from '@M/store/explore/reels.store.js';

	import ReelVideoPlayer from '@M/components/reels/ReelVideoPlayer.vue';
	import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';
	import VerificationBadge from '@/kernel/vue/components/general/badges/VerificationBadge.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import PublicationComments from '@M/components/timeline/feed/parts/comments/PublicationComments.vue';

	const defaultReactionId = '2764-fe0f';

	export default defineComponent({
		props: {
			postData: {
				type: Object,
				required: true
			},
			active: {
				type: Boolean,
				default: false
			},
			isNear: {
				type: Boolean,
				default: false
			},
			distanceFromActive: {
				type: Number,
				default: 0
			},
			position: {
				type: Number,
				default: 0
			},
			feedSessionId: {
				type: String,
				default: ''
			}
		},
		setup: function(props) {
			const state = reactive({
				shareMenu: useMenu(),
				commentsMenu: useMenu(),
				reactionMenu: useMenu(),
				mainMenu: useMenu(),
				sensitiveRevealed: false,
				showHeartBurst: false,
				isApplyingFeedback: false
			});
			const reelsStore = useExploreReelsStore();

			const postData = computed(() => {
				return props.postData;
			});

			const mediaItem = computed(() => {
				return postData.value.relations?.media?.[0] || {};
			});

			const postUser = computed(() => {
				return postData.value.relations?.user || {};
			});

			const postLink = computed(() => {
				return base_url(`publication/${postData.value.hash_id}`);
			});

			const postContent = computed(() => {
				return postData.value.content || '';
			});

			const canPlayImmediately = computed(() => {
				const item = mediaItem.value;

				if(! item?.id || MediaStatusUtils.isFailed(item.status)) {
					return false;
				}

				return MediaStatusUtils.isProcessed(item.status)
					|| (['r2_temp', 'r2_direct'].includes(item.metadata?.provider)
						&& item.metadata?.upload_state === 'uploaded'
						&& Boolean(item.preview_url));
			});

			const isPlaybackBlocked = computed(() => {
				return Boolean(postData.value.meta?.is_sensitive) && ! state.sensitiveRevealed;
			});

			const reactionCount = computed(() => {
				return (postData.value.relations?.reactions || []).reduce((total, reactionItem) => {
					return total + Number(reactionItem.total || 0);
				}, 0);
			});

			const hasReacted = computed(() => {
				return (postData.value.relations?.reactions || []).some((reactionItem) => {
					return Boolean(reactionItem.has_reacted);
				});
			});

			const hasHeartReaction = computed(() => {
				return (postData.value.relations?.reactions || []).some((reactionItem) => {
					return reactionItem.unified_id === defaultReactionId && Boolean(reactionItem.has_reacted);
				});
			});

			const formatCount = (count) => {
				if(count >= 1000000) {
					return `${Math.round((count / 1000000) * 10) / 10}M`;
				}

				if(count >= 1000) {
					return `${Math.round((count / 1000) * 10) / 10}K`;
				}

				return String(count || 0);
			};

			const animateHeart = () => {
				state.showHeartBurst = false;

				setTimeout(() => {
					state.showHeartBurst = true;

					setTimeout(() => {
						state.showHeartBurst = false;
					}, 620);
				}, 20);
			};

			const addReaction = (reactionId) => {
				state.reactionMenu.close();

				const rollbackReaction = applyOptimisticReaction(postData.value, reactionId);
				colibriEventBus.emit('timeline:post-updated', postData.value);

				colibriAPI().userTimeline().with({
					unified_id: reactionId,
					post_id: postData.value.id
				}).sendTo('post/reaction/add').then((response) => {
					postData.value.relations.reactions = response.data.data;
					colibriEventBus.emit('timeline:post-updated', postData.value);
				}).catch((error) => {
					rollbackReaction();
					colibriEventBus.emit('timeline:post-updated', postData.value);

					if(error.response) {
						toastError(error.response.data.message);
					}
				});
			};

			const ensureFeedContinuity = async () => {
				if(! reelsStore.posts.length) {
					await reelsStore.refreshFirstPage({
						refreshReason: 'feedback'
					}).catch(() => {});

					return;
				}

				if(reelsStore.posts.length <= Math.max(6, Number(props.position || 0) + 3)) {
					await reelsStore.loadNextPage().then((response) => {
						reelsStore.appendPosts(response.data.data);
					}).catch(() => {});
				}
			};

			const applyFeedbackAction = async (eventType, successMessage) => {
				if(state.isApplyingFeedback) {
					return;
				}

				state.mainMenu.close();
				state.isApplyingFeedback = true;

				const snapshot = reelsStore.applyFeedbackSuppression(postData.value.id, 'feedback');

				try {
					await sendFeedFeedbackEvent({
						eventType: eventType,
						postId: postData.value.id,
						sessionId: props.feedSessionId,
						feedType: 'reels',
						source: 'reels',
						position: props.position,
						refreshReason: 'feedback'
					});

					toastSuccess(successMessage);
					await ensureFeedContinuity();
				} catch (error) {
					reelsStore.rollbackFeedbackSuppression(snapshot);
					toastError(error.response?.data?.message || __t('labels.something_went_wrong'));
				} finally {
					state.isApplyingFeedback = false;
				}
			};

			return {
				state: state,
				postData: postData,
				mediaItem: mediaItem,
				postUser: postUser,
				postLink: postLink,
				postContent: postContent,
				canPlayImmediately: canPlayImmediately,
				isPlaybackBlocked: isPlaybackBlocked,
				hasReacted: hasReacted,
				reactionCountLabel: computed(() => {
					return formatCount(reactionCount.value);
				}),
				fallbackText: computed(() => {
					return __t('labels.video_processing');
				}),
				canReportPost: computed(() => {
					return postData.value.meta?.permissions?.can_report;
				}),
				revealSensitiveContent: () => {
					state.sensitiveRevealed = true;
				},
				openReactions: () => {
					state.reactionMenu.open();
				},
				openComments: () => {
					state.commentsMenu.open();
				},
				handleDoubleTap: () => {
					animateHeart();

					if(! hasHeartReaction.value) {
						addReaction(defaultReactionId);
					}
				},
				addReaction: addReaction,
				sharePost: () => {
					colibriAPI().userTimeline().with({
						id: postData.value.id
					}).sendTo('post/share/add').then((response) => {
						postData.value.shares_count = response.data.data.shares_count;
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});

					state.shareMenu.open();
				},
				bookmarkPost: () => {
					colibriAPI().userTimeline().with({
						id: postData.value.id
					}).sendTo('post/bookmarks/add').then((response) => {
						postData.value.meta.activity.bookmarked = response.data.data.bookmarked;

						if(response.data.data.bookmarked) {
							toastSuccess(__t('toast.post.bookmarked'));
						}
						else {
							toastError(__t('toast.post.unbookmarked'));
						}

						colibriEventBus.emit('timeline:post-updated', postData.value);
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});
				},
				copyLink: () => {
					try {
						navigator.clipboard.writeText(postLink.value).then(() => {
							toastSuccess(__t('toast.post.link_copied'));
						});
					} catch (error) {
						toastError(error);
					}
				},
				copyContent: () => {
					try {
						navigator.clipboard.writeText(postContent.value).then(() => {
							toastSuccess(__t('toast.post.content_copied'));
						});
					} catch (error) {
						toastError(error);
					}
				},
				reportPost: () => {
					colibriEventBus.emit('report:open', {
						type: 'post',
						reportableId: postData.value.id
					});
				},
				markNotInterested: () => {
					applyFeedbackAction('post_not_interested', __t('toast.post.show_fewer_reels'));
				},
				hideReel: () => {
					applyFeedbackAction('post_hide', __t('toast.post.reel_hidden'));
				}
			};
		},
		components: {
			ReelVideoPlayer: ReelVideoPlayer,
			SvgIcon: SvgIcon,
			VerificationBadge: VerificationBadge,
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			ActionSheetGroup: ActionSheetGroup,
			PublicationComments: PublicationComments,
			PublicationShare: defineAsyncComponent(() => {
				return import('@M/components/timeline/feed/parts/share/PublicationShare.vue');
			}),
			PublicationReactions: defineAsyncComponent(() => {
				return import('@M/components/timeline/feed/parts/reactions/PublicationReactions.vue');
			})
		}
	});
</script>

<style scoped>
	.reel-heart-burst {
		width: 6rem;
		height: 6rem;
		filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.35));
		animation: reel-heart-burst 620ms ease-out both;
	}

	@keyframes reel-heart-burst {
		0% {
			opacity: 0;
			transform: scale(0.55);
		}
		22% {
			opacity: 1;
			transform: scale(1.05);
		}
		100% {
			opacity: 0;
			transform: scale(1.45);
		}
	}
</style>
