<template>
    <div class="flex gap-2 items-center leading-none p-4">
        <div class="shrink-0">
            <PageTitle v-bind:hasBack="hasBack" v-bind:titleText="titleText"></PageTitle>
        </div>
		<div class="flex-1 flex justify-end items-center gap-2">
			<slot></slot>
		</div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

	import PageTitle from '@D/components/layout/PageTitle.vue';

    export default defineComponent({
        props: {
			titleText: {
                type: String,
                default: ''
            },
			hasBack: {
                type: Boolean,
                default: false
            },
        },
        setup: function(props) {
            return {
            }
        },
		components: {
			PageTitle: PageTitle
		}
    });
</script>