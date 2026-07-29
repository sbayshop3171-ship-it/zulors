<template>
    <PrimaryIconButton v-bind:iconName="iconName" iconSize="6"></PrimaryIconButton>
</template>

<script>
    import { defineComponent } from 'vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        props: {
            iconName: {
                type: String,
                default: 'dots-horizontal'
            }
        },
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>