<template>
    <div class="cursor-pointer flex items-center justify-between gap-3 px-4 py-2">
        <div class="flex-1 pr-12">
            <h5 class="text-par-m font-bold text-lab-pr2">
                {{ titleText }}
            </h5>
            
            <p v-if="$slots.captionText" class="text-par-m text-lab-sc">
                <slot name="captionText"></slot>
            </p>
            <p v-else class="text-par-m text-lab-sc" v-html="captionText"></p>
        </div>
        <div class="leading-zero shrink-0">
            <SecondarySwitcher v-bind:modelValue="modelValue" v-model="switcherValue"></SecondarySwitcher>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, watch } from 'vue';
    import SecondarySwitcher from '@M/components/inter-ui/switchers/SecondarySwitcher.vue';

    export default defineComponent({
        props: {
            titleText: {
                type: String,
                default: ''
            },
            captionText: {
                type: String,
                default: ''
            },
            modelValue: {
                type: Boolean,
                default: true
            }
        },
        emits: ['update:modelValue'],
        setup: function(props, context) {
            const switcherValue = ref(props.modelValue);

            watch(switcherValue, (newValue) => {
                context.emit('update:modelValue', newValue);
            });

			return {
				switcherValue: switcherValue
			};
		},
        components: {
            SecondarySwitcher: SecondarySwitcher
        }
    });
</script>