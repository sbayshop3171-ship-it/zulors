<template>
	<section
		class="reel-desktop-slide"
		v-bind:class="slideOrientationClass"
	>
		<div class="reel-desktop-grid">
			<div class="reel-info-desktop">
				<RouterLink v-bind:to="{ name: 'profile_index', params: { id: postUser.username } }" class="inline-flex max-w-full items-center gap-2.5">
					<img v-bind:src="postUser.avatar_url" v-bind:alt="postUser.username" class="size-10 rounded-full border border-white/20 object-cover">
					<div class="min-w-0 leading-4">
						<div class="flex min-w-0 items-center gap-1">
							<span class="truncate text-par-m font-bold text-white">
								{{ postUser.username }}
							</span>
							<VerificationBadge v-if="postUser.verified" size="xs"></VerificationBadge>
						</div>
						<p v-if="postUserCaption" class="mt-0.5 truncate text-cap-l text-white/50">
							{{ postUserCaption }}
						</p>
					</div>
				</RouterLink>

				<div v-if="postContent" class="mt-4 max-h-36 overflow-hidden text-par-s leading-5 text-white/90 content-text" v-html="$mdInline(postContent)"></div>

				<div class="mt-4 flex max-w-full items-center gap-2 text-cap-l text-white/55">
					<SvgIcon name="music-note-01" type="line" classes="size-4 shrink-0"></SvgIcon>
					<span class="truncate">{{ reelAudioLabel }}</span>
				</div>
			</div>

			<div
				class="reel-stage"
				v-bind:class="stageOrientationClass"
				v-bind:style="stageStyle"
			>
				<ReelVideoPlayer
					v-if="canPlayImmediately"
					v-bind:mediaItem="mediaItem"
					v-bind:postData="postData"
					v-bind:active="active"
					v-bind:isNear="isNear"
					v-bind:blocked="isPlaybackBlocked"
					v-bind:feedSessionId="feedSessionId"
					v-bind:position="position"
					v-on:double-tap="handleDoubleTap"
					v-on:presentation-metadata="handlePresentationMetadata"
				></ReelVideoPlayer>

				<div v-else class="absolute inset-0 bg-black inline-flex-center">
					<div class="max-w-64 px-6 text-center">
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
					class="absolute inset-0 z-20 bg-black/75 backdrop-blur-xl flex flex-col px-10 py-10 text-white"
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

				<div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 hidden h-1/2 bg-gradient-to-t from-black/85 via-black/35 to-transparent reel-info-gradient"></div>

				<div class="reel-info-mobile absolute left-5 right-5 bottom-7 z-20">
					<RouterLink v-bind:to="{ name: 'profile_index', params: { id: postUser.username } }" class="inline-flex max-w-full items-center gap-2 mb-3">
						<img v-bind:src="postUser.avatar_url" v-bind:alt="postUser.username" class="size-9 rounded-full border border-white/30 object-cover">
						<span class="min-w-0 truncate text-par-m font-bold text-white">
							{{ postUser.username }}
						</span>
						<VerificationBadge v-if="postUser.verified" size="xs"></VerificationBadge>
					</RouterLink>

					<div v-if="postContent" class="text-par-s leading-5 text-white/95 line-clamp-3 content-text" v-html="$mdInline(postContent)"></div>

					<div class="mt-3 inline-flex max-w-full items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-cap-l text-white/80 backdrop-blur">
						<SvgIcon name="music-note-01" type="line" classes="size-4 shrink-0"></SvgIcon>
						<span class="truncate">{{ reelAudioLabel }}</span>
					</div>
				</div>
			</div>

			<div class="reel-actions">
				<button type="button" v-on:click.stop="toggleDefaultReaction" class="reel-action-button">
					<SvgIcon name="heart-rounded" type="line" v-bind:classes="hasReacted ? 'size-8 text-red-900' : 'size-8 text-white'"></SvgIcon>
					<span>{{ reactionCountLabel }}</span>
				</button>

				<RouterLink v-bind:to="{ name: 'publication_index', params: { hash_id: postData.hash_id }}" class="reel-action-button">
					<SvgIcon name="message-circle-02" type="line" classes="size-8 text-white"></SvgIcon>
					<span>{{ postData.comments_count.formatted || 0 }}</span>
				</RouterLink>

				<div class="relative">
					<button type="button" v-on:click.stop="sharePost" class="reel-action-button">
						<SvgIcon name="share-06" type="line" classes="size-8 text-white"></SvgIcon>
						<span>{{ postData.shares_count.formatted || 0 }}</span>
					</button>

					<div v-if="state.isShareOpen" v-outside-click="closeShare" class="absolute bottom-0 left-16 z-40">
						<PublicationShare v-bind:postLink="postLink"></PublicationShare>
					</div>
				</div>

				<button type="button" v-on:click.stop="bookmarkPost" class="reel-action-button">
					<SvgIcon v-bind:name="postData.meta.activity.bookmarked ? 'bookmark-minus' : 'bookmark'" type="line" classes="size-8 text-white"></SvgIcon>
					<span>{{ postData.bookmarks_count.formatted || 0 }}</span>
				</button>

				<div class="relative">
					<button type="button" v-on:click.stop="toggleMenu" class="reel-action-button">
						<SvgIcon name="dots-horizontal" type="solid" classes="size-8 text-white"></SvgIcon>
					</button>

					<div v-if="state.isMenuOpen" v-outside-click="closeMenu" class="absolute bottom-0 left-16 z-40 w-56 overflow-hidden rounded-lg border border-white/10 bg-[#111417]/95 py-1 text-white shadow-xl backdrop-blur">
						<RouterLink v-bind:to="{ name: 'publication_index', params: { hash_id: postData.hash_id }}" class="flex items-center gap-2 px-4 py-3 text-par-s hover:bg-white/10">
							<SvgIcon name="arrow-up-right" type="line" classes="size-4"></SvgIcon>
							{{ $t('dd.post.open_post') }}
						</RouterLink>
						<button type="button" v-on:click="copyLink" class="flex w-full items-center gap-2 px-4 py-3 text-left text-par-s hover:bg-white/10">
							<SvgIcon name="copy-06" type="line" classes="size-4"></SvgIcon>
							{{ $t('dd.post.copy_link') }}
						</button>
						<button v-if="postContent" type="button" v-on:click="copyContent" class="flex w-full items-center gap-2 px-4 py-3 text-left text-par-s hover:bg-white/10">
							<SvgIcon name="type-01" type="line" classes="size-4"></SvgIcon>
							{{ $t('dd.copy_text') }}
						</button>
						<button v-if="canReportPost" type="button" v-on:click="reportPost" class="flex w-full items-center gap-2 px-4 py-3 text-left text-par-s text-red-900 hover:bg-white/10">
							<SvgIcon name="annotation-alert" type="line" classes="size-4"></SvgIcon>
							{{ $t('dd.post.report_post') }}
						</button>
					</div>
				</div>

				<img v-bind:src="postUser.avatar_url" v-bind:alt="postUser.username" class="mt-1 size-8 rounded-md border border-white/20 object-cover">
			</div>
		</div>
	</section>
