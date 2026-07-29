<x-card>
	<div class="p-4">
		<div class="mb-4">
			<x-entity.service service="ffmpeg"></x-entity.service>
		</div>
		<h4 class="text-par-l font-semibold mb-1 text-lab-pr2">
			{{ __('admin/info.ffmpeg_notice.title', ['app_name' => config('app.name')]) }}
		</h4>
		<p class="text-par-m text-lab-sc mb-2">
			{!! __('admin/info.ffmpeg_notice.line_one', ['app_name' => config('app.name')]) !!}
		</p>
        <p class="text-par-m text-lab-sc mb-4">
			{!! __('admin/info.ffmpeg_notice.line_two') !!}
		</p>

        <a href="{{ config('app.documentation_url') }}" target="_blank">
            <x-ui.buttons.pill size="sm" type="button" width="w-full" btnText="{{ __('labels.learn_more') }}"></x-ui.buttons.pill>
        </a>
	</div>
</x-card>
