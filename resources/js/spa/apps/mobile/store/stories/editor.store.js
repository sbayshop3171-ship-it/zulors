import { defineStore } from 'pinia';
import { publicationManager } from '@/kernel/services/media/publications/index.js';
import { isPublicationMedia, selectPublicationMedia, releasePublicationSelection } from '@/kernel/services/media/publications/selection.js';
import { directVideoUpload } from '@/kernel/services/media/r2-direct-video-upload.js';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useStoriesStore } from '@M/store/stories/stories.store.js';
import { appendStoryMusicUploadMetadata, storyMusicPublishPayload, storyMusicUploadOptions } from '@/kernel/services/media/story-music-upload.js';

const useStoriesEditorStore = defineStore('mobile_stories_editor_store', {
	state: function() {
		return {
			discardUploadedMedia: false,
			isUploading: false,
			uploadProgress: 0,
			videoClipCandidate: null,
			storyMedia: null,
			publicationSelection: null,
			publicationOptions: {},
			storyMediaObjectUrl: null,
			selectedMusicTrack: null,
			storyData: {
				content: ''
			}
		}
	},
	getters: {
		isFormValid: (state) => {
			return state.storyMedia !== null && !state.isUploading;
		}
	},
	actions: {
		releaseMediaPreview: function() {
			if(this.storyMediaObjectUrl) URL.revokeObjectURL(this.storyMediaObjectUrl);
			this.storyMediaObjectUrl = null;
		},
		resetEditor: function() {
			this.publicationSelection = null;
			this.publicationOptions = {};
			this.releaseMediaPreview();
			this.clearVideoClipCandidate();
			this.discardUploadedMedia = false;
			this.isUploading = false;
			this.uploadProgress = 0;
			this.storyMedia = null;
			this.selectedMusicTrack = null;
			this.storyData = {
				content: ''
			}
		},
		setSelectedMusicTrack: function(trackData) {
			this.selectedMusicTrack = trackData;
		},
		clearSelectedMusicTrack: function() {
			this.selectedMusicTrack = null;
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
			if (this.publicationSelection) {
				await publicationManager.enqueue({ kind: 'story', content: this.storyData.content, privacy: this.storyData.privacy || 'all', selected_user_ids: this.storyData.selected_user_ids || [], ...this.publicationOptions, ...storyMusicPublishPayload(this.selectedMusicTrack) }, [this.publicationSelection]);
				return { queued: true };
			}
			const storiesStore = useStoriesStore();
			if (this.storyMedia) {
				await colibriAPI().storyEditor().with({
					content: this.storyData.content,
					...storyMusicPublishPayload(this.selectedMusicTrack)
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
			const uploadOptions = storyMusicUploadOptions(mediaFile, options);

			if (isPublicationMedia(mediaFile)) {
				this.isUploading = true;
				try {
					if (await publicationManager.enabled('story')) {
						const selection = await selectPublicationMedia(mediaFile, publicationManager.account);
						if (this.discardUploadedMedia) {
							releasePublicationSelection(selection);
							this.discardUploadedMedia = false;
							return;
						}
						this.releaseMediaPreview();
						this.publicationSelection = selection;
						this.publicationOptions = uploadOptions;
						this.storyMediaObjectUrl = selection.preview_url;
						this.storyMedia = selection;
						return;
					}
				} finally { this.isUploading = false; }
			}
			if(mediaFile.type.startsWith('video/')) {
				this.isUploading = true;
				let uploadCreated = false;
				try {
					const media = await directVideoUpload({
						file: mediaFile,
						options: uploadOptions,
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

			this.discardUploadedMedia = false;
			this.isUploading = true;
			formData.append('media_file', mediaFile);

			if(uploadOptions.clip_start_seconds !== undefined) {
				formData.append('clip_start_seconds', uploadOptions.clip_start_seconds);
			}

			if(uploadOptions.clip_duration_seconds !== undefined) {
				formData.append('clip_duration_seconds', uploadOptions.clip_duration_seconds);
			}

			appendStoryMusicUploadMetadata(formData, uploadOptions);
			
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
			if (this.publicationSelection) {
				this.releaseMediaPreview();
				this.publicationSelection = null;
				this.publicationOptions = {};
				this.storyMedia = null;
				this.clearSelectedMusicTrack();
				return;
			}
			this.releaseMediaPreview();
			this.storyMedia = null;
			this.clearSelectedMusicTrack();

			await colibriAPI().storyEditor().delete('media/delete').catch((error) => {;
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		}
	}
});

export { useStoriesEditorStore };
