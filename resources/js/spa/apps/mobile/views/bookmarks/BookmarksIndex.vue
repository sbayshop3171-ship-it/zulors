<template>
	<TimelineContainer>
		<Toolbar v-on:close="$router.back" v-bind:title="$t('labels.bookmarks')"></Toolbar>

		<div class="block" v-if="state.isLoading">
			<TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
		</div>
		<div class="block" v-else>
			<div v-if="bookmarks.length">
				<TimelinePublication v-for="productData in bookmarks" v-bind:key="productData.id" v-bind:postData="productData"></TimelinePublication>
			</div>
			<div v-else>
				<div class="py-32">
					<p class="text-lab-sc text-par-s text-center">
						{{ $t('empty_state.post_bookmarks') }}
					</p>
				</div>
			</div>
		</div>
	</TimelineContainer>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted } from 'vue';
    import { useBookmarksStore } from '@M/store/bookmarks/bookmarks.store.js';

    import Toolbar from '@M/components/layout/Toolbar.vue';
    import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import TimelineContainer from '@M/components/timeline/feed/TimelineContainer.vue';

    export default defineComponent({
        setup: function() {
            const bookmarksStore = useBookmarksStore();
			const state = reactive({
				isLoading: true
			});

            bookmarksStore.resetBookmarks();

            const bookmarks = computed(() => {
				return bookmarksStore.bookmarks;
			});

            onMounted(async() => {
                state.isLoading = true;

                await bookmarksStore.fetchBookmarks({
                    type: 'posts'
                });

               	state.isLoading = false;
            });

            return {
                state: state,
				bookmarks: bookmarks
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