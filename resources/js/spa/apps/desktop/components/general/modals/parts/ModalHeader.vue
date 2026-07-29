<template>
    <div class="leading-none">
        <div class="max-w-full overflow-hidden px-4 py-4 text-center px-16">
            <ModalTitle v-bind:title="modalTitle"></ModalTitle>
        </div>
        <div v-if="hasTitleDivider" class="h-px bg-bord-pr"></div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

	import ModalTitle from '@D/components/general/modals/parts/ModalTitle.vue';

    export default defineComponent({
        props: {
            modalTitle: {
                type: String,
                default: ''
            },
            hasTitleDivider: {
                type: Boolean,
                default: true
            }
        },
        components: {
            ModalTitle: ModalTitle
        }
    });
</script>