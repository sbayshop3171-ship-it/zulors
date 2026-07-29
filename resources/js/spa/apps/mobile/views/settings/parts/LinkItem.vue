<template>
    <RouterLink v-bind:to="routeData" class="block" exactActiveClass="active-settings-tab">
        <NavItem v-bind:textLabel="textLabel" v-bind:iconName="iconName" v-bind:iconType="iconType" v-bind:linkColor="linkColor"></NavItem>
    </RouterLink>
</template>

<script>
    import { defineComponent } from 'vue';

    import NavItem from '@M/views/settings/parts/NavItem.vue';

    export default defineComponent({
        props: {
            linkColor: {
                type: Array,
                default: ['text-lab-pr2']
            },
            routeData: {
                type: [Object, String],
                default: {}
            },
            iconType: {
                type: String,
                default: 'line'
            },
            iconName: {
                type: String,
                default: ''
            },
            textLabel: {
                type: String,
                default: ''
            }
        },
        components: {
            NavItem: NavItem
        }
    });
</script>