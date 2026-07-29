<template>
    <div class="block">
        <div v-on:click="vote(index)" 
                class="px-4 py-2.5 border border-bord-pr overflow-hidden rounded-xl relative"
            v-bind:class="[choiceItem.has_voted_choice ? 'border-brand-900/15' : 'border-bord-pr', (hasVotedPoll) ? 'cursor-default opacity-80' : 'cursor-pointer']">

            <div class="h-full absolute top-0 left-0 rounded-xs overflow-hidden w-full flex">
                <div class="max-w-full h-full bg-bord-pr"
                    v-bind:style="{ width: `${choiceItem.percentage}%` }"
                v-bind:class="[choiceItem.has_voted_choice ? 'bg-brand-900/15' : 'bg-bord-tr']"></div>
            </div>
            <div class="flex justify-between text-lab-sc overflow-hidden items-center">
                <span class="text-par-n font-medium flex-1 truncate mr-4 leading-normal">
                    {{ choiceItem.choice_text }}
                </span>
                <span class="text-par-s" v-bind:class="[choiceItem.has_voted_choice ? 'text-lab-pr font-semibold' : 'text-lab-sc']">{{ choiceItem.percentage }}%</span>
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