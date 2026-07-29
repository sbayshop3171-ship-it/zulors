@props([
	'code' => 404,
	'title' => '',
	'message' => '',
	'hasBackButton' => true,
])

<div class="flex justify-center">
	<div class="w-content text-center">
		<div class="block">
			<span class="text-lab-pr2 text-title-1 font-bold opacity-80">
				{{ $code }}
			</span>
			<h1 class="text-lab-m font-bold text-lab-pr2 mb-1">
				{{ $title }}
			</h1>
			<p class="text-par-n text-lab-sc tracking-normal">
				{{ $message }}
			</p>
		</div>
		@if($hasBackButton)
			<div class="flex justify-center mt-4">
				<a href="{{ route('user.desktop.index') }}">
					<x-ui.buttons.pill size="sm" btnText="{{ __('labels.back_to_home') }}"></x-ui.buttons.pill>
				</a>
			</div>
		@endif
	</div>
</div>
