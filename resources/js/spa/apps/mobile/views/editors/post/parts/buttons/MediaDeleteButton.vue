<template>
    <div class="leading-zero z-10 inline-block bg-bg-pr/80 rounded-full border border-fill-tr cursor-pointer">
        <PrimaryIconButton iconAreaSize="6" iconSize="icon-small" name="x" buttonColor="text-lab-pr"></PrimaryIconButton>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>