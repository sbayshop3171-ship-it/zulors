import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCache, writeCache } from '@/kernel/services/cache/index.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const normalizeQuery = function(query = '') {
	return String(query || '').trim().toLowerCase().slice(0, 80);
};

const getExplorePeopleCacheKey = function(query = '') {
	const authStore = useAuthStore();

	return `colibri.mobile.explore.people.first_page.v2.${authStore.userData?.id || 'guest'}.${normalizeQuery(query) || 'default'}`;
};

const explorePeopleCacheLimit = 40;
const isSamePeopleFilter = function(currentFilter = {}, requestFilter = {}) {
	return normalizeQuery(currentFilter.query) === normalizeQuery(requestFilter.query)
		&& Number(currentFilter.page || 1) === Number(requestFilter.page || 1);
};

const useExplorePeopleStore = defineStore('mobile_explore_people_store', {
    state: function() {
		const cachedPeople = readCache(getExplorePeopleCacheKey(), []);

		return {
			people: cachedPeople,
			warmPromise: null,
			filter: {
				query: '',
				page: 1
			}
		}
	},
    actions: {
		makeLoadRequest: async function (filter = this.filter) {
			return await colibriAPI().explore().with({
				filter: filter
			}).sendTo('people');
		},
		fetchPeople: async function(filter = this.filter) {
			const requestFilter = { ...filter };

			return await this.makeLoadRequest(requestFilter).then((response) => {
				if(! isSamePeopleFilter(this.filter, requestFilter)) {
					return response.data.data;
				}

				this.people = response.data.data;
				this.persistFirstPage(requestFilter.query);

				return this.people;
			});
		},
		warmFirstPage: async function() {
			if(this.warmPromise) {
				return this.warmPromise;
			}

			this.warmPromise = this.makeLoadRequest({
				page: 1,
				query: ''
			}).then((response) => {
				const people = response.data.data;

				writeCache(getExplorePeopleCacheKey(), people.slice(0, explorePeopleCacheLimit));

				if(! this.filter.query && this.filter.page === 1) {
					this.people = people;
				}

				return people;
			}).finally(() => {
				this.warmPromise = null;
			});

			return this.warmPromise;
		},
		loadMorePeople: async function() {
			const requestFilter = { ...this.filter };

			return await this.makeLoadRequest(requestFilter).then((response) => {
				let people = response.data.data;

				if(! isSamePeopleFilter(this.filter, requestFilter)) {
					return false;
				}
				
				if (people.length) {	
					this.people = this.people.concat(people);
					return true;
				}

				return false;
			}).catch(() => {
				return false;
			});
		},
		getLastPersonId: function() {
			return this.people.at(-1).id;
		},
		resetFilter: function() {
			this.filter = {
				query: '',
				page: 1
			};

			if(! this.hydrateCachedFirstPage()) {
				this.people = [];
			}
		},
		hydrateCachedFirstPage: function(query = this.filter.query) {
			const cachedPeople = readCache(getExplorePeopleCacheKey(query), []);

			if(cachedPeople.length) {
				this.people = cachedPeople;

				return true;
			}

			return false;
		},
		persistFirstPage: function(query = this.filter.query) {
			if(Number(this.filter.page) !== 1) {
				return;
			}

			writeCache(getExplorePeopleCacheKey(query), this.people.slice(0, explorePeopleCacheLimit));
		}
    }
});

export { useExplorePeopleStore };
