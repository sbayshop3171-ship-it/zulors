<template>
	<template v-if="state.isLoading">
		<div class="block">
			<TimelinePublicationSkeleton v-for="i in 3"></TimelinePublicationSkeleton>
		</div>
	</template>
	<template v-else>
		<template v-if="profilePosts.length"> 
			<TimelinePublication 
				v-for="postData in profilePosts"
				v-bind:postData="postData"
				v-on:delete="handleDeletePost(postData)"
			v-bind:key="postData.hash_id"></TimelinePublication>

			<div v-if="state.isLoadingContent">
				<div class="flex justify-center my-4">
					<div class="colibri-primary-animation"></div>
				</div>
			</div>
		</template>
		<template v-else>
			<div class="block py-40">
				<TimelineEmptyState v-if="contentType == 'posts'" v-bind:desc="$t('empty_state.profile.posts.desc')"></TimelineEmptyState>
				<TimelineEmptyState v-else v-bind:desc="$t('empty_state.profile.media.desc')"></TimelineEmptyState>
			</div>
		</template>
	</template>
</template>

<script>
	import { defineComponent, ref, reactive, onMounted, onUnmounted, inject, watch } from 'vue';
	import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
	import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
	import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';

	export default defineComponent({
		props: {
			contentType: {
				type: String,
				default: 'posts'
			}
		},
		setup(props) {
			const profileData = inject('profileData');
			const profilePosts = ref([]);
			const { postDeleter } = useDeletePost();
			let realtimeChannel = null;
			
			const state = reactive({
                noMoreContent: false,
                isLoading: true,
                isLoadingContent: false
            });

			const fetchPosts = async (options = {}) => {
				try {
					const reset = options.reset || false;

					if(! state.isLoadingContent && (reset || ! state.noMoreContent) && profileData.value?.id) {
						state.isLoadingContent = true;

						let cursorId = 0;

						if(! reset && profilePosts.value.length) {
							cursorId = profilePosts.value.at(-1).id;
						}

						await colibriAPI().userProfile().params({
							id: profileData.value.id,
							filter: {
								type: props.contentType,
								cursor: cursorId
							}
						}).getFrom('profile/posts').then(function(response) {
							let content = response.data.data;

							if(reset) {
								profilePosts.value = content;
								state.noMoreContent = ! content.length;
							}
							else if(content.length) {
								profilePosts.value = profilePosts.value.concat(content);
							}
							else{
								state.noMoreContent = true;
							}
						}).catch((error) => {
							if(error.response) {
								state.noMoreContent = true;
							}
						});

						state.isLoadingContent = false;
					}
				} catch (error) {
					console.log(error);
				}
			}

			useInfiniteScroll({
				callback: fetchPosts
			});

			const refreshPosts = async () => {
				state.noMoreContent = false;
				await fetchPosts({ reset: true });
				state.isLoading = false;
			};

			const handlePostCreated = (event) => {
				if(event?.data?.user_id == profileData.value?.id) {
					refreshPosts();
				}
			};

			const setupRealtimeProfileUpdates = () => {
				if(window.ColibriBRD && ! realtimeChannel) {
					realtimeChannel = window.ColibriBRD.channel(BRD.getChannel('PUBLIC_TIMELINE'));
					realtimeChannel.listen(BRD.getEvent('TIMELINE_POST_CREATED'), handlePostCreated);
				}
			};

			useInstantRevalidation(refreshPosts, {
				minDelay: 2000
			});

				onMounted(async () => {
					await refreshPosts();

					colibriEventBus.on('timeline:post-updated', handlePostUpdated);
					setupRealtimeProfileUpdates();
				});

				onUnmounted(() => {
					colibriEventBus.off('timeline:post-updated', handlePostUpdated);

					if(realtimeChannel) {
						realtimeChannel.stopListening(BRD.getEvent('TIMELINE_POST_CREATED'));
					}
				});

				watch(() => `${profileData.value?.id || ''}:${props.contentType}`, async () => {
					if(profileData.value?.id) {
						state.isLoading = true;
						profilePosts.value = [];
						state.noMoreContent = false;

						await refreshPosts();
					}
				});

				const handleDeletePost = (postData) => {
					postDeleter(postData, (postId) => {
						const postIndex = profilePosts.value.findIndex((item) => {
							return item.id == postId;
						});

						if(postIndex !== -1) {
							const deletedPost = profilePosts.value[postIndex];

							profilePosts.value.splice(postIndex, 1);

							return () => {
								if(! profilePosts.value.some((item) => item.id == postId)) {
									profilePosts.value.splice(Math.min(postIndex, profilePosts.value.length), 0, deletedPost);
								}
							};
						}
					});
				}

				const handlePostUpdated = (postData) => {
					const postIndex = profilePosts.value.findIndex((item) => {
						return item.id == postData.id;
					});

					if(postIndex !== -1) {
						profilePosts.value.splice(postIndex, 1, postData);
					}
				}
				
				return {
				state: state,
				profilePosts: profilePosts,
				handleDeletePost: handleDeletePost
			};
		},
		components: {
            TimelinePublication: TimelinePublication,
			TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            TimelineEmptyState: TimelineEmptyState,
		}
	});
</script>
