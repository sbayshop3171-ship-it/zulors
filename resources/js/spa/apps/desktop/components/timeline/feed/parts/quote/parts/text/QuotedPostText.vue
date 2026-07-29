<template>
    <div class="block">
        <div class="line-clamp-[7] markdown-text leading-5 text-lab-pr2 text-par-n font-normal markdown-text break-words" v-html="$mdInline(postContent)"></div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            postContent: {
                type: String,
                default: ''
            }
        }
    });
</script>