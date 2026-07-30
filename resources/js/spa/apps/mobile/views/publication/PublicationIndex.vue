<template>
	<TimelineContainer>
		<Toolbar v-on:close="$router.back" v-bind:title="$t('labels.publication_page_title')"></Toolbar>
		<TimelinePublicationSkeleton v-if="state.isLoading"></TimelinePublicationSkeleton>
		<template v-else>
			<TimelinePublication v-bind:postData="postData" v-on:delete="handlePostDelete"></TimelinePublication>
		</template>
	</TimelineContainer>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted, reactive, ref } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';

	import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import TimelineContainer from '@M/components/timeline/feed/TimelineContainer.vue';
	import Toolbar from '@M/components/layout/Toolbar.vue';

	export default defineComponent({
		props: {
			hash_id: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			const router = useRouter();
			const { postDeleter } = useDeletePost();
			const state = reactive({
				isLoading: true
			});

			const postData = ref(null);
			const postAuthor = ref(null);

				onMounted(async () => {
					await colibriAPI().userTimeline().getFrom(`post/${props.hash_id}`).then(function(response) {
						postData.value = response.data.data.post;
						postAuthor.value = response.data.data.author;
						state.isLoading = false;
					}).catch(function(error) {
						router.push({
	                        name: 'error_404'
	                    });
					});

					colibriEventBus.on('timeline:post-updated', handlePostUpdated);
				});

				onUnmounted(() => {
					colibriEventBus.off('timeline:post-updated', handlePostUpdated);
				});

				const handlePostUpdated = (updatedPostData) => {
					if(postData.value?.id == updatedPostData.id) {
						postData.value = updatedPostData;
					}
				};

				return {
				state: state,
				postData: postData,
				postAuthor: postAuthor,
				handlePostDelete: (postData) => {
					postDeleter(postData, () => {
						toastSuccess(__t('toast.media.post_deleted'));

						router.push({
	                        name: 'home_index'
	                    });
					});
				}
			};
		},
		components: {
			TimelinePublication: TimelinePublication,
			TimelinePublicationSkeleton: TimelinePublicationSkeleton,
			TimelineContainer: TimelineContainer,
			Toolbar: Toolbar
		}
	});
</script>
