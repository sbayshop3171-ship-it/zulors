<x-card>
	<div class="p-4">
		<div class="mb-4">
			<x-entity.format format="locale"></x-entity.format>
		</div>
		<h4 class="text-par-l font-semibold mb-1 text-lab-pr2">
			{!! __('admin/info.translation_notice.title') !!}
		</h4>
		<p class="text-lab-sc text-par-m mb-2">
			{!! __('admin/info.translation_notice.line_one') !!}
			<br>
			<br>
			{!! __('admin/info.translation_notice.line_two') !!}
			<br>
			<br>
			{!! __('admin/info.translation_notice.line_three') !!}
			<br>
			<br>
			<a class="text-brand-900 underline" href="{{ config('app.documentation_url') }}" target="_blank">
				{!! __('admin/info.translation_notice.line_four') !!}
			</a>
		</p>
	</div>
</x-card>
