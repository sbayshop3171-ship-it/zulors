<template>
    <nav class="flex gap-2 flex-wrap">
        <div v-for="categoryItem in categories"
            class="rounded-full px-3.5 py-2.5 leading-none cursor-pointer"
            v-on:click="selectCategory(categoryItem)"
        v-bind:class="[(categoryItem.id == selected) ? 'bg-badge-pr text-lab-pr' : 'bg-fill-qt text-lab-pr2']">
            <span class="text-par-n font-medium">{{ categoryItem.name }}</span>
        </div>
    </nav>
</template>

<script>
    import { defineComponent } from 'vue';
    
    export default defineComponent({
        props: {
            categories: {
                type: Array,
                required: true
            },
            selected: {
                type: Number,
                default: 0
            }
        },
        emits: ['select'],
        setup: function(props, context) {
            return {
                selectCategory: (categoryItem) => {
                    context.emit('select', categoryItem);
                }
            };
        }
    });
</script>