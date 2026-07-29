<template>
    <ModalBackdrop>
        <ModalContent>
            <div class="block">
                <ModalHeader v-on:cancel="$emit('cancel')" v-bind:title="$t('story.share_story')" v-bind:buttonText="$t('labels.cancel')"></ModalHeader>
                <ShareContent v-bind:mediaOptions="mediaOptions" v-bind:link="storyUrl"></ShareContent>
            </div>
        </ModalContent>
    </ModalBackdrop>
</template>
<script>
    import { defineComponent, inject } from 'vue';

    import MarginalTextButton from '@D/components/inter-ui/buttons/MarginalTextButton.vue';
    import ModalBackdrop from '@D/views/stories/parts/modals/parts/ModalBackdrop.vue';
    import ModalContent from '@D/views/stories/parts/modals/parts/ModalContent.vue';
    import ModalHeader from '@D/views/stories/parts/modals/parts/ModalHeader.vue';
    import ShareContent from '@D/components/share/ShareContent.vue';

    export default defineComponent({
        props: {
            storyItem: {
                type: Object,
                default: {}
            }
        },
        emits: ['cancel'],
        setup: function() {
            return {
                mediaOptions: embedder('sharing.stories'),
                storyUrl: inject('storyUrl')
            }
        },
        components: {
            ModalHeader: ModalHeader,
            MarginalTextButton: MarginalTextButton,
            ModalBackdrop: ModalBackdrop,
            ModalContent: ModalContent,
            ShareContent: ShareContent
        }
    });
</script>