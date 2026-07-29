<template>
    <div class="p-4 text-center" v-if="greetingMessage && !isLoading">
        <div class="mb-4">
            <h3 class="text-lab-pr2 text-par-m">
                ❤️
            </h3>
            <p class="text-par-m text-lab-pr2">{{ greetingMessage }}</p>
        </div>
    </div>
</template>

<script>
    import { defineComponent, onMounted, ref } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    export default defineComponent({
        setup: function() {
            const greetingMessage = ref('');
            const isLoading = ref(true);

            onMounted(async function() {
                await colibriAPI().ai().getFrom('greeting-message').then((response) => {
                    greetingMessage.value = response.data.data.message;
                    isLoading.value = false;
                }).catch((error) => {
                    console.error(error);
                    isLoading.value = false;
                });
            });

            return {
                greetingMessage: greetingMessage,
                isLoading: isLoading
            }
        }
    });
</script>
