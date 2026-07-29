@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/lang.show_title') }}"></x-page-title>

	<div x-data="alpineComponent">
		<x-sided-content>
			<x-slot:sideContent>
				<x-entity.previews.locale></x-entity.previews.locale>
			</x-slot:sideContent>

			<div class="mb-8">
				<x-entity.header
					avatarUrl="{{ asset('assets/icons/avatar/translate-01.png') }}"
					name="{{ $localeData->name }} ({{ strtoupper($localeData->alpha_2_code) }})">

					<x-slot:controlOptions>
						<x-ui.dropdown.dropdown>
							<x-slot:dropdownButton>
								<span class="opacity-50 hover:opacity-100">
									<x-ui.buttons.icon></x-ui.buttons.icon>
								</span>
							</x-slot:dropdownButton>

							@if($localeData->status)
								@unless($localeData->is_default)
									<x-ui.dropdown.item tag="a" x-on:click="makeDefaultLanguage" itemText="{{ __('admin/dd.lang.make_default') }}">
										<x-slot:itemIcon>
											<x-ui-icon type="line" name="translate-01"></x-ui-icon>
										</x-slot:itemIcon>
									</x-ui.dropdown.item>
								@endunless

								<x-ui.dropdown.item tag="a" :danger="true" x-on:click="disableLanguage" itemText="{{ __('admin/dd.lang.disable') }}">
									<x-slot:itemIcon>
										<x-ui-icon type="line" name="toggle-03-left"></x-ui-icon>
									</x-slot:itemIcon>
								</x-ui.dropdown.item>
							@else
								<x-ui.dropdown.item tag="a" x-on:click="enableLanguage" itemText="{{ __('admin/dd.lang.enable') }}">
									<x-slot:itemIcon>
										<x-ui-icon type="line" name="toggle-03-right"></x-ui-icon>
									</x-slot:itemIcon>
								</x-ui.dropdown.item>
							@endif

							<x-div></x-div>

							<x-ui.dropdown.item tag="a" :danger="true" x-on:click="deleteLanguage" itemText="{{ __('admin/dd.lang.delete') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="trash-04"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						</x-ui.dropdown.dropdown>
					</x-slot:controlOptions>
				</x-entity.header>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('admin/lang.about_lang') }}"></x-entity.title>
			</div>
			<div class="mb-6">
				<x-counter.counter>
					<x-counter.counter-item counterValue="{{ $localeData->usage_count }}" captionText="{{ __('table.labels.usage') }}"></x-counter.counter-item>
					<x-counter.counter-item counterValue="{{ $localeData->direction }}" captionText="{{ __('table.labels.direction') }}"></x-counter.counter-item>
					<x-counter.counter-item counterValue="{{ $localeData->order }}" captionText="{{ __('table.labels.order') }}"></x-counter.counter-item>
				</x-counter.counter>
			</div>
			<div class="mb-6">
				<x-line-table.table>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.name') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $localeData->name }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.default') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							@if($localeData->is_default)
								{{ __('labels.yes') }} ✅
							@else
								{{ __('labels.no') }} ❌
							@endif
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.native_name') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $localeData->native_name }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							Alpha 2 Code
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $localeData->alpha_2_code }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.status') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							@if($localeData->status)
								{{ __('labels.status_labels.active') }} ✅
							@else
								{{ __('labels.status_labels.inactive') }} ❌
							@endif
						</x-slot:labelValue>
					</x-line-table.row>
				</x-line-table.table>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('labels.additional_info') }}"></x-entity.title>
			</div>

			<x-striped-table.table>
				<x-striped-table.row>
					<x-slot:labelText>
						#ID
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $localeData->id }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						{{ __('table.labels.created_at') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $localeData->created_at->getFormatted() }}
					</x-slot:labelValue>
				</x-striped-table.row>
			</x-striped-table.table>
		</x-sided-content>
	</div>

	<script>
		window.addEventListener('alpine:init', () => {
			Alpine.data('alpineComponent', () => {
				return {
					disableLanguage: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.disable_lang.title') }}",
							desc: "{{ __('admin/prompt.disable_lang.description') }}",
							formAction: "{{ route('admin.lang.disable', $localeData->id) }}",
							confirmButtonText: "{{ __('admin/prompt.disable_lang.confirm_btn_text') }}"
						});
					},
					enableLanguage: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.enable_lang.title') }}",
							desc: "{{ __('admin/prompt.enable_lang.description') }}",
							formAction: "{{ route('admin.lang.enable', $localeData->id) }}",
							confirmButtonText: "{{ __('admin/prompt.enable_lang.confirm_btn_text') }}"
						});
					},
					makeDefaultLanguage: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.make_default_lang.title') }}",
							desc: "{{ __('admin/prompt.make_default_lang.description') }}",
							formAction: "{{ route('admin.lang.make_default', $localeData->id) }}",
							confirmButtonText: "{{ __('admin/prompt.make_default_lang.confirm_btn_text') }}"
						});
					},
					deleteLanguage: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.delete_lang.title') }}",
							desc: "{{ __('admin/prompt.delete_lang.description') }}",
							formAction: "{{ route('admin.lang.delete', $localeData->id) }}",
							confirmButtonText: "{{ __('admin/prompt.delete_lang.confirm_btn_text') }}"
						});
					}
				}
			});
		});
	</script>
@endsection