</template>

<script>
	import { computed, defineComponent, defineAsyncComponent, onMounted, onUnmounted, reactive } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { applyOptimisticReaction } from '@/kernel/services/reactions/optimistic.js';
	import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';
	import { normalizeVideoDimensions } from '@/kernel/services/media/video-metadata.js';

	import ReelVideoPlayer from '@D/components/reels/ReelVideoPlayer.vue';
	import SvgIcon from '@/kernel/vue/components/icons/SvgIcon.vue';
	import VerificationBadge from '@/kernel/vue/components/general/badges/VerificationBadge.vue';

	const defaultReactionId = '2764-fe0f';
	const minReelAspectRatio = 9 / 16;
	const maxReelAspectRatio = 16 / 9;

	const clampAspectRatio = (ratio) => {
		const numericRatio = Number(ratio || 0);

		if(! Number.isFinite(numericRatio) || numericRatio <= 0) {
			return minReelAspectRatio;
		}

		return Math.max(minReelAspectRatio, Math.min(maxReelAspectRatio, numericRatio));
	};

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
				sensitiveRevealed: false,
				showHeartBurst: false,
				isShareOpen: false,
				isMenuOpen: false,
				presentationMetadata: {},
				viewportWidth: typeof window !== 'undefined' ? window.innerWidth : 1440,
				viewportHeight: typeof window !== 'undefined' ? window.innerHeight : 900
			});

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

			const postUserCaption = computed(() => {
				return postUser.value.caption || postData.value.date?.time_ago || '';
			});

			const reelAudioLabel = computed(() => {
				const timeLabel = postData.value.date?.time_ago;
				const authorLabel = postUser.value.name || postUser.value.username || __t('labels.reels');

				return timeLabel ? `${authorLabel} · ${timeLabel}` : authorLabel;
			});

			const mediaPresentationMetadata = computed(() => {
				return {
					...(mediaItem.value.metadata || {}),
					...(state.presentationMetadata || {})
				};
			});

			const mediaAspectRatio = computed(() => {
				const metadata = mediaPresentationMetadata.value;
				const dimensions = normalizeVideoDimensions(metadata);

				if(dimensions?.aspect_ratio) {
					return clampAspectRatio(dimensions.aspect_ratio);
				}

				return metadata?.is_portrait === false ? maxReelAspectRatio : minReelAspectRatio;
			});

			const stageOrientation = computed(() => {
				const ratio = mediaAspectRatio.value;

				if(ratio > 1.2) {
					return 'landscape';
				}

				if(ratio >= 0.85) {
					return 'square';
				}

				return 'portrait';
			});

			const stageStyle = computed(() => {
				const ratio = mediaAspectRatio.value;
				const viewportWidth = Math.max(320, Number(state.viewportWidth || 1440));
				const viewportHeight = Math.max(420, Number(state.viewportHeight || 900));
				const maxHeight = Math.max(280, Math.min(viewportHeight - 40, 900));
				const orientation = stageOrientation.value;

				let reserveWidth = 560;
				let preferredWidth = 560;

				if(orientation === 'landscape') {
					reserveWidth = 320;
					preferredWidth = 1188;
				}
				else if(orientation === 'square') {
					reserveWidth = 460;
					preferredWidth = 760;
				}

				if(viewportWidth < 1180) {
					reserveWidth = 156;
					preferredWidth = orientation === 'landscape' ? 920 : 640;
				}

				if(viewportWidth < 900) {
					reserveWidth = 132;
					preferredWidth = orientation === 'landscape' ? 760 : 560;
				}

				const maxWidth = Math.max(240, Math.min(preferredWidth, viewportWidth - reserveWidth));
				let frameWidth = Math.min(maxWidth, maxHeight * ratio);
				let frameHeight = frameWidth / ratio;

				if(frameHeight > maxHeight) {
					frameHeight = maxHeight;
					frameWidth = frameHeight * ratio;
				}

				return {
					width: `${Math.round(frameWidth)}px`,
					height: `${Math.round(frameHeight)}px`,
					aspectRatio: String(ratio)
				};
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

			const refreshViewportSize = () => {
				state.viewportWidth = window.innerWidth;
				state.viewportHeight = window.innerHeight;
			};

			onMounted(() => {
				refreshViewportSize();
				window.addEventListener('resize', refreshViewportSize);
			});

			onUnmounted(() => {
				window.removeEventListener('resize', refreshViewportSize);
			});

			return {
				state: state,
				postData: postData,
				mediaItem: mediaItem,
				postUser: postUser,
				postLink: postLink,
				postContent: postContent,
				postUserCaption: postUserCaption,
				reelAudioLabel: reelAudioLabel,
				stageStyle: stageStyle,
				stageOrientationClass: computed(() => {
					return `reel-stage--${stageOrientation.value}`;
				}),
				slideOrientationClass: computed(() => {
					return `reel-desktop-slide--${stageOrientation.value}`;
				}),
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
				handleDoubleTap: () => {
					animateHeart();

					if(! hasHeartReaction.value) {
						addReaction(defaultReactionId);
					}
				},
				handlePresentationMetadata: (metadata) => {
					state.presentationMetadata = metadata || {};
				},
				toggleDefaultReaction: () => {
					addReaction(defaultReactionId);
				},
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

					state.isShareOpen = true;
				},
				closeShare: () => {
					state.isShareOpen = false;
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
							toastSuccess(__t('toast.post.unbookmarked'));
						}

						colibriEventBus.emit('timeline:post-updated', postData.value);
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});
				},
				toggleMenu: () => {
					state.isMenuOpen = ! state.isMenuOpen;
				},
				closeMenu: () => {
					state.isMenuOpen = false;
				},
				copyLink: () => {
					state.isMenuOpen = false;
					navigator.clipboard.writeText(postLink.value).then(() => {
						toastSuccess(__t('toast.post.link_copied'));
					});
				},
				copyContent: () => {
					state.isMenuOpen = false;
					navigator.clipboard.writeText(postContent.value).then(() => {
						toastSuccess(__t('toast.post.content_copied'));
					});
				},
				reportPost: () => {
					state.isMenuOpen = false;
					colibriEventBus.emit('report:open', {
						type: 'post',
						reportableId: postData.value.id
					});
				}
			};
		},
		components: {
			ReelVideoPlayer: ReelVideoPlayer,
			SvgIcon: SvgIcon,
			VerificationBadge: VerificationBadge,
			PublicationShare: defineAsyncComponent(() => {
				return import('@D/components/timeline/feed/parts/share/PublicationShare.vue');
			})
		}
	});
