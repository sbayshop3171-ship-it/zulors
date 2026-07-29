<template>
    <RouterLink v-bind:to="{ name: 'mtl_index' }" class="fixed bottom-4 left-4 z-50 bg-black/20 backdrop-blur-2xl leading-normal pb-1 rounded-full px-3">
        <span class="text-cap-s text-white cursor-pointer hover:underline">
            &copy; Zulors. Made with &hearts; by Mansur Terla. &uparrow;
        </span>
    </RouterLink>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        setup: function(props) {
            return {
            };
        }
    });
</script>