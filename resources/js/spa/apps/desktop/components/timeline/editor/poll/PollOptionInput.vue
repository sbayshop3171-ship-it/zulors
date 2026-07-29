<template>
    <div class="block">
        <div class="flex bg-fill-qt rounded-xs overflow-hidden px-3 group" v-bind:class="{'ring-1 ring-red-900 bg-transparent': optionData.isInvalid}">
            <div class="flex-1">
                <input
                    v-on:input="updateModelValue"
                    v-bind:value="modelValue"
                    class="block w-full bg-transparent outline-hidden text-par-m text-lab-pr h-11 pr-4 placeholder:text-lab-tr hover:placeholder:text-lab-sc"
                    v-bind:placeholder="placeholder"
                v-bind:name="name">
            </div>
            <div class="shrink-0 h-11 inline-flex items-center">
                <span v-if="textLength" v-bind:class="['text-cap-l group-hover:invisible', (modelValue.length > textLength) ? 'text-red-900' : 'text-lab-sc']">
                    {{ modelValue.length }}/{{ textLength }}
                </span>
                <button v-bind:disabled="!removable" v-on:click="removeOption" type="button" class="hidden group-hover:inline-flex items-center justify-center  ml-2 size-6 bg-fill-sc rounded-full text-lab-tr cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="size-5">
                        <SvgIcon name="x"></SvgIcon>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, computed } from 'vue';

    export default defineComponent({
        props: {
            optionData: {
                type: Object,
                default: 0
            },
            optionIndex: {
                type: Number,
                default: 0
            },
            removable: {
                type: Boolean,
                default: true
            },
            textLength: {
                type: String,
                default: ''
            },
            labelTextBrackets: {
                type: String,
                default: ''
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
                required: true
            }
        },
        emits: ['update:modelValue',  'remove'],
        setup: function(props, context) {
            const optionIndex = computed(() => {
                return props.optionIndex;
            });

            const optionData = computed(() => {
                return props.optionData;
            });

            return {
                optionIndex: optionIndex,
                optionData: optionData,
                updateModelValue: (event) => {
                    context.emit('update:modelValue', event.target.value);
                },
                removeOption: () => {
                    context.emit('remove', optionIndex.value);
                }
            }
        }
    });
</script>