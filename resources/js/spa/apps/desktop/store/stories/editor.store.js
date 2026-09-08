import { defineStore } from 'pinia';
import { directVideoUpload } from '@/kernel/services/media/r2-direct-video-upload.js';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useStoriesStore } from '@D/store/stories/stories.store.js';

const useStoriesEditorStore = defineStore('stories_editor_store', {
	state: function() {
		return {
			opened: false,
			isUploading: false,
			uploadProgress: 0,
			storyMedia: null,
			storyMediaObjectUrl: null,
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
			return state.storyMedia !== null && !state.isUploading;
		}
	},
	actions: {
		releaseMediaPreview: function() {
			if(this.storyMediaObjectUrl) URL.revokeObjectURL(this.storyMediaObjectUrl);
			this.storyMediaObjectUrl = null;
		},
		openEditor: function() {
			this.opened = true;
		},
		closeEditor: function() {
			this.opened = false;
		},
		resetEditor: function() {
			this.releaseMediaPreview();
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
		uploadMedia: async function(mediaFile, options = {}) {
			if(mediaFile.type.startsWith('video/')) {
				this.isUploading = true;
				let uploadCreated = false;
				try {
					const media = await directVideoUpload({
						file: mediaFile,
						options,
						request: (action, payload) => colibriAPI().storyEditor().with(payload).sendTo('media/video/direct/' + action),
						onProgress: progress => { this.uploadProgress = progress; },
						onCreated: upload => {
							uploadCreated = true;
							this.releaseMediaPreview();
							this.storyMediaObjectUrl = URL.createObjectURL(mediaFile);
							this.storyMedia = { ...upload, source_url: this.storyMediaObjectUrl, preview_url: this.storyMediaObjectUrl };
						},
					});
					if(media) {
						this.releaseMediaPreview();
						this.storyMediaObjectUrl = URL.createObjectURL(mediaFile);
						this.storyMedia = { ...media, source_url: this.storyMediaObjectUrl, preview_url: this.storyMediaObjectUrl };
						if(this.discardUploadedMedia) {
							this.discardUploadedMedia = false;
							await this.deleteMedia();
						}
						return;
					}
				}
				catch(error) {
					if(uploadCreated) await this.deleteMedia().catch(() => {});
					throw error;
				}
				finally {
					this.isUploading = false;
					this.uploadProgress = 0;
				}
			}
			const formData = new FormData();
			const state = this;

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
			this.releaseMediaPreview();
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
