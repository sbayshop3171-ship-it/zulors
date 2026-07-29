<template>
	<PageHeader v-bind:hasBack="true" v-bind:titleText="$t('job.jobs_title')">
		<div class="relative">
			<div class="opacity-80 hover:opacity-100 translate-x-1">
				<PrimaryIconButton v-on:click.stop="state.mainMenu.open" iconName="plus"></PrimaryIconButton>
			</div>
			<div v-outside-click="state.mainMenu.close" v-if="state.mainMenu.status" class="absolute top-full right-0 w-96 z-50">
				<RichMenu>
					<a v-bind:href="$getRoute('business_dashboard_index')" target="_blank" class="block">
						<RichMenuItem
							v-bind:title="$t('job.nav_menu.create_job.title')"
							v-bind:description="$t('job.nav_menu.create_job.description')"
							trailingIconName="arrow-up-right"
							iconName="briefcase-01"
							iconType="line"
							plainIcon
						></RichMenuItem>
					</a>
					<Border></Border>
					<RouterLink v-bind:to="{ name: 'saved_jobs' }" class="block">
						<RichMenuItem
							v-bind:title="$t('job.nav_menu.bookmarks.title')"
							v-bind:description="$t('job.nav_menu.bookmarks.description')"
							trailingIconName="arrow-up-right"
							iconName="bookmark"
							iconType="line"
							plainIcon
						></RichMenuItem>
					</RouterLink>
				</RichMenu>
			</div>
		</div>
	</PageHeader>
</template>

<script>
	import { defineComponent, reactive } from 'vue';

	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import PageHeader from '@D/components/layout/PageHeader.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import RichMenu from '@D/components/general/rich-menu/RichMenu.vue';
	import RichMenuItem from '@D/components/general/rich-menu/RichMenuItem.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				mainMenu: useMenu()
			});

			return {
				state: state
			}
		},
		components: {
			PageHeader: PageHeader,
			PrimaryIconButton: PrimaryIconButton,
			RichMenu: RichMenu,
			RichMenuItem: RichMenuItem
		}
	});
</script>
