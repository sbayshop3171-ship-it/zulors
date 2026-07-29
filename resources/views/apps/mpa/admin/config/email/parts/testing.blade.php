<form action="{{ route('admin.config.email-testing') }}" method="POST" enctype="multipart/form-data">
    @csrf
   	<x-accordion.form title="{{ __('admin/config.email_testing.form.email_testing') }}">
		<div class="mb-6">
			<x-form.text-input
				labelText="{{ __('admin/config.email_testing.form.email') }} *"
				type="email"
				name="email"
				value="{{ config('admin.email') }}"
				placeholder="{{ __('admin/config.email_testing.form.email_placeholder') }}">

				<x-slot:feedbackInfo>
					{{ __('admin/config.email_testing.form.email_helper') }}
				</x-slot:feedbackInfo>
			</x-form.text-input>
		</div>
		<x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('admin/config.email_testing.form.send_button') }}"></x-ui.buttons.pill>
    </x-accordion.form>
</form>
