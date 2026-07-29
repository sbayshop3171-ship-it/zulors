@props([
	'avatarUrl' => '',
	'name' => '',
	'caption' => ''
])

<div class="flex items-center">
	<div class="shrink-0">
		<x-general.avatars.avatar-small :avatarUrl="$avatarUrl"></x-general.avatars.avatar-small>
	</div>
	<div class="flex-1 ml-2 mr-4 overflow-hidden leading-snug">
		<h5 class="text-par-m text-lab-pr2 font-semibold truncate">
			{{ $name }}
		</h5>
		@if($caption)
			<span class="text-cap-l text-lab-sc block">
				{{ $caption }}
			</span>
		@endif
	</div>

	@if(isset($controlOptions))
		<div class="shrink-0">
			{!! $controlOptions !!}
		</div>
	@endif
</div>