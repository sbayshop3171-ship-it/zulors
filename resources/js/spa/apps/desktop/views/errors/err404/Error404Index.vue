<template>
	<div class="my-top-offset block">
        <div class="mb-6">
            <PageTitle v-bind:hasBack="true" v-bind:backStep="2" v-bind:titleText="$t('error.error_404.page_title')"></PageTitle>
        </div>
		<div class="py-12">
			<h4 class="text-title-3 text-lab-pr font-medium mb-1">
				{{ $t('error.error_404.title') }}
			</h4>
			<p class="text-lab-pr3 text-par-s" v-html="$t('error.error_404.desc')"></p>
			<div class="mt-4">
				<RouterLink v-bind:to="{ name: 'home_index' }">
					<PrimaryPillButton buttonSize="lm" v-bind:buttonText="$t('labels.return_home')"></PrimaryPillButton>
				</RouterLink>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed, ref } from 'vue';
	import PageTitle from '@D/components/layout/PageTitle.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function(props) {
			return {
			};
		},
		components: {
			PageTitle: PageTitle,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>