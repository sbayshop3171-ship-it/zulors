<template>
    <template v-if="hasTips && userData">
        <ContentModal>
            <div class="p-4">
                <div class="mb-4">
                    <h4 class="text-par-m font-medium text-lab-pr2">
                        {{ $t('labels.welcome_user', { name: userData.name, app_name: appName }) }}
                    </h4>
                </div>
                <div class="mb-6">
                    <StepperProgress v-bind:currentStep="currentStep" v-bind:totalSteps="totalSteps"></StepperProgress>
                </div>
                <BioTip
                    v-if="onboardingTips.onboarding_bio"
                    v-bind:isSkippable="onboardingTips.onboarding_bio.skippable"
                v-on:close="closeModal('onboarding_bio')"></BioTip>
            
                <AvatarTip
                    v-else-if="onboardingTips.onboarding_avatar"
                    v-bind:isSkippable="onboardingTips.onboarding_avatar.skippable"
                v-on:close="closeModal('onboarding_avatar')"></AvatarTip>
            
                <FollowTip
                    v-else-if="onboardingTips.onboarding_follow"
                    v-bind:isSkippable="onboardingTips.onboarding_follow.skippable"
                v-on:close="closeModal('onboarding_follow')"></FollowTip>
            </div>
        </ContentModal>
    </template>
</template>

<script>
    import { defineComponent, computed, defineAsyncComponent, reactive, ref, onMounted } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';

    import ContentModal from '@D/components/general/modals/ContentModal.vue';
    import StepperProgress from '@D/components/tips/onboarding/parts/StepperProgress.vue';

    export default defineComponent({
        setup: function(props) {
            const authStore = useAuthStore();
            const hasTips = ref(false);
            const userData = computed(() => {
                return authStore.userData;
            });
            
            const onboardingTips = ref({}); 
            const currentStep = ref(1);
            const totalSteps = ref(3);

            onMounted(() => {
                if(userData.value?.has_tips) {
                    hasTips.value = true;
                    onboardingTips.value = userData.value.tips;
                    totalSteps.value = Object.keys(onboardingTips.value).length;
                }
            });

            return {
                onboardingTips: onboardingTips,
                closeModal: (tipName) => {
                    // If the last tip is closed, hide the modal.
                    if (currentStep.value === totalSteps.value) {
                        hasTips.value = false;
                        return false;
                    }

                    onboardingTips.value[tipName] = null;

                    currentStep.value++;
                },
                totalSteps: totalSteps,
                currentStep: currentStep,
                hasTips: hasTips,
                appName: embedder('config.app.name'),
                userData: userData
            }
        },
        components: {
            BioTip: defineAsyncComponent(() => {
                return import('@D/components/tips/onboarding/modals/BioTip.vue');
            }),
            AvatarTip: defineAsyncComponent(() => {
                return import('@D/components/tips/onboarding/modals/AvatarTip.vue');
            }),
            FollowTip: defineAsyncComponent(() => {
                return import('@D/components/tips/onboarding/modals/FollowTip.vue');
            }),
            ContentModal: ContentModal,
            StepperProgress: StepperProgress
        }
    });
</script>
