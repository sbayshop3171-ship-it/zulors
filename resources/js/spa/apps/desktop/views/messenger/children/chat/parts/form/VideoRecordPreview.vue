<template>
    <div class="flex h-16 items-center">
        <div class="w-3/12 inline-flex items-center gap-2">
            <span class="size-icon-x-small bg-red-900 rounded-full animate-pulse"></span>
            <span class="text-par-l text-lab-pr font-normal">
                {{ $filters.formatTime(videoRecorderElapsed) }}
            </span>
        </div>

        <div class="w-6/12 text-center">
            <span class="text-par-m text-lab-sc font-normal">
                {{ $t('chat.cancel_media_recording') }}
            </span>
        </div>

        <div class="w-3/12 flex justify-end">
            <IconButton v-on:click="sendVideo" iconName="send-03" iconType="solid"></IconButton>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { useChatStore } from '@D/store/chats/chat.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import IconButton from '@D/views/messenger/children/chat/parts/ui/IconButton.vue';

    export default defineComponent({
        setup: function() {
            const chatStore = useChatStore();
            const sendVideo = () => {
                colibriEventBus.emit('messenger-message:stop-video-recording');
            }

            return {
                videoRecorderElapsed: computed(() => {
                    return chatStore.messageForm.videoRecorder.elapsed;
                }),
                sendVideo: sendVideo,
            };
        },
        components: {
            IconButton: IconButton,
        }
    });
</script>
