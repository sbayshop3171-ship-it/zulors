<template>
    <div class="relative">
        <input
            v-on:input.trim.lazy="inputHandler"
            type="text" 
            class="pl-4 pr-10 h-10 w-full bg-fill-tr text-par-l border-none smoothing focus:bg-fill-sc outline-hidden rounded-lg text-lab-pr placeholder:font-normal placeholder:text-par-l placeholder:text-lab-sc"
        v-bind:placeholder="placeholder">
        <div class="absolute top-0 right-4 bottom-0 inline-flex items-center justify-center">
            <SvgIcon name="search-lg" class="size-icon-small text-lab-sc"></SvgIcon>
        </div>
    </div>
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