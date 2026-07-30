<template>
    <div class="min-h-screen bg-bg-pr text-lab-pr">
        <div class="fixed left-0 top-0 bottom-0 z-50 w-navbar bg-bg-pr">
            <SettingsNavbar></SettingsNavbar>
        </div>

        <div class="ml-navbar py-top-offset">
            <div class="flex justify-center">
                <div class="shrink-0 w-content">
                    <RouterView></RouterView>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    
    import SettingsNavbar from '@D/views/settings/parts/SettingsNavbar.vue';

    export default defineComponent({
        components: {
            SettingsNavbar: SettingsNavbar
        }
    });
</script>
