<template>
    <div class="text-lab-sc text-par-n mb-4 px-7 font-medium" v-html="textLabel">
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            textLabel: {
                type: String,
                default: ''
            }
        },
        setup: function() {
        }
    });
</script>