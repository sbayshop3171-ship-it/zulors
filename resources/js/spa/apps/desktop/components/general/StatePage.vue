<template>
    <div class="mb-10">
        <div class="mb-4" v-if="$slots.graphic">
            <slot name="graphic"></slot>
        </div>
        <h4 class="text-title-2 font-bold mb-3 leading-7 text-lab-pr">
            <slot name="title"></slot>
        </h4>

        <p class="text-par-s text-lab-sc mb-4">
            <slot name="description"></slot>
        </p>

        <div class="border-b border-edge-pr mb-2"></div>

        <div class="block" v-if="$slots.content">
            <slot name="content"></slot>
        </div>

        <div class="flex justify-between">
            <RouterLink v-bind:to="ctaPrimary" class="text-cap-l text-brand-900 underline font-medium">
                {{ ctaPrimaryLabel }}
            </RouterLink>
            <RouterLink v-bind:to="ctaSecondary" class="text-cap-l text-lab-sc underline">
                {{ ctaSecondaryLabel }}
            </RouterLink>
        </div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            ctaPrimary: {
                type: Object,
                default: { 
                    name: 'home_index'
                }
            },
            ctaSecondary: {
                type: Object,
                default: { 
                    name: 'home_index'
                }
            },
            ctaPrimaryLabel: {
                type: String,
                default: 'Return home'
            },
            ctaSecondaryLabel: {
                type: String,
                default: 'Support center'
            }
        }
    });
</script>