<template>
    <div class="size-medium-avatar relative">
        <div class="size-full bg-bg-pr rounded-full overflow-hidden">
            <img v-show="isFallback" v-bind:src="$config('user.default_avatar')" alt="Avatar">
            <img v-show="! isFallback" class="w-full" v-on:error="onError" v-bind:src="avatarSrc" alt="Avatar">
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref } from 'vue';

    export default defineComponent({
        props: {
            avatarSrc: {
                type: String,
                default: ''
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