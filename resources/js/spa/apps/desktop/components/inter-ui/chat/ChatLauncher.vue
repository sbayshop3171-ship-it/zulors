<template>
    <ContentModal v-on:close="$emit('close')">
        <div class="block">
            <ModalHeader v-bind:modalTitle="$t('market.write_message')"></ModalHeader>

            <template v-if="state.isLoading">
                <div class="flex py-20 justify-center">
                    <PrimarySpinAnimation></PrimarySpinAnimation>
                </div>
            </template>
            <template v-else>
                <div class="block py-8 px-4 ">
                    <ProfileOverviewCard v-bind:profileData="interlocutorData"></ProfileOverviewCard>
                </div>
                <div class="block">
                    <form class="block" v-on:submit.prevent="handleSubmit">
                        <Border height="h-3"></Border>
                        <div class="relative">
                            <span class="absolute bottom-3 left-4 text-lab-sc text-cap-l">{{ messageData.content.length }}/{{ validationRules.content.max }}</span>
                            <textarea
                                ref="messageTextInputField"
                                v-on:input="textInputHandler"
                                v-model="messageData.content"
                                class="resize-none w-full pl-4 pr-12 leading-5 pt-3.5 pb-6 bg-transparent text-par-n text-lab-pr2 max-h-96 overflow-y-auto min-h-28 outline-hidden placeholder:font-light placeholder:text-par-s" 
                            v-bind:placeholder="$t('chat.write_message')"></textarea>
                            
                            <div class="absolute right-2.5 bottom-1.5 ">
                                <div class="relative">
                                    <PrimaryIconButton
                                        v-on:click.stop="state.isEmojisPickerOpen = true" 
                                        iconName="face-smile"
                                        iconSize="icon-small"
                                        iconType="line"
                                    v-bind:disabled="state.isSubmitting"></PrimaryIconButton>
                                    <template v-if="state.isEmojisPickerOpen">
                                        <div class="block absolute top-6 right-0 w-80 z-50">
                                            <EmojisPicker 
                                                v-on:pick="insertStoryEmoji"
                                            v-on:close="state.isEmojisPickerOpen = false"></EmojisPicker>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <Border></Border>
                        <div class="flex justify-center py-4">
                            <PrimaryTextButton v-bind:disabled="! isFormValid" v-bind:loading="state.isSubmitting" v-bind:buttonText="$t('labels.send_message')" type="submit"></PrimaryTextButton>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </ContentModal>
</template>

<script>
    import { defineComponent, ref, onMounted, reactive, computed, defineAsyncComponent } from 'vue';
    import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useRouter } from 'vue-router';

    import AvatarMedium from '@D/components/general/avatars/AvatarMedium.vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    import ContentModal from '@D/components/general/modals/ContentModal.vue';
    import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
    import ProfileOverviewCard from '@D/components/profile/ProfileOverviewCard.vue';


    export default defineComponent({
        props: {
            userId: {
                type: Number,
                required: true
            },
            payload: {
                type: Object,
                default: {}
            }
        },
        emits: ['close'],
        setup: function(props, context) {
            const { autoResize, insertSymbolAtCaret } = useInputHandlers();
            const state = reactive({
                isEmojisPickerOpen: false,
                isSubmitting: false,
                isLoading: true
            });

            const validationRules = ref(null);
            const router = useRouter();
            const messageTextInputField = ref(null);
            const messageData = reactive({
                content: ''
            });

            const chatId = ref(null);
            const interlocutorData = ref(null);

            onMounted(async () => {
                autoResize(messageTextInputField.value);

                await colibriAPI().messenger().with({
                    user_id: props.userId
                }).sendTo('chats/launch').then((response) => {
                    interlocutorData.value = response.data.data.interlocutor;
                    chatId.value = response.data.data.chat_id;
                    validationRules.value = response.data.data.validation_rules;
                    state.isLoading = false;
                }).catch((error) => {
                    if(error.response) {
                        context.emit('close');
                        alert(error.response.data.message);
                    }
                });
            });

            return {
                state: state,
                messageTextInputField: messageTextInputField,
                messageData: messageData,
                validationRules: validationRules,
                isFormValid: computed(() => {
                    return messageData.content.length > 0;
                }),
                interlocutorData: interlocutorData,
                textInputHandler: function() {
                    autoResize(messageTextInputField.value);
                },
                insertStoryEmoji: (emojiSymbol) => {
					messageData.content = insertSymbolAtCaret(messageTextInputField.value, emojiSymbol);
                    messageTextInputField.value.focus();
				},
                handleSubmit: () => {
                    state.isSubmitting = true;

                    colibriAPI().messenger().with({
                        chat_id: chatId.value,
                        content: messageData.content,
                        payload: props.payload
                    }).sendTo('chats/launcher-send').then((response) => {
                        messageData.content = '';

                        context.emit('close');
                        
                        router.push({
                            name: 'messenger_chat',
                            params: {
                                chat_id: chatId.value
                            }
                        });
                    }).catch((error) => {
                        if(error.response) {
                            alert(error.response.data.message);
                        }

                        state.isSubmitting = false;
                    });
                }
            }
        },
        components: {
            AvatarMedium: AvatarMedium,
            PrimaryIconButton: PrimaryIconButton,
            ContentModal: ContentModal,
            ProfileOverviewCard: ProfileOverviewCard,
            ModalHeader: ModalHeader,
            PrimaryTextButton: PrimaryTextButton,
            EmojisPicker: defineAsyncComponent(() => {
                return import('@D/components/emojis/EmojisPicker.vue');
            }),
        }
    });
</script>