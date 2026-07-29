<form action="{{ route('admin.categories.upsert') }}" method="POST" enctype="multipart/form-data">
    @csrf
	<input type="hidden" name="category_id" value="{{ $categoryData->id }}">
	<input type="hidden" name="upsert_type" value="{{ $upsertType }}">
   	<x-accordion.form title="{{ __('admin/categories.form.category_info') }}">
		<div class="mb-6">
			<p class="text-par-n text-lab-sc">
				{{ __('admin/categories.form.translations_helper') }}
			</p>
		</div>
		@foreach ($appLanguages as $localeData)
			<div class="mb-6">
				<x-form.text-input
					labelText="{{ $localeData->name }}"
					type="text"
					errorKey="translations.{{ $localeData->alpha_2_code }}"
					name="translations[{{ $localeData->alpha_2_code }}]"
					value="{{ isset($categoryData->localization[$localeData->alpha_2_code]) ? $categoryData->localization[$localeData->alpha_2_code] : '' }}"
					placeholder="{{ __('admin/categories.form.name_placeholder', ['language' => $localeData->name]) }}">
				</x-form.text-input>
			</div>
		@endforeach

		<div class="mb-6">
			<x-form.radio-group labelText="{{ __('admin/categories.form.type') }} *">
				@foreach ($types as $type)
					<x-form.radio :checked="$type == $categoryData->categorizable_type" name="type" defaultValue="{{ $type->value }}" labelText="{{ $type->label() }}"></x-form.radio>
				@endforeach
			</x-form.radio-group>
		</div>
		<x-ui.buttons.pill size="sm" type="submit" btnText="{{ $upsertType == 'create' ? __('admin/categories.form.submit_button') : __('admin/categories.form.update_button') }}"></x-ui.buttons.pill>
	</x-accordion.form>
</form>
