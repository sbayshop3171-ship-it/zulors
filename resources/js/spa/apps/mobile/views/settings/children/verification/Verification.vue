<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.account_verification')"></Toolbar>

	<div v-if="userData" class="px-4 pb-6">
		<div class="overflow-hidden rounded-2xl border border-bord-pr bg-bg-pr">
			<div class="p-4">
				<div class="relative mb-4 inline-block">
					<AvatarMedium v-bind:avatarSrc="userData.avatar_url"></AvatarMedium>
					<div class="absolute bottom-0 -right-2 size-6 bg-bg-pr inline-flex items-center justify-center border-2 z-20 border-fill-pr rounded-full">
						<SvgIcon name="check-verified-02" v-bind:classes="[isVerified ? 'text-brand-900' : 'text-lab-sc', 'size-4'].join(' ')"></SvgIcon>
					</div>
				</div>

				<template v-if="isVerified">
					<h4 class="mb-1 text-par-l text-lab-pr2 font-semibold">
						{{ $t('settings.forms.verification_complete.title') }}
					</h4>
					<p class="text-par-m leading-5 text-lab-sc break-words" v-html="$t('settings.forms.verification_complete.desc')"></p>
				</template>

				<template v-else>
					<h4 class="mb-1 text-par-l text-lab-pr2 font-semibold">
						{{ $t('settings.forms.verification_settings.title') }}
					</h4>
					<p class="text-par-m leading-5 text-lab-sc break-words" v-html="$t('settings.forms.verification_settings.desc')"></p>
				</template>
			</div>

			<Border height="h-2" opacity="opacity-50"></Border>

			<div v-if="isVerified" class="py-4">
				<InfoList>
					<InfoListItem
						iconName="user-01"
						v-bind:labelText="$t('settings.forms.verification_complete.username')"
						v-bind:contentText="$t('settings.forms.verification_complete.username_helper', { username: userData.username })"></InfoListItem>
					<InfoListItem
						iconName="check-verified-02"
						v-bind:labelText="$t('settings.forms.verification_complete.verification_date')"
					v-bind:contentText="$t('settings.forms.verification_complete.verification_date_helper', { date: verifiedDate })"></InfoListItem>
				</InfoList>
			</div>

			<div v-else class="p-4">
				<div class="rounded-2xl bg-input-pr p-4 mb-4">
					<h4 class="text-par-m text-lab-pr2 font-bold">
						{{ $t('settings.forms.verification_settings.requirements_note.title') }}
					</h4>
					<p class="text-par-s leading-4 text-lab-sc break-words" v-html="$t('settings.forms.verification_settings.requirements_note.desc')"></p>
					<a v-bind:href="$getRoute('verification_rules')" class="text-brand-900 underline text-par-s font-semibold">
						{{ $t('settings.forms.verification_settings.requirements_note.link') }}
					</a>
				</div>

				<a v-bind:href="$config('verification.service_url')" target="_blank" class="block">
					<PrimaryPillButton
						v-bind:buttonFluid="true"
						buttonRole="accent"
					v-bind:buttonText="$t('settings.forms.verification_settings.apply_verification_button')"></PrimaryPillButton>
				</a>
			</div>
		</div>
	</div>

	<div v-else class="flex-center h-64">
		<PrimarySpinAnimation></PrimarySpinAnimation>
	</div>
</template>

<script>
	import { computed, defineComponent } from 'vue';
	import { useAuthStore } from '@M/store/auth/auth.store.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import AvatarMedium from '@M/components/general/avatars/AvatarMedium.vue';
	import InfoList from '@M/components/general/info/InfoList.vue';
	import InfoListItem from '@M/components/general/info/InfoListItem.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();
			const userData = computed(() => {
				return authStore.userData;
			});

			return {
				userData: userData,
				isVerified: computed(() => {
					return Boolean(userData.value?.verification?.status);
				}),
				verifiedDate: computed(() => {
					return userData.value?.verification?.date || '';
				})
			};
		},
		components: {
			Toolbar: Toolbar,
			AvatarMedium: AvatarMedium,
			InfoList: InfoList,
			InfoListItem: InfoListItem,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
