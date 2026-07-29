@props([
	'href' => '#'
])

<a href="{{ $href }}" style="background-color: #333333; text-align: center; padding: 8px 25px; margin: 0; border-radius: 6px; text-decoration: none; color: #f3f3f3; font-size: 13px; font-weight: 400;">
	{{ $slot }}
</a>