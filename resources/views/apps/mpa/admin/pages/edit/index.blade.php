@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/page.edit_title') }} ({{ __('admin/page.static_pages.' . $pageName . '.title') }})"></x-page-title>

	<div class="mb-4">
		<x-tabs.tabs>
			@foreach ($appLanguages as $localeData)
				<x-tabs.tab-item :active="$localeData->alpha_2_code == $selectedLocale" href="{{ route('admin.pages.edit', ['pageName' => $pageName, 'locale' => $localeData->alpha_2_code]) }}" textLabel="{{ $localeData->name }}"></x-tabs.tab-item>
			@endforeach
		</x-tabs.tabs>
	</div>
	<x-sided-content>
		<form action="{{ route('admin.pages.store') }}" method="POST" enctype="multipart/form-data">
			@csrf
			<input type="hidden" name="page_name" value="{{ $pageName }}">
			<input type="hidden" name="locale" value="{{ $selectedLocale }}">
			<input type="hidden" name="content" value="{{ $pageContent }}" id="rich-editor-content">

			@if($errors->any())
				<div class="mb-4">
					@foreach($errors->all() as $error)
						<x-form.valerr>
							{{ $error }}
						</x-form.valerr>
					@endforeach
				</div>
			@endif

			<div class="mb-4">
				<div id="rich-editor" class="text-lab-pr2" style="height: 500px;">
					{!! $pageContent !!}
				</div>
			</div>

			<x-ui.buttons.pill width="w-full" size="sm" type="submit" btnText="{{ __('admin/page.form.submit_button') }}"></x-ui.buttons.pill>
		</form>
		<x-slot:sideContent>
			<x-entity.previews.page></x-entity.previews.page>
		</x-slot:sideContent>
	</x-sided-content>
@endsection
