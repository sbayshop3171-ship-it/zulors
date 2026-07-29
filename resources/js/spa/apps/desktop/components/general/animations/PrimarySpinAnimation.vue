<template>
    <div class="size-8 inline-block">
        <SpinnerIcon v-bind:strokeColor="strokeColor"></SpinnerIcon>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    
    import SpinnerIcon from '@/kernel/vue/components/icons/SpinnerIcon.vue';

    export default defineComponent({
        props: {
            strokeColor: {
                type: String,
                default: 'stroke-brand-900'
            }
        },
        components: {
            SpinnerIcon: SpinnerIcon
        }
    });
</script>