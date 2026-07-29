<template>
	<RouterLink v-bind:to="postRoute" target="_blank">
		<div class="overflow-hidden border border-bord-card rounded-2xl">
			<div class="flex gap-1.5 items-center px-4 pt-4 leading-none">
				<div class="shrink-0">
					<AvatarExtraSmall v-bind:avatarSrc="postData.relations.user.avatar_url"></AvatarExtraSmall>
				</div>
				<div class="flex-1 overflow-hidden inline-flex gap-2 items-center">
					<span class="text-par-s font-semibold text-lab-pr2 leading-6 truncate">
						{{ postData.relations.user.name }}
					</span>
					<span class="text-cap-l text-lab-sc leading-5 truncate">
						{{ postUserCaption }}
					</span>
				</div>
			</div>
			
			<template v-if="postHasContent">
				<div class="px-4 mt-2">
					<QuotedPostText v-bind:postContent="postContent"></QuotedPostText>
				</div>
			</template>

			<template v-if="postHasMedia || postHasPoll">
				<div class="mt-3">
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
				</div>
			</template>
		</div>
	</RouterLink>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';
	import { PostTypeUtils } from '@/kernel/enums/post/post.type.js';

	import AvatarExtraSmall from '@M/components/general/avatars/AvatarExtraSmall.vue';
	import QuotedPostText from '@M/components/timeline/feed/parts/quote/parts/text/QuotedPostText.vue';
	import QuotedPostImage from '@M/components/timeline/feed/parts/quote/parts/media/QuotedPostImage.vue';
	import QuotedPostVideo from '@M/components/timeline/feed/parts/quote/parts/media/QuotedPostVideo.vue';
	import QuotedPostAudio from '@M/components/timeline/feed/parts/quote/parts/media/QuotedPostAudio.vue';
	import QuotedPostDocument from '@M/components/timeline/feed/parts/quote/parts/media/QuotedPostDocument.vue';
	import QuotedPostPoll from '@M/components/timeline/feed/parts/quote/parts/media/QuotedPostPoll.vue';

	export default defineComponent({
		props: {
			quotedPost: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			const postData = ref(props.quotedPost);

			return {
				postData: postData,
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