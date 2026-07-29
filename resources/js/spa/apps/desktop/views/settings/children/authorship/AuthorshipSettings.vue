<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.authorship')"></PageTitle>
    </div>
    <div v-if="! state.isLoading" class="block">
        <div class="mb-8">
            <h6 class="text-par-m text-lab-sc">
				{{ $t('settings.forms.authorship.page_desc') }}. <a target="_blank" v-bind:href="$getRoute('become_author')" class="text-brand-900">{{ $t('labels.learn_more') }}</a>
			</h6>
        </div>

        <div class="border border-bord-pr rounded-2xl mt-4 overflow-hidden">
            <div class="p-4">
                <AvatarRightSided
                    v-bind:avatarSrc="userData.avatar_url"
                    v-bind:name="userData.name"
                    v-bind:verified="userData.verified"
                    v-bind:linkRoute="{ name: 'profile_index', params: { id: userData.username } }"
                v-bind:caption="userData.caption"></AvatarRightSided>

                <div class="mt-8">
                    <h3 class="text-par-l font-bold text-lab-pr2 mb-1">
                        {{ $t('settings.forms.authorship.about_authorship.title') }}
                    </h3>
                    <p class="text-par-n text-lab-sc">
                        {{ $t('settings.forms.authorship.about_authorship.line_one') }}
                        <br>
                        <br>
                        <strong>
                            {{ $t('settings.forms.authorship.about_authorship.line_two') }}
                           
                            <a target="_blank" v-bind:href="$getRoute('become_author')" class="text-brand-900">{{ $t('labels.learn_more') }}</a>
                        </strong>
                    </p>
                </div>
            </div>
            <Border height="h-3" opacity="opacity-70"></Border>
            <template  v-if="requestData.status === 'authorized'">
               <div class="p-4">
                    <InfoList v-bind:listTitle="$t('settings.authorship')">
                        <InfoListItem 
                            iconName="star-04"
                            v-bind:labelText="$t('settings.forms.authorship.authorized.title')"
                        v-bind:contentText="$t('settings.forms.authorship.authorized.desc')"></InfoListItem>
                    </InfoList>
               </div>
            </template>
            <template v-else>
                <ModalRowButton v-if="requestData.status === 'not_requested'" v-on:click="submitForm" v-bind:disabled="state.isSubmitting" v-bind:loading="state.isSubmitting" v-bind:buttonText="$t('settings.forms.authorship.switch_to_author')" v-bind:hasArrow="true" iconName="star-04"></ModalRowButton>
    
                <ModalRowButton v-else-if="requestData.status === 'pending'" v-bind:disabled="true" v-bind:buttonText="$t('settings.forms.authorship.request_pending')" v-bind:hasArrow="true" iconName="star-04"></ModalRowButton>
    
                <ModalRowButton v-else-if="requestData.status === 'rejected'" buttonRole="danger" v-bind:disabled="true" v-bind:buttonText="$t('settings.forms.authorship.request_rejected')" v-bind:hasArrow="true" iconName="star-04"></ModalRowButton>
            </template>
        </div>
    </div>
    <div v-else class="block">
        <div class="flex-center h-96">
            <PrimarySpinAnimation></PrimarySpinAnimation>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, ref, reactive, onMounted } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useAuthStore } from '@D/store/auth/auth.store.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import ModalRowButton from '@D/components/inter-ui/buttons/ModalRowButton.vue';
    import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';
    import InfoList from '@D/components/general/info/InfoList.vue';
	import InfoListItem from '@D/components/general/info/InfoListItem.vue';
    
    export default defineComponent({
        setup: function() {
            const authStore = useAuthStore();
            const state = reactive({
                isLoading: true,
                isSubmitting: false
            });

            const userData = computed(() => {
                return authStore.userData;
            });

            const requestData = ref(null);

            onMounted(async () => {
                await colibriAPI().userSettings().getFrom('authorship/settings').then((response) => {
                    requestData.value = response.data.data;
                });

                state.isLoading = false;
            });

            return {
                state: state,
                userData: userData,
                requestData: requestData,
                submitForm: async () => {
                    if (state.isSubmitting === false) {
                        state.isSubmitting = true;

                        await colibriAPI().userSettings().sendTo('authorship/request').then((response) => {
                            requestData.value.status = 'pending';

                            toastSuccess(__t('toast.authorship.request_sent'));
                        }).catch((error) => {
                            if(error.response) {
                                toastError(error.response.data.message);
                            }
                        });

                        state.isSubmitting = false;
                    }
                }
            };
        },
        components: {
            AvatarRightSided: AvatarRightSided,
            PageTitle: PageTitle,
            ModalRowButton: ModalRowButton,
            InfoList: InfoList,
            InfoListItem: InfoListItem
        }
    });
</script>