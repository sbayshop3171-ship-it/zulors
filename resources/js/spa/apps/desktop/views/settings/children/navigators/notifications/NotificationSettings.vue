<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.notification_settings')"></PageTitle>
    </div>

    <div class="mb-8">
        <h6 class="text-par-s text-lab-sc" v-html="$t('settings.forms.notif_settings.page_desc')"></h6>
    </div>
    
    <div class="mb-12">
        <div class="mb-3">
            <SectionLink
                iconName="notification-box"
                v-bind:link="{ name: 'settings_push_notifications' }"
                v-bind:captionText="$t('settings.forms.notif_settings.push_notifications_helper')"
            v-bind:titleText="$t('settings.forms.notif_settings.push_notifications')"></SectionLink>
        </div>

        <div class="block">
            <SectionLink
                iconName="mail-04"
                v-bind:link="{ name: 'settings_email_notifications' }"
                v-bind:captionText="$t('settings.forms.notif_settings.email_notifications_helper')"
            v-bind:titleText="$t('settings.forms.notif_settings.email_notifications')"></SectionLink>
        </div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SectionLink from '@D/components/forms/SectionLink.vue';

    export default defineComponent({
        components: {
            SectionLink: SectionLink,
            PageTitle: PageTitle
        }
    });
</script>