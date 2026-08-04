import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useStoriesStore } from '@M/store/stories/stories.store.js';

const useStoriesEditorStore = defineStore('mobile_stories_editor_store', {
	state: function() {
		return {
			discardUploadedMedia: false,
			isUploading: false,
			uploadProgress: 0,
			videoClipCandidate: null,
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
			this.clearVideoClipCandidate();
			this.discardUploadedMedia = false;
			this.isUploading = false;
			this.uploadProgress = 0;
			this.storyMedia = null;
			this.storyData = {
				content: ''
			}
		},
		setVideoClipCandidate: function(clipCandidate) {
			this.clearVideoClipCandidate();
			this.videoClipCandidate = clipCandidate;
		},
		clearVideoClipCandidate: function() {
			if(this.videoClipCandidate?.objectUrl) {
				URL.revokeObjectURL(this.videoClipCandidate.objectUrl);
			}

			this.videoClipCandidate = null;
		},
		cancelPendingUpload: function() {
			if(this.isUploading) {
				this.discardUploadedMedia = true;
				return;
			}

			this.resetEditor();
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
		uploadMedia: async function(mediaFile, options = {}) {
			const formData = new FormData();

			this.discardUploadedMedia = false;
			this.isUploading = true;
			formData.append('media_file', mediaFile);

			if(options.clip_start_seconds !== undefined) {
				formData.append('clip_start_seconds', options.clip_start_seconds);
			}

			if(options.clip_duration_seconds !== undefined) {
				formData.append('clip_duration_seconds', options.clip_duration_seconds);
			}
			
			await colibriAPI().storyEditor().with(formData).withHeaders({
				'Content-Type': 'multipart/form-data'
			}).uploadProgress((progressEvent) => {
				this.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
			}).sendTo('media/upload').then((response) => {
				this.storyMedia = response.data.data;
				this.clearVideoClipCandidate();
				this.isUploading = false;
				this.uploadProgress = 0;

				if(this.discardUploadedMedia) {
					this.discardUploadedMedia = false;
					return this.deleteMedia();
				}
			}).catch((error) => {
				const wasDiscarding = this.discardUploadedMedia;

				this.discardUploadedMedia = false;
				this.isUploading = false;

				if(error.response) {
					this.uploadProgress = 0;

					if(wasDiscarding) {
						return;
					}

					throw new Error(error.response.data.message);
				}

				this.uploadProgress = 0;

				if(wasDiscarding) {
					return;
				}

				throw error;
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
