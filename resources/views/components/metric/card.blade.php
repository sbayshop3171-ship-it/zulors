@props(['title', 'value', 'iconName' => 'user', 'desc' => '', 'hasLink' => true])

<div class="bg-bg-pr rounded-2xl p-6 relative group cursor-pointer smoothing hover:-translate-y-1 hover:shadow-xs border border-bord-sc">
	<div class="flex flex-col min-h-44">
		<div class="shrink-0 size-8 text-brand-900/80">
			<x-ui-icon name="{{ $iconName }}" type="solar"></x-ui-icon>
		</div>
		<div class="mb-8">
			<span class="text-title-3 block text-lab-pr2 font-bold mt-2">{{ $title }}</span>
			<span class="text-par-m block text-lab-sc">
				{{ $desc }}
			</span>
		</div>
		<span class="mt-auto text-title-1 leading-none block text-lab-pr2 font-bold font-outfit tracking-tight">{{ $value }}</span>

		@if($hasLink)
			<div class="absolute bottom-4 right-4 size-icon-small text-lab-tr group-hover:text-brand-900">
				<x-ui-icon name="arrow-up-right" type="line"></x-ui-icon>
			</div>
		@endif
	</div>
</div>
