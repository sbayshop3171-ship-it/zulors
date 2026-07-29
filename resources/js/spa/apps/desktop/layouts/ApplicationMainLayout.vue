<template>
    <ApplicationSidebar></ApplicationSidebar>

    <div class="flex ml-page-offset">
        <div class="flex-1 max-w-full">
            <RouterView v-slot="{ Component, route }">
                <component v-bind:is="Component" v-bind:key="route.fullPath"></component>
            </RouterView>
        </div>
    </div>

    <ToastNotification></ToastNotification>

    <PublicationEditorModal></PublicationEditorModal>
    <AccountSwitcherModal></AccountSwitcherModal>

    <StoriesEditorModal v-if="openStoriesEditorModal"></StoriesEditorModal>

    <CheatSheetsPanel></CheatSheetsPanel>

    <SoundbarMainContext></SoundbarMainContext>

    <ConfirmationModal></ConfirmationModal>

    <LightboxPlayer></LightboxPlayer>

    <NotificationsModal v-if="openNotificationsModal"></NotificationsModal>

    <ReportModal></ReportModal>

    <OnboardingTips></OnboardingTips>
</template>

<script>
    import { defineComponent, computed, defineAsyncComponent } from 'vue';

    import { useStoriesEditorStore } from '@D/store/stories/editor.store.js';
    import { useNotificationsStore } from '@D/store/notifications/notifications.store.js';

    import ApplicationSidebar from '@D/components/layout/ApplicationSidebar.vue';
    import ToastNotification from '@D/components/notifications/toast/ToastNotification.vue';
    import LightboxPlayer from '@D/components/lightbox/LightboxPlayer.vue';
    import CheatSheetsPanel from '@D/components/layout/parts/cheat-sheets/CheatSheetsPanel.vue';
    import ConfirmationModal from '@D/components/general/modals/prompt/ConfirmationModal.vue';
    import NotificationsModal from '@D/components/notifications/native/NotificationsModal.vue';
    import ReportModal from '@D/components/reports/ReportModal.vue';
    import PublicationEditorModal from '@D/components/timeline/modals/PublicationEditorModal.vue';
    import AccountSwitcherModal from '@D/components/accounts/AccountSwitcherModal.vue';


    export default defineComponent({
        setup: function() {
            const storiesEditorStore = useStoriesEditorStore();
            const notificationsStore = useNotificationsStore();

            return {
                openStoriesEditorModal: computed(() => {
                    return storiesEditorStore.isOpen;
                }),
                openNotificationsModal: computed(() => {
                    return notificationsStore.isOpen;
                })
            }
        },
        components: {
            CheatSheetsPanel: CheatSheetsPanel,
            ApplicationSidebar: ApplicationSidebar,
            ToastNotification: ToastNotification,
            ConfirmationModal: ConfirmationModal,
            NotificationsModal: NotificationsModal,
            LightboxPlayer: LightboxPlayer,
            PublicationEditorModal: PublicationEditorModal,
            SoundbarMainContext: defineAsyncComponent(() => {
                return import('@D/components/soundbar/SoundbarMainContext.vue')
            }),
            StoriesEditorModal: defineAsyncComponent(() => {
                return import('@D/components/stories/modals/StoriesEditorModal.vue');
            }),
            ReportModal: ReportModal,
            AccountSwitcherModal: AccountSwitcherModal,
            OnboardingTips: defineAsyncComponent(() => {
                return import('@D/components/tips/onboarding/OnboardingTips.vue');
            })
        }
    });
</script>
