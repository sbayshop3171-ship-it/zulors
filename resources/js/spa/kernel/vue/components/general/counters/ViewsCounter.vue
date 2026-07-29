<template>
    <div class="inline-flex items-center gap-1">
        <span class="shrink-0">
            <SvgIcon name="eye" classes="size-4 text-lab-tr"></SvgIcon>
        </span>
        <span class="text-lab-sc text-par-s font-mono">
            {{ counterValue }}
        </span>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            counterValue: {
                type: [String, Number],
                default: 0
            }
        }
    });
</script>