import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useExplorePostsStore = defineStore('explore_posts_store', {
	deleteAware: true,
    state: function() {
		return {
			updateAttempts: 0,
			posts: [],
			update: [],
			filter: {
				page: 1,
				query: ''
			}
		}
	},
	actions: {
		updateFeed: async function() {
			if(! this.posts.length) {
				await this.refreshFirstPage();

				return this.posts.length > 0;
			}

            await colibriAPI().explore().params({
                filter: {
                    onset: this.posts.at(0).id,
					query: this.filter.query
                }
            }).sendTo('posts').then((response) => {
                this.update = response.data.data;
            }).catch((error) => {
                if(error.response) {
                    this.update = [];
                }
            });
        },
		applyUpdate: function() {
            this.update.forEach((postItem) => {
                // Check if post already exists before adding
                const exists = this.posts.slice(0, this.update.length).some((post) => {
					return post.id === postItem.id;
				});

                if (! exists) {
                    this.posts.unshift(postItem);
                }
            });

            this.update = [];
        },
		makeLoadRequest: async function () {
			return await colibriAPI().explore().with({
				filter: this.filter
			}).sendTo('posts');
		},
		fetchPosts: async function() {
			await this.makeLoadRequest().then((response) => {
				this.posts = response.data.data;
			});
		},
		refreshFirstPage: async function() {
			let pageNumber = this.filter.page;

			this.filter.page = 1;

			await this.fetchPosts();

			this.filter.page = pageNumber;
		},
		loadMorePosts: async function() {
			return await this.makeLoadRequest().then((response) => {
				let posts = response.data.data;
				
				if (posts.length) {	
					this.posts = this.posts.concat(posts);
					return true;
				}

				return false;
			}).catch(() => {
				return false;
			});
		},
		getLastPostId: function() {
			return this.posts.at(-1).id;
		},
		resetFilter: function() {
			this.filter = {
				page: 1,
				query: ''
			};
		}
    }
});

export { useExplorePostsStore };
