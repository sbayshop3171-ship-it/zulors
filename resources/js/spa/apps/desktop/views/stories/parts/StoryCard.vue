<template>
    <div class="h-full w-full flex items-center relative overflow-hidden opacity-20 border border-[#444444] cursor-pointer hover:opacity-100 bg-center bg-no-repeat bg-cover transition-all">
        <div class="absolute inset-0 bg-black/60  backdrop-blur-xs"></div>
        <div class="flex flex-col items-center w-full z-10">
            <div class="size-24 rounded-full bg-purple-50 mb-2 overflow-hidden">
                <img v-bind:src="storyAuthor.avatar_url" alt="Avatar">
            </div>
            <h4 class="text-par-l text-white font-medium">
                {{ storyAuthor.name }}
            </h4>
            <p class="text-par-s text-white/80 font-medium">
                {{ storyTime }}
            </p>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    export default defineComponent({
        props: {
            storyItem: {
                type: Object,
                required: true
            }
        },
        setup: function(props) {
            return {
                storyAuthor: computed(() => {
                    return props.storyItem.relations.user;
                }),
                storyTime: computed(() => {
                    return props.storyItem.relations.frames[0].date.time_ago;
                })
            }
        }
    });
</script>