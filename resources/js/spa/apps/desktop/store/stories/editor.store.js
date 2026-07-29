import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useStoriesStore } from '@D/store/stories/stories.store.js';

const useStoriesEditorStore = defineStore('stories_editor_store', {
	state: function() {
		return {
			opened: false,
			uploadProgress: 0,
			storyMedia: null,
			storyData: {
				content: ''
			}
		}
	},
	getters: {
		isOpen: (state) => {
            return state.opened;
        },
		isFormValid: (state) => {
			return state.storyMedia !== null;
		}
	},
	actions: {
		openEditor: function() {
			this.opened = true;
		},
		closeEditor: function() {
			this.opened = false;
		},
		resetEditor: function() {
			this.uploadProgress = 0;
			this.storyMedia = null;
			this.storyData = {
				content: ''
			}
		},
		publishStory: async function() {
			const state = this;
			const storiesStore = useStoriesStore();
			if (state.storyMedia) {
				await colibriAPI().storyEditor().with({
					content: state.storyData.content
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
			const state = this;

			formData.append('media_file', mediaFile);
			
			await colibriAPI().storyEditor().with(formData).withHeaders({
				'Content-Type': 'multipart/form-data'
			}).uploadProgress((progressEvent) => {
				state.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
			}).sendTo('media/upload').then((response) => {
				state.storyMedia = response.data.data;
				state.uploadProgress = 0;
			}).catch((error) => {
				if(error.response) {
					state.uploadProgress = 0;

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