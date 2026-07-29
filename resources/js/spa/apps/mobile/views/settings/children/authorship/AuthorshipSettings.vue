<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.authorship')"></Toolbar>

	<div v-if="! state.isLoading" class="px-4 pb-6">
		<p class="mb-4 text-par-m leading-5 text-lab-sc break-words">
			{{ $t('settings.forms.authorship.page_desc') }}.
			<a target="_blank" v-bind:href="$getRoute('become_author')" class="text-brand-900 font-semibold">
				{{ $t('labels.learn_more') }}
			</a>
		</p>

		<div class="overflow-hidden rounded-2xl border border-bord-pr bg-bg-pr">
			<div class="p-4">
				<AvatarRightSided
					v-if="userData"
					v-bind:avatarSrc="userData.avatar_url"
					v-bind:name="userData.name"
					v-bind:verified="userData.verified"
					v-bind:linkRoute="{ name: 'profile_index', params: { id: userData.username } }"
				v-bind:caption="userData.caption"></AvatarRightSided>

				<div class="mt-6">
					<h3 class="mb-2 text-par-l font-bold text-lab-pr2">
						{{ $t('settings.forms.authorship.about_authorship.title') }}
					</h3>
					<p class="text-par-m leading-5 text-lab-sc break-words">
						{{ $t('settings.forms.authorship.about_authorship.line_one') }}
					</p>
					<p class="mt-3 text-par-m leading-5 text-lab-pr2 font-semibold break-words">
						{{ $t('settings.forms.authorship.about_authorship.line_two') }}
						<a target="_blank" v-bind:href="$getRoute('become_author')" class="text-brand-900">
							{{ $t('labels.learn_more') }}
						</a>
					</p>
				</div>
			</div>

			<Border height="h-2" opacity="opacity-50"></Border>

			<div v-if="requestData.status === 'authorized'" class="py-4">
				<InfoList v-bind:listTitle="$t('settings.authorship')">
					<InfoListItem
						iconName="star-04"
						v-bind:labelText="$t('settings.forms.authorship.authorized.title')"
					v-bind:contentText="$t('settings.forms.authorship.authorized.desc')"></InfoListItem>
				</InfoList>
			</div>

			<div v-else class="p-4">
				<PrimaryPillButton
					v-if="requestData.status === 'not_requested'"
					v-on:click="submitForm"
					v-bind:buttonFluid="true"
					v-bind:loading="state.isSubmitting"
					buttonRole="accent"
				v-bind:buttonText="$t('settings.forms.authorship.switch_to_author')"></PrimaryPillButton>

				<PrimaryPillButton
					v-else-if="requestData.status === 'pending'"
					v-bind:buttonFluid="true"
					v-bind:isDisabled="true"
				v-bind:buttonText="$t('settings.forms.authorship.request_pending')"></PrimaryPillButton>

				<PrimaryPillButton
					v-else-if="requestData.status === 'rejected'"
					v-bind:buttonFluid="true"
					v-bind:isDisabled="true"
					buttonRole="danger"
				v-bind:buttonText="$t('settings.forms.authorship.request_rejected')"></PrimaryPillButton>
			</div>
		</div>
	</div>

	<div v-else class="flex-center h-64">
		<PrimarySpinAnimation></PrimarySpinAnimation>
	</div>
</template>

<script>
	import { computed, defineComponent, ref, reactive, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import AvatarRightSided from '@M/components/general/avatars/sided/small/AvatarRightSided.vue';
	import InfoList from '@M/components/general/info/InfoList.vue';
	import InfoListItem from '@M/components/general/info/InfoListItem.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();
			const state = reactive({
				isLoading: true,
				isSubmitting: false
			});

			const requestData = ref({
				status: 'not_requested'
			});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('authorship/settings').then((response) => {
					requestData.value = response.data.data;
				}).catch((error) => {
					if(error.response) {
						toastError(error.response.data.message);
					}
				});

				state.isLoading = false;
			});

			return {
				state: state,
				requestData: requestData,
				userData: computed(() => {
					return authStore.userData;
				}),
				submitForm: async () => {
					if (state.isSubmitting === false) {
						state.isSubmitting = true;

						await colibriAPI().userSettings().sendTo('authorship/request').then(() => {
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
			Toolbar: Toolbar,
			AvatarRightSided: AvatarRightSided,
			InfoList: InfoList,
			InfoListItem: InfoListItem,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
