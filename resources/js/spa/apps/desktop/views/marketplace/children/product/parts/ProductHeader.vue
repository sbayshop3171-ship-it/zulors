<template>
	<PageHeader v-bind:hasBack="true" v-bind:titleText="$t('market.product_title')">
    </PageHeader>
</template>

<script>
    import { defineComponent, reactive } from 'vue';

	import PageHeader from '@D/components/layout/PageHeader.vue';

    export default defineComponent({
		components: {
			PageHeader: PageHeader
		}
    });
</script>