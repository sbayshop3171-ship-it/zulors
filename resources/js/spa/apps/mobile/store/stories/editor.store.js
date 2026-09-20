import { defineStore } from 'pinia';
import { publicationManager } from '@/kernel/services/media/publications/index.js';
import { isPublicationMedia, selectPublicationMedia, releasePublicationSelection } from '@/kernel/services/media/publications/selection.js';
import { directVideoUpload } from '@/kernel/services/media/r2-direct-video-upload.js';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useStoriesStore } from '@M/store/stories/stories.store.js';
import { appendStoryMusicUploadMetadata, storyMusicPublishPayload, storyMusicUploadOptions } from '@/kernel/services/media/story-music-upload.js';

const STORY_EDITOR_PROVIDER = String(import.meta.env.VITE_STORY_EDITOR_PROVIDER || 'legacy').toLowerCase();
const STORY_EDITOR_LEGACY_FALLBACK = String(import.meta.env.VITE_STORY_EDITOR_LEGACY_FALLBACK ?? 'true') !== 'false';
const IMGLY_CESDK_LICENSE = String(import.meta.env.VITE_IMGLY_CESDK_LICENSE_KEY || import.meta.env.VITE_IMGLY_LICENSE_KEY || '').trim();

function isLocalEditableMedia(file) {
	const mime = file?.type || file?.mime || '';

	return file instanceof Blob && ! file.native_file_id && (mime.startsWith('image/') || mime.startsWith('video/')) && ! /\.gif$/i.test(file?.name || '');
}

function storyClipOptionsFromCandidate(clipCandidate = null) {
	if(! clipCandidate) {
		return {};
	}

	return {
		clip_start_seconds: Math.max(0, Math.floor(Number(clipCandidate.clipStartSeconds || 0))),
		clip_duration_seconds: Math.max(1, Math.min(60, Math.ceil(Number(clipCandidate.clipDurationSeconds || 60))))
	};
}

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
			creativeDraft: null,
			creativeMusicBaked: false,
			selectedMusicTrack: null,
			storyData: {
				content: ''
			}
		}
	},
	getters: {
		isFormValid: (state) => {
			return state.storyMedia !== null && !state.isUploading;
		},
		hasCreativeDraft: (state) => {
			return Boolean(state.creativeDraft?.file && state.creativeDraft?.objectUrl);
		},
		creativeEditorEnabled: () => {
			return STORY_EDITOR_PROVIDER === 'imgly' && Boolean(IMGLY_CESDK_LICENSE);
		},
		imglyLicenseKey: () => {
			return IMGLY_CESDK_LICENSE;
		},
		legacyEditorFallbackEnabled: () => {
			return STORY_EDITOR_LEGACY_FALLBACK;
		}
	},
	actions: {
		releaseMediaPreview: function() {
			if(this.storyMediaObjectUrl) URL.revokeObjectURL(this.storyMediaObjectUrl);
			this.storyMediaObjectUrl = null;
		},
		releaseCreativeDraft: function() {
			if(this.creativeDraft?.objectUrl) URL.revokeObjectURL(this.creativeDraft.objectUrl);
			this.creativeDraft = null;
		},
		resetEditor: function() {
			this.publicationSelection = null;
			this.publicationOptions = {};
			this.releaseMediaPreview();
			this.releaseCreativeDraft();
			this.clearVideoClipCandidate();
			this.creativeMusicBaked = false;
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
		canUseCreativeEditorForFile: function(file) {
			return this.creativeEditorEnabled && isLocalEditableMedia(file);
		},
		startCreativeDraft: function(file, clipCandidate = null) {
			if(! this.canUseCreativeEditorForFile(file)) {
				return false;
			}

			this.publicationSelection = null;
			this.publicationOptions = {};
			this.releaseMediaPreview();
			this.releaseCreativeDraft();
			this.clearVideoClipCandidate();
			this.discardUploadedMedia = false;
			this.isUploading = false;
			this.uploadProgress = 0;
			this.storyMedia = null;
			this.creativeMusicBaked = false;
			this.selectedMusicTrack = null;
			this.storyData = {
				content: ''
			};

			this.creativeDraft = {
				file: file,
				objectUrl: URL.createObjectURL(file),
				name: file.name || (file.type?.startsWith('video/') ? 'story-video.mp4' : 'story-image.jpg'),
				mime: file.type || 'application/octet-stream',
				type: file.type?.startsWith('video/') ? 'video' : 'image',
				clipCandidate: clipCandidate,
				legacyUploadOptions: storyClipOptionsFromCandidate(clipCandidate),
				createdAt: Date.now()
			};

			return true;
		},
		uploadCreativeDraftWithLegacy: async function() {
			const draft = this.creativeDraft;

			if(! draft?.file) {
				throw new Error('Select a photo or video.');
			}

			this.releaseCreativeDraft();

			return this.uploadMedia(draft.file, draft.legacyUploadOptions || {});
		},
		uploadCreativeExport: async function(mediaFile, exportMetadata = {}) {
			const uploadOptions = {
				...exportMetadata,
				skip_publication_manager: true
			};

			return this.uploadMedia(mediaFile, uploadOptions);
		},
		setCreativeMusicBaked: function(value) {
			this.creativeMusicBaked = Boolean(value);
		},
		publishStory: async function(options = {}) {
			const musicPayload = storyMusicPublishPayload(this.selectedMusicTrack, {
				bakedIntoMedia: Boolean(options.musicBaked)
			});

			if (this.publicationSelection) {
				await publicationManager.enqueue({ kind: 'story', content: this.storyData.content, privacy: this.storyData.privacy || 'all', selected_user_ids: this.storyData.selected_user_ids || [], ...this.publicationOptions, ...musicPayload }, [this.publicationSelection]);
				return { queued: true };
			}
			const storiesStore = useStoriesStore();
			if (this.storyMedia) {
				await colibriAPI().storyEditor().with({
					content: this.storyData.content,
					...musicPayload
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
			const { skip_publication_manager: skipPublicationManager, ...mediaOptions } = options;
			const uploadOptions = storyMusicUploadOptions(mediaFile, mediaOptions);

			if (! skipPublicationManager && isPublicationMedia(mediaFile)) {
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
