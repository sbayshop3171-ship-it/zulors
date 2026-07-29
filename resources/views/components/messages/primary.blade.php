@php($flashMessage = session('flashMessage'))

@if($flashMessage)
	<div x-data="{ show: true }" x-show="show" class="mb-4 border p-1.5 rounded-lg {{ $flashMessage['type'] === 'success' ? 'callout-success' : 'callout-error' }}">
		<div class="flex">
			<div class="shrink-0 flex-center size-10 cursor-pointer">
				<div class="size-6">
					@if($flashMessage['type'] === 'success')
						<x-ui-icon name="check-circle" type="line"></x-ui-icon>
					@else
						<x-ui-icon name="alert-circle" type="line"></x-ui-icon>
					@endif
				</div>
			</div>
			<div class="flex-1 pr-3 py-2">
				<p>{{ $flashMessage['content'] }}</p>
			</div>
			<div class="shrink-0">
				<button x-on:click="show = false" class="flex-center size-10 bg-fill-fv rounded-lg cursor-pointer hover:bg-fill-qt">
					<span class="size-6">
						<x-ui-icon name="x-close" type="line"></x-ui-icon>
					</span>
				</button>
			</div>
		</div>
	</div>
@endif
