<template>
	<TimelineContainer>
		<div class="mobile-safe-overlay-top sticky top-0 popup-background-tr z-10">
			<div class="px-4 pt-4">
				<QuickSearch v-on:cancel="handleSearchCancel" v-model.lazy="peopleSearchQuery" v-bind:placeholder="$t('labels.search')"></QuickSearch>
			</div>
			<ExploreTabs></ExploreTabs>
			<Border></Border>
		</div>
		<template v-if="state.isLoading">
			<PeopleListItemSkeleton v-for="i in 15" v-bind:key="i"></PeopleListItemSkeleton>
		</template>
		<template v-else-if="state.isEmpty">
			<div class="py-32">
				<p class="text-lab-sc text-par-s text-center">
					{{ $t('empty_state.empty') }}
				</p>
			</div>
		</template>
		<template v-else>
			<template v-if="state.isSearchLoading">
				<PeopleListItemSkeleton v-for="i in 15" v-bind:key="i"></PeopleListItemSkeleton>
			</template>

			<div v-else class="block">
				<template v-if="people.length" v-for="(userData, index) in people" v-bind:key="userData.id">
					<PeopleListItem v-bind:userData="userData"></PeopleListItem>

					<!-- Show ad card every 15 posts -->
                    <template v-if="(index + 1) % 15 === 0">
						<AdCard v-bind:key="index"></AdCard>
						<Border height="h-2" opacity="opacity-30"></Border>
					</template>
				</template>

				<div v-else class="py-32">
					<p class="text-lab-sc text-par-s text-center">
						{{ $t('empty_state.explore.people.filter') }}
					</p>
				</div>
			</div>

			<div v-if="state.isLoadingContent">
				<div class="flex justify-center my-4">
					<div class="colibri-primary-animation"></div>
				</div>
			</div>
		</template>
	</TimelineContainer>
</template>

<script>
	import { defineComponent, reactive, onMounted, ref, watch, computed } from 'vue';
	import { useExplorePeopleStore } from '@M/store/explore/people.store.js';
	import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';

	import TimelineContainer from '@M/components/timeline/feed/TimelineContainer.vue';
	import PeopleListItem from '@M/components/people/PeopleListItem.vue';
	import PeopleListItemSkeleton from '@M/components/people/PeopleListItemSkeleton.vue';

	import QuickSearch from '@M/components/general/search/QuickSearch.vue';
	import ExploreTabs from '@M/views/explore/parts/ExploreTabs.vue';
	import AdCard from '@M/components/ads/AdCard.vue';

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
				state.isLoading = ! people.value.length;

				if(people.value.length) {
					explorePeopleStore.fetchPeople().finally(() => {
						state.isLoading = false;
					});
				}
				else {
					await explorePeopleStore.fetchPeople();
					state.isLoading = false;
				}

				debounce(() => {
                    if(! people.value.length) {
                        state.isEmpty = true;
                    }
                }, 500);
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
				state.isSearchLoading = ! explorePeopleStore.hydrateCachedFirstPage(peopleSearchQuery.value);

				explorePeopleStore.fetchPeople().finally(() => {
					state.isSearchLoading = false;
				});
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
				handleSearchCancel: () => {
					peopleSearchQuery.value = '';
				}
			};
		},
		components: {
			TimelineContainer: TimelineContainer,
			PeopleListItem: PeopleListItem,
			QuickSearch: QuickSearch,
			PeopleListItemSkeleton: PeopleListItemSkeleton,
			ExploreTabs: ExploreTabs,
			AdCard: AdCard
		}
	});
</script>
