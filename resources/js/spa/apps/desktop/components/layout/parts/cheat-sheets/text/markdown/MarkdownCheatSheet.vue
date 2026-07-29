<template>
    <div class="block">
        <div v-for="textFormatter in textFormatters">
            <div class="mb-2">
                <h4 class="text-par-s font-medium text-brand-900">
                    {{ $t(textFormatter.groupTitle) }}
                </h4>
            </div>
            <div class="mb-6">
                <div v-for="item in textFormatter.items" class="flex text-lab-pr items-center justify-between h-10 border-b border-bord-pr">
                    <div class="flex items-center gap-1 leading-none">
                        <span class="size-icon-small">
                            <SvgIcon v-bind:name="item.icon" v-bind:type="item.iconType" classes="size-full"></SvgIcon>
                        </span>
                        <span class="text-par-s">
                            {{ $t(item.title) }}
                        </span>
                    </div>
                    <div class="shrink-0">
                        <span class="text-par-s">{{ item.symbols }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import { defineComponent, onMounted, onUnmounted, ref } from 'vue';

    export default defineComponent({
        setup: function() {
            const textFormatters = ref([
                {
                    groupTitle: 'labels.text_formatting',
                    items: [
                        {
                            icon: 'bold-01',
                            title: 'labels.bold_text',
                            symbols: '** x **',
                            iconType: 'solid'
                        },
                        {
                            icon: 'italic-01',
                            title: 'labels.italic_text',
                            symbols: '* x *',
                            iconType: 'solid'
                        },
                        {
                            icon: 'strikethrough-01',
                            title: 'labels.strikethrough',
                            symbols: '~~ x ~~',
                            iconType: 'solid'
                        },
                        {
                            icon: 'italic-02',
                            iconType: 'line',
                            title: 'labels.bold_and_italic',
                            symbols: '___ x ___'
                        },
                        {
                            icon: 'underline-01',
                            iconType: 'solid',
                            title: 'labels.text_underline',
                            symbols: '++ x ++'
                        },
                        {
                            icon: 'brush-03',
                            iconType: 'line',
                            title: 'labels.text_highlight',
                            symbols: ':: x ::'
                        }
                    ]
                },
                {
                    groupTitle: 'labels.block_formatting',
                    items: [
                        {
                            icon: 'code-snippet-02',
                            title: 'labels.code_block',
                            symbols: '`` x ``',
                            iconType: 'solid'
                        },
                        {
                            icon: 'image-01',
                            title: 'labels.image_linking',
                            symbols: '![alt text](image-url.png)',
                            iconType: 'line'
                        }
                    ]
                }
            ]);

            onMounted(() => {
                document.body.classList.add('overflow-hidden');
            });

            onUnmounted(() => {
                document.body.classList.remove('overflow-hidden');
            });

            return {
                textFormatters: textFormatters
            };
        }
    });
</script>