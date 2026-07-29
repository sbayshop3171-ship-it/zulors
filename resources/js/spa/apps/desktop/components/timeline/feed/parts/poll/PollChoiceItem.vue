<template>
    <div class="block">
        <div v-on:click="vote(index)" class="px-4 py-2.5 border border-bord-pr overflow-hidden rounded-xl relative"
            v-bind:class="[(! choiceItem.has_voted_choice && hasVotedPoll) ? 'cursor-default opacity-80' : 'cursor-pointer']">

            <div class="h-full absolute top-0 left-0 rounded-xs overflow-hidden w-full flex">
                <div class="max-w-full h-full bg-bord-pr smoothing" v-bind:style="{ width: `${choiceItem.percentage}%` }"></div>
            </div>
            <div class="flex gap-2 justify-between items-center leading-none relative z-10">
                <div class="size-4 shrink-0">
                    <template v-if="choiceItem.has_voted_choice">
                        <SvgIcon type="solid" name="check-circle" classes="size-full text-green-900"></SvgIcon>
                    </template>
                    <template v-else>
                        <SvgIcon type="line" name="circle" classes="size-full text-lab-sc"></SvgIcon>
                    </template>
                </div>
                <div class="flex-1 flex justify-between text-lab-pr overflow-hidden items-center">
                    <span class="text-par-m font-medium flex-1 truncate mr-4 leading-normal">
                        {{ choiceItem.choice_text }}
                    </span>
                    <span class="text-par-s font-semibold">{{ choiceItem.percentage }}%</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, ref } from 'vue';

    export default defineComponent({
        props: {
            choiceItem: {
                type: Object,
                default: {}
            },
            index: {
                type: Number,
                default: 0
            },
            hasVotedPoll: {
                type: Boolean,
                default: false
            }
        },
        emits: ['vote'],
        setup: function(props, context) {
            const choiceItem = computed(() => {
                return props.choiceItem;
            });

            return {
                choiceItem,
                vote: function(index) {
                    context.emit('vote', index);
                }
            };
        }
    });
</script>