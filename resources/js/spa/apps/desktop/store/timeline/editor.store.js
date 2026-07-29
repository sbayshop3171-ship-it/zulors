import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { PostType } from '@/kernel/enums/post/post.type.js';

const usePostEditorStore = defineStore('post_editor_store', {
    state: function() {
		return {
            draftPost: {},
            preservedPostData: {

            },
            quotedPost: null,
            initialType: PostType.TEXT,
            marks: {
                isSensitive: false,
                isAiGenerated: false
            },
            mentionName: null,
            quotePostId: null,
            editingPostId: null
		}
	},
    getters: {
        isEditingPost: (state) => {
            return state.editingPostId !== null;
        },
        pollChoices: (state) => {
            return state.draftPost.relations.poll.choices;
        },
        isSensitive: (state) => {
            return state.marks.isSensitive;
        },
        isAiGenerated: (state) => {
            return state.marks.isAiGenerated;
        }
    },
    actions: {
        fetchDraftPost: async function(options = {}) {
            if(this.isEditingPost) {
                return false;
            }

            let state = this;
            let preservedContent = null;

            if(options.preserveContent && typeof this.draftPost?.content === 'string') {
                preservedContent = this.draftPost.content;
            }

            await colibriAPI().postEditor().params({
                quoted_post_id: this.quotePostId
            }).getFrom('draft').then((response) => {
                if (response.data.data.draft) {
                    state.draftPost = {
                        ...response.data.data.draft,
                        ...state.preservedPostData
                    };
                }
                else {
                    state.draftPost = this.getDraftPostDefaultValue();
                }

                if(preservedContent !== null) {
                    state.draftPost.content = preservedContent;
                }

                if(response.data.data.quoted_post) {
                    state.quotedPost = response.data.data.quoted_post;
                }
            }).catch((response) => {
                state.draftPost = this.getDraftPostDefaultValue();

                if(preservedContent !== null) {
                    state.draftPost.content = preservedContent;
                }
            });
        },
        pollHasChoices: function() {
            return this.draftPost?.relations?.poll?.choices?.length > 0;
        },
        resetDraftPost: function() {
            this.draftPost = this.getDraftPostDefaultValue();
        },
        setDraftPost: function(postData) {
            this.draftPost = postData;
        },
        startEditingPost: function(postData) {
            this.editingPostId = postData.id;
            this.quotePostId = null;
            this.mentionName = null;
            this.quotedPost = postData.relations?.quoted_post || null;
            this.draftPost = {
                ...postData,
                relations: {
                    ...(postData.relations || {})
                }
            };
        },
        setPollChoices: function(choicesArr) {
            this.draftPost.relations.poll.choices = choicesArr;
        },
        validatePollOptions: function(validationErrors) {
            if(this.pollHasChoices()) {

                Object.keys(validationErrors).forEach((errorKey) => {
                    let optionIndex = errorKey.numberOf(1);

                    if(optionIndex && this.pollChoices[optionIndex]) {
                        this.pollChoices[optionIndex]['isInvalid'] = true;

                        setTimeout(() => {
                            this.pollChoices[optionIndex]['isInvalid'] = false;
                        }, 1000);
                    }
                });
            }
        },
        finishEditing: function() {
            // Reset mention name after editor is closed
            // and all other data like share link, etc.

            this.mentionName = null;
            this.quotePostId = null;
            this.quotedPost = null;
            this.initialType = PostType.TEXT;
            this.marks.isSensitive = false;
            this.marks.isAiGenerated = false;
            this.preservedPostData = {};
            this.editingPostId = null;
            this.resetDraftPost();
        },
        getDraftPostDefaultValue: function() {
            let content = '';

            if(this.mentionName) {
                content = `@${this.mentionName} `;
            }

            return {
                content: content,
                type: PostType.TEXT,
                relations: {
                },
                ...this.preservedPostData
            };
        },
        markPostAsSensitive: function() {
            this.marks.isSensitive = !this.marks.isSensitive;
        },
        markPostAsAiGenerated: function() {
            this.marks.isAiGenerated = !this.marks.isAiGenerated;
        },
        setLinkSnapshot: function(linkSnapshotData) {
            this.draftPost.relations.link_snapshot = linkSnapshotData;
        },
        deleteLinkSnapshot: function() {
            this.draftPost.relations.link_snapshot = null;
        }
    }
});

export { usePostEditorStore };
