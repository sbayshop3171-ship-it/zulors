<template>
	<a v-bind:href="translator.url" target="_blank" class="flex items-center leading-none text-brand-900">
		<img class="h-icon-small" v-bind:src="translator.logo_url" v-bind:alt="translator.name">
		<span class="text-par-s font-medium ml-1">
			{{ $t('labels.translated_by', { translator_name: translator.name }) }}
		</span>
		<span class="inline-block size-icon-small ">
			<SvgIcon name="arrow-up-right" type="line"></SvgIcon>
		</span>
	</a>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		setup: function() {
			return {
				translator: embedder('translation_service')
			}
		}
	});
</script>