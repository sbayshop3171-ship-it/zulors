<template>
    <div class="block">
        <span class="text-lab-sc text-par-s mb-2 inline-block">
            {{ $t('labels.almost_ready_stepper', {current: currentStep, total: totalSteps}) }}
        </span>
        <div class="h-0.5 bg-fill-tr leading-none">
            <div class="h-0.5 bg-brand-900 min-w-4 max-w-full" v-bind:style="{ width: progressWidth }"></div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    export default defineComponent({
        props: {
            currentStep: {
                type: Number,
                default: 0
            },
            totalSteps: {
                type: Number,
                default: 0
            }
        },
        setup: function(props) {
            return {
                progressWidth: computed(() => {
                    if (props.totalSteps === 0) {
                        return '0%'
                    };

                    const roundedPercentage = Math.min(Math.round((props.currentStep / props.totalSteps) * 100), 100);
                    
                    return `${roundedPercentage}%`;
                })
            }
        },
    });
</script>