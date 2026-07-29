import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useJobsStore = defineStore('jobs', {
	state: () => {
		return {
			categories: [],
			jobs: [],
			filter: {},
			job: null,
			metadata: {},
			bookmarkedJobs: []
		};
	},
	getters: {
		activeFiltersCount: function() {
			let count = 0;

			if(this.filter.category_id) {
				count++;
			}
			
			if(this.filter.query) {
				count++;
			}

			return count;
		}
	},
	actions: {
		fetchCategories: async function() {
			if(! this.categories.length) {
				await colibriAPI().jobs().getFrom('categories').then((response) => {
					this.categories = response.data.data;
				}).catch((error) => {
					if(error.response) {
						console.error(error.response.data.message);
					}
				});
			}
		},
		getLastJobId: function() {
			if(this.jobs.length) {
				return this.jobs[this.jobs.length -1].id;
			}

			return null;
		},
		loadMoreJobs: async function() {
			return await this.makeLoadRequest().then((response) => {
				let jobs = response.data.data;
				
				if (jobs.length) {
					this.jobs = this.jobs.concat(jobs);

					return true;
				}

				return false;
			}).catch((error) => {
				return false;
			});
		},
		makeLoadRequest: async function () {
			return await colibriAPI().jobs().with({
				filter: this.filter
			}).sendTo('jobs');
		},
		fetchJobs: async function() {
			await this.makeLoadRequest().then((response) => {
				this.jobs = response.data.data;
			}).catch((error) => {
				this.jobs = [];
			});
		},
		fetchJob: async function(id) {
			if(this.job && this.job.id == id) {
				return false;
			}

			await colibriAPI().jobs().getFrom(`jobs/${id}`).then((response) => {
				this.job = response.data.data;
				
				const jobItem = this.jobs.find((job) => {
					return job.hash_id == this.job.hash_id;
				});

				if(! jobItem) {
					this.jobs.unshift(this.job);
				}
			}).catch((error) => {
				this.job = null;
			});
		},
		bookmarkJob: async function(id) {
			return await colibriAPI().jobs().with({
				id: id
			}).sendTo('bookmarks/add');
		},
		resetFilter: function() {
			this.filter = {};
		}
	}
});

export { useJobsStore };