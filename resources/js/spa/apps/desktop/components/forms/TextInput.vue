<template>
    <div class="block">
        <label v-if="hasLabel" class="mb-2 font-medium block text-lab-pr2 text-par-m px-1">
            {{ labelText }}
            
            <span v-if="labelTextBrackets.length" class="text-lab-sc">
                ({{ labelTextBrackets }})
            </span>
        </label>

        <div v-if="asText" class="block">
            <textarea
                v-on:input="updateModelValue"
                v-bind:value="modelValue"
                class="block w-full bg-input-pr rounded-xl border min-h-24 outline-hidden font-normal text-par-m text-lab-pr px-5 py-4"
                v-bind:classes="classes"
                v-bind:class="[hasErrors ? 'bg-transparent border-red-900' : 'bg-edge-pr border-transparent']"
                v-bind:placeholder="placeholder"
            v-bind:name="name"></textarea>
        </div>
        <div v-else class="block relative">
            <input
                v-on:input="updateModelValue"
                v-bind:value="modelValue"
                class="block w-full bg-input-pr border rounded-xl outline-hidden text-par-m font-normal text-lab-pr px-5 py-4 pr-14"
                v-bind:classes="classes"
                v-bind:class="[hasErrors ? 'bg-transparent border-red-900' : 'bg-edge-pr border-transparent']"
                v-bind:placeholder="placeholder"
                v-bind:name="name"
            v-bind:type="inputType">

            <template v-if="hasErrors || isPassword">
                <span class="absolute right-0 top-0 bottom-0 px-4 inline-flex items-center gap-2">
                    <button v-if="hasErrors" class="size-icon-normal cursor-pointer" type="button">
                        <SvgIcon name="info-circle" type="line" classes="size-full text-red-900"></SvgIcon>
                    </button>
                    <button v-if="isPassword" v-on:click="switchPasswordType" class="size-icon-normal cursor-pointer" type="button">
                        <template v-if="inputType == 'password'">
                            <SvgIcon name="eye" type="solid" classes="size-full text-bord-sc"></SvgIcon>
                        </template>
                        <template v-else>
                            <SvgIcon name="eye-off" type="solid" classes="size-full text-bord-sc"></SvgIcon>
                        </template>
                    </button>
                </span>
            </template>
        </div>
        <div v-if="hasFeedback" class="flex justify-between mt-1 overflow-hidden px-1">
            <span v-if="$slots.feedbackInfo" class="inline-flex overflow-hidden break-all mr-6">
                <span v-if="$slots.feedbackIcon" class="mr-2.5">
                    <slot name="feedbackIcon"></slot>
                </span>
                <span class="text-par-s text-lab-sc break-words">
                    <slot name="feedbackInfo"></slot>
                </span>
            </span>

            <span v-if="textLength" class="ml-auto font-mono" v-bind:class="['ml-4 text-par-s', (modelValue.length > textLength) ? 'text-red-900' : 'text-lab-sc']">
                {{ modelValue.length }}/{{ textLength }}
            </span>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, computed } from 'vue';

    export default defineComponent({
        props: {
            asText: {
                type: Boolean,
                default: false
            },
            textLength: {
                type: [Number, String],
                default: ''
            },
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
            inputType: {
                type: String,
                default: 'text'
            },
            inputErrors: {
                type: Array,
                default: []
            },
            isPassword: {
                type: Boolean,
                default: false
            },
            placeholder: {
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
            }
        },
        emits: [
            'update:modelValue'
        ],
        setup: function(props, context) {
            const inputType = ref(props.inputType);
            const inputErrors = computed(() => {
                return props.inputErrors;
            });

            return {
                modelValue: computed(() => {
                    return props.modelValue;
                }),
                inputType: inputType,
                inputErrors: inputErrors,
                switchPasswordType: () => {
                    inputType.value = ((inputType.value == 'password') ? 'text' : 'password');
                },
                updateModelValue: (event) => {
                    context.emit('update:modelValue', event.target.value);
                },
                hasErrors: computed(() => {
                    return inputErrors.value.length > 0;
                })
            }
        }
    });
</script>