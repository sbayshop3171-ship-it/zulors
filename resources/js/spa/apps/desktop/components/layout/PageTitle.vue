<template>
    <div class="flex gap-2 items-center leading-none">
        <div class="shrink-0 cursor-pointer" v-if="hasBack">
            <span v-on:click="goBack" class="text-lab-pr">
                <SvgIcon name="arrow-left" classes="size-icon-normal"></SvgIcon>
            </span>
        </div>
        <div class="flex-1">
            <h1 class="text-title-3 2xl:text-title-2 font-bold text-lab-pr2 leading-tight">
                {{ titleText }}
            </h1>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref } from 'vue';
    import { useRouter } from 'vue-router';

    export default defineComponent({
        emits: ['back'],
        props: {
            hasBack: {
                type: Boolean,
                default: true
            },
            titleText: {
                type: String,
                default: ''
            },
            backStep: {
                type: Number,
                default: 1
            },
            navigateBack: {
                type: Boolean,
                default: true
            },
            backHome: {
                type: Boolean,
                default: false
            }
        },
        setup: function(props, context) {
            const router = useRouter();

            return {
                goBack: () => {
                    if(props.navigateBack) {
                        if(props.backHome) {
                            router.push({ name: 'home_index' });
                        }
                        else {
                            router.go(-props.backStep);
                        }
                    }
                    else {
                        context.emit('back');
                    }
                }
            }
        }
    });
</script>