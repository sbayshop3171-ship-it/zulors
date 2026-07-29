import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { PostType } from '@/kernel/enums/post/post.type.js';

const usePostEditorStore = defineStore('mobile_post_editor_store', {
    state: function() {
		return {
            draftPost: {},
            quotedPost: null,
            mentionName: null,
            quotePostId: null,
            editingPostId: null,
            initialType: PostType.TEXT
		}
	},
    getters: {
        isEditingPost: (state) => {
            return state.editingPostId !== null;
        },
        pollChoices: (state) => {
            return state.draftPost.relations.poll.choices;
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
                    state.draftPost = response.data.data.draft;
                }
                else {
                    state.draftPost = this.getDraftPostDefaultValue();
                }

                if(preservedContent !== null) {
                    state.draftPost.content = preservedContent;
                }
            }).catch((response) => {
                state.draftPost = this.getDraftPostDefaultValue();

                if(preservedContent !== null) {
                    state.draftPost.content = preservedContent;
                }
            }); 
        },
        pollHasChoices: function() {
            return (this.draftPost?.relations?.poll?.choices?.length > 0);
        },
        setPollChoices: function(choicesArr) {
            this.draftPost.relations.poll.choices = choicesArr;
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
        finishEditing: function() {
            this.initialType = PostType.TEXT;
            this.mentionName = null;
            this.quotePostId = null;
            this.quotedPost = null;
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
                relations: {}
            };
        }
    }
});

export { usePostEditorStore };
