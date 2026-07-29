<template>
	<h3 class="text-par-n font-semibold truncate" v-bind:class="[color]" v-bind:style="{ color: color }">
		{{ name }}
		<VerificationBadge v-if="isVerified" size="xs"></VerificationBadge>
	</h3>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			name: {
				type: String,
				required: true
			},
			color: {
				type: String,
				default: 'text-lab-pr2'
			},
			isVerified: {
				type: Boolean,
				default: false
			}
		}
	});
</script>