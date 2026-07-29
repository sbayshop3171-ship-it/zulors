<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <BoardHeader></BoardHeader>
                <Border></Border>
                <div class="sticky top-0 popup-background-tr z-10">
                    <div class="p-4 pb-8">
                        <div class="mb-4">
                            <SearchBar v-model.trim="searchQuery" v-bind:placeholder="$t('labels.search')">

                            </SearchBar>
                        </div>

                        <div class="flex justify-between items-end">
                            <h4 class="text-par-l text-lab-pr2 font-semibold truncate">
                                {{ selectedCategory ? selectedCategory.name : $t('labels.for_you') }}
                            </h4>

                            <div class="flex items-center gap-2 ml-auto">
                                <template v-if="activeFiltersCount">
                                    <PrimaryTextButton buttonRole="danger" v-on:click="resetFilter" v-bind:buttonText="$t('market.reset_filters')"></PrimaryTextButton>
                                    <div class="w-px h-4 bg-bord-pr"></div>
                                </template>

                                <div class="relative">
                                    <div class="relative">
                                        <PrimaryTextButton v-on:click.stop="state.filterMenu.toggle" buttonRole="marginal" v-bind:buttonText="$t('labels.filters')"></PrimaryTextButton>
                                        <span class="absolute -top-1 -right-1">
                                            <BadgeCounter v-if="activeFiltersCount" v-bind:count="activeFiltersCount"></BadgeCounter>
                                        </span>
                                    </div>

                                    <div v-outside-click="state.filterMenu.close" v-on:click="state.filterMenu.close" v-if="state.filterMenu.status" class="absolute top-full -right-4 bottom-0 w-content">
                                        <div class="popup-card bg-bg-pr">
                                            <PopupPanel>
                                                <div class="p-4 border-b border-b-bord-pr">
                                                    <h4 class="text-par-l text-lab-pr2 font-semibold text-center">
                                                        {{ $t('labels.filters') }}
                                                    </h4>
                                                </div>
                                                <div class="max-h-[700px] overflow-y-auto">
                                                    <div class="p-4">
                                                        <CategoriesFilter
                                                            v-on:select="selectCategory"
                                                            v-bind:selected="filter.category_id"
                                                        v-bind:categories="categories"></CategoriesFilter>
                                                    </div>
                                                </div>
                                            </PopupPanel>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <template v-if="state.isLoading">
                    <JobCardSkeleton v-for="i in 3" v-bind:key="i"></JobCardSkeleton>
                </template>
                <template v-else>
                    <template v-if="state.isEmpty">
                        <FluidEmptyState v-bind:text="$t('empty_state.empty')" iconType="solid"></FluidEmptyState>
                    </template>
                    <template v-else>
                        <template v-if="state.isSearchLoading">
                            <JobCardSkeleton v-for="i in 3" v-bind:key="i"></JobCardSkeleton>
                        </template>
                        <template v-else>
                            <template v-if="jobs.length">
                                <div v-for="jobItem in jobs">
                                    <RouterLink v-bind:to="{ name: 'jobs_show', params: { job_id: jobItem.hash_id } }" class="block">
                                        <JobCard v-bind:key="jobItem.id" v-bind:jobData="jobItem"></JobCard>
                                    </RouterLink>
                                </div>

                                <div v-if="state.isLoadingContent">
                                    <div class="flex justify-center my-4">
                                        <div class="colibri-primary-animation"></div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <FluidEmptyState v-bind:text="$t('empty_state.jobs.filter')" iconType="solid"></FluidEmptyState>
                            </template>
                        </template>
                    </template>
                </template>
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
    import { defineComponent, ref, onMounted, watch, computed, reactive } from 'vue';
    import { useRouter } from 'vue-router';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { useJobsStore } from '@D/store/jobs/jobs.store.js';
    import { useMenu } from '@/kernel/vue/composables/menu/index.js';

    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
    import BoardHeader from '@D/views/jobs/children/board/parts/BoardHeader.vue';
    import SearchBar from '@D/components/general/search/SearchBar.vue';

    import JobCard from '@D/views/jobs/children/board/parts/JobCard.vue';
    import CategoriesFilter from '@D/components/general/search/CategoriesFilter.vue';
    import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
    import ScrollTopButton from '@D/components/inter-ui/buttons/ScrollTopButton.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import PopupPanel from '@D/components/inter-ui/popups/PopupPanel.vue';
    import BadgeCounter from '@D/components/general/counters/BadgeCounter.vue';
    import JobCardSkeleton from '@D/views/jobs/children/board/parts/JobCardSkeleton.vue';
    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';

    export default defineComponent({
        setup: function(props) {
            const state = reactive({
                isLoading: true,
                isSearchLoading: false,
                noMoreContent: false,
                isLoadingContent: false,
                isEmpty: false,
                filterMenu: useMenu()
            });

            const selectedCategory = ref(null);
            const router = useRouter();
            const searchQuery = ref('');
            const jobsStore = useJobsStore();
            const jobs = computed(() => {
                return jobsStore.jobs;
            });

            const categories = computed(() => {
                return jobsStore.categories;
            });

            const filter = computed(() => {
                return jobsStore.filter;
            });

            watch(searchQuery, () => {
                jobsStore.filter.query = searchQuery.value;

                debounce(async () => {
                    await applyFilters();
                }, 500);
            });

            const applyFilters = async function() {
                state.noMoreContent = false;
                jobsStore.filter.cursor = null;

                state.isSearchLoading = true;
                await jobsStore.fetchJobs();
                state.isSearchLoading = false;
            }

            const activeFiltersCount = computed(() => {
                return jobsStore.activeFiltersCount;
            });

            useInfiniteScroll({
				callback: () => {
                    debounce(async () => {
                        if(! state.isLoadingContent && ! state.noMoreContent && jobs.value.length) {
                            state.isLoadingContent = true;

                            jobsStore.filter.cursor = jobsStore.getLastJobId();

                            state.noMoreContent = ! await jobsStore.loadMoreJobs();

                            state.isLoadingContent = false;
                        }
                    }, 200);
                }
			});

            onMounted(async function() {
                jobsStore.resetFilter();

                await jobsStore.fetchCategories();
                await jobsStore.fetchJobs();

                debounce(() => {
                    if(! jobs.value.length) {
                        state.isEmpty = true;
                    }
                }, 500);

                state.isLoading = false;
            });

            return {
                jobs: jobs,
                filter: filter,
                state: state,
                searchQuery: searchQuery,
                activeFiltersCount: activeFiltersCount,
                categories: categories,
                selectedCategory: selectedCategory,
                resetFilter: async () => {
                    jobsStore.resetFilter();
                    searchQuery.value = '';
                    selectedCategory.value = null;

                    state.isLoading = true;
                    await jobsStore.fetchJobs();
                    state.isLoading = false;
                },
                selectCategory: (categoryItem) => {
                    if(jobsStore.filter.category_id == categoryItem.id) {
                        jobsStore.filter.category_id = null;
                        selectedCategory.value = null;
                    }
                    else {
                        jobsStore.filter.category_id = categoryItem.id;
                        selectedCategory.value = categoryItem;
                    }

                    applyFilters();
                }
            };
        },
        components: {
            SidedContentLayout: SidedContentLayout,
            JobCard: JobCard,
            TimelineContainer: TimelineContainer,
            CategoriesFilter: CategoriesFilter,
            FluidEmptyState: FluidEmptyState,
            BoardHeader: BoardHeader,
            ScrollTopButton: ScrollTopButton,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem,
            PrimaryIconButton: PrimaryIconButton,
            JobCardSkeleton: JobCardSkeleton,
            PopupPanel: PopupPanel,
            BadgeCounter: BadgeCounter,
            SearchBar: SearchBar,
            BoardHeader: BoardHeader,
            PrimaryTextButton: PrimaryTextButton
        }
    });
</script>
