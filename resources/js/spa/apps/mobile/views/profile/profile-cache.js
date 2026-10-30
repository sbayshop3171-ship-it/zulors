export function shouldHydrateCachedProfileSnapshot(currentProfile, showLoader) {
	return Boolean(showLoader || ! currentProfile?.id);
}
