<template>
    <button v-bind:attrs="$attrs" class="outline-hidden size-icon-medium text-lab-pr cursor-pointer disabled:opacity-60 disabled:cursor-default">
        <SvgIcon v-bind:type="iconType" v-bind:name="iconName"></SvgIcon>
    </button>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            iconName: {
                type: String,
                default: 'image-plus'
            },
            iconType: {
                type: String,
                default: 'line'
            }
        }
    });
</script>
