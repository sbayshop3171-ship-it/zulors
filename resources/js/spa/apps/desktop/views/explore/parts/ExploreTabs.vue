<template>
	<div v-if="variant === 'dark'" class="grid grid-cols-3 border-b border-white/10 bg-black/55 backdrop-blur">
		<RouterLink v-bind:to="{ name: 'explore_posts' }" v-slot="{ isActive }" class="block">
			<span v-bind:class="isActive ? 'text-white border-white' : 'text-white/55 border-transparent'" class="h-12 inline-flex-center w-full border-b-2 text-par-m font-semibold">
				{{ $t('labels.explore') }}
			</span>
		</RouterLink>
		<RouterLink v-bind:to="{ name: 'explore_reels' }" v-slot="{ isActive }" class="block">
			<span v-bind:class="isActive ? 'text-white border-white' : 'text-white/55 border-transparent'" class="h-12 inline-flex-center w-full border-b-2 text-par-m font-semibold">
				{{ $t('labels.reels') }}
			</span>
		</RouterLink>
		<RouterLink v-bind:to="{ name: 'explore_people' }" v-slot="{ isActive }" class="block">
			<span v-bind:class="isActive ? 'text-white border-white' : 'text-white/55 border-transparent'" class="h-12 inline-flex-center w-full border-b-2 text-par-m font-semibold">
				{{ $t('labels.people') }}
			</span>
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

	import ContentTabs from '@D/components/general/tabs/content/ContentTabs.vue';
	import TabsLink from '@D/components/general/tabs/content/parts/TabsLink.vue';

	export default defineComponent({
		props: {
			variant: {
				type: String,
				default: 'light'
			}
		},
		components: {
			ContentTabs: ContentTabs,
			TabsLink: TabsLink
		}
	});
</script>
