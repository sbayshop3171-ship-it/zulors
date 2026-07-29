<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.hotkeys_settings')"></PageTitle>
    </div>
	<div>
		<FeatureIsComingSoon></FeatureIsComingSoon>
	</div>
</template>

<script>
    import { defineComponent  } from 'vue';

    import PageTitle from '@D/components/layout/PageTitle.vue';
	import FeatureIsComingSoon from '@D/components/page-states/coming/FeatureIsComingSoon.vue';
    export default defineComponent({
        setup: function() {
            return {
            };
        },
        components: {
            PageTitle: PageTitle,
			FeatureIsComingSoon: FeatureIsComingSoon
        }
    });
</script>