</script>

<style scoped>
	.reel-desktop-slide {
		position: relative;
		display: flex;
		min-height: 100dvh;
		scroll-snap-align: start;
		scroll-snap-stop: always;
		align-items: center;
		justify-content: center;
		overflow: hidden;
		padding: 20px 96px 20px 48px;
		color: white;
	}

	.reel-desktop-grid {
		display: grid;
		grid-template-columns: minmax(220px, 300px) minmax(0, auto) 84px;
		align-items: center;
		justify-content: center;
		column-gap: 20px;
		width: min(1260px, calc(100vw - 176px));
		min-height: min(calc(100dvh - 40px), 900px);
	}

	.reel-info-desktop {
		align-self: end;
		min-width: 0;
		padding-bottom: 8px;
	}

	.reel-stage {
		position: relative;
		justify-self: center;
		max-width: 100%;
		max-height: min(calc(100dvh - 40px), 900px);
		overflow: hidden;
		border: 1px solid rgba(255, 255, 255, 0.2);
		border-radius: 4px;
		background: #000;
		box-shadow: 0 24px 70px rgba(0, 0, 0, 0.42);
	}

	.reel-actions {
		position: relative;
		z-index: 20;
		align-self: center;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 18px;
		padding-bottom: 8px;
	}

	.reel-action-button {
		display: inline-flex;
		min-width: 56px;
		flex-direction: column;
		align-items: center;
		gap: 4px;
		color: white;
		font-size: 12px;
		font-weight: 700;
		line-height: 1;
		text-align: center;
		text-shadow: 0 1px 12px rgba(0, 0, 0, 0.6);
		transition: opacity 150ms ease, transform 150ms ease;
	}

	.reel-action-button:hover {
		opacity: 0.82;
		transform: translateY(-1px);
	}

	.reel-info-mobile {
		display: none;
	}

	.reel-desktop-slide--landscape .reel-desktop-grid {
		grid-template-columns: minmax(0, auto) 84px;
		width: min(1320px, calc(100vw - 176px));
	}

	.reel-desktop-slide--landscape .reel-info-desktop {
		display: none;
	}

	.reel-desktop-slide--landscape .reel-info-mobile,
	.reel-desktop-slide--landscape .reel-info-gradient {
		display: block;
	}

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

	@media (max-width: 1180px) {
		.reel-desktop-grid {
			grid-template-columns: minmax(0, auto) 78px;
			width: min(720px, calc(100vw - 156px));
		}

		.reel-info-desktop {
			display: none;
		}

		.reel-info-mobile,
		.reel-info-gradient {
			display: block;
		}
	}

	@media (max-width: 900px) {
		.reel-desktop-slide {
			padding-inline: 64px;
		}

		.reel-desktop-grid {
			grid-template-columns: minmax(0, 1fr) 68px;
			width: 100%;
			column-gap: 16px;
		}

		.reel-stage {
			max-height: calc(100dvh - 44px);
		}
	}
</style>
