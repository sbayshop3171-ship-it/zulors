@props([
	'title' => '',
	'desc' => '',
])

<div class="bg-bg-pr rounded-2xl px-6 py-40 text-center border border-bord-sc">
	<x-page-states.message :title="$title" :desc="$desc" />
</div>
