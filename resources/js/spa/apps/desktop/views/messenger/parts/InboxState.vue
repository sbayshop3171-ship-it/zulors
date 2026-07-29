<template>
    <div class="h-screen flex flex-col justify-center items-center">
        <div class="flex justify-center mb-2">
            <AvatarMedium v-bind:avatarSrc="userData.avatar_url"></AvatarMedium>
        </div>
        <h4 class="text-center text-title-3 font-semibold text-lab-pr2">
            {{ $t('chat.inbox_state.title', { app_name: $embedder('config.app.name') }) }}
        </h4>
        <p class="text-center text-par-s text-lab-sc max-w-sm">
            {{ $t('chat.inbox_state.desc', { app_name: $embedder('config.app.name') }) }}
        </p>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';

    import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';

    export default defineComponent({
        setup: function() {
            const authStore = useAuthStore();

            return {
                userData: authStore.userData
            }
        },
        components: {
            AvatarMedium: AvatarMedium
        }
    });
</script>