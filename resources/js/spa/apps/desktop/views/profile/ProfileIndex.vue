<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <div class="block">
                    <div v-if="state.isLoading" class="block">
                        <HeaderSkeleton></HeaderSkeleton>
                    </div>
                    <div v-else class="block">
                        <div class="block">
                            <ProfileCover></ProfileCover>
                        </div>
                        <div class="p-4 pb-2">
                            <div class="flex justify-between items-start mb-2">
                                <div class="-mt-[78px]">
                                    <ProfileAvatar></ProfileAvatar>
                                </div>
                                <div class="shrink-0">
                                    <ProfileControls></ProfileControls>
                                </div>
                            </div>
                            <ProfileName></ProfileName>
                            <div class="my-3 empty:hidden">
                                <ProfileOverview></ProfileOverview>
                            </div>
                            <div class="block">
                                <ProfileMetrics></ProfileMetrics>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="! state.isLoading" class="block">
                    <ContentTabs>
                        <TabsLink v-bind:link="{ name: 'profile_posts' }">
                            {{ $t('labels.posts') }}
                        </TabsLink>
                        <TabsLink v-bind:link="{ name: 'profile_media' }">
                            {{ $t('labels.media') }}
                        </TabsLink>
                        <TabsLink v-bind:link="{ name: 'profile_info' }">
                            {{ $t('labels.info') }}
                        </TabsLink>
                    </ContentTabs>
                </div>
                <div class="block border-t border-t-bord-pr">
                    <template v-if="! state.isLoading">
                        <RouterView></RouterView>
                    </template>
                    <template v-else>
                        <TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
                    </template>
                </div>

                <div v-if="! state.isLoading">
                    <div class="px-4 py-4 border-t border-t-bord-pr leading-zero">
                        <span class="text-cap-s text-lab-sc font-medium">{{ profileData.name }} @{{ profileData.username }}</span>
                    </div>
                </div>
            </TimelineContainer>
        </template>

        <template v-slot:sidebar>
            <FollowRecommendationList></FollowRecommendationList>
            <AdGridItem></AdGridItem>
        </template>
    </SidedContentLayout>

    <ScrollTopButton></ScrollTopButton>
</template>

<script>
    import { defineComponent, ref, reactive, provide, onMounted, watch} from 'vue';
    import { useRoute, useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';

    import ContentTabs from '@D/components/general/tabs/content/ContentTabs.vue';
    import TabsLink from '@D/components/general/tabs/content/parts/TabsLink.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';

    import TimelinePublicationSkeleton from '@D/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import HeaderSkeleton from '@D/views/profile/parts/skeletons/HeaderSkeleton.vue';
    import ProfileAvatar from '@D/views/profile/parts/ProfileAvatar.vue';
    import ProfileControls from '@D/views/profile/parts/controls/ProfileControls.vue';
    import ProfileName from '@D/views/profile/parts/ProfileName.vue';
    import ProfileCover from '@D/views/profile/parts/ProfileCover.vue';
    import ProfileMetrics from '@D/views/profile/parts/ProfileMetrics.vue';
    import ProfileOverview from '@D/views/profile/parts/ProfileOverview.vue';
    import ScrollTopButton from '@D/components/inter-ui/buttons/ScrollTopButton.vue';
    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';

    export default defineComponent({
        props: ['id'],
        setup: function(props) {
			const profileId = ref(props.id);
            const route = useRoute();
            const router = useRouter();
            const state = reactive({
                isLoading: true
            });

			watch(() => { return route.params.id; }, () => {
				if(route.params.id !== profileId.value) {
					profileId.value = route.params.id;
					fetchProfile();
				}
			});

            const profileData = ref({});

            provide('profileData', profileData);

            onMounted(() => {
				profileId.value = props.id;

                fetchProfile();
            });

			const fetchProfile = async (showLoader = true) => {
				if(showLoader) {
                    state.isLoading = true;
                }

				await colibriAPI().userProfile().params({ id: profileId.value }).getFrom('profile').then(function(response) {
                    profileData.value = response.data.data;
                    state.isLoading = false;
                }).catch(function(error) {
                    router.push({
                        name: 'error_404',
                        params: {
                            pathMatch: route.path.substring(1).split('/')
                        },
                        query: route.query,
                        hash: route.hash
                    });
                });
			};

            useInstantRevalidation(async () => {
                await fetchProfile(false);
            }, {
                minDelay: 3000
            });

            return {
                state: state,
                profileData: profileData
            };
        },
        components: {
            ContentTabs: ContentTabs,
            TabsLink: TabsLink,
            TimelineContainer: TimelineContainer,
            HeaderSkeleton: HeaderSkeleton,
            ProfileAvatar: ProfileAvatar,
            ProfileControls: ProfileControls,
            ProfileName: ProfileName,
            ProfileCover: ProfileCover,
            ProfileMetrics: ProfileMetrics,
            ProfileOverview: ProfileOverview,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            ScrollTopButton: ScrollTopButton,
            SidedContentLayout: SidedContentLayout,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem
        }
    });
</script>
