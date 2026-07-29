<template>
    <div class="px-4">
        <div class="mb-2 flex items-center gap-1 text-lab-sc">
            <span class="size-icon-x-small shrink-0">
                <SvgIcon type="line" name="bar-chart-12" classes="size-full"></SvgIcon>
            </span>
            <span class="text-par-s">{{ $t('labels.public_poll') }}</span>
        </div>
        <div class="block mb-2">
            <div v-for="(choiceItem, index) in pollChoices" v-bind:key="index" class="mb-2 last:mb-0">
                <PollChoiceItem v-bind:hasVotedPoll="hasVotedPoll" v-on:vote="votePoll" v-bind:index="index" v-bind:choiceItem="choiceItem"></PollChoiceItem>
            </div>
        </div>
        <div class="text-center text-cap-l text-lab-sc">
            {{ $t('labels.votes_number', { number: pollVotesTotal }) }} - {{ postPoll.is_expired ? $t('labels.final_results') : $t('labels.active_poll') }}
        </div>
        <template v-if="pollHasVoters">
            <div class="mt-2">
                <PollVoterUsers v-bind:voterUsers="postPoll.voter_users"></PollVoterUsers>
            </div>
        </template>
    </div>
</template>

<script>
    import { defineComponent, computed, defineAsyncComponent } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useTimelineStore } from '@M/store/timeline/timeline.store.js';

    import PollChoiceItem from '@M/components/timeline/feed/parts/poll/PollChoiceItem.vue';

    export default defineComponent({
        props: {
            postPoll: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const postPoll = computed(() => {
                return props.postPoll;
            });

            const timelineStore = useTimelineStore();
            const hasVotedPoll = computed(() => {
                return postPoll.value.has_voted;
            });

            return {
                postPoll: postPoll,
                pollChoices: computed(() => {
                    return postPoll.value.choices;
                }),
                pollVotesTotal: computed(() => {
                    return postPoll.value.votes.length
                }),
                hasVotedPoll: hasVotedPoll,
                votePoll: (choiceIndex) => {
                    if(! hasVotedPoll.value) {
                        colibriAPI().userTimeline().with({
                            choice_id: choiceIndex,
                            poll_id: postPoll.value.id
                        }).sendTo('post/poll/vote').then((response) => {
                            timelineStore.updatePoll(response.data.data);
                        });
                    }
                },
                pollHasVoters: computed(() => {
                    return postPoll.value.voter_users.length > 0;
                })
            };
        },
        components: {
            PollChoiceItem: PollChoiceItem,
            PollVoterUsers: defineAsyncComponent(() => {
                return import('@M/components/timeline/feed/parts/poll/PollVoterUsers.vue');
            })
        }
    });
</script>