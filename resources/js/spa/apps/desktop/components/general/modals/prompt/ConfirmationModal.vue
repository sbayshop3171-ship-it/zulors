<template>
	<template v-if="state.isModalOpen">
		<ContentModal contentWidth="w-8/12" v-on:close="cancel">
			<div class="block">
				<div class="p-4">
					<div class="px-4 py-4">
						<h4 class="text-title-3 text-center text-lab-pr2 font-bold mb-1" v-html="modalData.title"></h4>
						<p class="text-par-m text-center text-lab-pr3" v-html="modalData.description"></p>
					</div>
				</div>
				<div class="border-t border-bord-pr">
					<div class="py-3 hover:bg-fill-qt smoothing">
						<PrimaryTextButton v-bind:buttonFluid="true" v-on:click="confirm" v-bind:buttonText="modalData.confirm" buttonRole="danger"></PrimaryTextButton>
					</div>
					<template v-if="modalData.confirmSecondary">
						<Border></Border>
						<div class="py-3 hover:bg-fill-qt smoothing">
							<PrimaryTextButton v-bind:buttonFluid="true" v-on:click="confirmSecondary" v-bind:buttonText="modalData.confirmSecondaryText" buttonRole="danger"></PrimaryTextButton>
						</div>
					</template>
					<Border></Border>
					<div class="py-3 hover:bg-fill-qt smoothing">
						<PrimaryTextButton v-bind:buttonFluid="true" v-on:click="cancel" v-bind:buttonText="modalData.cancel"></PrimaryTextButton>
					</div>
				</div>
			</div>
		</ContentModal>
	</template>
</template>

<script>
	import { defineComponent, reactive, ref, onMounted } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import ContentModal from '@D/components/general/modals/ContentModal.vue';
	import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';

	export default defineComponent({
		setup: function(props) {
			const state = reactive({
				isModalOpen: false,
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
					confirmSecondaryText: ''
				};
			};

			onMounted(() => {
				colibriEventBus.on('confirmation-modal:open', (data) => {
					resetModalData();

					modalData.value.title = data.title;
					modalData.value.description = data.description;
				
					if (data.confirmButtonText) {
						modalData.value.confirm = data.confirmButtonText;
					}

					if (data.cancelButtonText) {
						modalData.cancel = data.cancelButtonText;
					}

					if (data.confirmSecondary) {
						modalData.value.confirmSecondary = true;
						modalData.value.confirmSecondaryText = data.confirmSecondaryText;
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
				confirm: function() {
					if(modalCallbacks.onConfirm) {
						modalCallbacks.onConfirm();
					}

					state.isModalOpen = false;
				},
				cancel: function() {
					if(modalCallbacks.onCancel) {
						modalCallbacks.onCancel();
					}

					state.isModalOpen = false;
				},
				confirmSecondary: function() {
					if(modalCallbacks.onConfirmSecondary) {
						modalCallbacks.onConfirmSecondary();
					}

					state.isModalOpen = false;
				}
			};
		},
		components: {
			ContentModal: ContentModal,
			PrimaryTextButton: PrimaryTextButton
		}
	});
</script>