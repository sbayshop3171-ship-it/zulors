<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.account_privacy')"></Toolbar>

	<div v-if="! state.isLoading" class="pb-6">
		<div class="px-4 mb-6">
			<p class="text-par-m leading-5 text-lab-sc break-words">
				{{ $t('settings.forms.account_privacy.page_desc') }}
			</p>
		</div>

		<section class="mx-4 mb-6 rounded-2xl border border-bord-pr bg-bg-pr p-4">
			<div class="flex items-start justify-between gap-4">
				<div>
					<h3 class="text-par-m font-bold text-lab-pr2">Personalized ads</h3>
					<p class="mt-1 text-par-s leading-5 text-lab-sc">Allow activity from posts and searches to help select relevant ads. Turning this off clears your saved ad topics.</p>
				</div>
				<input v-model="adPersonalization" type="checkbox" class="mt-1 size-5 accent-current" v-on:change="saveAdPersonalization">
			</div>
			<p v-if="adInterests.length" class="mt-3 text-par-s text-lab-sc">Ad topics: {{ adInterests.map(item => item.topic).join(', ') }}</p>
			<button v-if="adInterests.length" type="button" class="mt-3 text-par-s font-semibold text-lab-pr2 underline" v-on:click="resetAdInterests">Reset ad topics</button>
		</section>

		<div v-for="privacyGroup in privacyGroups" v-bind:key="privacyGroup.title" class="mb-6">
			<div class="px-4 mb-2">
				<h6 class="text-par-m text-lab-sc font-medium break-words">
					{{ privacyGroup.title }}
				</h6>
			</div>

			<div class="mx-4 overflow-hidden rounded-2xl border border-bord-pr bg-bg-pr divide-y divide-bord-pr">
				<div
					v-for="privacyItem in privacyGroup.items"
					v-bind:key="privacyItem.fieldName"
					class="flex min-h-20 items-start gap-3 px-4 py-3">
					<span class="shrink-0 size-6 text-lab-pr">
						<SvgIcon v-bind:name="privacyItem.iconName" type="line"></SvgIcon>
					</span>

					<span class="min-w-0 flex-1">
						<span class="block text-par-m font-bold text-lab-pr2 break-words">
							{{ privacyItem.titleText }}
						</span>
						<span class="block text-par-s leading-4 text-lab-sc break-words" v-html="privacyItem.captionText"></span>
					</span>

					<span class="shrink-0 w-32 max-w-[46%]">
						<span v-if="state.activeField === privacyItem.fieldName" class="inline-block colibri-primary-animation"></span>
						<Listbox
							v-else
							as="div"
							v-bind:modelValue="selectedPrivacyOption(privacyItem)"
							v-on:update:modelValue="updatePrivacy(privacyItem.fieldName, $event.value)"
							v-slot="{ open }"
							class="relative w-full">
							<ListboxButton class="flex w-full items-center justify-between gap-2 rounded-full border border-bord-pr bg-input-pr px-3 py-2 text-par-s text-lab-pr outline-hidden">
								<span class="min-w-0 flex-1 truncate text-left">
									{{ selectedPrivacyLabel(privacyItem) }}
								</span>
								<span class="shrink-0 size-4 text-lab-sc">
									<SvgIcon name="chevron-down"></SvgIcon>
								</span>
							</ListboxButton>

							<ListboxOptions
								v-if="open"
								static
								class="mt-2 w-full overflow-hidden rounded-xl border border-bord-pr bg-bg-pr shadow-lg focus:outline-hidden">
								<ListboxOption
									v-for="optionItem in privacyItem.options"
									v-bind:key="optionItem.value"
									v-bind:value="optionItem"
									v-slot="{ active, selected }">
									<span
										v-bind:class="[
											(active || selected) ? 'bg-fill-qt text-lab-pr2' : 'text-lab-sc',
											'block cursor-pointer px-3 py-2 text-par-s font-medium break-words'
										]">
										{{ optionItem.label }}
									</span>
								</ListboxOption>
							</ListboxOptions>
						</Listbox>
					</span>
				</div>
			</div>
		</div>
	</div>

	<div v-else class="flex-center h-64">
		<PrimarySpinAnimation></PrimarySpinAnimation>
	</div>
</template>

