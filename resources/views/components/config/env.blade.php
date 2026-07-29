@props([
	'name' => '',
	'description' => '',
	'value' => '',
	'copyable' => true
])

<div class="block">
	<div class="mb-2">
		<h4 class="text-par-m font-light text-lab-pr3 font-mono mb-0.5">
			{{ $name }}
		</h4>
		@if ($description)
			<p class="text-par-m text-lab-sc">
				{!! $description !!}
			</p>
		@endif
	</div>
	<x-code :copyable="$copyable">{{ $value }}</x-code>
</div>
