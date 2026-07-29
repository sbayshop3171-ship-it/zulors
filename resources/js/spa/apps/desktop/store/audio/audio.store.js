import { defineStore } from 'pinia';

const useAudioStore = defineStore('audio_store', {
    state: function() {
		return {
			audio: null,
            label: null,
            playerState: {}
		}
	},
    getters: {
        audioData: function() {
            return this.audio;
        }
    },
    actions: {
        add: function(audio, label = null) {
            if(this.audio === null || (this.audio !== null && this.audio.id !== audio.id)) {
                this.audio = audio;
                this.label = label;
                this.refreshState();
            }
        },
        remove: function() {
            this.audio = null;
            this.playerState = this.getState();
        },
        refreshState: function() {
            this.playerState = this.getState();
        },
        getState: function() {
            return new Object({
                playing: false,
                playbackTime: 0,
                progressBar: 0,
                isMuted: false,
                rate: 1,
                loop: false,
                isLoading: true,
                errors: []
            });
        },
        updateStateValue: function(key, value) {
            this.playerState[key] = value;
        },
        addStateError: function(error) {
            this.playerState.errors.push(error);
        },
        clearStateErrors: function() {
            this.playerState.errors = [];
        }
    }
});

export { useAudioStore };
