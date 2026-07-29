@props([
	'groupData' => null,
])

<x-card>
	<div class="p-4">
		<div class="size-14 overflow-hidden rounded-full border border-bord-pr">
			<img src="{{ $groupData->avatar_url }}" alt="{{ $groupData->name }}" class="size-full object-cover">
		</div>
		<div class="mb-4">
			<h4 class="text-lab-pr2 text-par-l font-semibold">
				{{ $groupData->name }}
			</h4>

			<p class="text-lab-sc text-par-m">
				{{ __('labels.participants') }} ({{ $groupData->participants_count }})
			</p>

			@if($groupData->description)
				<p class="text-lab-sc text-par-m mt-2 leading-snug">
					{!! nl2br($groupData->description) !!}
				</p>
			@endif
		</div>

		<a href="{{ $groupData->url }}" target="_blank">
			<x-ui.buttons.pill size="sm" type="button" width="w-full" btnText="{{ __('admin/users.view_profile') }}"></x-ui.buttons.pill>
		</a>
	</div>
</x-card>
