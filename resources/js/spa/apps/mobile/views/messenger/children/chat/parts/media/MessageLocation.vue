<template>
    <a v-bind:href="locationUrl" target="_blank" rel="noopener noreferrer" class="block min-w-56 rounded-xl bg-bg-pr/70 p-3 text-lab-pr2">
        <div class="flex items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-900/10 text-brand-900">
                <SvgIcon type="line" name="marker-pin-01" classes="size-icon-normal"></SvgIcon>
            </span>
            <div class="min-w-0 flex-1">
                <span class="block truncate text-par-s font-semibold">{{ $t('labels.location') }}</span>
                <span class="block truncate text-cap-l text-lab-sc">{{ $t('labels.open') }}</span>
            </div>
        </div>
    </a>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            locationUrl: {
                type: String,
                required: true
            }
        }
    });
</script>
