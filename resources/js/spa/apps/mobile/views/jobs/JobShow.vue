<template>
	<Toolbar v-on:close="$router.back" v-bind:title="$t('job.jobs_overview')">
		<PrimaryIconButton
			v-if="! state.isLoading && jobData"
			v-on:click.prevent="bookmarkJob"
			iconName="bookmark"
			hoverText="hover:text-brand-900"
			v-bind:buttonColor="hasBookmarked ? 'text-brand-900' : 'text-lab-pr2'"
		v-bind:iconType="hasBookmarked ? 'solid' : 'line'"></PrimaryIconButton>
	</Toolbar>

	<div v-if="state.isLoading" class="px-4 py-8">
		<div class="skeleton h-7 w-8/12 mb-3"></div>
		<div class="skeleton h-5 w-6/12 mb-6"></div>
		<div class="skeleton h-28 w-full rounded-2xl"></div>
	</div>

	<div v-else-if="jobData" class="px-4 pb-24">
		<div class="flex items-center gap-3 py-4">
			<img v-bind:src="publisher.avatar_url" v-bind:alt="publisher.username" class="size-12 rounded-xl object-cover">
			<div class="min-w-0 flex-1">
				<RouterLink v-bind:to="{ name: 'profile_index', params: { id: publisher.username } }" class="flex items-center gap-1">
					<span class="text-par-m text-lab-pr2 font-semibold truncate">{{ publisher.name }}</span>
					<span v-if="publisher.verified" class="size-icon-small text-brand-900">
						<SvgIcon name="check-verified-02" type="solid"></SvgIcon>
					</span>
				</RouterLink>
				<span class="block text-par-s text-lab-sc truncate">{{ publisher.caption }}</span>
			</div>
		</div>

		<div class="py-4 border-t border-bord-pr">
			<div v-if="jobData.is_urgent" class="mb-2">
				<span class="rounded-full bg-red-900/10 px-3 py-1 text-cap-l font-bold text-red-900">
					{{ $t('job.urgent_order') }}
				</span>
			</div>
			<h1 class="text-title-2 font-bold leading-tight text-lab-pr">
				{{ jobData.title }}
			</h1>
			<p class="mt-2 text-par-m font-semibold text-lab-pr2">
				{{ incomeLabel }}
			</p>
			<p class="mt-2 text-par-s text-lab-sc">
				{{ jobData.category_name }} · {{ jobData.is_remote ? $t('job.remote_job') : $t('job.office_job') }} · {{ jobData.date.time_ago }}
			</p>
			<p v-if="jobData.location" class="mt-1 text-par-s text-lab-sc">
				{{ $t('labels.location') }}: {{ jobData.is_remote ? $t('job.remote_job') : jobData.location }}
			</p>
		</div>

		<div class="py-4 border-t border-bord-pr">
			<p class="text-par-l text-lab-pr2 leading-relaxed" v-html="$mdInline(jobData.overview || '')"></p>
		</div>

		<div class="flex gap-3 py-4">
			<RouterLink v-bind:to="{ name: 'profile_index', params: { id: publisher.username } }" class="flex-1">
				<PrimaryPillButton buttonFluid buttonRole="stroked" v-bind:buttonText="$t('labels.view_profile')"></PrimaryPillButton>
			</RouterLink>
		</div>

		<div class="py-4 border-t border-bord-pr">
			<h2 class="text-par-l text-lab-pr font-semibold mb-2">{{ $t('job.job_description') }}</h2>
			<p class="text-par-m text-lab-pr2 leading-relaxed break-words" v-html="$mdInline(jobData.description || '')"></p>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, onMounted, reactive } from 'vue';
	import { useRouter } from 'vue-router';
	import { useJobsStore } from '@D/store/jobs/jobs.store.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		props: {
			job_id: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			const router = useRouter();
			const jobsStore = useJobsStore();
			const state = reactive({
				isLoading: true
			});

			const jobData = computed(() => {
				return jobsStore.job;
			});

			onMounted(async () => {
				await jobsStore.fetchJob(props.job_id);

				if(! jobData.value) {
					router.push({ name: 'error_404' });
				}

				state.isLoading = false;
			});

			return {
				state: state,
				jobData: jobData,
				publisher: computed(() => {
					return jobData.value.relations.user;
				}),
				incomeLabel: computed(() => {
					return jobData.value.is_start_income ? __t('job.income_from', { amount: jobData.value.income.formatted }) : __t('job.income_to', { amount: jobData.value.income.formatted });
				}),
				hasBookmarked: computed(() => {
					return jobData.value.meta.activity.bookmarked;
				}),
				bookmarkJob: async function() {
					await jobsStore.bookmarkJob(jobData.value.id).then((response) => {
						jobData.value.meta.activity.bookmarked = response.data.data.bookmarked;
					}).catch((error) => {
						if(error.response) {
							alert(error.response.data.message);
						}
					});
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			PrimaryIconButton: PrimaryIconButton,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
