<template>
    <div v-bind:class="['grid', gridCols]">
        <slot></slot>
    </div>
</template>

<script>
    import { defineComponent, ref } from 'vue';

    export default defineComponent({
        props: {
            cols: {
                type: Number,
                default: 3
            }
        },
        setup: function(props) {
            const colsMap = {
                2: 'grid-cols-2',
                3: 'grid-cols-3',
                4: 'grid-cols-4',
            };

            return {
                gridCols: colsMap[props.cols]
            }
        }
    });
</script>