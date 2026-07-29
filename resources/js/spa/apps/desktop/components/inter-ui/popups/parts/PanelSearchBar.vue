<template>
    <input
        v-on:input.trim.lazy="inputHandler"
        type="text" 
        class="px-3 placeholder:text-center leading-normal py-1.5 w-full bg-fill-tr border border-transparent smoothing hover:ring-1 ring-fill-pr outline-hidden rounded-lg text-lab-pr placeholder:text-par-n placeholder:text-lab-sc"
    v-bind:placeholder="placeholder">
</template>

<script>
    import { defineComponent, computed, ref } from 'vue';

    export default defineComponent({
        props: {
            placeholder: {
                type: String,
                default: ''
            }
        },
        emit: ['input'],
        setup: function(props, context) {
            return {
                inputHandler: (event) => {
                    context.emit('input', event.target.value);
                }
            };
        }
    });
</script>