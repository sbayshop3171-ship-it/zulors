<template>
    <div class="border border-bord-sc rounded-2xl">
        <div class="p-6">
            <div class="inline-block relative">
                <AvatarMedium v-bind:avatarSrc="userData.avatar_url"></AvatarMedium>
                <div class="absolute bottom-1 -right-2.5 size-6 bg-bg-pr inline-flex items-center justify-center border-2 z-20 border-fill-pr rounded-full">
                    <SvgIcon name="check-verified-02" classes="text-brand-900 size-4"></SvgIcon>
                </div>
            </div>
            <h4 class="text-par-l text-lab-pr2 font-semibold">
                {{ $t('settings.forms.verification_complete.title') }}
            </h4>
            <p class="text-par-s text-lab-sc" v-html="$t('settings.forms.verification_complete.desc')"></p>
        </div>
        <Border height="h-4" opacity="opacity-80"></Border>
        <div class="block px-6 py-4 mb-6">
            <InfoList>
                <InfoListItem
                    iconName="user-01"
                    v-bind:labelText="$t('settings.forms.verification_complete.username')"
                    v-bind:contentText="$t('settings.forms.verification_complete.username_helper', { username: userData.username })"></InfoListItem>
                <InfoListItem
                    iconName="check-verified-02"
                    v-bind:labelText="$t('settings.forms.verification_complete.verification_date')"
                v-bind:contentText="$t('settings.forms.verification_complete.verification_date_helper', { date: userData.verification.date })"></InfoListItem>
            </InfoList>
        </div>
        <div class="flex gap-4 px-6 pb-6">
			<a v-bind:href="{ name: 'home_index' }">
				<PrimaryPillButton v-bind:buttonText="$t('labels.return_home')"></PrimaryPillButton>
			</a>
        </div>
    </div>
</template>

<script>
    import { defineComponent, inject } from 'vue';

    import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import InfoList from '@D/components/general/info/InfoList.vue';
    import InfoListItem from '@D/components/general/info/InfoListItem.vue';

    export default defineComponent({
        setup: function() {
            return {
                userData: inject('userData')
            }
        },
        components: {
            AvatarMedium: AvatarMedium,
            PrimaryPillButton: PrimaryPillButton,
            InfoList: InfoList,
            InfoListItem: InfoListItem
        }
    });
</script>