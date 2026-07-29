<template>
    <form class="block" v-on:submit.prevent="submitForm">
        <div class="mb-6">
            <div class="flex items-center flex-col select-none mb-4">
                <AvatarLarge v-bind:avatarSrc="$embedder('assets.images.bio_avatar')"></AvatarLarge>
            </div>
            <HeadingBlock v-bind:title="$t('tips.onboarding_bio.title')" v-bind:caption="$t('tips.onboarding_bio.caption')"></HeadingBlock>
        </div>
        <div class="mb-6">
            <SoloTextInput
                v-model="bio"
                v-bind:inputError="validationError"
                v-bind:placeholder="$t('labels.about_you')"
                v-bind:hasLabel="false"
            v-bind:textLength="$embedder('config.validation.user.bio.max')">
                <template v-slot:feedbackInfo>
                    {{ $t('tips.onboarding_bio.input_helper_text') }}
                </template>
            </SoloTextInput>
        </div>
        <div class="flex justify-between">
            <PrimaryPillButton
                v-bind:loading="state.isSubmitting"
                type="submit"
                v-bind:buttonText="$t('labels.save')"
            buttonSize="lm"></PrimaryPillButton>

            <PrimaryPillButton v-if="!state.isSubmitting && isSkippable" v-on:click="skip" v-bind:buttonText="$t('labels.no_skip_it')" buttonSize="lm" buttonRole="marginalDanger"></PrimaryPillButton>
        </div>
    </form>
</template>

<script>
    import { defineComponent, ref, reactive } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    
    import SoloTextInput from '@D/components/forms/solo/SoloTextInput.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import HeadingBlock from '@D/components/tips/onboarding/parts/HeadingBlock.vue';
    import AvatarLarge from '@D/components/general/avatars/AvatarLarge.vue';

    export default defineComponent({
        props: {
            isSkippable: {
                type: Boolean,
                default: false
            }
        },
        emits: ['close'],
        setup: function(props, context) {
            const state = reactive({
                isSubmitting: false
            });

            const bio = ref('');

            const validationError = ref('');

            const handleSubmit = (formData) => {
                validationError.value = '';
                state.isSubmitting = true;

                colibriAPI().tips().with(formData).sendTo('bio/update').then((response) => {
                    context.emit('close');
                }).catch((error) => {
                    validationError.value = error.response.data.message;
                    state.isSubmitting = false;
                });
            }

            return {
                state: state,
                bio: bio,
                validationError: validationError,
                submitForm: () => {
                    handleSubmit({
                        bio: bio.value,
                        skip: false
                    });
                },
                skip: () => {
                    handleSubmit({
                        bio: bio.value,
                        skip: true
                    });
                }
            };
        },
        components: {
            SoloTextInput: SoloTextInput,
            PrimaryPillButton: PrimaryPillButton,
            HeadingBlock: HeadingBlock,
            AvatarLarge: AvatarLarge
        }
    });
</script>