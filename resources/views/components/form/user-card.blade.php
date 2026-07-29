@props([
    'userData' => null,
])

<div class="border border-bord-pr gap-2.5 px-3 rounded-xl flex h-14 items-center overflow-hidden">
	<div class="size-7 overflow-hidden rounded-full">
		<img src="{{ $userData->avatar_url }}" alt="Avatar Image">
	</div>
	<div class="flex-1 truncate">
		<span class="text-lab-pr2 text-par-m font-medium">{{ $userData->name }} <span class="text-lab-sc">({{ $userData->username }})</span></span>
	</div>
</div>