<template>
	<div class="flex justify-between items-center">
		<div class="flex gap-2 items-center overflow-hidden">
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
		</div>
		<div class="ml-4 inline-flex gap-1 items-center">
			<PrimaryIconButton v-if="playerState.isPaused" v-on:click="play" iconName="play" iconSize="5" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
			<PrimaryIconButton v-else v-on:click="pause" iconName="pause" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>

			<template v-if="isVideo">
				<PrimaryIconButton v-if="state.isMuted" v-on:click="toggleVideosVolume" iconName="volume-x" iconSize="5" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
				<PrimaryIconButton v-else v-on:click="toggleVideosVolume" iconName="volume-max" iconSize="5" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
			</template>
			<div class="relative">
				<PrimaryIconButton v-on:click.stop="toggleMenu" iconName="dots-horizontal" buttonColor="text-gray-300" hoverText="text-white" hoverBg="hover:bg-white/20"></PrimaryIconButton>
				<div class="absolute top-full right-0 z-50 leading-none" v-if="state.isMenuOpen">
					<DropdownMenu v-outside-click="toggleMenu" v-on:click="toggleMenu">
						<DropdownMenuItem v-if="canDelete" v-on:click="deleteStory" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.story.delete_story')"></DropdownMenuItem>
						<DropdownMenuItem v-if="canSeeViews" v-on:click="showViews" iconName="eye" v-bind:textLabel="$t('dd.story.show_views')"></DropdownMenuItem>
						<DropdownMenuItem v-if="canHide" v-on:click="$comingSoon" iconName="eye-off" v-bind:textLabel="$t('dd.story.hide_stories', { name: storyAuthor.name })"></DropdownMenuItem>
						<DropdownMenuItem v-if="canReport" v-on:click="$comingSoon" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.story.report_story')"></DropdownMenuItem>
					</DropdownMenu>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, reactive, onMounted, inject } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';

	export default defineComponent({
		emits: ['play', 'pause'],
		setup: function(props, context) {
			const state = reactive({
				isMenuOpen: false,
				isMuted: false
			});

			const playerState = inject('playerState');

			onMounted(() => {
				if (localStorage.getItem('stories_videos_muted')) {
					state.isMuted = true;       
				}
			});

			const play = () => {
				context.emit('play');
			}

			const pause = () => {
				context.emit('pause');
			};

			return {
				state: state,
				playerState: playerState,
				storyAuthor: computed(() => {
                    return playerState.storyAuthor;
                }),
				isVideo: computed(() => {
                    if (playerState.frameData.type == 'video') {
                        return true;
                    }

                    return false;
                }),
				play: play,
				pause: pause,
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
				toggleMenu: () => {
                    state.isMenuOpen = !state.isMenuOpen;

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
				})
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
            AvatarSmall: AvatarSmall,
			DropdownMenu: DropdownMenu,
			DropdownMenuItem: DropdownMenuItem
		}
	});
</script>