<template>
    <div class="block">
        <label v-if="hasLabel" class="mb-1 font-medium block text-lab-pr text-par-m">{{ labelText }}</label>
        <div v-if="hasFeedback" class="block mb-3">
            <span v-if="$slots.feedbackInfo" class="inline-flex">
                <span class="text-par-s text-lab-tr">
                    <slot name="feedbackInfo"></slot>
                </span>
            </span>
        </div>
        <div class="block">
            <RadioGroup v-model="selectedOption" class="flex flex-col gap-4">
                <RadioGroupOption v-for="radioOption in options"  v-bind:key="radioOption.value" v-slot="{ checked }" v-bind:value="radioOption.value">
                    <span class="truncate flex items-center gap-2 cursor-pointer text-par-m text-lab-pr2">
                        <span class="flex-1">
                            {{ radioOption.label }}
                        </span>
                        <span v-if="checked" class="size-icon-normal shrink-0 text-lab-pr2">
                            <SvgIcon name="circle-radio" type="solid"></SvgIcon>
                        </span>
                        <span v-else class="size-icon-normal shrink-0 text-lab-sc">
                            <SvgIcon name="circle" type="line"></SvgIcon>
                        </span>
                    </span>
                </RadioGroupOption>
            </RadioGroup>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, watch } from 'vue';
    import { RadioGroup, RadioGroupOption } from '@headlessui/vue';

    export default defineComponent({
        props: {
            hasLabel: {
                type: Boolean,
                default: true
            },
            hasFeedback: {
                type: Boolean,
                default: true
            },
            labelText: {
                type: String,
                default: ''
            },
            classes: {
                type: String,
                default: ''
            },
            name: {
                type: String,
                default: ''
            },
            modelValue: {
                type: String,
                default: ''
            },
            options: {
                type: Array,
                default: []
            }
        },
        emits: ['update:modelValue'],
        components:{
            RadioGroup: RadioGroup,
            RadioGroupOption: RadioGroupOption
        },
        setup: function(props, context) {
            const selectedOption = ref(props.modelValue);

            watch(selectedOption, (value) => {
                context.emit('update:modelValue', value);
            });

            return {
                selectedOption: selectedOption
            }
        }
    });
</script>