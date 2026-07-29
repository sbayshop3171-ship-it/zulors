@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/config.verification_settings') }}"></x-page-title>

	<x-sided-content>
		<x-slot:sideContent>
			<x-config.readonly-notice></x-config.readonly-notice>
		</x-slot:sideContent>

		<div class="flex flex-col gap-6">
			<x-config.env
				name="VERIFICATION_SERVICE_URL"
				description="{{ __('admin/config.captions.verification_service_url') }}"
			value="{{ config('verification.service_url') }}"/>
		</div>
	</x-sided-content>
@endsection
