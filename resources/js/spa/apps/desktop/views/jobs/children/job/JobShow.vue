<template>
	<SidedContentLayout>
		<template v-slot:content>
            <TimelineContainer>
                <div class="sticky top-0 popup-background-tr z-10 border-b border-bord-pr">
					<PageHeader v-bind:hasBack="true" v-bind:titleText="$t('job.jobs_overview')"></PageHeader>
				</div>
				<template v-if="state.isLoading">
					<div class="flex-center h-96">
						<PrimarySpinAnimation></PrimarySpinAnimation>
					</div>
				</template>
				<template v-else>
					<JobOverview v-bind:jobData="jobData"></JobOverview>
				</template>
			</TimelineContainer>
		</template>
		<template v-slot:sidebar>
			<FollowRecommendationList></FollowRecommendationList>
			<AdGridItem></AdGridItem>
		</template>
	</SidedContentLayout>
</template>

<script>
	import { defineComponent, onMounted, reactive, computed } from 'vue';
	import { useRouter } from 'vue-router';
	import { useJobsStore } from '@D/store/jobs/jobs.store.js';

	import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
	import PageHeader from '@D/components/layout/PageHeader.vue';
	import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
	import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
	import JobOverview from '@D/views/jobs/children/job/parts/JobOverview.vue';

	export default defineComponent({
		props: {
            job_id: {
                type: String,
                required: true
            }
        },
		setup: function(props) {
			const router = useRouter();
			const state = reactive({
				isLoading: true
			});

			const jobStore = useJobsStore();

			const jobData = computed(() => {
				return jobStore.job;
			});

			onMounted(async () => {
				await jobStore.fetchJob(props.job_id);

				if(! jobData.value) {
					router.push({ name: 'error_404' });
				}

				state.isLoading = false;
			});
			

			return {
				state: state,
				jobData: jobData
			}
		},
		components: {
			PageHeader: PageHeader,
			TimelineContainer: TimelineContainer,
			SidedContentLayout: SidedContentLayout,
			JobOverview: JobOverview,
			FollowRecommendationList: FollowRecommendationList,
			AdGridItem: AdGridItem
		}
	})
</script>