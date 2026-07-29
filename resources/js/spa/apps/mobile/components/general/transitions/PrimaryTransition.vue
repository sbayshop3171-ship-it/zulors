<template>
    <Transition
        v-bind:duration="duration"
        v-bind:name="transitionName"
        v-bind:enter-active-class="enterActiveClass"
        v-bind:enter-from-class="enterFromClass"
        v-bind:enter-to-class="enterToClass"
        v-bind:leave-active-class="leaveActiveClass"
        v-bind:leave-from-class="leaveFromClass"
    v-bind:leave-to-class="leaveToClass">
        <slot></slot>
    </Transition>
</template>
<script>
    import { defineComponent } from 'vue';
    
    export default defineComponent({
        props: {
            transitionName: {
                type: String,
                default: 'fade'
            },
            duration: {
                type: String,
                default: '1000'
            },
            enterActiveClass: {
                type: String,
                default: 'transition duration-100 ease-out',
            },
            enterFromClass: {
                type: String,
                default: 'transform scale-95 opacity-0',
            },
            enterToClass: {
                type: String,
                default: 'transform scale-100 opacity-100',
            },
            leaveActiveClass: {
                type: String,
                default: 'transition duration-75 ease-in',
            },
            leaveFromClass: {
                type: String,
                default: 'transform scale-100 opacity-100',
            },
            leaveToClass: {
                type: String,
                default: 'transform scale-95 opacity-0',
            }
        }
    });
</script>