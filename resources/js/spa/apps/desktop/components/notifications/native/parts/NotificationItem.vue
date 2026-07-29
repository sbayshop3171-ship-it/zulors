<template>
    <div class="hover:bg-fill-fv smoothing px-4 py-2.5">
        <div class="flex relative">
            <div v-if="! notificationData.is_read" class="absolute top-1 -left-3">
                <span class="size-1.5 rounded-full bg-brand-900 inline-block"></span>
            </div>
            <div class="shrink-0">
                <AvatarSmall v-bind:avatarSrc="notificationData.actor.avatar_url"></AvatarSmall>
            </div>
            <div class="flex-1 ml-2 leading-none">
                <div class="block">
                    <span class="font-medium text-par-n text-lab-pr mr-1">
                        {{ notificationData.actor.name }}<template v-if="notificationData.actor.verified">&nbsp;<VerificationBadge size="xs"></VerificationBadge></template>
                    </span>
                    <span v-on:click="handleRouting" class="text-par-s text-lab-pr2 leading-4 cursor-pointer hover:text-lab-pr">
                        {{ notificationData.message }}<template v-if="notificationData.entity.content">
                            :<span class="font-normal">&quot;{{ notificationData.entity.content }}&quot;</span>
                        </template>
                    </span>
                </div>
                <div v-if="hasPreviewImage" class="block my-1">
                    <div v-on:click="handleRouting" class="size-10 overflow-hidden rounded-md cursor-pointer">
                        <img class="size-full object-cover smoothing hover:scale-110" v-bind:src="notificationData.entity.preview_lqip_base64" alt="Image">
                    </div>
                </div>
                <div class="block">
                    <time class="text-par-s text-lab-sc">{{ notificationData.date.time_ago }}</time>
                </div>
            </div>
            <div v-if="isReaction" v-on:click="handleRouting" class="shrink-0 ml-2 cursor-pointer overflow-hidden">
                <img class="size-6 smoothing hover:scale-110" v-bind:src="notificationData.metadata.reaction_image_url" alt="Emoji">
            </div>
            <div v-else-if="isViewable" class="shrink-0 ml-4">
                <PrimaryPillButton v-on:click="handleRouting" v-bind:buttonText="$t('labels.view')" buttonSize="md"></PrimaryPillButton>
            </div>
            <div v-else-if="isFollowRequest" class="shrink-0 ml-4">
                <FollowAcceptPillButton 
                    v-bind:followableId="notificationData.entity.id" 
                    v-bind:isApproved="metadata.is_approved"
                    v-on:click="handleFollowAccept"
                buttonSize="md"></FollowAcceptPillButton>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { useNotificationsStore } from '@D/store/notifications/notifications.store.js';

    import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
    import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import FollowAcceptPillButton from '@D/components/inter-ui/buttons/follows/FollowAcceptPillButton.vue';

    export default defineComponent({
        props: {
            notificationData: {
                type: Object,
                default: {}
            }
        },
        setup: function(props, context) {
            const notificationsStore = useNotificationsStore();
            const notificationRoute = computed(() => {
                if(['post.reacted', 'post.commented', 'post.mentioned'].includes(props.notificationData.type)) {
                    return {
                        name: 'publication_index',
                        params: {
                            hash_id: props.notificationData.entity.hash_id
                        }
                    }
                }
                else if(['comment.mentioned', 'comment.reacted'].includes(props.notificationData.type)) {
                    return {
                        name: 'publication_index',
                        params: {
                            hash_id: props.notificationData.entity.post_hash_id
                        }
                    }
                }
                else if(['story.mentioned'].includes(props.notificationData.type)) {
                    return {
                        name: 'stories_index',
                        params: {
                            story_uuid: props.notificationData.entity.story_uuid
                        }
                    }
                }
                else if(['user.followed-requested', 'account-linked', 'user.followed', 'user.follow-accepted'].includes(props.notificationData.type)) {
                    return {
                        name: 'profile_index',
                        params: {
                            id: props.notificationData.entity.username
                        }
                    }
                }
	                else if(['important.wallet-deposit'].includes(props.notificationData.type)) {
	                    return {
	                        name: 'wallet_index'
	                    };
	                }
	                else if(['important.product-approved', 'important.product-rejected'].includes(props.notificationData.type)) {
	                    return {
	                        external_url: props.notificationData.entity.business_url
	                    };
	                }

	                return '#';
	            });

            const metadata = computed(() => {
                if(props.notificationData.metadata) {
                    return props.notificationData.metadata;
                }

                return {};
            });
            
            return {
                handleRouting: () => {
                    context.emit('route', notificationRoute.value);
                },
                metadata: metadata,
                isReaction: computed(() => {
                    if(['post.reacted', 'comment.reacted'].includes(props.notificationData.type)) {
                        return true;
                    }

                    return false;
                }),
                isViewable: computed(() => {
                    if(metadata.value.is_viewable) {
                        return true;
                    }

                    return false;
                }),
                isFollowRequest: computed(() => {
                    if(props.notificationData.type === 'user.followed-requested') {
                        return true;
                    }

                    return false;
                }),
                hasPreviewImage: computed(() => {
                    if(props.notificationData.entity) {
                        if(props.notificationData.entity.preview_lqip_base64) {
                            return true;
                        }
                    }

                    return false;
                }),
                handleFollowAccept: function() {
                    debounce(() => {
                        notificationsStore.deleteNotification(props.notificationData.id);
                    }, 2500);
                }
            }
        },
        components: {
            AvatarSmall: AvatarSmall,
            DropdownButton: DropdownButton,
            PrimaryPillButton: PrimaryPillButton,
            FollowAcceptPillButton: FollowAcceptPillButton
        }
    });
</script>
