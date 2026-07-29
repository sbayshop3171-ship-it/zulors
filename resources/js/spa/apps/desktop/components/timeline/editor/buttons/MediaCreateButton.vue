<template>
    <button class="size-icon-medium disabled:opacity-70 outline-hidden smoothing hover:text-lab-pr cursor-pointer text-lab-pr3" type="button" v-bind:disabled="disabled">
        <SvgIcon v-bind:type="iconType" v-bind:name="iconName"></SvgIcon>
    </button>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            iconName: {
                type: String,
                default: ''
            },
            iconType: {
                type: String,
                default: 'line'
            },
            disabled: {
                type: Boolean,
                default: false
            }
        }
    });
</script>