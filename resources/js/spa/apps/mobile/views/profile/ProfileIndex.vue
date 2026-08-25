<template>
	<template v-if="state.isLoading">
		<HeaderSkeleton></HeaderSkeleton>
	</template>
	<template v-else>
		<div class="mobile-safe-page-start mb-2">
			<div class="mb-4 px-4">
				<ProfileControls></ProfileControls>
			</div>
			<ProfileAvatar></ProfileAvatar>
		</div>
		<div class="mb-2 px-4">
			<ProfileBio></ProfileBio>
		</div>
		<div class="mb-3 px-4 empty:hidden">
			<ProfileOverview></ProfileOverview>
		</div>
		<div class="px-4">
			<ProfileMetrics></ProfileMetrics>
		</div>

		<div v-if="profileData.meta.permissions.can_follow || profileData.meta.permissions.can_message" class="px-4 py-3">
			<ProfileActions></ProfileActions>
		</div>

		<div v-if="! state.isLoading" class="mobile-safe-sticky-top block sticky bg-bg-pr z-20 transition-transform duration-300"
		v-bind:class="profileChrome.hiddenClass">
			<ContentTabs>
				<TabsLink v-bind:link="{ name: 'profile_posts' }">
					{{ $t('labels.posts') }}
				</TabsLink>
				<TabsLink v-bind:link="{ name: 'profile_media' }">
					{{ $t('labels.media') }}
				</TabsLink>
				<TabsLink v-bind:link="{ name: 'profile_info' }">
					{{ $t('labels.info') }}
				</TabsLink>
			</ContentTabs>
		</div>
		<div class="block border-t border-t-bord-pr">
			<RouterView></RouterView>
		</div>
	</template>
</template>

<script>
	import { defineComponent, computed, ref, watch, provide, onMounted, reactive } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useRoute, useRouter } from 'vue-router';
	import { useInstantRevalidation } from '@/kernel/vue/composables/instant-revalidation/index.js';
	import { useScrollAwareHeader } from '@/kernel/vue/composables/scroll-aware-header/index.js';
	import { normalizeProfileUsername, isValidProfileUsername } from '@/kernel/support/profile-routing/index.js';

	import HeaderSkeleton from '@M/views/profile/parts/skeletons/HeaderSkeleton.vue';
	import ProfileAvatar from '@M/views/profile/parts/ProfileAvatar.vue';
	import ProfileBio from '@M/views/profile/parts/ProfileBio.vue';
	import ProfileControls from '@M/views/profile/parts/controls/ProfileControls.vue';
	import ProfileOverview from '@M/views/profile/parts/ProfileOverview.vue';
	import ProfileMetrics from '@M/views/profile/parts/ProfileMetrics.vue';
	import ContentTabs from '@M/components/general/tabs/content/ContentTabs.vue';
    import TabsLink from '@M/components/general/tabs/content/parts/TabsLink.vue';
	import DropdownButton from '@M/components/general/dropdowns/DropdownButton.vue';
	import ProfileActions from '@M/views/profile/parts/controls/ProfileActions.vue';

	export default defineComponent({
		props: ['id'],
		setup: function(props) {
			const route = useRoute();
			const router = useRouter();

			const state = reactive({
				isLoading: true
			});
			const profileChrome = useScrollAwareHeader({
				disabled: computed(() => {
					return state.isLoading;
				})
			});

			const profileId = ref(normalizeProfileUsername(props.id));

			watch(() => { return route.params.id; }, () => {
				const nextProfileId = normalizeProfileUsername(route.params.id);

				if(nextProfileId !== profileId.value) {
					profileId.value = nextProfileId;
					fetchProfile();
				}
			});

			const profileData = ref({});

			provide('profileData', profileData);

			onMounted(async () => {
				profileId.value = normalizeProfileUsername(props.id);

                await fetchProfile();
            });

			const redirectProfileHome = async () => {
				await router.replace({
					name: 'home_index'
				}).catch(() => {});
			};

			const fetchProfile = async (showLoader = true) => {
				if(showLoader) {
					state.isLoading = true;
				}

				const currentProfileId = normalizeProfileUsername(profileId.value);
				profileId.value = currentProfileId;

				if(! isValidProfileUsername(currentProfileId)) {
					if(showLoader) {
						await redirectProfileHome();
					}

					return;
				}

				try {
					const response = await colibriAPI().userProfile().params({ id: currentProfileId }).getFrom('profile');

					profileData.value = response.data.data;
				} catch (error) {
					if(showLoader) {
						await redirectProfileHome();
					}
				} finally {
					state.isLoading = false;
				}
			};

			useInstantRevalidation(async () => {
				await fetchProfile(false);
			}, {
				minDelay: 3000
			});

			return {
				state: state,
				profileData: profileData,
				profileChrome: profileChrome
			};
		},
		components: {
			HeaderSkeleton: HeaderSkeleton,
			ProfileAvatar: ProfileAvatar,
			ProfileBio: ProfileBio,
			ProfileOverview: ProfileOverview,
			ProfileMetrics: ProfileMetrics,
			ContentTabs: ContentTabs,
			TabsLink: TabsLink,
			ProfileActions: ProfileActions,
			DropdownButton: DropdownButton,
			ProfileControls: ProfileControls
		}
	});
</script>
