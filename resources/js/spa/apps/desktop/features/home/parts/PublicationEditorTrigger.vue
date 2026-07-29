<template>
    <div class="cursor-pointer flex text-lab-pr2 items-center py-3.5 justify-center gap-2" v-on:click="openPostEditorModal">
        <span class="text-par-l gap-2.5">
            {{ $t('editor.post_text_input_placeholder') }}
        </span>
        <SvgIcon name="pencil-line" type="line" classes="size-4"></SvgIcon>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    export default defineComponent({
        setup: function() {
            return {
                openPostEditorModal: () => {
                    colibriEventBus.emit('post-editor:open');
                }
            };
        }
    });
</script>