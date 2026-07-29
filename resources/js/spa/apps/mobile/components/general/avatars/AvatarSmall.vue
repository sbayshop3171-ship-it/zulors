<template>
    <div class="size-small-avatar relative">
        <div class="size-full bg-bg-pr overflow-hidden"
        v-bind:class="[rounded ? 'rounded-full' : '']">
            <img v-show="isFallback" v-bind:src="$config('user.default_avatar')" alt="Avatar">
            <img v-show="! isFallback" class="w-full" v-on:error="onError" v-bind:src="avatarSrc" alt="Avatar">
        </div>
        <span v-if="unreadIndicator" class="size-2.5 border-2 border-white rounded-full inline-block bg-brand-900 absolute top-0 left-0"></span>
    </div>
</template>

<script>
    import { defineComponent, ref } from 'vue';

    export default defineComponent({
        props: {
            unreadIndicator: {
                type: Boolean,
                default: false
            },
            avatarSrc: {
                type: String,
                default: ''
            },
            rounded: {
                type: Boolean,
                default: true
            }
        },
        setup: function() {
            const isFallback = ref(false);

            return {
                isFallback: isFallback,
                onError: () => {
                    isFallback.value = true;
                }
            }
        }
    });
</script>