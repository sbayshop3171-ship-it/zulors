import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useStoriesStore } from '@M/store/stories/stories.store.js';

const useStoriesEditorStore = defineStore('mobile_stories_editor_store', {
	state: function() {
		return {
			uploadProgress: 0,
			storyMedia: null,
			storyData: {
				content: ''
			}
		}
	},
	getters: {
		isFormValid: (state) => {
			return state.storyMedia !== null;
		}
	},
	actions: {
		resetEditor: function() {
			this.uploadProgress = 0;
			this.storyMedia = null;
			this.storyData = {
				content: ''
			}
		},
		publishStory: async function() {
			const storiesStore = useStoriesStore();
			if (this.storyMedia) {
				await colibriAPI().storyEditor().with({
					content: this.storyData.content
				}).sendTo('create').then((response) => {
					if(response.data.data) {
						storiesStore.prependFeedItem(response.data.data);
					}
				}).catch((error) => {
					if(error.response) {
						throw new Error(error.response.data.message);
					}
				});
			}
		},
		uploadMedia: async function(mediaFile) {
			const formData = new FormData();

			formData.append('media_file', mediaFile);
			
			await colibriAPI().storyEditor().with(formData).withHeaders({
				'Content-Type': 'multipart/form-data'
			}).uploadProgress((progressEvent) => {
				this.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
			}).sendTo('media/upload').then((response) => {
				this.storyMedia = response.data.data;
				this.uploadProgress = 0;
			}).catch((error) => {
				if(error.response) {
					this.uploadProgress = 0;

					throw new Error(error.response.data.message);
				}
			});
		},
		deleteMedia: async function() {
			this.storyMedia = null;

			await colibriAPI().storyEditor().delete('media/delete').catch((error) => {;
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		}
	}
});

export { useStoriesEditorStore };