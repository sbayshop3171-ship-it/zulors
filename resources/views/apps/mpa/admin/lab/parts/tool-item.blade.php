<x-card>
	<a href="{{ empty($toolData['is_external']) ? url($toolData['route_path']) : $toolData['route_path'] }}" target="{{ empty($toolData['is_external']) ? '_self' : '_blank' }}" class="block h-full cursor-pointer">
		<div class="rounded-2xl overflow-hidden flex flex-col h-full">
			<div class="overflow-visible">
				<img src="{{ asset($toolData['preview_image']) }}" alt="{{ $toolData['title'] }}" class="w-full">
			</div>
			<div class="p-4 flex-1">
				<div class="flex flex-col h-full">
					<h4 class="text-lab-sc text-par-m font-bold mb-1">
						{{ $toolData['title'] }}
					</h4>
					<p class="text-lab-sc text-par-s mb-4">
						{{ $toolData['description'] }}
					</p>

					<div class="mt-auto">
						<x-ui.buttons.pill btnText="{{ __('labels.open') }}" width="w-full" size="sm"></x-ui.buttons.pill>
					</div>
				</div>
			</div>
		</div>
	</a>
</x-card>
