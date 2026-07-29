<template>
    <img v-bind:src="$asset(iconUrl)" v-bind:class="classes" alt="Document Icon" loading="lazy">
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { FileFormatIconsMap } from '@/assets/file-format/index.js';

    export default defineComponent({
        props: {
            classes: {
                default: 'w-full',
                type: String
            },
            extension: {
                default: 'text',
                type: String
            }
        },
        setup: function(props) {
            const iconUrl = computed(() => {
                let path = FileFormatIconsMap[props.extension];

                if(!path) {
                    path = FileFormatIconsMap['text'];
                }

                return path;
            });

            return {
                iconUrl: iconUrl
            };
        }
    });
</script>