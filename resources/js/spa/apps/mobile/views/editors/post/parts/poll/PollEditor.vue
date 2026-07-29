<template>
    <ActionSheet v-bind:isMuted="true" v-bind:isLocked="true">
        <div class="h-full overflow-y-auto">
            <div class="text-center px-4 mb-4">
                <SheetTitle v-bind:title="$t('editor.new_poll')"></SheetTitle>
            </div>
            <div class="mb-4">
                <div class="mb-2 px-4">
                    <h4 class="text-lab-pr3 text-par-s mb-1.5">
                        {{ $t('editor.poll_question') }}
                    </h4>
                </div>
                <ActionSheetGroup>
                    <div class="flex max-h-72 overflow-y-auto">
                        <textarea ref="questionTextInputField"
                            v-bind:placeholder="$t('editor.post_poll_input_placeholder')"
                            v-model.trim="pollQuestion"
                            v-on:input="questionInputHandler"
                        class="w-full h-24 min-h-24 outline-hidden resize-vertical px-4 py-4 text-par-l text-lab-pr placeholder:text-lab-tr focus:placeholder:text-lab-sc"></textarea>
                    </div>
                </ActionSheetGroup>
            </div>
            <div class="mb-4">
                <div class="mb-2 px-4">
                    <h4 class="text-lab-pr3 text-par-s mb-1.5">
                        {{ $t('labels.answer_options') }}
                    </h4>
                </div>
                <ActionSheetGroup>
                    <div class="block">
                        <div v-for="(choiceItem, index) in pollOptions" v-bind:key="index"
                        class="relative after:content-[''] after:absolute after:left-4 after:right-0 after:bottom-0 after:h-[1px] after:bg-bord-pr last:after:hidden">
                            <PollOptionInput
                                v-on:remove="removeOption(index)"
                                v-model="choiceItem.choice_text"
                                v-bind:removable="canRemoveOption"
                            v-bind:placeholder="$t('labels.option_number', { number: (index + 1) })"></PollOptionInput>
                        </div>
                    </div>
                </ActionSheetGroup>

                <div v-if="canAddOption" class="flex justify-center pt-4" v-bind:title="$t('editor.add_option')">
                    <PrimaryIconButton v-bind:disabled="state.postSubmitting" iconName="plus" v-on:click="addOption" iconType="solid"></PrimaryIconButton>
                </div>
            </div>
    
            <div v-if="state.validationError" class="mb-4 px-4">
                <div class="text-red-900 text-par-s">
                    {{ state.validationError }}
                </div>
            </div>
    
            <div class="mb-4">
                <ActionSheetGroup>
                    <ActionSheetItem v-bind:loading="state.postSubmitting" v-on:click="publishPoll" v-bind:textLabel="$t('editor.publish')" iconName="send-03" iconType="solid"></ActionSheetItem>
                </ActionSheetGroup>
            </div>
            <div class="px-4 py-2">
                <div class="flex items-center justify-center">
                    <PrimaryTextButton v-bind:disabled="state.postSubmitting" v-on:click="removePoll" buttonRole="marginal" v-bind:buttonText="$t('editor.remove_poll')"></PrimaryTextButton>
                </div>
            </div>
        </div>
    </ActionSheet>
</template>

<script>
    import { defineComponent, computed, ref, watch, reactive } from 'vue';
    import { Arr } from '@/kernel/helpers/javascript/index.js';
    import { usePostEditorStore } from '@M/store/timeline/editor.store.js';
    import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
    import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
    import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
    import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';

    import PollOptionInput from '@M/views/editors/post/parts/poll/PollOptionInput.vue';
    import PrimaryTextButton from '@M/components/inter-ui/buttons/PrimaryTextButton.vue';
    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
    
    export default defineComponent({
        emits: ['leave'],
        setup: function(props, context) {
            const postEditorStore = usePostEditorStore();
            const questionTextInputField = ref(null);
            const state = reactive({
                postSubmitting: false,
                validationError: null
            });

            const pollQuestion = ref('');
            const pollOptions = ref([]);
            const { autoResize } = useInputHandlers();

            if(postEditorStore.pollHasChoices()) {
                pollOptions.value = postEditorStore.pollChoices;
            }
            else {
                pollOptions.value = [{ choice_text: "" }, { choice_text: "" }, { choice_text: "" }];
            }

            const questionInputHandler = function() {
                autoResize(questionTextInputField.value);
            }

            const canAddOption = computed(() => {
                return pollOptions.value.length < 10;
            });

            watch(pollOptions, (newValue) => {
                postEditorStore.setPollChoices(newValue);
            }, { deep: true });
            
            const canRemoveOption = computed(() => {
                return pollOptions.value.length > 2;
            });

            return {
                pollOptions: pollOptions,
                canAddOption: canAddOption,
                canRemoveOption: canRemoveOption,
                questionTextInputField: questionTextInputField,
                questionInputHandler: questionInputHandler,
                pollQuestion: pollQuestion,
                state: state,
                addOption: () => {
                    if(canAddOption.value) {
                        pollOptions.value.push({ 
                            choice_text: ''
                        });
                    }
                },
                removeOption: (optionIndex) => {
                    if(canRemoveOption.value) {
                        Arr.make(pollOptions.value).removeItem(optionIndex);
                    }
                },
                removePoll: async () => {
                    postEditorStore.finishEditing();

                    await colibriAPI().postEditor().delete('poll/delete').then(() => {
                        postEditorStore.fetchDraftPost();
                    });
                },
                publishPoll: async () => {
                    if(state.postSubmitting) {
                        return false;
                    }

                    state.postSubmitting = true;
                    
                    await colibriAPI().postEditor().with({
                        content: pollQuestion.value,
                        poll_options: pollOptions.value
                    }).sendTo('create').then((response) => {
                        postEditorStore.finishEditing();

                        autoResize(questionTextInputField.value);

                        toastSuccess(__t('toast.post_published'));

                        context.emit('leave');
                    }).catch((error) => {
                        state.validationError = error.response.data.message;

                        debounce(() => {
                            state.validationError = null;
                        }, 3000);
                    });

                    state.postSubmitting = false;
                }
            };
        },
        components: {
            PollOptionInput: PollOptionInput,
            PrimaryTextButton: PrimaryTextButton,
            PrimaryIconButton: PrimaryIconButton,
            ActionSheet: ActionSheet,
            SheetTitle: SheetTitle,
            ActionSheetGroup: ActionSheetGroup,
            ActionSheetItem: ActionSheetItem
        }
    });
</script>