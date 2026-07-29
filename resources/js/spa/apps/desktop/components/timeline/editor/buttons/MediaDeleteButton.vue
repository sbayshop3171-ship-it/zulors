<template>
    <div class="leading-none z-10 inline-block bg-bg-pr/80 rounded-full border border-fill-tr cursor-pointer">
        <PrimaryIconButton name="x"></PrimaryIconButton>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>