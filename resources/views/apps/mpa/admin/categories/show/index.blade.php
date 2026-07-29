@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/categories.show_title') }}"></x-page-title>

	<div x-data="alpineComponent">
		<x-sided-content>
			<x-slot:sideContent>
				<x-entity.previews.category></x-entity.previews.category>
			</x-slot:sideContent>

			<div class="mb-8">
				<x-entity.header
					avatarUrl="{{ asset('assets/icons/avatar/tag-01.png') }}"
					name="{{ $categoryData->category_name }}">

					<x-slot:controlOptions>
						<x-ui.dropdown.dropdown>
							<x-slot:dropdownButton>
								<span class="opacity-50 hover:opacity-100">
									<x-ui.buttons.icon></x-ui.buttons.icon>
								</span>
							</x-slot:dropdownButton>
							<x-ui.dropdown.item tag="a" href="{{ route('admin.categories.edit', $categoryData->id) }}" itemText="{{ __('admin/dd.category.edit_category') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="edit-03"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
							<x-div></x-div>
							<x-ui.dropdown.item tag="a" :danger="true" x-on:click="deleteCategory" itemText="{{ __('admin/dd.category.delete_category') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="trash-04"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						</x-ui.dropdown.dropdown>
					</x-slot:controlOptions>
				</x-entity.header>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('admin/categories.about_category') }}"></x-entity.title>
			</div>
			<div class="mb-6">
				<x-counter.counter>
					<x-counter.counter-item counterValue="{{ $categoryData->category_name }}" captionText="{{ __('table.labels.name') }}"></x-counter.counter-item>
					<x-counter.counter-item counterValue="{{ $categoryData->usage_count }}" captionText="{{ __('table.labels.usage') }}"></x-counter.counter-item>
				</x-counter.counter>
			</div>

			<div class="mb-4">
				<x-entity.title title="{{ __('admin/categories.category_translations') }}"></x-entity.title>
			</div>
			@unless(empty($categoryData->localization))
				<div class="mb-6">
					<x-line-table.table>
						@foreach($categoryData->localization as $locale => $translation)
							<x-line-table.row>
								<x-slot:labelText>
									<span class="uppercase font-semibold">{{ $locale }}</span>
								</x-slot:labelText>
								<x-slot:labelValue>
									{{ $translation }}
								</x-slot:labelValue>
							</x-line-table.row>
						@endforeach
					</x-line-table.table>
				</div>
			@else
				<div class="mb-6">
					<p class="text-par-s text-lab-sc text-center">{{ __('admin/categories.no_translations') }}</p>
				</div>
			@endif

			<x-striped-table.table>
				<x-striped-table.row>
					<x-slot:labelText>
						{{ __('table.labels.name') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $categoryData->category_name }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						{{ __('table.labels.type') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $categoryData->categorizable_type->label() }} {{ $categoryData->categorizable_type->emoji() }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						{{ __('table.labels.status') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $categoryData->status->label() }} {{ $categoryData->status->emoji() }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						{{ __('table.labels.depth') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $categoryData->depth }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						#ID
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $categoryData->id }}
					</x-slot:labelValue>
				</x-striped-table.row>
			</x-striped-table.table>
		</x-sided-content>
	</div>

	<script>
		window.addEventListener('alpine:init', () => {
			Alpine.data('alpineComponent', () => {
				return {
					deleteCategory: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.delete_category.title') }}",
							desc: "{{ __('admin/prompt.delete_category.description') }}",
							formAction: "{{ route('admin.categories.destroy', $categoryData->id) }}"
						});
					},
				}
			});
		});
	</script>
@endsection
