<template>
    <div class="max-w-full">
        <swiper-container slides-per-view="auto" space-between="12" speed="200" mousewheel="true" grab-cursor="true" class="w-full">
            <swiper-slide v-if="userData" class="w-[74px] shrink-0">
                <div v-on:click="createStory" class="w-[74px] cursor-pointer">
                    <div class="size-[74px] relative p-1">
                        <div class="size-full rounded-full overflow-hidden">
                            <img class="size-full inline-block bg-fill-pr" v-bind:src="userData.avatar_url" alt="Image">
                        </div>
                        <div class="border-3 border-bg-pr rounded-full inline-flex-center text-bg-pr size-icon-normal bg-lab-pr2 absolute bottom-0.5 right-0.5 z-10">
                            <SvgIcon name="plus"></SvgIcon>
                        </div>
                    </div>
                    <div class="text-par-s font-medium text-lab-pr text-center whitespace-nowrap overflow-hidden text-ellipsis">
                        {{ $t('labels.new_story') }}
                    </div>
                </div>
            </swiper-slide>
            <template v-if="storiesFeed.length">
                <swiper-slide  v-for="storyData in storiesFeed" v-bind:key="storyData.story_uuid" class="w-[74px] shrink-0">
                    <RouterLink v-if="storyCanOpen(storyData)" v-bind:to="{ name: 'stories_index', params: { story_uuid: storyData.story_uuid } }">
                        <div class="size-[74px] rounded-full border-2 p-[3px] relative" v-bind:class="storyBorderClasses(storyData)">
                            <div v-if="! storyData.is_seen && ! storyData.is_owner && ! isStoryProcessing(storyData)" class="absolute inset-0">
                                <img v-bind:src="$asset('assets/avatars/story-avatar-ring.png')" alt="Image">
                            </div>
                            <div class="rounded-full size-full overflow-hidden">
                                <img class="size-full inline-block bg-fill-pr" v-bind:src="storyData.relations.user.avatar_url" alt="Image">
                            </div>
                            <div v-if="isStoryProcessing(storyData)" class="absolute inset-[3px] rounded-full bg-black/60 inline-flex-center flex-col">
                                <span class="text-white text-cap-l font-semibold leading-none">{{ storyProgress(storyData) }}%</span>
                                <span class="mt-0.5 max-w-[58px] truncate text-[9px] leading-none text-white/85">{{ storyStageLabel(storyData) }}</span>
                            </div>
                            <div v-if="isStoryProcessing(storyData)" class="absolute -bottom-1 left-1/2 -translate-x-1/2 h-1 w-12 rounded-full bg-fill-tr overflow-hidden border border-white/70">
                                <span class="block h-full bg-brand-900 transition-width ease-in-out" v-bind:style="{ width: `${storyProgress(storyData)}%` }"></span>
                            </div>
                        </div>
                        <div class="text-par-s font-medium text-lab-pr text-center whitespace-nowrap overflow-hidden text-ellipsis">
                            {{ storyLabel(storyData) }}
                        </div>
                    </RouterLink>
                    <div v-else class="block cursor-wait" role="status" aria-live="polite">
                        <div class="size-[74px] rounded-full border-2 p-[3px] relative" v-bind:class="storyBorderClasses(storyData)">
                            <div class="rounded-full size-full overflow-hidden">
                                <img class="size-full inline-block bg-fill-pr" v-bind:src="storyData.relations.user.avatar_url" alt="Image">
                            </div>
                            <div class="absolute inset-[3px] rounded-full bg-black/60 inline-flex-center flex-col">
                                <span class="text-white text-cap-l font-semibold leading-none">{{ storyProgress(storyData) }}%</span>
                                <span class="mt-0.5 max-w-[58px] truncate text-[9px] leading-none text-white/85">{{ storyStageLabel(storyData) }}</span>
                            </div>
                            <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 h-1 w-12 rounded-full bg-fill-tr overflow-hidden border border-white/70">
                                <span class="block h-full bg-brand-900 transition-width ease-in-out" v-bind:style="{ width: `${storyProgress(storyData)}%` }"></span>
                            </div>
                        </div>
                        <div class="text-par-s font-medium text-lab-pr text-center whitespace-nowrap overflow-hidden text-ellipsis">
                            {{ storyLabel(storyData) }}
                        </div>
                    </div>
                </swiper-slide>
            </template>
        </swiper-container>
    </div>
