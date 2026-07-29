@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/page.index_title') }}"></x-page-title>

	<div class="grid grid-cols-4 gap-4">
		@foreach (config('pages.static') as $page)
			<x-card>
				<a href="{{ route('admin.pages.edit', ['pageName' => $page['name']]) }}" class="p-6 h-56 relative block group cursor-pointer">
					<h4 class="font-semibold text-lab-pr2">
						{{ __("admin/page.static_pages.{$page['name']}.title") }}
					</h4>
					<p class="text-par-n text-lab-sc">
						{{ __("admin/page.static_pages.{$page['name']}.description") }}
					</p>

					<div class="absolute bottom-4 right-4 size-icon-medium text-lab-sc group-hover:text-brand-900">
						<x-ui-icon name="arrow-up-right" type="line"></x-ui-icon>
					</div>
				</a>
			</x-card>
		@endforeach
	</div>
@endsection
