<template>
    <div class="select-none z-50 leading-none rounded-xs" v-bind:class="textColor">
        <span class="text-par-s font-normal">{{ $filters.mediaDuration(duration) }}</span>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';

    export default defineComponent({
        props: {
            textColor: {
                type: String,
                default: 'text-white'
            },
            videoDuration: {
                type: Object,
                default: {
                    hours: '00',
                    minutes: '00',
                    seconds: '00'
                }
            }
        },
        setup: function(props) {
            return {
                duration: computed(() => {
                    return props.videoDuration;
                })
            };
        }
    });
</script>
