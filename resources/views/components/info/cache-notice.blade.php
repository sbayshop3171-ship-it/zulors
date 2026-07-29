@props([
	'ttl' => null
])

<p class="text-par-s text-lab-sc mt-4 italic font-light">
	💿
	@if($ttl)
		Information is updated every {{ $ttl }} minutes. 
	@else
		Information is cached.
	@endif

	Reset the cache to see the latest data.
</p>