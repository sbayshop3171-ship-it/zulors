<template>
	<Toolbar v-on:close="$router.back" v-bind:title="$t('job.jobs_title')">
		<a v-if="$config('features.business_accounts.enabled')" v-bind:href="$getRoute('business_jobs_create')">
			<PrimaryIconButton iconName="plus" iconType="line"></PrimaryIconButton>
		</a>
	</Toolbar>

	<div class="px-4 pb-24">
		<div class="sticky top-0 z-20 bg-bg-pr pt-2 pb-3">
			<label class="flex h-11 items-center gap-2 rounded-full border border-bord-pr bg-fill-qt px-4">
				<span class="size-icon-small text-lab-sc">
					<SvgIcon name="search-lg" type="line"></SvgIcon>
				</span>
				<input
					v-model.trim="searchQuery"
					type="search"
					class="w-full bg-transparent text-par-m text-lab-pr outline-hidden placeholder:text-lab-sc"
				v-bind:placeholder="$t('labels.search')">
			</label>

			<div v-if="categories.length" class="mt-3 flex gap-2 overflow-x-auto pb-1 no-scrollbar">
				<button
					type="button"
					v-on:click="selectCategory(null)"
					class="shrink-0 rounded-full border px-4 py-2 text-par-s font-semibold"
				v-bind:class="selectedCategory ? 'border-bord-pr bg-bg-pr text-lab-sc' : 'border-lab-pr2 bg-lab-pr2 text-bg-pr'">
					{{ $t('labels.for_you') }}
				</button>
				<button
					v-for="categoryItem in categories"
					v-bind:key="categoryItem.id"
					type="button"
					v-on:click="selectCategory(categoryItem)"
					class="shrink-0 rounded-full border px-4 py-2 text-par-s font-semibold"
				v-bind:class="filter.category_id === categoryItem.id ? 'border-lab-pr2 bg-lab-pr2 text-bg-pr' : 'border-bord-pr bg-bg-pr text-lab-sc'">
					{{ categoryItem.name }}
				</button>
			</div>
		</div>

		<template v-if="state.isLoading || state.isSearchLoading">
			<div class="space-y-4 pt-2">
				<div v-for="item in 6" v-bind:key="`job-skeleton-${item}`" class="flex gap-3 rounded-2xl border border-bord-pr p-4">
					<div class="skeleton size-12 rounded-xl shrink-0"></div>
					<div class="flex-1 space-y-2">
						<div class="skeleton h-5 w-10/12"></div>
						<div class="skeleton h-4 w-6/12"></div>
						<div class="skeleton h-4 w-full"></div>
					</div>
				</div>
			</div>
		</template>

		<template v-else>
			<div v-if="jobs.length" class="space-y-3 pt-2">
				<RouterLink
					v-for="jobData in jobs"
					v-bind:key="jobData.hash_id"
					v-bind:to="{ name: 'jobs_show', params: { job_id: jobData.hash_id } }"
					class="block rounded-2xl border border-bord-pr bg-bg-pr p-4 active:bg-fill-qt"
				>
					<div class="flex gap-3">
						<img v-bind:src="jobData.relations.user.avatar_url" alt="Job publisher" class="size-12 shrink-0 rounded-xl object-cover">
						<div class="min-w-0 flex-1">
							<div class="flex items-start gap-2">
								<h4 class="text-par-l font-bold leading-tight text-lab-pr line-clamp-2">
									{{ jobData.title }}
								</h4>
								<span v-if="jobData.is_urgent" class="shrink-0 rounded-full bg-red-900/10 px-2 py-1 text-cap-l font-bold text-red-900">
									{{ $t('job.urgent_order') }}
								</span>
							</div>
							<p class="mt-1 text-par-s font-semibold text-lab-pr2">
								{{ incomeLabel(jobData) }}, {{ jobData.relations.user.name }}
							</p>
							<p class="mt-1 text-par-s text-lab-sc line-clamp-2" v-html="jobData.overview"></p>
							<p class="mt-2 text-par-s text-lab-sc">
								{{ jobData.category_name }}, {{ jobData.is_remote ? $t('job.remote_job') : $t('job.office_job') }}
							</p>
						</div>
					</div>
				</RouterLink>
			</div>

			<div v-else class="py-32 text-center">
				<p class="text-par-s text-lab-sc">
					{{ state.isEmpty ? $t('empty_state.empty') : $t('empty_state.jobs.filter') }}
				</p>
			</div>
		</template>

		<div v-if="state.isLoadingContent" class="flex justify-center py-4">
			<div class="colibri-primary-animation"></div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, reactive, computed, onMounted, watch } from 'vue';
	import { useJobsStore } from '@D/store/jobs/jobs.store.js';
	import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		setup: function() {
			const jobsStore = useJobsStore();
			const searchQuery = ref('');
			const selectedCategory = ref(null);
			const state = reactive({
				isLoading: true,
				isSearchLoading: false,
				isLoadingContent: false,
				noMoreContent: false,
				isEmpty: false
			});

			const jobs = computed(() => {
				return jobsStore.jobs;
			});

			const categories = computed(() => {
				return jobsStore.categories;
			});

			const filter = computed(() => {
				return jobsStore.filter;
			});

			const applyFilters = async function() {
				state.noMoreContent = false;
				jobsStore.filter.cursor = null;
				state.isSearchLoading = true;

				await jobsStore.fetchJobs();

				state.isSearchLoading = false;
			};

			watch(searchQuery, () => {
				jobsStore.filter.query = searchQuery.value;

				debounce(async () => {
					await applyFilters();
				}, 450);
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

			onMounted(async () => {
				jobsStore.resetFilter();
				await jobsStore.fetchCategories();
				await jobsStore.fetchJobs();

				state.isEmpty = ! jobs.value.length;
				state.isLoading = false;
			});

			return {
				state: state,
				filter: filter,
				jobs: jobs,
				categories: categories,
				searchQuery: searchQuery,
				selectedCategory: selectedCategory,
				incomeLabel: (jobData) => {
					return jobData.is_start_income ? __t('job.income_from', { amount: jobData.income.formatted }) : __t('job.income_to', { amount: jobData.income.formatted });
				},
				selectCategory: async (categoryItem) => {
					selectedCategory.value = categoryItem;
					jobsStore.filter.category_id = categoryItem ? categoryItem.id : null;
					await applyFilters();
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>
