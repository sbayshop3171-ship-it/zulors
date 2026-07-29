<template>
    <PrimaryIconButton v-bind:iconName="iconName" iconSize="icon-normal" v-bind:iconType="iconType"></PrimaryIconButton>
</template>

<script>
    import { defineComponent } from 'vue';
    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        props: {
            iconName: {
                type: String,
                default: 'dots-horizontal'
            },
            iconType: {
                type: String,
                default: 'solid'
            }
        },
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>