<template>
	<main class="messenger-render-error flex w-full items-center justify-center bg-bg-pr px-6 text-center" role="alert">
		<section class="w-full max-w-md">
			<p class="mb-2 text-cap-m font-semibold uppercase tracking-wide text-lab-sc">Messenger</p>
			<h1 class="mb-3 text-title-3 font-semibold text-lab-pr">This conversation could not be opened</h1>
			<p class="mb-6 text-par-m text-lab-sc">Your messages are safe. Retry this screen or return to your chats.</p>
			<div class="flex flex-col justify-center gap-3 sm:flex-row">
				<button type="button" class="rounded-full bg-brand-900 px-6 py-3 text-par-m font-semibold text-white" v-on:click="$emit('retry')">
					Try again
				</button>
				<button type="button" class="rounded-full border border-bord-pr px-6 py-3 text-par-m font-semibold text-lab-pr" v-on:click="$emit('back')">
					Back to chats
				</button>
			</div>
		</section>
	</main>
</template>

<script>
import { defineComponent } from 'vue';

export default defineComponent({
	name: 'MessengerRenderError',
	emits: ['retry', 'back']
});
</script>

<style scoped>
.messenger-render-error {
	min-height: 100vh;
	min-height: 100dvh;
	padding-top: max(1.5rem, env(safe-area-inset-top, 0px));
	padding-bottom: max(1.5rem, env(safe-area-inset-bottom, 0px));
}
</style>
