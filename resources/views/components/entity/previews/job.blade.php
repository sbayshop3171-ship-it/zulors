@props(['jobData'])

<x-card>
	<div class="p-4">
		<div class="mb-4">
			<h4 class="text-lab-pr2 text-par-l font-semibold mb-2">
				{{ $jobData->title }}
			</h4>
			<p class="text-lab-sc text-par-m">
				{{ $jobData->overview }}
			</p>

			@if($jobData->is_remote)
				<p class="text-lab-sc text-par-m mt-3">
					{{ __('business/jobs.remote_work') }}
				</p>
			@else
				<p class="text-lab-sc text-par-m mt-3 line-clamp-2">
					<span class="inline-block size-icon-x-small align-middle text-lab-tr">
						<x-ui-icon type="solid" name="marker-pin-01"></x-ui-icon>
					</span>
					{{ $jobData->location }}
				</p>
			@endif
			<p class="text-lab-sc text-par-m mt-2">
				{{ $jobData->type->label() }}, {{ $jobData->created_at->getCalendar() }}
			</p>
		</div>
		<a href="{{ $jobData->url }}" target="_blank">
			<x-ui.buttons.pill size="sm" width="w-full" type="button" btnText="{{ __('business/dd.job.open_job') }}"></x-ui.buttons.pill>
		</a>
	</div>
</x-card>
