<template>
    <div class="flex overflow-hidden items-center">
        <div class="flex-1">
            <input v-on:input="updateModelValue"
                v-bind:value="modelValue"
                class="block w-full bg-transparent outline-hidden text-par-l text-lab-pr h-12 px-4 placeholder:text-lab-tr focus:placeholder:text-lab-sc"
            v-bind:placeholder="placeholder">
        </div>
        <div v-if="removable" class="shrink-0 size-11 inline-flex items-center justify-center">
            <ClearButton v-on:click="$emit('remove')"></ClearButton>
        </div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    import ClearButton from '@M/components/inter-ui/buttons/ClearButton.vue';

    export default defineComponent({
        props: {
            removable: {
                type: Boolean,
                default: true
            },
            placeholder: {
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
            return {
                updateModelValue: (event) => {
                    context.emit('update:modelValue', event.target.value);
                }
            }
        },
        components: {
            ClearButton: ClearButton
        }
    });
</script>