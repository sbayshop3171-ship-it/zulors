<template>
	<template v-if="state.isModalOpen">
		<ContentModal contentWidth="w-8/12" v-on:close="cancel">
			<div class="block">
				<div class="p-4">
					<div class="px-4 py-4">
						<h4 class="text-title-3 text-center text-lab-pr2 font-bold mb-1" v-html="modalData.title"></h4>
						<p class="text-par-m text-center text-lab-pr3" v-html="modalData.description"></p>
						<p v-if="state.errorMessage" class="text-par-s text-center text-red-900 mt-3" role="alert">
							{{ state.errorMessage }}
						</p>
					</div>
				</div>
				<div class="border-t border-bord-pr">
					<button type="button" class="w-full py-3 text-par-n font-medium text-red-900 hover:bg-fill-qt smoothing disabled:cursor-not-allowed disabled:opacity-60" v-bind:disabled="state.isSubmitting" v-on:click.stop.prevent="confirm">
						{{ state.isSubmitting ? 'Please wait...' : modalData.confirm }}
					</button>
					<template v-if="modalData.confirmSecondary">
						<Border></Border>
						<button type="button" class="w-full py-3 text-par-n font-medium text-red-900 hover:bg-fill-qt smoothing disabled:cursor-not-allowed disabled:opacity-60" v-bind:disabled="state.isSubmitting" v-on:click.stop.prevent="confirmSecondary">
							{{ modalData.confirmSecondaryText }}
						</button>
					</template>
					<Border></Border>
					<button type="button" class="w-full py-3 text-par-n font-medium text-brand-900 hover:bg-fill-qt smoothing disabled:cursor-not-allowed disabled:opacity-60" v-bind:disabled="state.isSubmitting" v-on:click.stop.prevent="cancel">
						{{ modalData.cancel }}
					</button>
				</div>
			</div>
		</ContentModal>
	</template>
</template>

<script>
	import { defineComponent, reactive, ref, onMounted } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import ContentModal from '@D/components/general/modals/ContentModal.vue';

	export default defineComponent({
		setup: function(props) {
			const state = reactive({
				isModalOpen: false,
				isSubmitting: false,
				errorMessage: '',
			});

			const modalData = ref({});

			const modalCallbacks = reactive({
				onConfirm: null,
				onCancel: null,
				onConfirmSecondary: null
			});

			const resetModalData = () => {
				modalData.value = {
					title: '',
					description: '',
					confirm: __t('prompt.confirm_prompt_button'),
					cancel: __t('prompt.cancel_prompt_button'),
					confirmSecondary: false,
					confirmSecondaryText: '',
					closeOnConfirm: false
				};
			};

			const resetCallbacks = () => {
				modalCallbacks.onConfirm = null;
				modalCallbacks.onCancel = null;
				modalCallbacks.onConfirmSecondary = null;
			};

			const closeModal = () => {
				state.isModalOpen = false;
				state.isSubmitting = false;
				state.errorMessage = '';
				resetCallbacks();
			};

			onMounted(() => {
				colibriEventBus.on('confirmation-modal:open', (data) => {
					resetModalData();
					resetCallbacks();
					state.isSubmitting = false;
					state.errorMessage = '';

					modalData.value.title = data.title;
					modalData.value.description = data.description;
				
					if (data.confirmButtonText) {
						modalData.value.confirm = data.confirmButtonText;
					}

					if (data.cancelButtonText) {
						modalData.value.cancel = data.cancelButtonText;
					}

					if (data.confirmSecondary) {
						modalData.value.confirmSecondary = true;
						modalData.value.confirmSecondaryText = data.confirmSecondaryText;
					}

					if (data.closeOnConfirm) {
						modalData.value.closeOnConfirm = true;
					}

					modalCallbacks.onConfirm = data.onConfirm;
					modalCallbacks.onCancel = data.onCancel;

					if (data.onConfirmSecondary) {
						modalCallbacks.onConfirmSecondary = data.onConfirmSecondary;
					}

					state.isModalOpen = true;
				});
			});

			return {
				modalData: modalData,
				modalCallbacks: modalCallbacks,
				state: state,
				confirm: async function() {
					if(state.isSubmitting) {
						return;
					}

					if(modalData.value.closeOnConfirm) {
						const onConfirm = modalCallbacks.onConfirm;

						closeModal();

						try {
							const result = onConfirm?.();

							if(result && typeof result.catch === 'function') {
								result.catch((error) => {
									toastError(error?.response?.data?.message || 'Unable to complete this action. Please try again.');
								});
							}
						} catch (error) {
							toastError(error?.response?.data?.message || 'Unable to complete this action. Please try again.');
						}

						return;
					}

					state.isSubmitting = true;

					try {
						await Promise.resolve(modalCallbacks.onConfirm?.());
						closeModal();
					} catch (error) {
						state.isSubmitting = false;
						state.errorMessage = error?.response?.data?.message || 'Unable to complete this action. Please try again.';
					}
				},
				cancel: function() {
					const onCancel = modalCallbacks.onCancel;

					closeModal();
					onCancel?.();
				},
				confirmSecondary: async function() {
					if(state.isSubmitting) {
						return;
					}

					state.isSubmitting = true;

					try {
						await Promise.resolve(modalCallbacks.onConfirmSecondary?.());
						closeModal();
					} catch (error) {
						state.isSubmitting = false;
						state.errorMessage = error?.response?.data?.message || 'Unable to complete this action. Please try again.';
					}
				}
			};
		},
		components: {
			ContentModal: ContentModal
		}
	});
</script>
