<template>
    <RouterLink v-bind:to="linkRoute" class="block min-w-0 max-w-full cursor-pointer">
        <div class="flex min-w-0 max-w-full items-center overflow-hidden gap-2">
            <div class="shrink-0">
                <AvatarSmall 
                    v-bind:avatarSrc="avatarSrc"
                    v-bind:unreadIndicator="unreadIndicator"
                v-bind:rounded="rounded"></AvatarSmall>
            </div>
            <div class="min-w-0 flex-1 leading-4 overflow-hidden">
                <h3 class="text-par-m font-semibold text-lab-pr2 truncate mb-0.5">
                    {{ name }} <VerificationBadge v-if="verified" size="xs"></VerificationBadge>
                </h3>
                <p class="text-par-s text-lab-sc truncate">
                    {{ caption }}
                </p>
            </div>
        </div>
    </RouterLink>
</template>

<script>
    import { defineComponent } from 'vue';
    import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';

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
