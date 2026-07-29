<template>
    <ContentModal v-if="state.isOpen" v-on:close="state.isOpen = false">
		<ModalHeader v-bind:modalTitle="$t('labels.account_switcher.title')"></ModalHeader>
		<div v-if="state.isLoading" class="block">
			<div class="py-12 flex justify-center">
				<PrimarySpinAnimation></PrimarySpinAnimation>
			</div>
		</div>
		<div v-else class="block max-h-96 overflow-y-auto">
			<LinkedAccountItem v-on:switch="switchAccount" v-for="accountData in linkedAccounts" v-bind:accountData="accountData" v-bind:key="accountData.id"></LinkedAccountItem>
		</div>
        <template v-if="$config('features.link_accounts.enabled')">
            <Border height="h-3"></Border>
            <div class="block p-4">
                <div class="mb-2">
                    <p class="text-par-s text-lab-sc">
                        {{ $t('labels.account_switcher.description', { app_name: $config('app.name') }) }}
                    </p>
                </div>
                <a v-bind:href="$getRoute('user_linker_index')" class="block">
                    <PrimaryPillButton
                        v-bind:buttonText="$t('labels.account_switcher.button')"
                        buttonType="button"
                    v-bind:buttonFluid="true"></PrimaryPillButton>
                </a>
            </div>
        </template>
    </ContentModal>
</template>

<script>
    import { defineComponent, computed, reactive, onMounted, onUnmounted, ref } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import ContentModal from '@D/components/general/modals/ContentModal.vue';
	import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import LinkedAccountItem from '@D/components/accounts/parts/LinkedAccountItem.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isOpen: false,
				isLoading: true
            });

            const authStore = useAuthStore();
			const linkedAccounts = ref([]);
            const userData = computed(() => {
                return authStore.userData;
            });

            const openSwitcher = async (data) => {
                state.isOpen = true;
                state.isLoading = true;

				await colibriAPI().userSettings().getFrom('account/linked').then((response) => {
					linkedAccounts.value = response.data.data;
				}).catch((error) => {
					if(error.response) {
						alert(error.response.data.message);
					}
				}).finally(() => {
					state.isLoading = false;
				});
            };

            onMounted(() => {
                colibriEventBus.on('account-switcher:open', openSwitcher);
            });

            onUnmounted(() => {
                colibriEventBus.off('account-switcher:open', openSwitcher);
            });

            return {
				linkedAccounts: linkedAccounts,
                state: state,
				switchAccount: async (accountData) => {
                    if(! userData.value) {
                        return;
                    }

					if (userData.value.id !== accountData.id && config('features.switch_account.enabled')) {
                        accountData.isSwitching = true;

                        await colibriAPI().userSettings().with({
                            account_id: accountData.id
                        }).sendTo('account/switch').then((response) => {
                            window.location.reload();
                        }).catch((error) => {
                            if(error.response) {
                                alert(error.response.data.message);
                            }

                            accountData.isSwitching = false;
                        });
					}
				}
			};
        },
        components: {
            ContentModal: ContentModal,
			PrimaryIconButton: PrimaryIconButton,
			ModalHeader: ModalHeader,
			PrimaryPillButton: PrimaryPillButton,
			LinkedAccountItem: LinkedAccountItem
        }
    });
</script>
