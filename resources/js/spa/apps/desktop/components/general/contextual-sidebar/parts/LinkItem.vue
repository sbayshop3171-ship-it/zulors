<template>
    <RouterLink v-bind:to="routeData" class="px-4 block" exactActiveClass="active-settings-tab">
        <div class="flex items-center px-3 leading-none h-12 link-item-container">
            <span class="shrink-0">
                <SvgIcon v-bind:type="iconType" v-bind:name="iconName" v-bind:classes="['size-icon-medium', ...linkColor].join(' ')"></SvgIcon>
            </span>
            <span v-bind:class="['ml-2.5', 'text-par-m font-medium', ...linkColor]">
                {{ textLabel }}
            </span>
            <span class="shrink-0 ml-auto">
                <SvgIcon name="chevron-right" classes="size-5 text-lab-sc"></SvgIcon>
            </span>
        </div>
    </RouterLink>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            linkColor: {
                type: Array,
                default: ['text-lab-pr2']
            },
            routeData: {
                type: Object,
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
        }
    });
</script>