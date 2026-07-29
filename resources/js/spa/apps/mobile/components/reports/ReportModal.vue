<template>
	<ActionSheet v-if="state.isOpen" v-on:close="closeReportModal">
		<div class="flex flex-col h-full">
			<div class="px-12 text-center pb-4 shrink-0">
				<h3 class="text-par-l font-bold text-lab-pr mb-1">
					{{ reportInfo.title }}
				</h3>
				<p class="text-par-n text-lab-sc">
					{{ reportInfo.description }}
				</p>
			</div>
			<div class="flex-1 overflow-y-auto border-y border-y-bord-pr">
				<ReasonItem v-on:click="selectReason(idx)" v-bind:isSelected="state.selectedReasonIndex === idx" v-for="(reasonData, idx) in reportInfo.reasons" v-bind:key="idx" v-bind:reasonData="reasonData"></ReasonItem>
			</div>
			<div class="pt-4 px-6 shrink-0">
				<PrimaryPillButton
					v-bind:disabled="state.selectedReasonIndex === null"
					v-bind:buttonText="$t('labels.send_report')"
					buttonType="button"
					buttonRole="danger"
					v-on:click="sendReport"
				v-bind:buttonFluid="true"></PrimaryPillButton>
			</div>
		</div>
	</ActionSheet>
</template>

<script>
	import { computed, defineComponent, onMounted, onUnmounted, reactive } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useReportStore } from '@M/store/report/report.store.js';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ReasonItem from '@M/components/reports/parts/ReasonItem.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

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
			};

			const closeReportModal = () => {
				state.isOpen = false;
				state.isLoading = true;
				state.reportType = null;
				state.reportableId = null;
				state.selectedReasonIndex = null;
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
			ReasonItem: ReasonItem,
			ActionSheet: ActionSheet,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>