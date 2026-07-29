@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/lang.create_title') }}"></x-page-title>

	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.translation></x-entity.previews.translation>
		</x-slot:sideContent>

		<div class="block">
			<form action="{{ route('admin.lang.store') }}" method="POST" enctype="multipart/form-data">
				@csrf
				<x-accordion.form title="{{ __('admin/lang.form.lang_info') }}">
					<div class="mb-6">
						<x-form.text-input
							labelText="{{ __('admin/lang.form.name') }} *"
							type="text"
							name="name"
							value="{{ old('name') }}"
							placeholder="{{ __('admin/lang.form.name_placeholder') }}">
						</x-form.text-input>
					</div>
					<div class="mb-6">
						<x-form.radio-group labelText="{{ __('admin/lang.form.direction') }} *">
							<x-form.radio
									name="direction"
									defaultValue="ltr"
									:checked="true"
								labelText="LTR">
							</x-form.radio>
							<x-form.radio
									name="direction"
									defaultValue="rtl"
								labelText="RTL">
							</x-form.radio>
						</x-form.radio-group>
					</div>
					<div class="mb-6">
						<x-form.text-input
							labelText="{{ __('admin/lang.form.native_name') }} *"
							type="text"
							name="native_name"
							value="{{ old('native_name') }}"
							placeholder="{{ __('admin/lang.form.native_name_placeholder') }}">

							<x-slot:feedbackInfo>
								{{ __('admin/lang.form.native_name_helper') }}
							</x-slot:feedbackInfo>
						</x-form.text-input>
					</div>

					<div class="mb-6">
						<x-form.text-input
							labelText="{{ __('admin/lang.form.alpha_2') }} *"
							type="text"
							name="alpha_2"
							value="{{ old('alpha_2') }}"
							placeholder="{{ __('admin/lang.form.alpha_2_placeholder') }}">

							<x-slot:feedbackInfo>
								{{ __('admin/lang.form.alpha_2_helper') }}
							</x-slot:feedbackInfo>
						</x-form.text-input>
					</div>
					<x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('admin/lang.form.submit') }}"></x-ui.buttons.pill>
				</x-accordion.form>
			</form>
		</div>
	</x-sided-content>
@endsection
