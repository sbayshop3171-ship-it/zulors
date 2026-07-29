<template>
	<div class="flex justify-between p-4">
		<div class="overflow-hidden">
			<AvatarRightSided
				v-bind:avatarSrc="publisher.avatar_url"
				v-bind:name="publisher.name"
				v-bind:verified="publisher.verified"
				v-bind:linkRoute="{ name: 'profile_index', params: { id: publisher.username } }"
			v-bind:caption="publisher.caption"></AvatarRightSided>
		</div>
		<div class="shrink-0">
			<PrimaryIconButton
				iconName="bookmark"
				v-bind:buttonColor="(hasBookmarked ? 'text-brand-900' : 'text-lab-pr2')"
				hoverText="hover:text-brand-900"
				v-on:click.prevent="bookmarkJob"
			v-bind:iconType="(hasBookmarked ? 'solid' : 'line')"></PrimaryIconButton>
		</div>
	</div>
	<div class="px-4 pb-10">
		<div class="mb-4">
			<h4 class="text-title-2 2xl:text-title-1 font-bold text-lab-pr leading-tight mb-2">
				{{ jobData.title }}
			</h4>
			<p class="block text-par-n mb-2 text-lab-pr2 font-semibold">
				{{ jobData.is_start_income ? $t('job.income_from', { amount: jobData.income.formatted }) : $t('job.income_to', { amount: jobData.income.formatted }) }}
			</p>
			
			<p class="block text-par-n mb-2 text-lab-sc font-light">
				{{ jobData.category_name }}, {{ jobData.is_remote ? $t('job.remote_job') : $t('job.office_job') }} &middot; {{ jobData.date.time_ago }}
			</p>
	
			<div v-if="jobData.is_urgent || 2" class="mb-2">
				<JobHighlighter v-bind:labelText="$t('job.urgent_order')"></JobHighlighter>
			</div>
			<p class="text-par-l text-lab-pr2 mb-2" v-html="$mdInline(jobData.overview)"></p>
		</div>
		
		
		<div class="flex items-end mb-4">
			<div class="mr-4 inline-flex items-center gap-3">
				<PrimaryPillButton
					v-if="permissions.can_apply"
					buttonType="button"
					v-on:click="chatLauncherToggle"
					buttonSize="lm"
				v-bind:buttonText="$t('job.apply_now')"></PrimaryPillButton>
				<RouterLink v-bind:to="{ name: 'profile_index', params: { id: publisher.username } }">
					<PrimaryPillButton
						buttonType="button"
						buttonSize="lm"
						buttonRole="stroked"
					v-bind:buttonText="$t('labels.view_profile')"></PrimaryPillButton>
				</RouterLink>
			</div>
			<div class="shrink-0 leading-none ml-auto">
				<ViewsCounter v-bind:counterValue="jobData.views_count.formatted"></ViewsCounter>
			</div>
		</div>
		<div class="mb-4">
			<Border></Border>
		</div>
		<div class="block">
			<h3 class="text-title-3 text-lab-pr2 font-semibold mb-4">
				{{ $t('job.job_description') }}
			</h3>
			<div class="text-par-m text-lab-pr2">
				<ul class="mb-3 flex flex-col gap-2 text-lab-sc">
					<li>
						<span class="font-semibold text-lab-pr2">{{ $t('labels.published_at') }}:</span> {{ jobData.date.iso }}
					</li>
					<li>
						<span class="font-semibold text-lab-pr2">{{ $t('labels.location') }}:</span> {{ jobData.is_remote ? $t('job.remote_job') : jobData.location }}
					</li>
				</ul>

				<div class="block break-words">
					<p ref="jobDescriptionHolder" v-bind:class="[!state.textExpanded ? 'line-clamp-[12]' : '']" v-html="$mdInline(jobData.description)"></p>
				</div>
				<div v-if="state.textOverflow" class="block mt-2">
					<PrimaryTextButton
						v-on:click="state.textExpanded = !state.textExpanded"
					v-bind:buttonText="state.textExpanded ? $t('labels.see_less_description') : $t('labels.see_full_description')"></PrimaryTextButton>
				</div>
			</div>
		</div>
	</div>
    
	<Teleport v-if="state.isChatLauncherOpen" to="body">
		<PrimaryTransition>
			<ChatLauncher
				v-bind:userId="publisher.id"
				v-bind:payload="chatLauncherPayload"
			v-on:close="state.isChatLauncherOpen = false"></ChatLauncher>
		</PrimaryTransition>
	</Teleport>
</template>

<script>
	import { defineComponent, ref, reactive, computed, onMounted, defineAsyncComponent } from 'vue';

	import { checkTextOverflow } from '@/kernel/helpers/html/index.js';
	import { useJobsStore } from '@D/store/jobs/jobs.store.js';

	import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import JobHighlighter from '@D/views/jobs/children/board/parts/JobHighlighter.vue';
	import ViewsCounter from '@/kernel/vue/components/general/counters/ViewsCounter.vue';
	import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';

	export default defineComponent({
		props: {
			jobData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			const jobData = ref(props.jobData);
			const jobDescriptionHolder = ref(null);
			const jobsStore = useJobsStore();
			const state = reactive({
				textExpanded: false,
				textOverflow: false,
				isChatLauncherOpen: false
			});

			onMounted(() => {
                state.textOverflow = checkTextOverflow(jobDescriptionHolder.value, 12);
            });

			return {
				jobData: jobData,
				state: state,
				jobDescriptionHolder: jobDescriptionHolder,
				publisher: computed(() => {
					return jobData.value.relations.user;
				}),
				permissions: jobData.value.meta.permissions,
				hasBookmarked: computed(() => {
                    return jobData.value.meta.activity.bookmarked;
                }),
				chatLauncherPayload: computed(() => {
					return {
						type: 'job',
						id: jobData.value.id
					}
				}),
				chatLauncherToggle: function() {
                    state.isChatLauncherOpen = !state.isChatLauncherOpen;
                },
				bookmarkJob: async function() {
					await jobsStore.bookmarkJob(jobData.value.id).then((response) => {
                        if(response.data.data.bookmarked) {
                            jobData.value.meta.activity.bookmarked = true;
                            toastSuccess(__t('toast.job.job_bookmarked'));
                        }
                        else{
                            jobData.value.meta.activity.bookmarked = false;
                            toastSuccess(__t('toast.job.job_unbookmarked'));
                        }
                    }).catch((error) => {
                        if(error.response) {
                            alert(error.response.data.message);
                        }
                    });
				}
			};
		},
		components: {
			AvatarRightSided: AvatarRightSided,
			PrimaryPillButton: PrimaryPillButton,
			PrimaryIconButton: PrimaryIconButton,
			JobHighlighter: JobHighlighter,
			ViewsCounter: ViewsCounter,
			PrimaryTextButton: PrimaryTextButton,
			ChatLauncher: defineAsyncComponent(function() {
                return import('@D/components/inter-ui/chat/ChatLauncher.vue');
            })
		}
	});
</script>