<template>
	<div v-if="surface === 'dark'" class="flex items-center">
		<RouterLink v-bind:to="{ name: 'explore_posts' }" v-slot="{ isActive }" class="block flex-1">
			<div v-bind:class="isActive ? 'border-brand-900' : 'border-transparent'" class="py-4 cursor-pointer w-full px-3 text-center overflow-hidden truncate leading-4 border-b-2">
				<span v-bind:class="isActive ? 'text-white' : 'text-white/55'" class="text-par-m font-semibold">
					{{ $t('labels.explore') }}
				</span>
			</div>
		</RouterLink>
		<RouterLink v-bind:to="{ name: 'explore_reels' }" v-slot="{ isActive }" class="block flex-1">
			<div v-bind:class="isActive ? 'border-brand-900' : 'border-transparent'" class="py-4 cursor-pointer w-full px-3 text-center overflow-hidden truncate leading-4 border-b-2">
				<span v-bind:class="isActive ? 'text-white' : 'text-white/55'" class="text-par-m font-semibold">
					{{ $t('labels.reels') }}
				</span>
			</div>
		</RouterLink>
		<RouterLink v-bind:to="{ name: 'explore_people' }" v-slot="{ isActive }" class="block flex-1">
			<div v-bind:class="isActive ? 'border-brand-900' : 'border-transparent'" class="py-4 cursor-pointer w-full px-3 text-center overflow-hidden truncate leading-4 border-b-2">
				<span v-bind:class="isActive ? 'text-white' : 'text-white/55'" class="text-par-m font-semibold">
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
