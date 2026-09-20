import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

function frameStoryMusic(frameData) {
	return frameData?.story_music || frameData?.meta?.story_music?.selected_track || null;
}

function frameHasStoryMusic(frameData) {
	return Boolean(frameStoryMusic(frameData)?.track_id);
}

function createStoryMusicPlayback(playerState) {
	const audio = new Audio();
	audio.loop = true;
	audio.preload = 'auto';

	let requestToken = 0;

	const resetAudio = () => {
		audio.pause();

		try {
			audio.currentTime = 0;
		}
		catch (error) {}
	};

	const play = () => {
		if(! audio.src || playerState.isPaused) {
			return;
		}

		const playPromise = audio.play();

		if(playPromise?.catch) {
			playPromise.catch(() => {});
		}
	};

	const pause = () => {
		audio.pause();
	};

	const syncPauseState = () => {
		if(playerState.isPaused) {
			pause();
		}
		else {
			play();
		}
	};

	const syncFrame = async () => {
		const frameData = playerState.frameData;
		const musicData = frameStoryMusic(frameData);
		const token = ++requestToken;

		resetAudio();
		audio.removeAttribute('src');

			if(! musicData?.track_id) {
				return;
			}

			if(musicData.baked_into_media) {
				return;
			}

			try {
			const response = await colibriAPI().storyMusic().getFrom(`tracks/${musicData.track_id}/play-url`);

			if(token !== requestToken || playerState.frameData?.id !== frameData?.id) {
				return;
			}

			audio.src = response.data.data.play_url;
			audio.currentTime = 0;
			play();
		}
		catch (error) {}
	};

	const destroy = () => {
		requestToken += 1;
		resetAudio();
		audio.removeAttribute('src');
	};

	return {
		syncFrame,
		syncPauseState,
		destroy
	};
}

export { createStoryMusicPlayback, frameHasStoryMusic, frameStoryMusic };
