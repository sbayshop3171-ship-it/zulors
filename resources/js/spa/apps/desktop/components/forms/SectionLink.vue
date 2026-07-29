<template>
    <div class="block">
        <RouterLink v-bind:to="link" class="cursor-pointer gap-3 flex items-center justify-between border border-bord-pr px-5 py-4 rounded-xl">
            <div class="shrink-0 size-icon-normal text-lab-pr">
                <SvgIcon v-bind:name="iconName" v-bind:type="iconType"/>
            </div>
            <div class="flex-1">
                <h5 class="text-par-n font-semibold text-lab-pr2">
                    {{ titleText }}
                </h5>
                <p class="text-par-s text-lab-sc" v-html="captionText"></p>
            </div>
            <div class="shrink-0 size-icon-small text-lab-sc">
                <SvgIcon name="chevron-right"/>
            </div>
        </RouterLink>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            iconName: {
                type: String,
                default: ''
            },
            iconType: {
                type: String,
                default: 'line'
            },
            link: {
                type: Object,
                default: {}
            },
            titleText: {
                type: String,
                default: ''
            },
            captionText: {
                type: String,
                default: ''
            }
        }
    });
</script>