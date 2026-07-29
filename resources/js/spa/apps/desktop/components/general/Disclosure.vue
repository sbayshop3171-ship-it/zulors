<template>
    <div v-for="option in disclosureOptions" class="block border-b border-edge-pr">
        <Disclosure v-slot="{ open }">
            <DisclosureButton class="flex w-full py-4 items-center justify-between">
                <span class="text-par-s font-normal text-lab-pr">
                    {{ option.title }}
                </span>

                <span class="shrink-0">
                    <SvgIcon name="chevron-down" v-bind:classes="['size-icon-small text-lab-tr', ((open == true) ? 'rotate-180' : '')].join(' ')"></SvgIcon>
                </span>
            </DisclosureButton>
            <DisclosurePanel class="text-gray-500 text-par-s mb-4">
                {{ option.description }}
            </DisclosurePanel>
        </Disclosure>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'

    export default defineComponent({
        props: {
            disclosureOptions: {
                type: Array,
                default: []
            }
        },
        components: {
            Disclosure: Disclosure,
            DisclosureButton: DisclosureButton,
            DisclosurePanel: DisclosurePanel
        }
    });
</script>