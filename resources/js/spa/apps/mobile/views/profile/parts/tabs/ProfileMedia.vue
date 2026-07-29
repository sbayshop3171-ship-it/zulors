<template>
	<PostsContent contentType="media"></PostsContent>
</template>

<script>
	import { defineComponent } from 'vue';
	import PostsContent from '@M/views/profile/parts/ProfileTimeline.vue';
	
	export default defineComponent({
		components: {
			PostsContent: PostsContent
		}
	});
</script>