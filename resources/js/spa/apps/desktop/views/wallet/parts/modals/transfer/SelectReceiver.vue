<template>
	<div class="p-4">
		<div class="mb-4">
			<ModalTextInput
				v-bind:placeholder="$t('wallet.receiver_placeholder')"
				v-model="walletNumber"
				v-on:input="searchReceivers"
				v-on:clear="clearSearch"
			v-bind:labelText="$t('wallet.receiver')">
				<template v-slot:feedbackInfo>
					{{ $t('wallet.receiver_helper') }}
				</template>
			</ModalTextInput>
		</div>
		<div class="max-h-[min(24rem,45vh)] overflow-y-auto p-1">
			<div v-if="state.isLoading" class="mb-6 grid grid-cols-3 gap-3 min-[420px]:grid-cols-4 sm:grid-cols-6 md:grid-cols-8 md:gap-4">
				<div v-for="i in 4" v-bind:key="i" class="size-full aspect-square skeleton-circle"></div>
			</div>
			<div v-else class="mb-6 grid grid-cols-3 gap-3 min-[420px]:grid-cols-4 sm:grid-cols-6 md:grid-cols-8 md:gap-4">
				<UserCircleCard
					v-if="receivers.length"
					v-for="receiverItem in receivers"
					v-bind:key="receiverItem.id" 
					v-bind:avatarSrc="receiverItem.relations.user.avatar_url"
					v-on:click="$emit('select', receiverItem)"
				v-bind:name="receiverItem.relations.user.name"></UserCircleCard>
				<UserCircleCard
					v-else
					v-for="receiverItem in history"
					v-bind:key="receiverItem.id"
					v-on:click="$emit('select', receiverItem)"
					v-bind:avatarSrc="receiverItem.relations.user.avatar_url" 
				v-bind:name="receiverItem.relations.user.name"></UserCircleCard>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed, reactive, onMounted } from 'vue';
	import { useWalletStore } from '@D/store/wallet/wallet.store.js';

	import ModalTextInput from '@D/components/forms/modal/ModalTextInput.vue';
	import UserCircleCard from '@D/components/general/payments/UserCircleCard.vue';

	export default defineComponent({
		emits: ['select'],
		setup: function() {
			const state = reactive({
				isSearching: false,
				isLoading: true
			});

			const walletNumber = ref('');
			const receivers = ref([]);
			const history = computed(() => {
				return walletStore.receiverHistory;
			});

			const walletStore = useWalletStore();

			onMounted(async () => {
				await walletStore.fetchReceiverHistory();

				state.isLoading = false;
			});

			return {
				walletNumber: walletNumber,
				state: state,
				searchReceivers: async () => {
					state.isSearching = true;

					await walletStore.fetchReceivers(walletNumber.value).then((response) => {
						receivers.value.push(response.data.data);
					}).catch((error) => {
						receivers.value = [];
					});

					state.isSearching = false;
				},
				clearSearch: () => {
					walletNumber.value = '';
					receivers.value = [];
				},
				receivers: receivers,
				history: history
			}
		},
		components: {
			ModalTextInput: ModalTextInput,
			UserCircleCard: UserCircleCard
		}
	});
</script>
