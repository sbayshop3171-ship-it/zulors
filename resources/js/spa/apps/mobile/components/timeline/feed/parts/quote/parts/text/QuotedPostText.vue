<template>
    <div class="line-clamp-[7] leading-5 text-lab-pr2 text-par-n font-normal publication-text break-words" v-html="$mdInline(postContent)"></div>
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