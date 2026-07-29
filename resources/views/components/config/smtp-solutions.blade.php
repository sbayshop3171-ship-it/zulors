<x-card>
	<div class="p-4">
		<h4 class="text-par-l font-semibold mb-1 text-lab-pr2">
			{{ __('admin/info.smtp_solutions.title') }}
		</h4>

		<p class="text-par-m text-lab-sc mb-2">
			1. {!! __('admin/info.smtp_solutions.line_one') !!}
		</p>

		<p class="text-par-m text-lab-sc mb-4">
			2. {!! __('admin/info.smtp_solutions.line_two') !!}
		</p>

        <p class="text-par-m text-lab-sc mb-4">
            3. {!! __('admin/info.smtp_solutions.line_three') !!}
        </p>

        <a href="{{ config('app.documentation_url') }}" class="block" target="_blank">
            <x-ui.buttons.pill size="sm" type="button" width="w-full" btnText="{{ __('labels.learn_more') }}"></x-ui.buttons.pill>
        </a>
	</div>
</x-card>
