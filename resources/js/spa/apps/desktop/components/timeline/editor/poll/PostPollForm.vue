<template>
    <div class="overflow-hidden rounded-2xl border border-fill-pr">
        <div class="p-4">
            <div class="mb-4">
                <h4 class="text-lab-pr2 text-par-s mb-1.5">
                    {{ $t('labels.answer_options') }}
                </h4>
            </div>
            <div class="block overflow-hidden rounded-md">
                <div v-for="(choiceItem, index) in pollOptions" v-bind:key="index" class="border-b border-fill-pr last:border-none">
                    <PollOptionInput
                        v-on:remove="removeOption"
                        v-model="choiceItem.choice_text"
                        v-bind:optionIndex="index"
                        v-bind:optionData="choiceItem"
                        v-bind:removable="canRemoveOption"
                        v-bind:placeholder="$t('labels.option_number', { number: (index + 1) })"
                    textLength="40"></PollOptionInput>
                </div>
            </div>
            <div v-if="canAddOption" class="flex justify-center mt-4">
                <PrimaryAddButton v-on:click="addOption"></PrimaryAddButton>
            </div>
        </div>
        <div class="flex justify-center border-t border-fill-pr p-4">
            <PrimaryTextButton v-on:click="removePoll" v-bind:loading="isLoading" v-bind:buttonText="$t('editor.remove_poll')" buttonRole="danger"></PrimaryTextButton>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, ref, watch } from 'vue';
    import { Arr } from '@/kernel/helpers/javascript/index.js';
    import PollOptionInput from '@D/components/timeline/editor/poll/PollOptionInput.vue';
    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    import PrimaryAddButton from '@D/components/inter-ui/buttons/PrimaryAddButton.vue';
    import { usePostEditorStore } from '@D/store/timeline/editor.store.js';
    
    export default defineComponent({
        emits: ['remove'],
        setup: function(props, context) {
            const isLoading = ref(false);
            const postEditorStore = usePostEditorStore();

            const pollOptions = ref([]);

            if(postEditorStore.pollHasChoices()) {
                pollOptions.value = postEditorStore.pollChoices;
            }
            else {
                pollOptions.value = [{ choice_text: "" }, { choice_text: "" }, { choice_text: "" }];
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
                isLoading: isLoading,
                pollOptions: pollOptions,
                canAddOption: canAddOption,
                canRemoveOption: canRemoveOption,
                addOption: () => {
                    if(canAddOption.value) {
                        pollOptions.value.push({ 
                            choice_text: "" 
                        });
                    }
                },
                removeOption: (optionIndex) => {
                    if(canRemoveOption.value) {
                        Arr.make(pollOptions.value).removeItem(optionIndex);
                    }
                },
                removePoll: () => {

                    context.emit('remove');

                    isLoading.value = true;
                }
            };
        },
        components: {
            PollOptionInput: PollOptionInput,
            PrimaryTextButton: PrimaryTextButton,
            PrimaryAddButton: PrimaryAddButton
        }
    });
</script>