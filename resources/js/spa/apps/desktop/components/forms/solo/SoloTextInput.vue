<template>
    <label v-if="hasLabel" class="mb-2 text-cap-l block text-lab-pr">
        {{ labelText }}
    </label>
    <textarea
        v-on:input="updateModelValue"
        v-bind:value="modelValue"
        v-bind:classes="classes"
        v-bind:placeholder="placeholder"
        class="resize-none border border-bord-tr rounded-xl bg-fill-qt w-full pl-4 pr-12 leading-5 pt-3 text-par-n text-lab-pr2 max-h-96 overflow-y-auto min-h-20 outline-hidden placeholder:font-light placeholder:text-par-m"
    v-bind:name="name"></textarea>

    <div v-if="hasFeedback" class="flex justify-between">
        <span v-if="$slots.feedbackInfo" class="inline-flex">
            <span class="text-cap-l text-lab-sc">
                <slot name="feedbackInfo"></slot>
            </span>
        </span>

        <span v-if="textLength" v-bind:class="['ml-4 text-cap-l', (modelValue.length > textLength) ? 'text-red-900' : 'text-lab-sc']">
            {{ modelValue.length }}/{{ textLength }}
        </span>
    </div>
    <template v-if="inputError">
        <p class="text-cap-l text-red-900 px-4 py-3.5 bg-red-900/10 rounded-md mt-3">{{ inputError }}</p>
    </template>
</template>

<script>
    import { defineComponent, ref } from 'vue';

    export default defineComponent({
        props: {
            inputError: {
                type: String,
                default: ''
            },
            modelValue: {
                type: String,
                required: true
            },
            textLength: {
                type: [String, Number],
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
            }
        },
        emits: [
            'update:modelValue'
        ],
        setup: function(props, context) {
            const inputType = ref(props.inputType);

            return {
                updateModelValue: (event) => {
                    context.emit('update:modelValue', event.target.value);
                }
            }
        }
    });
</script>