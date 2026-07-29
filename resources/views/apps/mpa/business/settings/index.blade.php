@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText="{{ __('business/settings.index_title') }}"></x-page-title>

	<div class="bg-bg-pr rounded-2xl p-6 border border-bord-sc">
		<div class="mb-3">
			<div class="mb-24">
				<h5 class="text-par-l font-semibold text-lab-pr2 mb-1">
					{{ __('business/settings.business_account') }}

					@if($accountData->updated_at)
						@if($accountData->verified)
							<span class="text-green-900">
								({{ __('business/settings.verified') }}) &check;
							</span>
						@else
							<span class="text-lab-tr">
								({{ __('business/settings.not_verified') }})
							</span>
						@endif
					@endif
				</h5>
				@if($accountData->updated_at)
					<p class="text-par-n font-normal text-lab-pr2">
						{{ __('business/settings.last_submission', ['date' => $accountData->updated_at->getIso()]) }}
					</p>
				@else
					<p class="text-par-n font-normal text-lab-pr2">
						{{ __('business/settings.no_submission') }}
					</p>
				@endif

				<div class="block mt-4">
					<a href="{{ route('business.settings.edit') }}">
						<x-ui.buttons.pill
							size="sm"
							type="button"
						btnText="{{ __('business/settings.edit_account_btn') }}"></x-ui.buttons.pill>
					</a>
				</div>
			</div>

			<h5 class="text-par-n font-semibold text-lab-sc">
				{{ __('business/settings.business_info_policy') }}
			</h5>
			<p class="text-par-n font-normal text-lab-sc">
				{!! __('business/settings.business_info_policy_description', ['url' => route('business.settings.edit')]) !!}
			</p>
		</div>
	</div>
@endsection
