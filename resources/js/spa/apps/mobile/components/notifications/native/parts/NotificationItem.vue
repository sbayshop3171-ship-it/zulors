<template>
    <div class="hover:bg-fill-fv smoothing px-4 py-4 overflow-hidden">
        <div class="flex">
            <div class="shrink-0 size-small-avatar relative">
                <AvatarSmall v-bind:avatarSrc="notificationData.actor.avatar_url"></AvatarSmall>
                <span v-if="! notificationData.is_read" class="absolute border-2 border-bg-pr bottom-0 right-0 size-3 rounded-full bg-red-900 inline-block"></span>
            </div>
            <div class="flex-1 ml-2 leading-none">
                <div class="block">
                    <span class="font-semibold text-par-m text-lab-pr mr-1">
                        {{ notificationData.actor.name }}<template v-if="notificationData.actor.verified">&nbsp;<VerificationBadge size="xs"></VerificationBadge></template>
                    </span>
                    <span v-on:click="handleRouting" class="text-par-m text-lab-sc leading-5 cursor-pointer hover:text-lab-pr">
                        {{ notificationData.message }}<template v-if="notificationData.entity.content">
                            - <span class="font-normal">&quot;{{ notificationData.entity.content }}&quot;</span>

                            <span v-if="isReaction" v-on:click="handleRouting" class="ml-2 align-middle cursor-pointer overflow-hidden inline-block">
                                <img class="size-4" v-bind:src="notificationData.metadata.reaction_image_url" alt="Emoji">
                            </span>
                        </template>
                    </span>
                </div>
                <div class="block">
                    <time class="text-par-s text-lab-sc">{{ notificationData.date.time_ago }}</time>
                </div>
            </div>
            <div v-if="hasPreviewImage" class="shrink-0 ml-4">
                <div v-on:click="handleRouting" class="size-11 overflow-hidden rounded-md cursor-pointer">
                    <img class="size-full object-cover smoothing hover:scale-110" v-bind:src="notificationData.entity.preview_lqip_base64" alt="Image">
                </div>
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
    import { useNotificationsStore } from '@M/store/notifications/notifications.store.js';

    import AvatarSmall from '@M/components/general/avatars/AvatarSmall.vue';
    import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
    import FollowAcceptPillButton from '@M/components/inter-ui/buttons/follows/FollowAcceptPillButton.vue';

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
                else if(['story.mentioned', 'story.liked'].includes(props.notificationData.type)) {
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
	                        name: 'wallet_page'
	                    }
	                }
	                else if(['important.product-approved', 'important.product-rejected'].includes(props.notificationData.type)) {
	                    return {
	                        external_url: props.notificationData.entity.business_url
	                    }
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
                    if(['post.reacted', 'comment.reacted', 'story.liked'].includes(props.notificationData.type)) {
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
            PrimaryPillButton: PrimaryPillButton,
            FollowAcceptPillButton: FollowAcceptPillButton
        }
    });
</script>
