<template>
    <aside class="fixed left-0 top-0 bottom-0 sticky-bar-bottom-offset z-50">
        <div class="flex w-page-offset h-full">
            <ApplicationActionBar></ApplicationActionBar>
            <div class="flex-1 h-full shrink-0">
                <div class="flex flex-col h-full pt-6">
                    <div class="mb-4 mx-6">
                        <div class="flex items-center gap-2">
                            <div class="block">
                                <RouterLink v-bind:to="{ name: 'home_index' }">
                                    <img class="h-7" v-bind:src="$embedder('assets.logos.url')" alt="Logo">
                                </RouterLink>
                            </div>
                        </div>
                    </div>
                    <div class="pl-6">
                        <ApplicationNavbar></ApplicationNavbar>
                    </div>
                    <div class="pl-6 mt-auto mb-4">
                        <ApplicationFooter></ApplicationFooter>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>

<script>
    import { defineComponent, computed, defineAsyncComponent } from 'vue';

    import ApplicationNavbar from '@D/components/layout/ApplicationNavbar.vue';
    import ApplicationActionBar from '@D/components/layout/parts/navbar/ApplicationActionBar.vue';
    import ApplicationFooter from '@D/components/layout/ApplicationFooter.vue';

    export default defineComponent({
        components: {
            ApplicationNavbar: ApplicationNavbar,
            ApplicationActionBar: ApplicationActionBar,
            ApplicationFooter: ApplicationFooter
        }
    });
</script>
