<template>
    <div class="p-4 cursor-pointer smoothing hover:bg-fill-fv flex gap-2.5">
        <div class="shrink-0 size-medium-avatar overflow-hidden rounded-sm border border-bord-pr">
            <img v-bind:src="publisher.avatar_url" alt="Job Image" class="w-full h-full object-cover">
        </div>
        <div class="block">
            <div class="flex justify-between gap-1">
                <h4 class="text-title-3 font-semibold text-lab-pr2 leading-tight line-clamp-2 mb-1">
                    {{ jobData.title }}
                </h4>
    
    
                <div v-if="jobData.is_urgent" class="shrink-0">
                    <JobHighlighter v-bind:labelText="$t('job.urgent_order')"></JobHighlighter>
                </div>
            </div>
            <div class="block">
                <span class="text-par-s text-lab-pr2 font-semibold">
                    {{ jobData.is_start_income ? $t('job.income_from', { amount: jobData.income.formatted }) : $t('job.income_to', { amount: jobData.income.formatted }) }},
                    <RouterLink v-bind:to="{ name: 'profile_index', params: { id: publisher.username } }" class="text-lab-pr2 font-medium hover:underline truncate">
                        {{ publisher.name }}
                    </RouterLink>
                </span>
            </div>
            <div class="block">
                <p class="text-par-m text-lab-pr3 line-clamp-3" v-html="jobData.overview"></p>
            </div>
            <p class="text-par-n text-lab-sc mt-2">
                {{ jobData.category_name }}, {{ jobData.is_remote ? $t('job.remote_job') : $t('job.office_job') }}
            </p>
            

            <div class="mt-1">
                <span class="text-par-s text-lab-sc font-medium truncate">{{ jobData.type.label }}</span>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ViewsCounter from '@/kernel/vue/components/general/counters/ViewsCounter.vue';
    import JobHighlighter from '@D/views/jobs/children/board/parts/JobHighlighter.vue';

    // This component is used to display a job card in the jobsboard.
    // It is used in the JobsIndex component and the JobBookmarksPage component.
    // Changes to this component will affect both the jobsboard and the bookmarks page.

    export default defineComponent({
        props: {
            jobData: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            return {
                publisher: computed(() => {
                    return props.jobData.relations.user;
                })
            }
        },
        components: {
            PrimaryIconButton: PrimaryIconButton,
			ViewsCounter: ViewsCounter,
            JobHighlighter: JobHighlighter
        }
    });
</script>