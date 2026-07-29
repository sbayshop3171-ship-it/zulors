<template>
	<div class="h-full flex flex-col items-center justify-center">
		<span class="inline-flex text-lab-sc items-center gap-8">
			{{ $t('chat.creating_group') }} <div class="colibri-primary-animation"></div>
		</span>
	</div>
</template>

<script>
	import { defineComponent, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useRouter } from 'vue-router';

	export default defineComponent({
		setup: function() {
			const router = useRouter();

			onMounted(async () => {
				await colibriAPI().messenger().getFrom('groups/create').then((response) => {
					router.push({
						name: 'messenger_group_edit', params: {
							chat_id: response.data.data.relations.chat.chat_id
						}
					});
				}).catch((error) => {
					if(error.response) {
						toastError(error.response.data.message);
					}
				});
			});
		}
	});
</script>