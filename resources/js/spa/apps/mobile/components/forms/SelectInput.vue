<template>
    <label v-if="hasLabel" class="mb-1.5 font-medium block text-lab-pr2 text-par-m px-1">{{ labelText }}</label>

    <Listbox v-bind:modelValue="selectedOption" v-on:update:modelValue="updateValue" class="relative w-full">
        <div class="relative mt-1 inline-block max-w-full">
            <ListboxButton class="cursor-pointer w-full overflow-hidden bg-input-pr rounded-xl px-5 py-3">
                <span class="flex items-center gap-2 w-full justify-between overflow-hidden">
                    <span v-if="selectedOption" class="flex-1 text-lab-pr2 text-ellipsis truncate text-par-m font-medium text-left">
                        {{ selectedOption.label }}
                    </span>
                    <span class="shrink-0">
                        <SvgIcon name="chevron-down" classes="size-icon-small text-lab-tr"/>
                    </span>
                </span>
            </ListboxButton>
            <ListboxOptions class="absolute py-2 overflow-hidden right-0 left-0 w-full mb-2 rounded-2xl backdrop-blur-xs bg-bg-pr/85 shadow-2xl focus:outline-hidden z-50">
                <div v-if="isSearchable" class="block py-2 px-4 border-b border-bord-pr">
                    <div class="relative">
                        <input class="w-full pr-4 pl-10 py-3 text-par-m font-medium text-lab-sc outline-none border border-bord-card rounded-xl" v-model="searchQuery" v-bind:placeholder="$t('labels.search')">
    
                        <div class="absolute top-0 left-4 bottom-0 inline-flex-center text-lab-tr">
                            <span class="size-icon-small">
                                <SvgIcon name="search-lg"/>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="overflow-y-auto max-h-80 divide-y divide-bord-sc">
                    <template v-if="optionsToShow.length">
                        <ListboxOption
                            v-for="(listOption) in optionsToShow"
                            v-bind:key="listOption.value"
                            v-bind:value="listOption"
                        v-slot="{ active, selected }">
                                <span v-bind:class="[((active || selected) ? 'bg-fill-qt' : ''), 'block py-3 px-4 cursor-pointer text-par-n font-medium text-lab-sc']" v-html=" listOption.label"></span>
                        </ListboxOption>
                    </template>
                    <template v-else>
                        <span class="block text-center py-12 px-4 cursor-pointer text-par-n font-medium text-lab-sc">
                            {{ $t('labels.nothing_found') }}
                        </span>
                    </template>
                </div>
            </ListboxOptions>
        </div>
    </Listbox>

    <div v-if="hasFeedback" class="mt-1 px-1">
        <span v-if="$slots.feedbackInfo" class="text-par-s text-lab-tr break-words flex-1 break-all">
            <slot name="feedbackInfo"></slot>
        </span>
    </div>
</template>

<script>
    import { defineComponent, ref, computed, watch } from 'vue';
    import { Listbox, ListboxButton, ListboxOptions, ListboxOption } from '@headlessui/vue';

    export default defineComponent({
        props: {
            hasLabel: {
                type: Boolean,
                default: true
            },
            isSearchable: {
                type: Boolean,
                default: false
            },
            labelText: {
                type: String,
                default: ''
            },
            hasFeedback: {
                type: Boolean,
                default: true
            },
            options: {
                type: Array,
                default: []
            },
            modelValue: {
                type: [String, Number],
                default: ''
            }
        },
        emits: ['update:modelValue'],
        setup: function(props, context) {
            const searchQuery = ref('');
            const selectedOption = computed(() => {
                return props.options.find((option) => {
                    return option.value == props.modelValue;
                });
            });
            
            const searchResult = ref([]);

            watch(searchQuery, (newVal) => {
                searchResult.value = props.options.filter((option) => {
                    return option.label.toLowerCase().includes(newVal.toLowerCase());
                });
            });
            
            return {
                selectedOption: selectedOption,
                optionsToShow: computed(() => {
                    if(searchQuery.value.length > 0) {
                        return searchResult.value;
                    }

                    return props.options;
                }),
                updateValue: function(event) {
                    context.emit('update:modelValue', event.value);

                    searchQuery.value = '';
                    searchResult.value = [];
                },
                searchQuery: searchQuery
            };
        },
        components: {
            Listbox: Listbox,
            ListboxButton: ListboxButton,
            ListboxOptions: ListboxOptions,
            ListboxOption: ListboxOption
        }
    });
</script>