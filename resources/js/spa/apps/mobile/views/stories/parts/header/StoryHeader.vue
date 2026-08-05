<template>
	<div class="flex items-center gap-1">
		<PrimaryIconButton v-on:click="leaveStories" iconName="chevron-left" iconSize="7" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
		<button
			type="button"
			v-on:click.stop="openAuthorProfile"
			class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden rounded-xl text-left transition-opacity active:opacity-80"
		>
			<div class="shrink-0">
				<AvatarSmall v-bind:avatarSrc="storyAuthor.avatar_url"></AvatarSmall>
			</div>
			<div class="flex-1 overflow-hidden leading-none truncate">
				<span class="text-par-n font-medium text-white mb-0.5">
					{{ storyAuthor.name }} <VerificationBadge v-if="storyAuthor.verified" size="xs"></VerificationBadge>
				</span>
				<span class="text-cap-l text-white ml-1 opacity-80">
					{{ playerState.frameData.date.time_ago ?? '' }}
				</span>
			</div>
		</button>
		<div class="inline-flex gap-1 items-center">
			<PrimaryIconButton v-if="playerState.isPaused && ! isFrameProcessing" v-on:click="play" iconName="play" iconSize="5" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
			<template v-if="isVideo && ! isFrameProcessing">
				<PrimaryIconButton v-if="state.isMuted" v-on:click="toggleVideosVolume" iconName="volume-x" iconSize="5" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
				<PrimaryIconButton v-else v-on:click="toggleVideosVolume" iconName="volume-max" iconSize="5" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
			</template>
			<PrimaryIconButton v-on:click.stop="openMenu" iconName="dots-horizontal" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
		</div>
	</div>

	<Teleport to="body">
		<ActionSheet v-if="state.mainMenu.status" v-bind:isMuted="true" v-on:close="state.mainMenu.close">
			<div v-on:click.stop="state.mainMenu.close">
				<ActionSheetGroup>
					<ActionSheetItem v-if="canDelete" v-on:click="deleteStory" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.story.delete_story')"></ActionSheetItem>
					<ActionSheetItem v-if="canSeeViews" v-on:click="showViews" iconName="eye" v-bind:textLabel="$t('dd.story.show_views')"></ActionSheetItem>
					<ActionSheetItem v-if="canHide" v-on:click="$comingSoon" iconName="eye-off" v-bind:textLabel="$t('dd.story.hide_stories', { name: storyAuthor.name })"></ActionSheetItem>
					<ActionSheetItem v-if="canReport" v-on:click="$comingSoon" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.story.report_story')"></ActionSheetItem>
				</ActionSheetGroup>
			</div>
		</ActionSheet>
	</Teleport>
</template>

<script>
	import { defineComponent, computed, reactive, onMounted, inject } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import AvatarSmall from '@M/components/general/avatars/AvatarSmall.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';

	export default defineComponent({
		emits: ['pause', 'play'],
		setup: function(props, context) {
			const state = reactive({
				isMuted: false,
				mainMenu: useMenu()
			});

			const playerState = inject('playerState');
			const router = useRouter();
			const storyAuthor = computed(() => {
				return playerState.storyAuthor;
			});
			const authorProfileRoute = computed(() => {
				if(! storyAuthor.value?.username) {
					return null;
				}

				return {
					name: 'profile_index',
					params: {
						id: storyAuthor.value.username
					}
				};
			});

			onMounted(() => {
				if (localStorage.getItem('stories_videos_muted')) {
					state.isMuted = true;       
				}
			});

			const pause = () => {
				context.emit('pause');
			};

			const play = () => {
				context.emit('play');
			}

			return {
				state: state,
				playerState: playerState,
				storyAuthor: storyAuthor,
				authorProfileRoute: authorProfileRoute,
				isVideo: computed(() => {
                    if (playerState.frameData.type == 'video') {
                        return true;
                    }

                    return false;
                }),
				isFrameProcessing: computed(() => {
					return playerState.frameData?.status === 'processing' || playerState.frameData?.media?.status === 'processing';
				}),
				pause: pause,
				play: play,
				toggleVideosVolume: () => {
                    if (localStorage.getItem('stories_videos_muted')) {
                        localStorage.removeItem('stories_videos_muted');
                        state.isMuted = false;

						colibriEventBus.emit('story:unmute');
                    }
                    else{
                        localStorage.setItem('stories_videos_muted', 1);
                        state.isMuted = true;

						colibriEventBus.emit('story:mute');
                    }
                },
				deleteStory: () => {
					colibriEventBus.emit('story:delete', playerState.frameData.id);
				},
				showViews: () => {
                    colibriEventBus.emit('story:show-views');
                },
				openMenu: () => {
                    state.mainMenu.open();

					pause();
                },
				canReport: computed(() => {
					return playerState.permissions.can_report;
				}),
				canHide: computed(() => {
					return playerState.permissions.can_hide;
				}),
				canDelete: computed(() => {
					return playerState.permissions.can_delete;
				}),
				canSeeViews: computed(() => {
					return playerState.isOwner;
				}),
				openAuthorProfile: () => {
					if(! storyAuthor.value?.username) {
						return;
					}

					pause();
					state.mainMenu.close();
					router.push(authorProfileRoute.value);
				},
				leaveStories: () => {
					colibriEventBus.emit('story:leave');
				}
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
            AvatarSmall: AvatarSmall,
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			ActionSheetGroup: ActionSheetGroup
		}
	});
</script>
