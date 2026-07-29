<template>
    <button class="text-par-s cursor-pointer hover:text-brand-900" v-bind:class="[buttonColor]" v-bind:type="buttonType">
        {{ buttonText }}
    </button>
</template>


<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            buttonText: {
                type: String,
                default: 'Label'
            },
            buttonColor: {
                type: String,
                default: 'text-brand-900'
            },
            buttonType: {
                type: String,
                default: 'button'
            }
        }
    });
</script>