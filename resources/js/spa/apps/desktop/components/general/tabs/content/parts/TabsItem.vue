<template>
    <div class="cursor-pointer w-full flex-1 text-center overflow-hidden truncate leading-4" v-bind:class="[sizeClass, isActive ? 'active-tab-link' : '']">
        <span v-bind:class="['font-semibold', (isActive ? 'text-lab-pr2' : 'text-lab-sc')]">
            <slot></slot>
        </span>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            isActive: {
                type: Boolean,
                default: false
            },
            size: {
                type: String,
                default: 'md'
            }
        },
        setup: function(props) {
            const sizemap = {
                md: 'text-par-m px-3 py-5',
                sm: 'text-par-s px-3 py-4',
                lg: 'text-par-l px-3 py-5'
            };

            return {
                sizeClass: sizemap[props.size]
            };
        }
    });
</script>