<script>
	import { computed, defineComponent, ref, reactive, onMounted } from 'vue';
	import { Listbox, ListboxButton, ListboxOptions, ListboxOption } from '@headlessui/vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';

	export default defineComponent({
		setup: function() {
			const formData = ref({});
			const adPersonalization = ref(false);
			const adInterests = ref([]);
			const state = reactive({
				isLoading: true,
				isSubmitting: false,
				activeField: null
			});

			const privacyOptions = ref({
				followers: [
					{ label: __t('settings.forms.account_privacy.all_users'), value: 'all' },
					{ label: __t('settings.forms.account_privacy.only_approved'), value: 'approved' }
				],
				direct_messages: [
					{ label: __t('settings.forms.account_privacy.all_users'), value: 'all' },
					{ label: __t('settings.forms.account_privacy.nobody'), value: 'nobody' }
				],
				story_replies: [
					{ label: __t('settings.forms.account_privacy.all_users'), value: 'all' },
					{ label: __t('settings.forms.account_privacy.nobody'), value: 'nobody' }
				],
				group_invites: [
					{ label: __t('settings.forms.account_privacy.all_users'), value: 'all' },
					{ label: __t('settings.forms.account_privacy.nobody'), value: 'nobody' }
				],
				mentions: [
					{ label: __t('settings.forms.account_privacy.all_users'), value: 'all' },
					{ label: __t('settings.forms.account_privacy.nobody'), value: 'nobody' }
				],
				payment_transfers: [
					{ label: __t('settings.forms.account_privacy.all_users'), value: 'all' },
					{ label: __t('settings.forms.account_privacy.nobody'), value: 'nobody' }
				]
			});

			const privacyGroups = computed(() => {
				return [
					{
						title: __t('settings.forms.account_privacy.auditory'),
						items: [
							{
								fieldName: 'followers',
								iconName: 'user-plus-01',
								options: privacyOptions.value.followers,
								titleText: __t('settings.forms.account_privacy.followers'),
								captionText: __t('settings.forms.account_privacy.followers_helper')
							}
						]
					},
					{
						title: __t('settings.forms.account_privacy.messages_and_stories'),
						items: [
							{
								fieldName: 'direct_messages',
								iconName: 'message-chat-circle',
								options: privacyOptions.value.direct_messages,
								titleText: __t('settings.forms.account_privacy.direct_messages'),
								captionText: __t('settings.forms.account_privacy.direct_messages_helper')
							},
							{
								fieldName: 'story_replies',
								iconName: 'send-01',
								options: privacyOptions.value.story_replies,
								titleText: __t('settings.forms.account_privacy.story_replies'),
								captionText: __t('settings.forms.account_privacy.story_replies_helper')
							}
						]
					},
					{
						title: __t('settings.forms.account_privacy.permissions'),
						items: [
							{
								fieldName: 'group_invites',
								iconName: 'users-01',
								options: privacyOptions.value.group_invites,
								titleText: __t('settings.forms.account_privacy.group_invites'),
								captionText: __t('settings.forms.account_privacy.group_invites_helper')
							},
							{
								fieldName: 'mentions',
								iconName: 'at-sign',
								options: privacyOptions.value.mentions,
								titleText: __t('settings.forms.account_privacy.mentions'),
								captionText: __t('settings.forms.account_privacy.mentions_helper')
							}
						]
					},
					{
						title: __t('settings.forms.account_privacy.payment_transfers'),
						items: [
							{
								fieldName: 'payment_transfers',
								iconName: 'wallet-02',
								options: privacyOptions.value.payment_transfers,
								titleText: __t('settings.forms.account_privacy.payments'),
								captionText: __t('settings.forms.account_privacy.payments_helper')
							}
						]
					}
				];
			});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('privacy/settings').then((response) => {
					formData.value = response.data.data;
				}).catch((error) => {
					if(error.response) {
						toastError(error.response.data.message);
					}
				});
				await colibriAPI().userSettings().getFrom('ad-personalization').then(response => {
					adPersonalization.value = Boolean(response.data.data.personalized_ads);
				}).catch(() => {});
				await loadAdInterests();

				state.isLoading = false;
			});

			const loadAdInterests = async () => {
				await colibriAPI().userSettings().getFrom('ad-interests').then(response => {
					adInterests.value = response.data.data || [];
				}).catch(() => { adInterests.value = []; });
			};
			const saveAdPersonalization = async () => {
				await colibriAPI().userSettings().with({ personalized_ads: adPersonalization.value }).patchTo('ad-personalization').then(response => {
					adPersonalization.value = Boolean(response.data.data.personalized_ads);
					if(!adPersonalization.value) adInterests.value = [];
				}).catch(error => { adPersonalization.value = !adPersonalization.value; toastError(error.response?.data?.message || 'Could not update ad settings.'); });
			};
			const resetAdInterests = async () => {
				await colibriAPI().userSettings().delete('ad-interests').then(() => { adInterests.value = []; }).catch(() => toastError('Could not reset ad topics.'));
			};

			const submitForm = async (fieldName) => {
				if (state.isSubmitting === false) {
					state.isSubmitting = true;
					state.activeField = fieldName;

					await colibriAPI().userSettings().with(formData.value).putTo('privacy/update').then(() => {
						toastSuccess(__t('toast.forms.changes_saved'));
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});

					state.isSubmitting = false;
					state.activeField = null;
				}
			};

			return {
				state: state,
				adPersonalization,
				adInterests,
				saveAdPersonalization,
				resetAdInterests,
				formData: formData,
				privacyOptions: privacyOptions,
				privacyGroups: privacyGroups,
				selectedPrivacyOption: (privacyItem) => {
					return privacyItem.options.find((optionItem) => {
						return optionItem.value === formData.value[privacyItem.fieldName];
					});
				},
				selectedPrivacyLabel: (privacyItem) => {
					let selectedOption = privacyItem.options.find((optionItem) => {
						return optionItem.value === formData.value[privacyItem.fieldName];
					});

					return selectedOption ? selectedOption.label : '';
				},
				updatePrivacy: (fieldName, fieldValue) => {
					formData.value[fieldName] = fieldValue;
					submitForm(fieldName);
				},
				submitForm: submitForm
			};
		},
		components: {
			Listbox: Listbox,
			ListboxButton: ListboxButton,
			ListboxOptions: ListboxOptions,
			ListboxOption: ListboxOption,
			Toolbar: Toolbar
		}
	});
</script>
