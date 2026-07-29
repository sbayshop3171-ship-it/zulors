<template>
	<SidedContentLayout>
		<template v-slot:content>
			<TimelineContainer>
				<div class="sticky top-0 popup-background-tr z-10">
					<ContentTabs v-bind:cols="2">
						<TabsLink v-bind:link="{ name: 'explore_posts' }">
							{{ $t('labels.explore') }}
						</TabsLink>
						<TabsLink v-bind:link="{ name: 'explore_people' }">
							{{ $t('labels.people') }}
						</TabsLink>
					</ContentTabs>
					<Border></Border>

					<div class="p-4">
						<SearchBar v-model.lazy="peopleSearchQuery" v-bind:placeholder="$t('labels.search')"></SearchBar>
					</div>
				</div>
				<Border></Border>
				<template v-if="state.isLoading">
					<PeopleListItemSkeleton v-for="i in 15" v-bind:key="i"></PeopleListItemSkeleton>
				</template>
				<template v-else-if="state.isEmpty">
					<FluidEmptyState v-bind:text="$t('empty_state.empty')" iconName="users-03" iconType="line"></FluidEmptyState>
				</template>
				<template v-else>
					<template v-if="state.isSearchLoading">
						<PeopleListItemSkeleton v-for="i in 15" v-bind:key="i"></PeopleListItemSkeleton>
					</template>

					<div v-else class="block">
						<PeopleListItem v-if="people.length" v-for="userData in people" v-bind:key="userData.id" v-bind:userData="userData"></PeopleListItem>

						<FluidEmptyState v-else v-bind:text="$t('empty_state.explore.people.filter')" iconName="search-lg" iconType="solid"></FluidEmptyState>
					</div>

					<InfinityScrollContentLoader v-if="state.isLoadingContent"></InfinityScrollContentLoader>
				</template>
			</TimelineContainer>
		</template>
		<template v-slot:sidebar>
            <AdGridItem></AdGridItem>
        </template>
	</SidedContentLayout>
</template>

<script>
	import { defineComponent, reactive, onMounted, ref, watch, computed } from 'vue';
	import { useExplorePeopleStore } from '@D/store/explore/people.store.js';
	import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';

	import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
	import AdGridItem from '@D/components/ads/AdGridItem.vue';
	import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
	import PeopleListItem from '@D/components/people/PeopleListItem.vue';
	import PeopleListItemSkeleton from '@D/components/people/PeopleListItemSkeleton.vue';
	import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
	import SearchBar from '@D/components/general/search/SearchBar.vue';
	import InfinityScrollContentLoader from '@D/components/loaders/InfinityScrollContentLoader.vue';
	import ContentTabs from '@D/components/general/tabs/content/ContentTabs.vue';
    import TabsLink from '@D/components/general/tabs/content/parts/TabsLink.vue';

	export default defineComponent({
		setup: function() {
			const peopleSearchQuery = ref('');

			const state = reactive({
				noMoreContent: false,
                isLoadingContent: false,
				isEmpty: false,
				isLoading: true,
				isSearchLoading: false
			});

			const people = computed(() => {
				return explorePeopleStore.people;
			});

			const explorePeopleStore = useExplorePeopleStore();

			onMounted(async () => {
				// Reset filter on mount.
				// Because there can be a filter applied from the previous visits.

				explorePeopleStore.resetFilter();

				await explorePeopleStore.fetchPeople();

				debounce(() => {
                    if(! people.value.length) {
                        state.isEmpty = true;
                    }
                }, 500);

                state.isLoading = false;
			});

			watch(peopleSearchQuery, () => {
                explorePeopleStore.filter.query = peopleSearchQuery.value;

                debounce(async () => {
                    await applyFilters();
                }, 500);
            });

			const applyFilters = async () => {
				explorePeopleStore.filter.page = 1;
				state.noMoreContent = false;
				state.isSearchLoading = true;
				await explorePeopleStore.fetchPeople();
				state.isSearchLoading = false;
			}

			useInfiniteScroll({
				callback: () => {
					debounce(async () => {
                        if(! state.isLoadingContent && ! state.noMoreContent && people.value.length) {
                            state.isLoadingContent = true;

                            explorePeopleStore.filter.page += 1;

                            state.noMoreContent = (! await explorePeopleStore.loadMorePeople());

                            state.isLoadingContent = false;
                        }
                    }, 200);
				}
			});

			return {
				people: people,
				state: state,
				peopleSearchQuery: peopleSearchQuery,
			};
		},
		components: {
			TimelineContainer: TimelineContainer,
			SidedContentLayout: SidedContentLayout,
			PeopleListItem: PeopleListItem,
			AdGridItem: AdGridItem,
			SearchBar: SearchBar,
			FluidEmptyState: FluidEmptyState,
			PeopleListItemSkeleton: PeopleListItemSkeleton,
			InfinityScrollContentLoader: InfinityScrollContentLoader,
			ContentTabs: ContentTabs,
			TabsLink: TabsLink
		}
	});
</script>
