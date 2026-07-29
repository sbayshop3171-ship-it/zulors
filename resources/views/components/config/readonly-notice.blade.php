<x-card>
	<div class="p-4">
		<div class="mb-4">
			<x-entity.format format="env"></x-entity.format>
		</div>
		<h4 class="text-par-l font-semibold mb-1 text-lab-pr2">
			{{ __('admin/info.env_edit_notice.title') }}
		</h4>
		<p class="text-par-m text-lab-sc mb-2">
			{!! __('admin/info.env_edit_notice.line_one') !!}
		</p>

		<p class="text-par-m text-lab-sc mb-4">
			{!! __('admin/info.env_edit_notice.line_two') !!}
		</p>

		<div class="p-4 bg-fill-qt rounded-lg">
			<p class="text-par-m text-lab-pr2">
				{!! __('admin/info.env_edit_notice.env_privacy') !!}
			</p>
		</div>
	</div>
</x-card>
