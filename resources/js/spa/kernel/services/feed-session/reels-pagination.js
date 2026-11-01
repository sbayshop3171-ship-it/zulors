async function requestNextReelsPage(currentPage, requestPage, commitPage) {
	const nextPage = Math.max(1, Math.trunc(Number(currentPage) || 0) + 1);
	const response = await requestPage(nextPage);

	if(! Array.isArray(response?.data?.data)) {
		throw new Error('The reels page response is invalid.');
	}

	commitPage(nextPage, response);

	return response;
}

export {
	requestNextReelsPage
};
