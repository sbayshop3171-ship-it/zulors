<template>
    <TabsItem v-bind:isActive="isActive">
        <slot></slot>
    </TabsItem>
</template>

<script>
    import { defineComponent } from 'vue';
	
	import TabsItem from '@M/components/general/tabs/content/parts/TabsItem.vue';
	
    export default defineComponent({
        props: {
            isActive: {
                type: Boolean,
                default: false
            }
        },
		components: {
			TabsItem: TabsItem
		}
    });
</script>