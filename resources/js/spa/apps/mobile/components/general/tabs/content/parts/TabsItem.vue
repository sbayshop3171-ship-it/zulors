<template>
    <div class="mobile-tab-link py-5 cursor-pointer w-full flex-1 px-3 text-center overflow-hidden truncate leading-4 transform-gpu transition-transform duration-150 ease-out active:scale-[0.98]" v-bind:class="[isActive ? 'mobile-tab-link--active' : '']">
        <span v-bind:class="['text-par-m font-semibold transition-colors duration-200 ease-out', (isActive ? 'text-lab-pr2' : 'text-lab-sc')]">
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
