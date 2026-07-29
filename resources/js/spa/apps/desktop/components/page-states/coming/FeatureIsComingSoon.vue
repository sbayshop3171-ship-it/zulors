<template>
    <div class="block border border-bord-pr rounded-2xl">
        <div class="p-4">
            <img class="w-full" v-bind:src="$asset('assets/images/development-state.png')" alt="Image">
        </div>
        <Border height="h-3"></Border>
        <div class="p-4">
            <h4 class="text-title-3 font-bold mb-1 text-lab-pr2">
                This feature is coming soon!
            </h4>
            <p class="text-lab-pr3 text-par-m">
                We're hard at work enhancing Zulors to bring you an even better experience. <a v-bind:href="APP_NEWS_RESOURCE_URL" class="underline" target="_blank">Stay tuned for updates right here</a>!
            </p>
            <div class="mt-8">
                <RouterLink v-bind:to="{ name: 'home_index' }">
                    <PrimaryPillButton buttonRole="accent" buttonText="Homepage" buttonSize="lm"></PrimaryPillButton>
                </RouterLink>
            </div>
        </div>
    </div>
</template>
<script>
    import { defineComponent } from 'vue';

    import { APP_NEWS_RESOURCE_URL } from '@/kernel/env/index.js';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        setup: function(props) {
            return {
                APP_NEWS_RESOURCE_URL: APP_NEWS_RESOURCE_URL
            };
        },
        components: {
            PrimaryPillButton: PrimaryPillButton
        }
    });
</script>