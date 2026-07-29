<template>
	<div class="border border-bord-pr rounded-lg overflow-hidden">
		<LinkSnapshot v-bind="$attrs"></LinkSnapshot>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import LinkSnapshot from '@/kernel/vue/components/media/links/LinkSnapshot.vue';

	export default defineComponent({
		components: {
			LinkSnapshot: LinkSnapshot
		}
	})
</script>