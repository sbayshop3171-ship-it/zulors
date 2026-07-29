<template>
    <div class="flex justify-center">
        <div v-for="(voterItem, index) in voterUsers" v-bind:key="index" class="-ml-2 first:ml-0 rounded-full border border-fill-tr shadow-xs first:ml-0">
            <AvatarExtraSmall v-bind:avatarSrc="voterItem.avatar_url"></AvatarExtraSmall>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import AvatarExtraSmall from '@D/components/general/avatars/AvatarExtraSmall.vue';

    export default defineComponent({
        props: {
            voterUsers: {
                type: Array,
                default: []
            }
        },
        setup: function(props) {

            const voterUsers = computed(() => {
                return props.voterUsers;
            });

            return {
                voterUsers: voterUsers
            };
        },
        components: {
            AvatarExtraSmall: AvatarExtraSmall
        }
    });
</script>