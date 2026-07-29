<x-card>
	<div class="p-4">
		<div class="mb-4">
			<x-entity.format format="locale"></x-entity.format>
		</div>
		<h4 class="text-par-l font-semibold mb-1 text-lab-pr2">
			{!! __('admin/info.language_edit_notice.title') !!}
		</h4>
		<p class="text-lab-sc text-par-m mb-2">
			{!! __('admin/info.language_edit_notice.line_one') !!}
		</p>
		<p class="text-lab-sc text-par-m">
			{!! __('admin/info.language_edit_notice.line_two', ['documentation_url' => config('app.documentation_url')]) !!}
		</p>
	</div>
</x-card>
