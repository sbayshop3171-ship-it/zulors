import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
const colibriTranslator = function() {
	return {
		translate: async (text) => {
			return await colibriAPI().translator().with({
				text: text
			}).sendTo('translate').then((response) => {
				return response.data.data.translated_text;
			}).catch((error) => {
				if (error.response.data.message) {
					alert(error.response.data.message);
				}

				return false;
			});
		}
	};
}

export { colibriTranslator };