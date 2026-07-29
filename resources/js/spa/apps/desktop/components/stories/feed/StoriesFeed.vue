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
                    <RouterLink v-bind:to="{ name: 'stories_index', params: { story_uuid: storyData.story_uuid } }">
                        <div class="size-[74px] rounded-full border-2 p-[3px]" v-bind:class="[(storyData.is_owner || storyData.is_seen) ? 'border-bord-card' : '']">
                            <div v-if="! storyData.is_seen && ! storyData.is_owner" class="absolute inset-0">
                                <img v-bind:src="$asset('assets/avatars/story-avatar-ring.png')" alt="Image">
                            </div>
                            <div class="rounded-full size-full overflow-hidden">
                                <img class="size-full inline-block bg-fill-pr" v-bind:src="storyData.relations.user.avatar_url" alt="Image">
                            </div>
                        </div>
                        <div class="text-par-s font-medium text-lab-pr text-center whitespace-nowrap overflow-hidden text-ellipsis">
                            {{ storyData.relations.user.name }}
                        </div>
                    </RouterLink>
                </swiper-slide>
            </template>
        </swiper-container>
    </div>
</template>

<script>
    import { computed, defineComponent, onMounted } from 'vue';
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
                    storiesEditorStore.openEditor();
                }
            };
        },
        components: {
            AvatarMedium: AvatarMedium
        }
    });
</script>
