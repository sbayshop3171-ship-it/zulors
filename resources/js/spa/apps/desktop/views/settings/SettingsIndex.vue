<template>
    <div class="fixed left-0 top-0 bottom-0 z-50 w-navbar">
        <SettingsNavbar></SettingsNavbar>
    </div>

    <div class="ml-navbar my-top-offset">
        <div class="flex justify-center">
            <div class="shrink-0 w-content">
                <RouterView></RouterView>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    import { ref } from 'vue';
    
    import SettingsNavbar from '@D/views/settings/parts/SettingsNavbar.vue';

    export default defineComponent({
        components: {
            SettingsNavbar: SettingsNavbar
        }
    });
</script>