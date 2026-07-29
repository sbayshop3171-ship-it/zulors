<template>
    <Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.notification_settings')"></Toolbar>
    <SettingsDesc v-bind:text="$t('settings.forms.notif_settings.page_desc')">
    </SettingsDesc>
    <div class="mb-3">
        <Border height="h-2" opacity="opacity-50"></Border>
    </div>
    <SectionLink
        iconName="notification-box"
        v-bind:link="{ name: 'settings_push_notifications' }"
        v-bind:captionText="$t('settings.forms.notif_settings.push_notifications_helper')"
    v-bind:titleText="$t('settings.forms.notif_settings.push_notifications')"></SectionLink>
    <div class="my-3">
        <Border height="h-2" opacity="opacity-50"></Border>
    </div>
    <SectionLink
        iconName="mail-04"
        v-bind:link="{ name: 'settings_email_notifications' }"
        v-bind:captionText="$t('settings.forms.notif_settings.email_notifications_helper')"
    v-bind:titleText="$t('settings.forms.notif_settings.email_notifications')"></SectionLink>
</template>

<script>
    import { defineComponent } from 'vue';
    import Toolbar from '@M/components/layout/Toolbar.vue';
    import SettingsDesc from '@M/views/settings/parts/SettingsDesc.vue';
    import SectionLink from '@M/components/forms/SectionLink.vue';

    export default defineComponent({
        components: {
            SectionLink: SectionLink,
            Toolbar: Toolbar,
            SettingsDesc: SettingsDesc
        }
    });
</script>