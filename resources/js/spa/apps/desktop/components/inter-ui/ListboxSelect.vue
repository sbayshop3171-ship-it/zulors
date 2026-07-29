<template>
    <Listbox v-bind:modelValue="selectedOption" v-on:update:modelValue="updateValue" class="relative max-w-full">
        <div class="relative inline-block max-w-full">
            <ListboxButton class="cursor-pointer max-w-full overflow-hidden text-cap-l">
                <span class="flex items-center overflow-hidden">
                    <span class="flex-1 text-lab-sc text-ellipsis truncate font-medium">
                        {{ selectedOption.label }}
                    </span>
                    <span class="shrink-0">
                        <SvgIcon name="chevron-down" classes="size-4 text-lab-sc"></SvgIcon>
                    </span>
                </span>
            </ListboxButton>
            <PrimaryTransition>
                <ListboxOptions class="absolute py-2 overflow-hidden right-0 mb-2 w-60 divide-y divide-fill-sc rounded-xl backdrop-blur-xs bg-bg-pr/85 shadow-2xl focus:outline-hidden z-50">
                        <ListboxOption
                            v-for="(listOption) in options"
                            v-bind:key="listOption.value"
                            v-bind:value="listOption"
                        v-slot="{ active, selected }">
                            
                            <span v-bind:class="[((active || selected) ? 'bg-fill-qt' : ''), 'block py-2 px-4 cursor-pointer text-par-s text-lab-pr']">
                                {{ listOption.label }}
                            </span>
                    </ListboxOption>
                </ListboxOptions>
            </PrimaryTransition>
        </div>
    </Listbox>
</template>

<script>
    import { defineComponent, computed, ref } from 'vue';
    import { Listbox, ListboxButton, ListboxOptions, ListboxOption } from '@headlessui/vue';

    export default defineComponent({
        props: {
            modelValue: {
                type: String,
                default: ''
            },
            options: {
                type: Array,
                default: []
            }
        },
        emits: ['update:modelValue'],
        setup: function(props, context) {

            const selectedOption = computed(() => {
                return props.options.find((option) => {
                    return option.value === props.modelValue;
                });
            });
            
            return {
                selectedOption: selectedOption,
                updateValue: function(event) {
                    context.emit('update:modelValue', event.value);
                }
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