@props([
    'avatarSrc' => null,
	'name' => null,
	'link' => '#',
])

<a href="{{ $link }}" class="flex items-center gap-2 max-w-40">
	<div class="size-8 overflow-hidden shrink-0 rounded-full border border-bord-pr">
		<x-general.avatars.img src="{{ $avatarSrc }}" alt="{{ $name }}" class="size-full object-cover" />
	</div>
	<div class="text-par-n text-lab-sc truncate">{{ $name }}</div>
</a>