</template>

<script>
    import { computed, defineComponent, onMounted, onUnmounted, watch } from 'vue';
    import { useStoriesEditorStore } from '@D/store/stories/editor.store.js';
    import { useStoriesStore } from '@D/store/stories/stories.store.js';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { register  } from 'swiper/element/bundle';

    register();

    import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';

    export default defineComponent({
        setup: function() {
            const storiesEditorStore = useStoriesEditorStore();
            const storiesStore = useStoriesStore();
            const authStore = useAuthStore();
            let processingPollTimer = null;

            const userData = computed(() => {
                return authStore.userData;
            });

            const refreshStoriesFeed = async () => {
                try {
                    await storiesStore.fetchStoriesFeed();
                } catch (error) {
                    /* Pass */
                }
            };

            const stopProcessingPoll = () => {
                if(processingPollTimer) {
                    clearInterval(processingPollTimer);
                    processingPollTimer = null;
                }
            };

            const startProcessingPoll = () => {
                if(processingPollTimer) {
                    return;
                }

                processingPollTimer = setInterval(async () => {
                    await refreshStoriesFeed();

                    if(! storiesStore.hasProcessingStories) {
                        stopProcessingPoll();
                    }
                }, 3000);
            };

            onMounted(async () => {
                await refreshStoriesFeed();

                if(storiesStore.hasProcessingStories) {
                    startProcessingPoll();
                }
            });

            onUnmounted(stopProcessingPoll);

            watch(() => {
                return storiesStore.hasProcessingStories;
            }, (hasProcessingStories) => {
                if(hasProcessingStories) {
                    startProcessingPoll();
                }
                else {
                    stopProcessingPoll();
                }
            });

            const storiesFeed = computed(() => {
                return storiesStore.storiesFeed;
            });

            const isStoryProcessing = (storyData) => {
                return storyData.status === 'processing';
            };

            const storyProgress = (storyData) => {
                return Math.max(1, Math.min(100, Number(storyData.progress?.display || storyData.progress?.overall || storyData.progress?.processing || 1)));
            };

            const storyStageLabel = (storyData) => {
                switch(storyData.progress?.stage) {
                    case 'uploading':
                        return __t('labels.uploading');
                    case 'uploaded':
                        return __t('labels.story_uploaded_waiting');
                    case 'finishing':
                        return __t('labels.story_finishing_video');
                    case 'failed':
                        return __t('labels.story_failed_upload');
                    case 'ready':
                        return __t('labels.story_ready_soon');
                    default:
                        return __t('labels.story_processing_video');
                }
            };

            return {
                storiesFeed: storiesFeed,
                userData: userData,
                isStoryProcessing: isStoryProcessing,
                storyProgress: storyProgress,
                storyStageLabel: storyStageLabel,
                storyCanOpen: (storyData) => {
                    return storyData.can_open !== false;
                },
                storyBorderClasses: (storyData) => {
                    if(isStoryProcessing(storyData)) {
                        return 'border-brand-900';
                    }

                    return (storyData.is_owner || storyData.is_seen) ? 'border-bord-card' : '';
                },
                storyLabel: (storyData) => {
                    if(isStoryProcessing(storyData)) {
                        const stageLabel = storyStageLabel(storyData);

                        if(['uploaded', 'failed', 'ready'].includes(storyData.progress?.stage)) {
                            return stageLabel;
                        }

                        return `${stageLabel} ${storyProgress(storyData)}%`;
                    }

                    return storyData.relations.user.name;
                },
                createStory: () => {
                    storiesEditorStore.openEditor();
                }
            };
        },
        components: {
            AvatarMedium: AvatarMedium
        }
    });
</script>
