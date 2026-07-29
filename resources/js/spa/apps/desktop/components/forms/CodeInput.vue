<template>
    <div class="block">
        <label v-if="hasLabel" class="mb-2 font-medium block text-lab-pr2 text-par-s">
            {{ labelText }} <span class="opacity-60">{{ modelValue.length }}/6</span>
            
            <span v-if="labelTextBrackets.length" class="text-lab-sc">
                ({{ labelTextBrackets }})
            </span>
        </label>

        <div class="block">
            <InputOtp v-model="modelValue" v-on:update:modelValue="$emit('update:modelValue', $event)" v-bind:length="6" v-bind:integerOnly="true" class="flex gap-2">
                <template v-slot:default="{ attrs, events }">
                    <div  class="w-12">
                        <input type="text" placeholder="0" v-bind="attrs" v-on="events" class="outline-hidden bg-fill-tr rounded-xs w-full h-14 text-center text-title-1 placeholder:text-fill-pr focus:placeholder:opacity-0"/>
                    </div>
                </template>
            </InputOtp>
        </div>

        <div v-if="hasFeedback">
            <span v-if="$slots.feedbackInfo" class="inline-flex">
                <span v-if="$slots.feedbackIcon" class="mr-2.5">
                    <slot name="feedbackIcon"></slot>
                </span>
                <span class="text-cap-l text-lab-sc">
                    <slot name="feedbackInfo"></slot>
                </span>
            </span>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref } from 'vue';
    import InputOtp from 'primevue/inputotp';

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
            labelTextBrackets: {
                type: String,
                default: ''
            },
            modelValue: {
                type: String,
                default: ''
            }
        },
        setup: function(props) {
            const modelValue = ref(props.modelValue);

            return {
                modelValue: modelValue
            }
        },
        components: {
            InputOtp: InputOtp
        }
    });
</script>
