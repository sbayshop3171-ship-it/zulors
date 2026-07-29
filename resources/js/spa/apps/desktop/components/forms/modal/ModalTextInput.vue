<template>
    <div class="block">
        <label v-if="hasLabel" class="mb-1 font-normal block text-lab-pr3 text-par-s">
            {{ labelText }}
        </label>
        <div class="block relative bg-fill-qt overflow-hidden rounded-xl border border-bord-tr">
			<input
				v-bind:type="inputType"
				v-on:input="updateModelValue"
                v-bind:value="modelValue"
				v-bind:placeholder="placeholder"
                v-bind:name="name"
			class="w-full text-par-n h-12 bg-transparent outline-hidden pl-4 font-medium placeholder:text-lab-tr placeholder:text-par-n text-lab-pr2">

            <div v-if="modelValue" class="absolute right-0 top-0 bottom-0 size-12 inline-flex-center">
                <PrimaryIconButton
                    v-on:click="$emit('clear')"
                iconName="x"></PrimaryIconButton>
            </div>
        </div>
        <template v-if="hasErrors">
            <div v-for="errorMessage in inputErrors" class="overflow-hidden">
                <span class="text-cap-l text-red-900 break-words">
                    ⚑ {{ errorMessage }}
                </span>
            </div>
        </template>
        <template v-else>
            <div v-if="hasFeedback" class="flex justify-between overflow-hidden">
                <span v-if="$slots.feedbackInfo" class="mr-6">
                    <span class="text-cap-l text-lab-sc break-words">
                        <slot name="feedbackInfo"></slot>
                    </span>
                </span>
    
                <span v-if="textLength" class="ml-auto font-mono mt-1" v-bind:class="['ml-4 text-cap-l', (modelValue.length > textLength) ? 'text-red-900' : 'text-lab-sc']">
                    {{ modelValue.length }}/{{ textLength }}
                </span>
            </div>
        </template>
    </div>
</template>

<script>
    import { defineComponent, ref, computed } from 'vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        props: {
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
            inputType: {
                type: String,
                default: 'text'
            },
            inputErrors: {
                type: Array,
                default: []
            },
            placeholder: {
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
            'update:modelValue',
            'clear'
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
                updateModelValue: (event) => {
                    context.emit('update:modelValue', event.target.value);
                },
                hasErrors: computed(() => {
                    return inputErrors.value.length > 0;
                })
            }
        },
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>