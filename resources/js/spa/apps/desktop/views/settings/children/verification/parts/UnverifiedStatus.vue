<template>
    <div class="block p-6">
		<div class="inline-block relative">
			<AvatarMedium v-bind:avatarSrc="userData.avatar_url"></AvatarMedium>
			<div class="absolute bottom-1 -right-2.5 size-6 bg-bg-pr inline-flex items-center justify-center border-2 z-20 border-fill-pr rounded-full">
				<SvgIcon name="check-verified-02" classes="text-lab-sc size-4"></SvgIcon>
			</div>
		</div>
		<div class="mb-4">
			<h4 class="text-par-l text-lab-pr2 font-semibold">
				{{ $t('settings.forms.verification_settings.title') }}
			</h4>
			<p class="text-par-s text-lab-sc" v-html="$t('settings.forms.verification_settings.desc')"></p>
		</div>
		<div class="block bg-input-pr rounded-lg p-4 mb-4">
			<h4 class="text-par-n text-lab-pr2 font-bold">
				{{ $t('settings.forms.verification_settings.requirements_note.title') }}
			</h4>
			<p class="text-par-s text-lab-sc" v-html="$t('settings.forms.verification_settings.requirements_note.desc')"></p>

			<a v-bind:href="$getRoute('verification_rules')" class="text-brand-900 underline text-par-s font-semibold">
				{{ $t('settings.forms.verification_settings.requirements_note.link') }}
			</a>
		</div>

		<div class="flex gap-4">
			<a v-bind:href="$config('verification.service_url')" target="_blank">
				<PrimaryPillButton v-bind:buttonText="$t('settings.forms.verification_settings.apply_verification_button')"></PrimaryPillButton>
			</a>

			<RouterLink v-bind:to="{ name: 'home_index' }">
				<PrimaryPillButton buttonRole="danger" v-bind:buttonText="$t('labels.cancel')"></PrimaryPillButton>
			</RouterLink>
		</div>
	</div>
</template>

<script>
    import { defineComponent, inject } from 'vue';

    import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        setup: function() {
            return {
                userData: inject('userData')
            }
        },
        components: {
            AvatarMedium: AvatarMedium,
			PrimaryPillButton: PrimaryPillButton
        }
    });
</script>