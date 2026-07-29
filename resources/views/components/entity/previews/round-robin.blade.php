<x-card>
	<div class="p-4">
		<div class="mb-4">
			<x-entity.format format="s3"></x-entity.format>
		</div>
		<h4 class="text-par-l font-semibold mb-1 text-lab-pr2">
			{{ __('admin/info.round_robin_notice.title') }}
		</h4>
		<p class="text-lab-sc text-par-m mb-4">
			{{ __('admin/info.round_robin_notice.line_one') }}
			<br>
			<br>
			{{ __('admin/info.round_robin_notice.line_two') }}
			<br>
			<br>
			{{ __('admin/info.round_robin_notice.line_three') }}
		</p>

		<a href="{{ config('app.documentation_url') }}" class="block" target="_blank">
			<x-ui.buttons.pill size="sm" type="button" width="w-full" btnText="{{ __('labels.learn_more') }}"></x-ui.buttons.pill>
		</a>
	</div>
</x-card>
