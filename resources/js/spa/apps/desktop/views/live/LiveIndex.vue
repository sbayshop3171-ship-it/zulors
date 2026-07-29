<template>
    <div class="mt-top-offset block">
        <div class="mb-6">
            <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('labels.create_live_stream')"></PageTitle>
        </div>
        <div class="min-w-content w-content">
            <FeatureIsComingSoon></FeatureIsComingSoon>
        </div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    
    import PageTitle from '@D/components/layout/PageTitle.vue';

    import FeatureIsComingSoon from '@D/components/page-states/coming/FeatureIsComingSoon.vue';

    export default defineComponent({
        components: {
            PageTitle: PageTitle,
            FeatureIsComingSoon: FeatureIsComingSoon
        }
    });
</script>