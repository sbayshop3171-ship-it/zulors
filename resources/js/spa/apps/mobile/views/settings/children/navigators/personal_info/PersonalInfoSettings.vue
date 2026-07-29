<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.personal_info')"></Toolbar>

	<div v-if="! state.isLoading" class="pb-6">
		<div class="px-4 mb-6">
			<p class="text-par-m leading-5 text-lab-sc break-words">
				{{ $t('settings.forms.personal_info.page_desc') }}
			</p>
		</div>

		<div class="mx-4 overflow-hidden rounded-2xl border border-bord-pr bg-bg-pr divide-y divide-bord-pr">
			<SectionLink
				iconName="calendar-check-01"
				v-bind:link="{ name: 'settings_birthdate' }"
				v-bind:captionText="formData.birth_date ?? $t('settings.forms.personal_info.birth_date_helper', { app_name: $embedder('config.app.name')})"
			v-bind:titleText="$t('labels.birth_date')"></SectionLink>

			<SectionLink
				iconName="globe-06"
				v-bind:link="{ name: 'settings_country' }"
				v-bind:captionText="formData.country ?? $t('settings.forms.personal_info.country_helper')"
			v-bind:titleText="$t('settings.forms.personal_info.country')"></SectionLink>

			<SectionLink
				iconName="building-08"
				v-bind:link="{ name: 'settings_city' }"
				v-bind:captionText="formData.city ?? $t('settings.forms.personal_info.residence_city_helper')"
			v-bind:titleText="$t('settings.forms.personal_info.residence_city')"></SectionLink>
		</div>
	</div>

	<div v-else class="flex-center h-64">
		<PrimarySpinAnimation></PrimarySpinAnimation>
	</div>
</template>

<script>
	import { defineComponent, reactive, ref, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import SectionLink from '@M/components/forms/SectionLink.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true
			});

			const formData = ref({});

			onMounted(async () => {
				await colibriAPI().userSettings().getFrom('personal/settings').then((response) => {
					formData.value = response.data.data;
				}).catch((error) => {
					if(error.response) {
						toastError(error.response.data.message);
					}
				});

				state.isLoading = false;
			});

			return {
				state: state,
				formData: formData
			};
		},
		components: {
			Toolbar: Toolbar,
			SectionLink: SectionLink
		}
	});
</script>
