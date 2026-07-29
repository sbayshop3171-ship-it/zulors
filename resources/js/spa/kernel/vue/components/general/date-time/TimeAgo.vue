<template>
	{{ date }}
</template>

<script>
	import { defineComponent, computed } from 'vue';

	// TODO: Complete this component and use it everywhere to show time.
	// Update time to keep it accurate.

	export default defineComponent({
		props: {
			timestamp: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			const date = computed(() => {
				return new Date(props.timestamp).toLocaleString();
			});

			return {
				date: date
			}
		}
	})
	</script>