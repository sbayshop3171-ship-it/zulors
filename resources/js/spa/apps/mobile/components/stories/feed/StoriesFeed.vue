<template>
    <div class="max-w-full">
        <swiper-container slides-per-view="auto" space-between="12" speed="200" mousewheel="true" grab-cursor="true" class="w-full">
            <swiper-slide v-if="userData" class="w-[80px] shrink-0">
                <div v-on:click="createStory" class="block w-[80px] cursor-pointer shrink-0">
                    <div class="relative mb-1 w-[80px] p-1">
                        <div class="rounded-full border-3 border-bg-pr overflow-hidden inline-flex-center size-6 bg-lab-pr2 absolute bottom-1 -right-0.5 z-10">
                            <SvgIcon name="plus" classes="text-bg-pr size-4"></SvgIcon>
                        </div>
                        <div class="rounded-full overflow-hidden">
                            <img class="size-full" v-bind:src="userData.avatar_url" alt="Avatar">
                        </div>
                    </div>
                    <div class="text-par-s text-lab-pr text-center whitespace-nowrap overflow-hidden text-ellipsis">
                        {{ $t('labels.new_story') }}
                    </div>
                </div>
            </swiper-slide>

            <template v-if="storiesFeed.length">
                <swiper-slide  v-for="storyData in storiesFeed" v-bind:key="storyData.story_uuid" class="w-[80px] shrink-0">
                    <RouterLink v-bind:to="{ name: 'stories_index', params: { story_uuid: storyData.story_uuid } }">
                        <div class="shrink-0 w-[80px]">
                            <div class="size-[80px] rounded-full border-2 p-[3px]" v-bind:class="[(storyData.is_owner || storyData.is_seen) ? 'border-bord-card' : '']">
                                <div v-if="! storyData.is_seen && ! storyData.is_owner" class="absolute inset-0">
                                    <img v-bind:src="$asset('assets/avatars/story-avatar-ring.png')" alt="Image">
                                </div>
                                <div class="suze-full rounded-full overflow-hidden">
                                    <img class="size-full object-cover inline-block bg-fill-pr" v-bind:src="storyData.relations.user.avatar_url" alt="Image">
                                </div>
                            </div>
                            <div class="text-par-s text-lab-pr text-center whitespace-nowrap overflow-hidden text-ellipsis">
                                {{ storyData.relations.user.name }}
                            </div>
                        </div>
                    </RouterLink>
                </swiper-slide>
            </template>
        </swiper-container>
    </div>
</template>

<script>
    import { computed, defineComponent, onMounted } from 'vue';
    import { useStoriesStore } from '@M/store/stories/stories.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { useAuthStore } from '@M/store/auth/auth.store.js';
    import { register  } from 'swiper/element/bundle';

    register();

    import AvatarMedium from '@M/components/general/avatars/AvatarMedium.vue';

    export default defineComponent({
        setup: function() {
            const storiesStore = useStoriesStore();
            const authStore = useAuthStore();

            const userData = computed(() => {
                return authStore.userData;
            });

            onMounted(async () => {
                try {
                    await storiesStore.fetchStoriesFeed();
                } catch (error) {
                    /* Pass */
                }
            });

            const storiesFeed = computed(() => {
                return storiesStore.storiesFeed;
            });

            return {
                storiesFeed: storiesFeed,
                userData: userData,
                createStory: () => {
                    colibriEventBus.emit('story:create');
                }
            };
        },
        components: {
            AvatarMedium: AvatarMedium
        }
    });
</script>
