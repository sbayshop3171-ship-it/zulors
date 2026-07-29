<template>
    <RouterLink v-bind:to="link" v-slot="{ isActive }" class="block flex-1">
		<TabsItem v-bind:isActive="isActive">
            <slot></slot>
        </TabsItem>
    </RouterLink>
</template>

<script>
    import { defineComponent } from 'vue';
	import TabsItem from '@M/components/general/tabs/content/parts/TabsItem.vue';
	
    export default defineComponent({
        props: {
            link: {
                type: Object,
                default: { name: 'home_index' }
            }
        },
		components: {
			TabsItem: TabsItem
		}
    });
</script>