<template>
    <svg class="svg-icon" v-bind:class="classes" v-html="iconCode" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"></svg>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { UIIconsMap } from '@/assets/icons/index.js';

    export default defineComponent({
        props: {
            classes: {
                default: 'size-full',
                type: String
            },
            type: {
                default: 'solid',
                type: String
            },
            name: {
                default: '',
                type: String
            }
        },
        setup: function(props) {
            const iconCode = computed(() => {
                return UIIconsMap[props.type][props.name];
            });

            return {
                iconCode: iconCode
            };
        }
    });
</script>