<template>
	<RouterLink v-bind:to="postRoute" target="_blank">
		<div class="overflow-hidden border smoothing border-bord-card rounded-2xl">
			<template v-if="isLoading">
				<div class="flex justify-center py-8">
					<PrimarySpinAnimation></PrimarySpinAnimation>
				</div>
			</template>
			<template v-else>
				<div class="px-4 pt-4">
					<div class="flex gap-2 items-center mb-2">
						<div class="shrink-0">
							<AvatarExtraSmall v-bind:avatarSrc="postData.relations.user.avatar_url"></AvatarExtraSmall>
						</div>
						<div class="flex-1 overflow-hidden">
							<div class="flex items-center gap-2">
								<h3 class="text-par-n font-semibold text-lab-pr2 truncate">
									<span class="flex items-center gap-1">
										<span class="shrink-0">
											{{ postData.relations.user.name }}
										</span>

										<span class="size-icon-x-small inline-block text-brand-900">
											<SvgIcon name="check-verified-02"></SvgIcon>
										</span>
									</span>
								</h3>
								<p class="text-par-s text-lab-sc truncate">
									{{ postUserCaption }}
								</p>
							</div>
						</div>
					</div>

					<template v-if="postHasContent">
						<div class="mb-4">
							<QuotedPostText v-bind:postContent="postContent"></QuotedPostText>
						</div>
					</template>
				</div>

				<template v-if="postHasMedia || postHasPoll">
                    <template v-if="PostTypeUtils.isImage(postData.type) || PostTypeUtils.isGif(postData.type)">
                        <QuotedPostImage v-bind:postMedia="postMedia"></QuotedPostImage>
                    </template>
                    <template v-else-if="PostTypeUtils.isVideo(postData.type)">
                        <QuotedPostVideo v-bind:postMedia="postMedia"></QuotedPostVideo>
                    </template>
                    <template v-else-if="PostTypeUtils.isAudio(postData.type)">
                        <QuotedPostAudio v-bind:postMedia="postMedia"></QuotedPostAudio>
                    </template>
                    <template v-else-if="PostTypeUtils.isDocument(postData.type)">
                        <QuotedPostDocument v-bind:postMedia="postMedia"></QuotedPostDocument>
                    </template>
                    <template v-else-if="PostTypeUtils.isPoll(postData.type)">
                        <QuotedPostPoll></QuotedPostPoll>
                    </template>
				</template>
			</template>
		</div>
	</RouterLink>
</template>

<script>
	import { defineComponent, ref, computed, onMounted } from 'vue';
	import { PostTypeUtils } from '@/kernel/enums/post/post.type.js';

	import AvatarExtraSmall from '@D/components/general/avatars/AvatarExtraSmall.vue';
	import QuotedPostText from '@D/components/timeline/feed/parts/quote/parts/text/QuotedPostText.vue';
	import QuotedPostImage from '@D/components/timeline/feed/parts/quote/parts/media/QuotedPostImage.vue';
	import QuotedPostVideo from '@D/components/timeline/feed/parts/quote/parts/media/QuotedPostVideo.vue';
	import QuotedPostAudio from '@D/components/timeline/feed/parts/quote/parts/media/QuotedPostAudio.vue';
	import QuotedPostDocument from '@D/components/timeline/feed/parts/quote/parts/media/QuotedPostDocument.vue';
	import QuotedPostPoll from '@D/components/timeline/feed/parts/quote/parts/media/QuotedPostPoll.vue';

	export default defineComponent({
		props: {
			quotedPost: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			const isLoading = ref(true);
			const postData = ref(props.quotedPost);

			onMounted(() => {
				isLoading.value = false;
			});

			return {
				postData: postData,
				isLoading: isLoading,
				PostTypeUtils: PostTypeUtils,
				postUserCaption: computed(() => {
                    return `${postData.value.relations.user.caption} · ${postData.value.date.time_ago}`;
                }),
				postHasContent: computed(() => {
                    return postData.value.content.length;
                }),
				postContent: computed(() => {
                    return postData.value.content;
                }),
				postHasMedia: computed(() => {
                    return postData.value.relations.media.length;
                }),
				postMedia: computed(() => {
                    return postData.value.relations.media;
                }),
				postHasPoll: computed(() => {
                    return  PostTypeUtils.isPoll(postData.value.type);
                }),
				postRoute: computed(() => {
					return {
						name: 'publication_index',
						params: {
							hash_id: postData.value.hash_id
						}
					}
				})
			};
		},
		components: {
			AvatarExtraSmall: AvatarExtraSmall,
			QuotedPostText: QuotedPostText,
			QuotedPostImage: QuotedPostImage,
			QuotedPostVideo: QuotedPostVideo,
			QuotedPostAudio: QuotedPostAudio,
			QuotedPostDocument: QuotedPostDocument,
			QuotedPostPoll: QuotedPostPoll
		}
	});
</script>
