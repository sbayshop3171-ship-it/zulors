<template>
	<div class="block mb-4" v-if="profileData.bio || profileData.website">
		<div v-if="profileData.bio">
			<p class="text-par-n text-lab-pr2 mb-2" v-html="$mdInline(profileData.bio)"></p>
		</div>
		<div v-if="profileData.website">
			<a v-bind:href="profileData.website" target="_blank" class="text-lab-pr text-par-m underline">
				{{ profileData.website }}
			</a>
		</div>
	</div>
</template>

<script>
	import { defineComponent, inject } from 'vue';

	export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');

			return {
				profileData: profileData
			};
		}
	});
</script>
