<template>
    <div class="cursor-pointer flex items-center justify-between border border-bord-pr px-5 py-4 rounded-xl gap-3">
        <div class="shrink-0 size-icon-normal text-lab-pr">
            <SvgIcon v-bind:name="iconName" v-bind:type="iconType"/>
        </div>
        <div class="flex-1 pr-12">
            <h5 class="text-par-m font-semibold text-lab-pr2">
                {{ titleText }}
            </h5>
            
            <p v-if="$slots.captionText" class="text-par-n text-lab-sc">
                <slot name="captionText"></slot>
            </p>
            <p v-else class="text-par-n text-lab-sc" v-html="captionText"></p>
        </div>
        <div class="leading-zero shrink-0">
            <SecondarySwitcher v-bind:modelValue="modelValue" v-model="switcherValue"></SecondarySwitcher>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, watch } from 'vue';
    import SecondarySwitcher from '@D/components/inter-ui/switchers/SecondarySwitcher.vue';

    export default defineComponent({
        props: {
            iconName: {
                type: String,
                default: ''
            },
            iconType: {
                type: String,
                default: 'line'
            },
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