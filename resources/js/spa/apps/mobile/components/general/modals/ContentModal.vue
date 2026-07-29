<template>
    <Backdrop>
        <div class="py-12 px-6 h-full flex flex-col justify-center">
            <PrimaryTransition>
                <div v-if="renderModal" class="popup-background-tr w-full rounded-xl origin-top">
                    <slot></slot>
                </div>
            </PrimaryTransition>
        </div>
    </Backdrop>
</template>


<script>
    import { defineComponent, ref, onMounted, computed } from 'vue';
    
    import Backdrop from '@M/components/general/modals/Backdrop.vue';

    export default defineComponent({
        setup: function(props, context) {
            const renderModal = ref(false);

            onMounted(() => {
                setTimeout(() => {
                    renderModal.value = true;
                }, 200);
            });
            
            return {
                renderModal: renderModal
            }
        },
        components: {
            Backdrop: Backdrop
        }
    });
</script>