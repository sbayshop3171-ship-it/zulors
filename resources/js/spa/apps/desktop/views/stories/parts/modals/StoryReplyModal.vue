<template>
    <div class="absolute inset-0 z-20 bg-black/60 backdrop-blur-xs">
        <PrimaryTransition>
            <div v-if="renderBox" class="size-full p-4 flex items-end origin-bottom">
                <div class="rounded-md w-full popup-background-pr">
                    <div class="flex justify-between text-par-s font-medium border-b border-fill-sc p-4">
                        <span class="text-lab-pr">
                            {{ $t('story.reply_story_author', { name: 'Dress Code Style'}) }}
                        </span>
                        <button class="text-brand-900" v-on:click="cancelReply">
                            {{ $t('labels.cancel') }}
                        </button>
                    </div>
                    <div class="block py-8 px-4 mb-12">
                        <div class="flex flex-col items-center gap-2">
                            <AvatarMedium avatarSrc="https://image.ibb.co/gsF8Ob/gary_1.jpg"></AvatarMedium>
                            <div class="leading-4 mt-2">
                                <RouterLink to="/profile" class="block cursor-pointer text-center">
                                    <h3 class="text-par-s font-medium text-lab-pr mb-1">
                                        Dress Code Style
                                    </h3>
                                    <p class="text-par-s text-lab-sc mb-2">
                                        Fisherman
                                    </p>
                                    <p class="text-par-s text-green-500">
                                        {{ $t('labels.online') }}
                                    </p>
                                </RouterLink>
                            </div>
                        </div>
                    </div>
                    <div class="block p-4 pb-6 max-h-52 overflow-y-auto">
                        <form class="block">
                            <div class="block">
                                <SoloTextInput
                                    v-model="replyMessage"
                                    v-bind:hasLabel="false"
                                v-bind:placeholder="$t('story.enter_message')"></SoloTextInput>
                            </div>
                            <div class="block">
                                <PrimaryTextButton v-bind:buttonText="$t('labels.send_message')">
                                </PrimaryTextButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </PrimaryTransition>
    </div>
</template>

<script>
    import { defineComponent, onMounted, onUnmounted, ref } from 'vue';
    import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';
    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    import SoloTextInput from '@D/components/forms/solo/SoloTextInput.vue';

    export default defineComponent({
        emits: ['cancelreply'],
        setup: function(props, context) {
            const renderBox = ref(false);
            const replyMessage = ref('How\'s the fishing? Or is the fish on vacation again? 🎣😄');

            onMounted(() => {
                setTimeout(() => {
                    renderBox.value = true;
                }, 200);
            });

            onUnmounted(() => {
                
            });

            return {
                renderBox: renderBox,
                replyMessage: replyMessage,
                cancelReply: () => {
                    renderBox.value = false;

                    setTimeout(() => {
                        context.emit('cancelreply');
                    }, 200);
                }
            }
        },
        components: {
            AvatarMedium: AvatarMedium,
            PrimaryTextButton: PrimaryTextButton,
            SoloTextInput: SoloTextInput
        }
    });
</script>