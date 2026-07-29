@props([
	'title' => null,
	'content' => null,
	'link' => null
])

<h5 class="text-title-3 text-lab-pr leading-tight mb-2 font-bold hover:text-brand-900 transition-all ease-linear">
	<a href="{{ $link }}">
		{{ $title ?? '' }}
	</a>
</h5>
<p class="text-par-n text-lab-sc">
	{{ $content }}
</p>