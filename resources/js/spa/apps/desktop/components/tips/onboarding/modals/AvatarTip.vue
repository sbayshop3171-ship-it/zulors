<template>
    <form class="block">
        <div class="block mb-12">
            <div class="flex items-center flex-col select-none mb-4">
                <AvatarLarge v-bind:avatarSrc="$embedder('assets.images.upload_avatar')"></AvatarLarge>
            </div>
            <HeadingBlock v-bind:title="$t('tips.onboarding_avatar.title')" v-bind:caption="$t('tips.onboarding_avatar.caption')"></HeadingBlock>
        </div>
        <div class="flex justify-between">
            <PrimaryPillButton
                v-bind:loading="state.isSubmitting"
                v-on:click="selectAvatar" 
                type="button"
                v-bind:buttonText="$t('labels.upload_avatar')"
            buttonSize="lm"></PrimaryPillButton>

            <PrimaryPillButton v-if="! state.isSubmitting && isSkippable" v-on:click="skip" v-bind:buttonText="$t('labels.no_skip_it')" buttonSize="lm" buttonRole="marginalDanger"></PrimaryPillButton>
        </div>

        <input type="file" v-on:change="uploadAvatar"  accept="image/*" ref="imageInput" class="hidden">
    </form>
</template>

<script>
    import { defineComponent, ref, reactive } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

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
            const imageInput = ref(null);

            const state = reactive({
                isSubmitting: false
            });

            const handleSubmit = (formData) => {
                state.isSubmitting = true;

                colibriAPI().tips().with(formData).withHeaders({
                    'Content-Type': 'multipart/form-data'
                }).sendTo('avatar/update').then((response) => {
                    context.emit('close');
                }).catch((error) => {
                    state.isSubmitting = false;
                });
            }

            return {
                state: state,
                imageInput: imageInput,
                skip: () => {
                    handleSubmit({
                        skip: true
                    });
                },
                selectAvatar: () => {
                    imageInput.value.click();
                },
                uploadAvatar: (event) => {
                    const formData = new FormData();
                    formData.append('image', event.target.files[0]);

                    handleSubmit(formData);
                }
            };
        },
        components: {
            PrimaryPillButton: PrimaryPillButton,
            HeadingBlock: HeadingBlock,
            AvatarLarge: AvatarLarge
        }
    });
</script>