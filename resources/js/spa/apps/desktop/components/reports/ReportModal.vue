<template>
	<Teleport v-if="state.isOpen" to="body">
		<ContentModal v-on:close="closeReportModal">
			<ModalHeader v-bind:modalTitle="$t('labels.report')"></ModalHeader>

			<div v-if="state.isLoading" class="flex justify-center py-12">
				<PrimaryDotsAnimation></PrimaryDotsAnimation>
			</div>
			<div class="block" v-else>
				<div class="block py-6 px-4 text-center">
					<div class="flex justify-center">
						<div class="size-6 text-red-500">
							<SvgIcon name="alert-hexagon"></SvgIcon>
						</div>
					</div>
					<h3 class="text-title-3 font-bold text-lab-pr2">
						{{ reportInfo.title }}
					</h3>
					<p class="text-par-s text-lab-sc">
						{{ reportInfo.description }}
					</p>
				</div>
				<Border height="h-3"></Border>
				<div class="block max-h-96 overflow-y-auto">
					<ReasonItem v-on:click="selectReason(idx)" v-bind:isSelected="state.selectedReasonIndex === idx" v-for="(reasonData, idx) in reportInfo.reasons" v-bind:key="idx" v-bind:reasonData="reasonData"></ReasonItem>
				</div>
				<Border></Border>
				<div class="block py-4 px-4">
					<PrimaryPillButton
						v-bind:disabled="state.selectedReasonIndex === null"
						v-bind:buttonText="$t('labels.send_report')"
						buttonType="button"
						v-on:click="sendReport"
					v-bind:buttonFluid="true"></PrimaryPillButton>
				</div>
			</div>
		</ContentModal>
	</Teleport>
</template>

<script>
	import { computed, defineComponent, onMounted, onUnmounted, reactive } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useReportStore } from '@/apps/desktop/store/report/report.store.js';

	import ContentModal from '@D/components/general/modals/ContentModal.vue';
	import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
	import ReasonItem from '@D/components/reports/parts/ReasonItem.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isOpen: false,
				isLoading: true,
				reportType: null,
				selectedReasonIndex: null
			});

			const reportStore = useReportStore();

			const openReportModal = async (data) => {
				state.isOpen = true;
				state.reportType = data.type;
				state.reportableId = data.reportableId;

				if(! reportStore.reportReasons[state.reportType]) {
					await reportStore.fetchReportReasons(state.reportType);
				}
				
				state.isLoading = false;

				document.body.style.overflow = 'hidden';
			};

			const closeReportModal = () => {
				state.isOpen = false;
				state.isLoading = true;
				state.reportType = null;
				state.reportableId = null;
				state.selectedReasonIndex = null;

				document.body.style.overflow = 'auto';
			};

			onMounted(() => {
				colibriEventBus.on('report:open', openReportModal);
				colibriEventBus.on('report:close', closeReportModal);
			});

			onUnmounted(() => {
				colibriEventBus.off('report:open', openReportModal);
				colibriEventBus.off('report:close', closeReportModal);
			});

			return {
				state: state,
				closeReportModal: closeReportModal,
				reportInfo: computed(() => {
					return reportStore.reportReasons[state.reportType];
				}),
				selectReason: function(idx) {
					if(state.selectedReasonIndex === idx) {
						state.selectedReasonIndex = null;
					} else {
						state.selectedReasonIndex = idx;
					}
				},
				sendReport: function() {
					reportStore.sendReport({
						type: state.reportType,
						reason_index: state.selectedReasonIndex,
						reportable_id: state.reportableId
					});

					toastSuccess(__t('toast.report_sent'));

					closeReportModal();
				}
			};
		},
		components: {
			ContentModal: ContentModal,
			ModalHeader: ModalHeader,
			ReasonItem: ReasonItem,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>