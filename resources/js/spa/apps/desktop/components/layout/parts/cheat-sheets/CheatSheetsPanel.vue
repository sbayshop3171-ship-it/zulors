<template>
    <div v-if="isOpen" class="fixed inset-0 z-40 bg-black/15 backdrop-blur-xs"></div>
    <PrimaryTransition
        transitionName="slide-up"
        enterFromClass="translate-y-full"
        enterToClass="translate-y-0"
        leaveFromClass="translate-y-0"
        leaveToClass="translate-y-full"
    duration="3000">
        <template v-if="isOpen">
            <div class="fixed left-0 right-0 bottom-0 z-50 popup-background-tr min-h-40 shadow-vertical-tr flex flex-col">
                <div class="pl-navbar pr-8 border-b border-fill-pr">
                    <div class="ml-8 flex gap-6">
                        <button class="py-3 text-par-s text-lab-pr opacity-60 cursor-not-allowed text-nowrap">
                            {{ $t('labels.shortcuts') }}
                        </button>
                        <button v-on:click="changeTab(tabs.TEXT_FORMATTING)" v-bind:class="[isActiveTab(tabs.TEXT_FORMATTING) ? 'active-tab text-brand-900' : '']" class="py-3 text-par-s text-nowrap">
                            {{ $t('labels.text_formatting') }}
                        </button>
                        <button v-on:click="closeCheatSheetPanel" class="py-3 text-par-s ml-auto text-brand-900 text-nowrap">
                            {{ $t('labels.close') }}
                        </button>
                    </div>
                </div>
                <div class="pl-navbar pr-8 max-h-half-screen overflow-y-auto">
                    <div class="ml-8 w-content">
                        <div class="py-6">
                            <template v-if="isActiveTab(tabs.TEXT_FORMATTING)">
                                <MarkdownCheatSheet></MarkdownCheatSheet>
                            </template>
                            <template v-else>
                                <ShortcutsCheatSheet></ShortcutsCheatSheet>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </PrimaryTransition>
</template>

<script>
    import { defineComponent, computed, ref, defineAsyncComponent } from 'vue';
    import { useCheatSheet } from '@D/core/composables/cheat-sheet/index.js';

    export default defineComponent({
        props: {
        },
        setup: function(props) {
            const { closeCheatSheetPanel, isOpen } = useCheatSheet();

            
            const tabs = ref({
                SHORT_CUTS: 'shortcuts',
                TEXT_FORMATTING: 'text-formatting'
            });

            const activeTab = ref(tabs.value.TEXT_FORMATTING);

            return {
                closeCheatSheetPanel: closeCheatSheetPanel,
                isOpen: isOpen,
                tabs: tabs,
                activeTab: activeTab,
                changeTab: (name) => {
                    activeTab.value = name;
                },
                isActiveTab: (name = '') => {
                    return activeTab.value == name;
                }
            };
        },
        components: {
            MarkdownCheatSheet: defineAsyncComponent(() => {
                return import('@D/components/layout/parts/cheat-sheets/text/markdown/MarkdownCheatSheet.vue');
            }),
            ShortcutsCheatSheet: defineAsyncComponent(() => {
                return import('@D/components/layout/parts/cheat-sheets/shortcuts/ShortcutsCheatSheet.vue');
            })
        }
    });
</script>