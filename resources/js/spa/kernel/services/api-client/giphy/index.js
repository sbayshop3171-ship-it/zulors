import { GiphyFetch } from '@giphy/js-fetch-api';

const giphyFetcher = new GiphyFetch(import.meta.env.VITE_GIPHY_API_KEY);

const giphyAPI = function () {
    return {
        __giphy__: giphyFetcher,
        __options__: {
            limit: 60,
            offset: 0,
            rating: 'g'
        },
        limit: function (limit) {
            this.__options__.limit = limit;

            return this;
        },
        getTrending: async function () {
            return await giphyFetcher.trending({ 
                limit: this.__options__.limit,
                q: 'America'
            });
        },
        search: async function (searchText = '') {
            return await giphyFetcher.search(searchText, {
                limit: this.__options__.limit,
                offset: this.__options__.offset,
                rating: this.__options__.rating,
                q: searchText
            });
        }
    }
}

export { giphyAPI };