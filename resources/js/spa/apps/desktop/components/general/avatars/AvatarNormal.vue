<template>
    <div class="size-normal-avatar relative">
        <div class="size-full bg-bg-pr rounded-full overflow-hidden" v-bind:class="[hasBorder ? 'border border-bord-pr' : '']">
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
            },
            hasBorder: {
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