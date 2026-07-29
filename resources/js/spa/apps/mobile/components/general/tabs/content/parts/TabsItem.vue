<template>
    <div class="py-5 cursor-pointer w-full flex-1 px-3 text-center overflow-hidden truncate leading-4" v-bind:class="[isActive ? 'active-tab-link' : '']">
        <span v-bind:class="['text-par-m font-semibold', (isActive ? 'text-lab-pr2' : 'text-lab-sc')]">
            <slot></slot>
        </span>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            isActive: {
                type: Boolean,
                default: false
            }
        }
    });
</script>