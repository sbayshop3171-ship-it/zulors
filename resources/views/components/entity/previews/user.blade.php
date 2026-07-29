@props([
	'userData' => null,
])

<x-card>
	<div class="overflow-hidden rounded-2xl">
		<div class="overflow-hidden">
			<img src="{{ $userData->cover_url }}" alt="{{ $userData->name }}" class="w-full">
		</div>
		<div class="p-4">
			<div class="size-14 overflow-hidden rounded-full border border-bord-pr">
				<img src="{{ $userData->avatar_url }}" alt="{{ $userData->name }}" class="size-full object-cover">
			</div>
			<div class="mb-4">
				<h4 class="text-lab-pr2 text-par-l font-semibold">
					{{ $userData->name }}
				</h4>
				<p class="text-lab-sc text-par-m">
					{{ $userData->caption }}
				</p>

				<p class="text-lab-sc text-par-m">
					{{ '@' . $userData->username }}
				</p>

				@if($userData->bio)
					<p class="text-lab-sc text-par-m mt-2 leading-snug">
						{!! nl2br($userData->bio) !!}
					</p>
				@endif
			</div>

			<a href="{{ $userData->profile_url }}" target="_blank">
				<x-ui.buttons.pill size="sm" type="button" width="w-full" btnText="{{ __('admin/users.view_profile') }}"></x-ui.buttons.pill>
			</a>
		</div>
	</div>
</x-card>
