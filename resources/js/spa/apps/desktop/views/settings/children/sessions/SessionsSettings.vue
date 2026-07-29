<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.active_sessions')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
                {{ $t('settings.forms.active_sessions.page_desc') }}
            </h6>
        </div>
        <div v-if="sessionsList.length">
            <template v-if="currentSession">
                <div class="mb-4">
                    <h6 class="text-par-m text-lab-sc font-medium">
                        {{ $t('settings.forms.active_sessions.current_session') }}
                    </h6>
                </div>
                <div class="mb-12">
                    <SessionItem v-bind:sessionData="currentSession"></SessionItem>
                </div>
            </template>
            <div class="mb-4">
                <h6 class="text-par-m text-lab-sc font-medium">
                    {{ $t('settings.forms.active_sessions.all_sessions') }}
                </h6>
            </div>
            <div class="mb-12">
                <div class="mb-4" v-for="sessionItem in sessionsList">
                    <SessionItem v-bind:sessionData="sessionItem"></SessionItem>
                </div>

                <div class="mb-4" v-if="sessionsList.length > 1">
                    <button v-on:click="terminateOtherSessions" type="button" class="flex w-full items-center border-b border-b-fill-pr pb-4 overflow-hidden">
                        <div class="size-normal-avatar shrink-0 rounded-full overflow-hidden flex justify-center items-center bg-fill-tr">
                            <SvgIcon type="solid" name="log-out-01" classes="size-icon-small text-red-900"></SvgIcon>
                        </div>
                        <div class="flex-1 ml-4 overflow-hidden text-left">
                            <h4 class="text-par-m font-semibold text-red-900 leading-tight truncate">
                                {{ $t('settings.forms.active_sessions.terminate_other_sessions') }}
                            </h4>
                            <span class="block text-par-s text-lab-sc truncate">
                                {{ $t('settings.forms.active_sessions.terminate_other_sessions_helper') }}
                            </span>
                        </div>
                        <div class="ml-4 shrink-0">
                            <SvgIcon name="chevron-right" classes="size-6 text-lab-sc"></SvgIcon>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div v-else class="block">
        <div class="flex-center h-96">
            <PrimarySpinAnimation></PrimarySpinAnimation>
        </div>
    </div>
</template>

<script>
    import { defineComponent, reactive, onMounted, ref, computed } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SessionItem from '@D/views/settings/children/sessions/parts/SessionItem.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true
            });

            const sessionsList = ref([]);

            const fetchUserSessions = async () => {
                state.isLoading = true;

                await colibriAPI().userSettings().getFrom('sessions').then((response) => {
                    sessionsList.value = response.data.data.sessions;
                });

                state.isLoading = false;
            }

            onMounted(() => {
                fetchUserSessions();
            });

            return {
                state: state,
                sessionsList: sessionsList,
                currentSession: computed(() => {
                    return sessionsList.value.find((session) => {
                        return session.is_current === true;
                    });
                }),
                terminateOtherSessions: async () => {
                    state.isLoading = true;

                    await colibriAPI().userSettings().delete('sessions/terminate/other').then((response) => {
                        fetchUserSessions();
                    }).catch((error) => {
                        if (error.response) {
                        }
                    });

                    state.isLoading = false;
                }
            }
        },
        components: {
            PageTitle: PageTitle,
            SessionItem: SessionItem
        }
    });
</script>