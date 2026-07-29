import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const colibriFollow = function() {
	return {
		__followableType: null,
		__followableId: null,
		user: function (id) {
			this.__followableId = id;
			this.__followableType = 'user';

			return this;
		},
		follow: async function() {
			return await colibriAPI().relations().with({
				id: this.__followableId
			}).sendTo(`follow/${this.__followableType}`).then((response) => {
				return response.data.data;
			}).catch((error) => {
				if(error.response) {
					return error.response.data;
				}
				else{
					return false;
				}
			});
		},
		accept: function() {
			colibriAPI().relations().with({
				id: this.__followableId
			}).sendTo(`accept/${this.__followableType}`);
		}
	};
}

export { colibriFollow };
