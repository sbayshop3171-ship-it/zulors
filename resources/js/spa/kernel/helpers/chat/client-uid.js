export function createChatClientUid() {
	if(globalThis.crypto?.randomUUID) {
		return globalThis.crypto.randomUUID();
	}

	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
		const random = Math.random() * 16 | 0;
		return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16);
	});
}

export default createChatClientUid;
