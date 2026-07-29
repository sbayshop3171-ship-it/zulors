<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.account_verification')"></PageTitle>
    </div>

    <ContentContainer>
        <VerifiedStatus v-if="userData.verification.status"></VerifiedStatus>
        <UnverifiedStatus v-else></UnverifiedStatus>
    </ContentContainer>
</template>

<script>
    import { defineComponent, ref, reactive, onMounted, provide } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import ContentContainer from '@D/components/layout/ContentContainer.vue';
    import UnverifiedStatus from '@D/views/settings/children/verification/parts/UnverifiedStatus.vue';
    import VerifiedStatus from '@D/views/settings/children/verification/parts/VerifiedStatus.vue';

    export default defineComponent({
        setup: function() {
            const authStore = useAuthStore();
            const userData = ref(authStore.userData);

			provide('userData', userData);

            return {
                userData: userData
            }
        },
        components: {
            PageTitle: PageTitle,
            ContentContainer: ContentContainer,
            UnverifiedStatus: UnverifiedStatus,
            VerifiedStatus: VerifiedStatus
        }
    });
</script>