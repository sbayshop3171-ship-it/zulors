<template>
    <template v-if="state.isLoading">
        <div class="flex justify-center py-12">
            <PrimarySpinAnimation></PrimarySpinAnimation>
        </div>
    </template>
    <template v-else>
        <form class="block" v-on:submit.prevent="submitForm">
            <div class="mb-12">
                <div class="flex mb-3 justify-center -space-x-3">
                    <AvatarSmall v-for="suggestedUser in followSuggestions" v-bind:avatarSrc="suggestedUser.avatar_url"></AvatarSmall>
                </div>
                <HeadingBlock v-bind:title="$t('tips.onboarding_follow.title')" v-bind:caption="$t('tips.onboarding_follow.caption')"></HeadingBlock>
            </div>
            <div class="flex justify-between">
                <PrimaryPillButton
                    v-bind:loading="state.isSubmitting"
                    type="submit"
                    v-bind:buttonText="$t('tips.onboarding_follow.yes_follow')"
                buttonSize="lm"></PrimaryPillButton>

                <PrimaryPillButton v-if="! state.isSubmitting && isSkippable" v-on:click="handleSkip" v-bind:buttonText="$t('labels.no_skip_it')" buttonSize="lm" buttonRole="marginalDanger"></PrimaryPillButton>
            </div>
        </form>
    </template>
</template>

<script>
    import { defineComponent, ref, reactive, onMounted } from 'vue';

    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import HeadingBlock from '@D/components/tips/onboarding/parts/HeadingBlock.vue';
    import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';

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
                isSubmitting: false,
                isLoading: true
            });

            const followSuggestions = ref([]);

            const handleSubmit = (formData) => {
                state.isSubmitting = true;

                colibriAPI().tips().with(formData).sendTo('recommended/follow').then((response) => {
                    context.emit('close');
                }).catch((error) => {
                    state.isSubmitting = false;
                });
            }

            onMounted(async () => {
                await colibriAPI().recommendations().getFrom('follow').then((response) => {
                    followSuggestions.value = response.data.data;
                });

                if (! followSuggestions.value.length) {
                    // If there are no follow suggestions, skip the form.

                    handleSkip();
                }

                state.isLoading = false;
            });

            const handleSkip = () => {
                handleSubmit({
                    skip: true
                });
            }

            return {
                state: state,
                followSuggestions: followSuggestions,
                submitForm: () => {
                    handleSubmit({
                        skip: false
                    });
                },
                handleSkip: handleSkip
            };
        },
        components: {
            PrimaryPillButton: PrimaryPillButton,
            HeadingBlock: HeadingBlock,
            AvatarSmall: AvatarSmall
        }
    });
</script>