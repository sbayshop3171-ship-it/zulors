<template>
    <RouterLink v-bind:to="linkRoute" class="block">
        <div class="flex items-center overflow-hidden gap-2">
            <div class="shrink-0">
                <AvatarSmall 
                    v-bind:avatarSrc="avatarSrc"
                    v-bind:unreadIndicator="unreadIndicator"
                v-bind:rounded="rounded"></AvatarSmall>
            </div>
            <div class="leading-4 overflow-hidden">
                <Name v-bind:name="name" v-bind:isVerified="verified"></Name>
                <p class="text-par-s text-lab-sc truncate mt-0.5">
                    {{ caption }}
                </p>
            </div>
        </div>
    </RouterLink>
</template>

<script>
    import { defineComponent } from 'vue';
    import AvatarSmall from '@M/components/general/avatars/AvatarSmall.vue';

    export default defineComponent({
        props: {
            name: {
                type: String,
                default: ''
            },
            verified: {
                type: Boolean,
                default: false
            },
            caption: {
                type: String,
                default: ''
            },
            linkRoute: {
                type: Object,
                default: { name: 'home_index' }
            },
            hasRoute: {
                type: Boolean,
                default: true
            },
            avatarSrc: {
                type: String,
                default: ''
            },
            rounded: {
                type: Boolean,
                default: true
            },
            unreadIndicator: {
                type: Boolean,
                default: false
            }
        },
        components: {
            AvatarSmall: AvatarSmall
        }
    });
</script>