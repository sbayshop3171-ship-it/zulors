<div x-data="{isSubmitting: false}" x-cloak>
	<div x-show="$store.confirmModal.isOpen">
		<x-modals.modal>
			<form x-bind:action="$store.confirmModal.formAction" x-on:submit="isSubmitting = true" method="POST">
				@csrf
				@method('POST')

				<div class="px-8 text-center py-7">
					<h4 x-html="$store.confirmModal.content.title" class="text-title-2 text-lab-pr2 font-semibold mb-1"></h4>
					<p x-html="$store.confirmModal.content.desc" class="text-par-m text-lab-sc mb-4"></p>
				</div>
					<div class="grid grid-cols-2 border-t border-bord-pr">
						<button
							x-bind:disabled="isSubmitting"
							x-on:click="$store.confirmModal.close()"
							type="button"
							class="flex w-full min-h-[3.5rem] items-center justify-center border-r border-bord-pr px-4 py-4 text-par-m font-medium leading-tight text-brand-900 outline-hidden cursor-pointer transition-colors duration-200 ease-in-out hover:bg-fill-fv disabled:opacity-80 disabled:cursor-not-allowed"
							x-text="$store.confirmModal.content.cancelButtonText || '{{ __('labels.cancel_button') }}'">
						</button>
						<button
							x-bind:disabled="isSubmitting"
							type="submit"
							class="flex w-full min-h-[3.5rem] items-center justify-center px-4 py-4 text-par-m font-medium leading-tight text-red-900 outline-hidden cursor-pointer transition-colors duration-200 ease-in-out hover:bg-fill-fv disabled:opacity-80 disabled:cursor-not-allowed"
							x-text="$store.confirmModal.content.confirmButtonText || '{{ __('labels.delete_button') }}'">
						</button>
					</div>
			</form>
		</x-modals.modal>
	</div>
</div>
