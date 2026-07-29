<template>
	<div class="block">
		<div class="block">
			<h1 class="text-title-2 font-bold leading-none text-lab-pr">
				{{ profileData.name }} <VerificationBadge size="md" v-if="profileData.verified"></VerificationBadge>
			</h1>
			<p class="text-par-s text-lab-sc">
				<span class="inline-flex items-center gap-1">
					<span>@{{ profileData.username }}</span>
				</span>
			</p>
		</div>
		<div v-if="profileData.bio" class="mt-4 max-w-8/12">
			<p class="text-par-m text-lab-pr2" v-html="markdownRenderer(profileData.bio)"></p>
		</div>
		<div v-if="profileData.website" class="mb-6">
			<a v-bind:href="profileData.website" target="_blank" class="text-brand-900 text-par-n font-medium hover:underline">
				{{ profileData.website }}
			</a>
		</div>
	</div>
</template>

<script>
	import { defineComponent, inject } from 'vue';
	import MarkdownParser from 'markdown-it';

	export default defineComponent({
		setup() {
			const profileData = inject('profileData');
            const MarkdownIT = new MarkdownParser({
                html: true,
                breaks: true,
                linkify: true
            });

			return {
				profileData: profileData,
                markdownRenderer: (text) => {
                    return MarkdownIT.renderInline(text);
                }
			};
		}
	});
</script>