<template>
	<div v-if="surface === 'dark'" class="flex items-center">
		<RouterLink v-bind:to="{ name: 'explore_posts' }" v-slot="{ isActive }" class="block flex-1">
			<div v-bind:class="isActive ? 'explore-tabs__link--active' : ''" class="explore-tabs__link py-4 cursor-pointer w-full px-3 text-center overflow-hidden truncate leading-4">
				<span v-bind:class="isActive ? 'text-white' : 'text-white/55'" class="text-par-m font-semibold transition-colors duration-200 ease-out">
					{{ $t('labels.explore') }}
				</span>
			</div>
		</RouterLink>
		<RouterLink v-bind:to="{ name: 'explore_reels' }" v-slot="{ isActive }" class="block flex-1">
			<div v-bind:class="isActive ? 'explore-tabs__link--active' : ''" class="explore-tabs__link py-4 cursor-pointer w-full px-3 text-center overflow-hidden truncate leading-4">
				<span v-bind:class="isActive ? 'text-white' : 'text-white/55'" class="text-par-m font-semibold transition-colors duration-200 ease-out">
					{{ $t('labels.reels') }}
				</span>
			</div>
		</RouterLink>
		<RouterLink v-bind:to="{ name: 'explore_people' }" v-slot="{ isActive }" class="block flex-1">
			<div v-bind:class="isActive ? 'explore-tabs__link--active' : ''" class="explore-tabs__link py-4 cursor-pointer w-full px-3 text-center overflow-hidden truncate leading-4">
				<span v-bind:class="isActive ? 'text-white' : 'text-white/55'" class="text-par-m font-semibold transition-colors duration-200 ease-out">
					{{ $t('labels.people') }}
				</span>
			</div>
		</RouterLink>
	</div>
	<ContentTabs v-else v-bind:cols="3">
		<TabsLink v-bind:link="{ name: 'explore_posts' }">
			{{ $t('labels.explore') }}
		</TabsLink>
		<TabsLink v-bind:link="{ name: 'explore_reels' }">
			{{ $t('labels.reels') }}
		</TabsLink>
		<TabsLink v-bind:link="{ name: 'explore_people' }">
			{{ $t('labels.people') }}
		</TabsLink>
	</ContentTabs>
</template>

<script>
	import { defineComponent } from 'vue';

	import ContentTabs from '@M/components/general/tabs/content/ContentTabs.vue';
	import TabsLink from '@M/components/general/tabs/content/parts/TabsLink.vue';

	export default defineComponent({
		props: {
			surface: {
				type: String,
				default: 'default'
			}
		},
		components: {
			ContentTabs: ContentTabs,
			TabsLink: TabsLink
		}
	});
</script>

<style scoped>
	.explore-tabs__link {
		position: relative;
		border-bottom: 2px solid transparent;
		transform: translate3d(0, 0, 0);
		transition: transform 150ms ease-out;
	}

	.explore-tabs__link::after {
		content: '';
		position: absolute;
		left: 0;
		right: 0;
		bottom: -2px;
		height: 2px;
		background: var(--brand-900);
		opacity: 0;
		transform: scaleX(0.35);
		transform-origin: center;
		transition: transform 180ms cubic-bezier(0.22, 1, 0.36, 1), opacity 180ms ease-out;
	}

	.explore-tabs__link:active {
		transform: scale(0.98);
	}

	.explore-tabs__link--active::after {
		opacity: 1;
		transform: scaleX(1);
	}
</style>
